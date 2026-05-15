<?php

namespace App\Actions\FiscalDocument;

use App\DTOs\Agent\CommandFailureData;
use App\DTOs\Agent\CommandResultData;
use App\Enums\ManifestationStatus;
use App\Enums\XmlDownloadStatus;
use App\Models\AgentCommand;
use App\Models\FiscalDocument;
use App\Models\FiscalDocumentSummary;
use App\Models\FiscalDocumentXml;
use App\Services\Sefaz\DistributionStateService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RecordFiscalDocumentSyncResultAction
{
    /** @var list<string> */
    private const TRUSTED_STATUS_CODES = ['137', '138'];

    public function __construct(private readonly DistributionStateService $distributionStateService) {}

    public function recordCompleted(AgentCommand $command, CommandResultData $data): void
    {
        $result = $data->result ?? [];
        $statusCode = $data->sefazStatusCode ?? $this->string($result, 'sefaz_status_code');
        $message = $data->sefazMessage ?? $this->string($result, 'sefaz_message');

        $state = $this->distributionStateService->stateForCommand($command, $result);
        $this->distributionStateService->recordCompleted($state, $command, $result, $statusCode, $message);

        if (! in_array($statusCode, self::TRUSTED_STATUS_CODES, true)) {
            return;
        }

        $documents = $result['documents'] ?? [];
        if (! is_array($documents)) {
            return;
        }

        foreach ($documents as $documentPayload) {
            if (is_array($documentPayload)) {
                $this->recordDocument($command, $documentPayload, $statusCode, $message);
            }
        }
    }

    public function recordFailed(AgentCommand $command, CommandFailureData $data): void
    {
        $state = $this->distributionStateService->stateForCommand($command, $data->errorDetails ?? []);
        $this->distributionStateService->recordFailed($state, $command, $data);
    }

    /** @param array<string, mixed> $payload */
    private function recordDocument(AgentCommand $command, array $payload, ?string $statusCode, ?string $message): void
    {
        $accessKey = $this->digits($payload['access_key'] ?? null, 44);
        if ($accessKey === null) {
            return;
        }

        /** @var FiscalDocument $document */
        $document = FiscalDocument::query()->firstOrNew([
            'tenant_id' => $command->tenant_id,
            'company_id' => $command->company_id,
            'access_key' => $accessKey,
        ]);
        $document->fill([
            'nsu' => $this->string($payload, 'nsu'),
            'schema_version' => $this->schema($payload),
            'issuer_cnpj' => $this->digits($payload['issuer_cnpj'] ?? null, 14),
            'issuer_name' => $this->string($payload, 'issuer_name'),
            'recipient_cnpj' => $this->digits($payload['recipient_cnpj'] ?? null, 14) ?? $this->companyCnpj($command),
            'number' => $this->string($payload, 'number'),
            'series' => $this->string($payload, 'series'),
            'issued_at' => $this->string($payload, 'issued_at'),
            'total_amount' => $this->decimal($payload['total_amount'] ?? null),
            'manifestation_status' => $document->manifestation_status ?? ManifestationStatus::NoManifestation,
            'xml_download_status' => is_string($payload['full_xml'] ?? null)
                ? XmlDownloadStatus::Available
                : ($document->xml_download_status ?? XmlDownloadStatus::NotRequested),
            'last_sefaz_status_code' => $statusCode,
            'last_sefaz_message' => $message,
        ])->save();

        $summaryXml = is_string($payload['summary_xml'] ?? null) ? $payload['summary_xml'] : null;
        /** @var FiscalDocumentSummary $summary */
        $summary = FiscalDocumentSummary::query()->firstOrNew(['fiscal_document_id' => $document->id]);
        $summary->fill([
            'tenant_id' => $command->tenant_id,
            'company_id' => $command->company_id,
            'storage_disk' => $summaryXml === null ? null : $this->xmlDisk(),
            'storage_path' => $summaryXml === null ? null : $this->storeXml($command, $accessKey, 'summary', $summaryXml),
            'content_hash' => $summaryXml === null ? $this->string($payload, 'content_hash') : hash('sha256', $summaryXml),
            'summary_payload' => $this->summaryPayload($payload),
            'received_at' => now(),
        ])->save();

        $fullXml = is_string($payload['full_xml'] ?? null) ? $payload['full_xml'] : null;
        if ($fullXml !== null) {
            $hash = hash('sha256', $fullXml);
            FiscalDocumentXml::query()->updateOrCreate(
                [
                    'tenant_id' => $command->tenant_id,
                    'company_id' => $command->company_id,
                    'fiscal_document_id' => $document->id,
                    'content_hash' => $hash,
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'storage_disk' => $this->xmlDisk(),
                    'storage_path' => $this->storeXml($command, $accessKey, 'full', $fullXml),
                    'size_bytes' => strlen($fullXml),
                    'schema_version' => $this->schema($payload),
                    'source' => 'distribution',
                    'downloaded_at' => now(),
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function summaryPayload(array $payload): array
    {
        return Arr::except($payload, ['summary_xml', 'full_xml']);
    }

    /** @param array<string, mixed> $payload */
    private function schema(array $payload): ?string
    {
        $schema = $this->string($payload, 'schema');

        return $schema === null ? null : Str::limit($schema, 20, '');
    }

    private function xmlDisk(): string
    {
        $disk = config('filesystems.default', 'local');

        return is_string($disk) ? $disk : 'local';
    }

    private function storeXml(AgentCommand $command, string $accessKey, string $kind, string $xml): string
    {
        $path = sprintf(
            'fiscal-documents/%d/%s/%s-%s.xml',
            $command->company_id,
            $accessKey,
            $kind,
            hash('sha256', $xml),
        );

        Storage::disk($this->xmlDisk())->put($path, $xml);

        return $path;
    }

    private function companyCnpj(AgentCommand $command): ?string
    {
        $cnpj = $command->payload['cnpj'] ?? null;

        return $this->digits($cnpj, 14);
    }

    /** @param array<string, mixed> $payload */
    private function string(array $payload, string $key): ?string
    {
        $value = Arr::get($payload, $key);

        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    private function digits(mixed $value, int $length): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $value);

        return is_string($digits) && strlen($digits) === $length ? $digits : null;
    }

    private function decimal(mixed $value): ?string
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
