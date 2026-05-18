<?php

namespace App\Actions\FiscalDocument;

use App\DTOs\Agent\CommandFailureData;
use App\DTOs\Agent\CommandResultData;
use App\Enums\ManifestationEventType;
use App\Enums\SefazRequestStatus;
use App\Models\Company;
use App\Models\CompanyCertificate;
use App\Models\FiscalDocument;
use App\Models\ManifestationAttempt;
use App\Models\RecipientManifestation;
use App\Models\SefazRequest;
use App\Models\SefazResponse;
use App\Services\Sefaz\A1ManifestationSender;
use Throwable;

class SendA1ManifestationAction
{
    public function __construct(
        private readonly A1ManifestationSender $sender,
        private readonly RecordManifestationResultAction $recordManifestationResultAction,
    ) {}

    public function execute(
        Company $company,
        FiscalDocument $document,
        RecipientManifestation $manifestation,
        ManifestationAttempt $attempt,
        CompanyCertificate $certificate,
        ManifestationEventType $eventType,
        ?string $justification,
        string $correlationId,
    ): void {
        try {
            $result = $this->sender->send($company, $document, $manifestation, $certificate, $eventType, $justification, $correlationId);
            $this->recordManifestationResultAction->recordDirectCompleted($manifestation, $attempt, $result);
            $this->recordSefazResult($company, $result);
        } catch (Throwable $exception) {
            $this->recordManifestationResultAction->recordDirectFailed($manifestation, $attempt, new CommandFailureData(
                errorCode: 'SEFAZ_A1_MANIFESTATION_FAILED',
                errorMessage: $this->sanitizeErrorMessage($exception->getMessage()),
                errorDetails: null,
                sefazStatusCode: null,
                sefazMessage: null,
                durationMs: null,
            ), isFinalFailure: true);

            throw $exception;
        }
    }

    private function recordSefazResult(Company $company, CommandResultData $result): void
    {
        $request = SefazRequest::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'service' => $result->sefaz['service'] ?? $result->result['service'] ?? 'NFeRecepcaoEvento4',
            'environment' => $company->fiscal_environment,
            'endpoint' => $result->sefaz['endpoint'] ?? $result->result['endpoint'] ?? 'web-a1',
            'soap_action' => $result->sefaz['soap_action'] ?? $result->result['soap_action'] ?? null,
            'request_xml_storage_disk' => $result->requestXml['storage_disk'] ?? null,
            'request_xml_storage_path' => $result->requestXml['storage_path'] ?? null,
            'request_hash' => $result->requestXml['content_hash'] ?? null,
            'correlation_id' => $result->sefaz['correlation_id'] ?? $result->result['correlation_id'] ?? null,
            'sent_at' => $result->sefaz['sent_at'] ?? now(),
        ]);

        SefazResponse::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'sefaz_request_id' => $request->id,
            'status' => in_array((string) $result->sefazStatusCode, ['135', '136', '155'], true)
                ? SefazRequestStatus::Succeeded
                : SefazRequestStatus::Rejected,
            'http_status_code' => $result->sefaz['http_status_code'] ?? null,
            'sefaz_status_code' => $result->sefazStatusCode,
            'sefaz_message' => $result->sefazMessage,
            'response_xml_storage_disk' => $result->responseXml['storage_disk'] ?? null,
            'response_xml_storage_path' => $result->responseXml['storage_path'] ?? null,
            'response_hash' => $result->responseXml['content_hash'] ?? null,
            'received_at' => $result->sefaz['received_at'] ?? now(),
            'duration_ms' => $result->durationMs,
        ]);
    }

    private function sanitizeErrorMessage(string $message): string
    {
        return str($message)
            ->replaceMatches('/-----BEGIN[^-]+-----.*?-----END[^-]+-----/s', '[material sensível removido]')
            ->replaceMatches('/(password|senha|pin|secret|token)\s*[:=]\s*\S+/i', '$1=[removido]')
            ->limit(500, '')
            ->toString();
    }
}
