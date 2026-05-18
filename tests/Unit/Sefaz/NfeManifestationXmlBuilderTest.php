<?php

namespace Tests\Unit\Sefaz;

use App\Enums\FiscalEnvironment;
use App\Enums\ManifestationEventType;
use App\Models\Company;
use App\Models\FiscalDocument;
use App\Models\RecipientManifestation;
use App\Services\Sefaz\Dto\A1CertificateMaterial;
use App\Services\Sefaz\SefazEndpointResolver;
use App\Services\Sefaz\Xml\NfeManifestationXmlBuilder;
use App\Services\Sefaz\Xml\NfeSchemaValidator;
use App\Services\Sefaz\Xml\NfeXmlSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class NfeManifestationXmlBuilderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  non-empty-string|null  $justification
     */
    #[DataProvider('manifestationEvents')]
    public function test_signed_manifestation_xml_validates_against_env_evento_schema(
        ManifestationEventType $eventType,
        string $expectedEventCode,
        ?string $justification,
    ): void {
        $company = Company::factory()->create([
            'cnpj' => '12345678000195',
            'uf' => 'SP',
            'fiscal_environment' => FiscalEnvironment::Homologation,
        ]);
        $document = FiscalDocument::factory()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'access_key' => '35260512345678000195550010000000011000000010',
            'recipient_cnpj' => $company->cnpj,
        ]);
        $manifestation = RecipientManifestation::factory()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'fiscal_document_id' => $document->id,
            'event_type' => $eventType,
        ]);
        $certificate = $this->fakeCertificateMaterial();

        $xml = app(NfeManifestationXmlBuilder::class)->build(
            company: $company,
            document: $document,
            manifestation: $manifestation,
            eventType: $eventType,
            justification: $justification,
            lotId: '1',
        );
        $signedXml = app(NfeXmlSigner::class)->sign($xml, $certificate->certificatePem, $certificate->privateKeyPem);

        $this->assertSame([], app(NfeSchemaValidator::class)->validateEnvEvento($signedXml));
        $this->assertStringContainsString("<tpEvento>{$expectedEventCode}</tpEvento>", $signedXml);
        $this->assertStringContainsString('<Signature xmlns="http://www.w3.org/2000/09/xmldsig#">', $signedXml);
        $this->assertStringNotContainsString('PRIVATE KEY', $signedXml);

        if ($justification !== null) {
            $this->assertStringContainsString('<xJust>'.$justification.'</xJust>', $signedXml);
        }
    }

    public function test_endpoint_resolver_uses_authorized_national_endpoint_as_safe_fallback(): void
    {
        $endpoint = app(SefazEndpointResolver::class)->resolveNfeRecepcaoEvento(FiscalEnvironment::Production, 'RJ');

        $this->assertSame('AN', $endpoint->uf);
        $this->assertSame('https://www.nfe.fazenda.gov.br/NFeRecepcaoEvento4/NFeRecepcaoEvento4.asmx', $endpoint->url);
    }

    /**
     * @return iterable<string, array{ManifestationEventType, non-empty-string, non-empty-string|null}>
     */
    public static function manifestationEvents(): iterable
    {
        yield 'acknowledgement' => [ManifestationEventType::OperationAcknowledgement, '210210', null];
        yield 'confirmation' => [ManifestationEventType::OperationConfirmation, '210200', null];
        yield 'unknown' => [ManifestationEventType::OperationUnknown, '210220', null];
        yield 'not_performed' => [
            ManifestationEventType::OperationNotPerformed,
            '210240',
            'Operacao comercial nao reconhecida pela empresa.',
        ];
    }

    private function fakeCertificateMaterial(): A1CertificateMaterial
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if (! $privateKey instanceof \OpenSSLAsymmetricKey) {
            throw new RuntimeException('Não foi possível criar chave privada de teste.');
        }

        $csr = openssl_csr_new(['commonName' => 'Teste MWS'], $privateKey);
        if (! $csr instanceof \OpenSSLCertificateSigningRequest) {
            throw new RuntimeException('Não foi possível criar CSR de teste.');
        }

        $certificate = openssl_csr_sign($csr, null, $privateKey, 365);
        if ($certificate === false) {
            throw new RuntimeException('Não foi possível criar certificado de teste.');
        }

        $certificatePem = '';
        $privateKeyPem = '';
        openssl_x509_export($certificate, $certificatePem);
        openssl_pkey_export($privateKey, $privateKeyPem);

        return new A1CertificateMaterial($certificatePem, $privateKeyPem);
    }
}
