<?php

namespace App\Services\Sefaz;

use App\DTOs\Agent\CommandResultData;
use App\Enums\ManifestationEventType;
use App\Enums\SefazManifestationEventCode;
use App\Models\Company;
use App\Models\CompanyCertificate;
use App\Models\FiscalDocument;
use App\Models\RecipientManifestation;
use App\Services\Sefaz\Xml\NfeEventResponseParser;
use App\Services\Sefaz\Xml\NfeManifestationXmlBuilder;
use App\Services\Sefaz\Xml\NfeSchemaValidator;
use App\Services\Sefaz\Xml\NfeSoapEnvelopeBuilder;
use App\Services\Sefaz\Xml\NfeXmlSigner;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class A1ManifestationSender
{
    public function __construct(
        private readonly A1CertificateMaterialLoader $certificateMaterialLoader,
        private readonly NfeManifestationXmlBuilder $xmlBuilder,
        private readonly NfeXmlSigner $xmlSigner,
        private readonly NfeSchemaValidator $schemaValidator,
        private readonly SefazEndpointResolver $endpointResolver,
        private readonly NfeSoapEnvelopeBuilder $soapEnvelopeBuilder,
        private readonly NfeEventResponseParser $responseParser,
    ) {}

    public function send(
        Company $company,
        FiscalDocument $document,
        RecipientManifestation $manifestation,
        CompanyCertificate $certificate,
        ManifestationEventType $eventType,
        ?string $justification,
        string $correlationId,
    ): CommandResultData {
        $material = $this->certificateMaterialLoader->load($certificate);
        $sefazEventCode = SefazManifestationEventCode::fromManifestationEventType($eventType);
        $endpoint = $this->endpointResolver->resolveNfeRecepcaoEvento($company->fiscal_environment, $company->uf);
        $requestXml = $this->xmlBuilder->build($company, $document, $manifestation, $eventType, $justification, $this->lotId($manifestation));
        $signedXml = $this->xmlSigner->sign($requestXml, $material->certificatePem, $material->privateKeyPem);
        $schemaErrors = $this->schemaValidator->validateEnvEvento($signedXml);

        if ($schemaErrors !== []) {
            throw new RuntimeException('XML de manifestação NF-e não validou contra schema: '.implode('; ', $schemaErrors));
        }

        $soapEnvelope = $this->soapEnvelopeBuilder->build($endpoint, $signedXml);
        $requestArtifact = $this->storeXml("sefaz/a1/requests/{$correlationId}-event-request.xml", $signedXml);
        $startedAt = microtime(true);
        $temporaryFiles = $this->temporaryCertificateFiles($material->certificatePem, $material->privateKeyPem);

        try {
            $response = Http::withOptions([
                'cert' => $temporaryFiles['certificate'],
                'ssl_key' => $temporaryFiles['private_key'],
                'timeout' => (int) config('sefaz.timeout_seconds', 30),
            ])
                ->withHeaders([
                    'Content-Type' => 'application/soap+xml; charset=utf-8; action="'.$endpoint->soapAction.'"',
                ])
                ->withBody($soapEnvelope, 'application/soap+xml')
                ->post($endpoint->url);
        } finally {
            $this->deleteTemporaryFiles($temporaryFiles);
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $responseXml = $response->body();
        $responseArtifact = $this->storeXml("sefaz/a1/responses/{$correlationId}-event-response.xml", $responseXml);

        if (! $response->successful()) {
            throw new RuntimeException('SEFAZ retornou HTTP '.$response->status().' para manifestação NF-e.');
        }

        $eventResponse = $this->responseParser->parse($responseXml);

        return new CommandResultData(
            result: [
                'service' => 'NFeRecepcaoEvento4',
                'environment' => $company->fiscal_environment->value,
                'endpoint' => $endpoint->url,
                'soap_action' => $endpoint->soapAction,
                'correlation_id' => $correlationId,
                'transport' => 'web_a1',
                'event_code' => $sefazEventCode->value,
            ],
            sefaz: [
                'service' => 'NFeRecepcaoEvento4',
                'environment' => $company->fiscal_environment->value,
                'endpoint' => $endpoint->url,
                'soap_action' => $endpoint->soapAction,
                'correlation_id' => $correlationId,
                'http_status_code' => $response->status(),
                'sent_at' => now(),
                'received_at' => now(),
            ],
            requestXml: $requestArtifact,
            responseXml: $responseArtifact,
            protocolNumber: $eventResponse->eventProtocolNumber,
            sefazStatusCode: $eventResponse->eventStatusCode ?? $eventResponse->batchStatusCode,
            sefazMessage: $eventResponse->eventReason ?? $eventResponse->batchReason,
            durationMs: $durationMs,
        );
    }

    /**
     * @return array{storage_disk: string, storage_path: string, content_hash: string}
     */
    private function storeXml(string $path, string $xml): array
    {
        $disk = (string) config('filesystems.default', 'local');
        Storage::disk($disk)->put($path, $xml);

        return [
            'storage_disk' => $disk,
            'storage_path' => $path,
            'content_hash' => hash('sha256', $xml),
        ];
    }

    private function lotId(RecipientManifestation $manifestation): string
    {
        return str_pad((string) $manifestation->id, 15, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{certificate: string, private_key: string}
     */
    private function temporaryCertificateFiles(string $certificatePem, string $privateKeyPem): array
    {
        $basePath = storage_path('framework/cache/sefaz-a1');
        if (! is_dir($basePath) && ! mkdir($basePath, 0700, true) && ! is_dir($basePath)) {
            throw new RuntimeException('Não foi possível criar diretório temporário para certificado A1.');
        }

        $prefix = bin2hex(random_bytes(16));
        $certificatePath = $basePath.DIRECTORY_SEPARATOR.$prefix.'.crt.pem';
        $privateKeyPath = $basePath.DIRECTORY_SEPARATOR.$prefix.'.key.pem';

        file_put_contents($certificatePath, $certificatePem);
        file_put_contents($privateKeyPath, $privateKeyPem);
        @chmod($certificatePath, 0600);
        @chmod($privateKeyPath, 0600);

        return [
            'certificate' => $certificatePath,
            'private_key' => $privateKeyPath,
        ];
    }

    /**
     * @param  array{certificate: string, private_key: string}  $paths
     */
    private function deleteTemporaryFiles(array $paths): void
    {
        foreach ($paths as $path) {
            try {
                if (is_file($path)) {
                    unlink($path);
                }
            } catch (Throwable) {
                // A limpeza é melhor esforço; o diretório temporário fica sob storage/framework/cache.
            }
        }
    }
}
