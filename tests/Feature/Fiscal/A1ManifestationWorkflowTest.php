<?php

namespace Tests\Feature\Fiscal;

use App\Actions\FiscalDocument\RequestManifestationAction;
use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Enums\FiscalEnvironment;
use App\Enums\ManifestationEventType;
use App\Enums\ManifestationRecordStatus;
use App\Enums\ManifestationStatus;
use App\Models\AgentCommand;
use App\Models\Company;
use App\Models\CompanyCertificate;
use App\Models\FiscalDocument;
use App\Models\SefazRequest;
use App\Models\SefazResponse;
use App\Services\Fiscal\ManifestationRequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class A1ManifestationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a1_manifestation_is_sent_directly_by_web_without_agent_command(): void
    {
        Storage::fake('local');
        Http::fake([
            '*' => Http::response($this->acceptedEventSoapResponse(), 200),
        ]);

        $company = Company::factory()->create([
            'cnpj' => '12345678000195',
            'uf' => 'SP',
            'fiscal_environment' => FiscalEnvironment::Homologation,
        ]);
        $this->createStoredA1Certificate($company);
        $document = FiscalDocument::factory()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'access_key' => '35260512345678000195550010000000011000000010',
            'recipient_cnpj' => $company->cnpj,
            'manifestation_status' => ManifestationStatus::NoManifestation,
        ]);

        app(RequestManifestationAction::class)->execute(
            document: $document,
            eventType: ManifestationEventType::OperationAcknowledgement,
            justification: null,
            context: new ManifestationRequestContext,
            createdBy: null,
        );

        $this->assertSame(0, AgentCommand::query()->count());
        $this->assertDatabaseHas('recipient_manifestations', [
            'fiscal_document_id' => $document->id,
            'event_type' => ManifestationEventType::OperationAcknowledgement->value,
            'status' => ManifestationRecordStatus::Accepted->value,
            'protocol_number' => '135240000000001',
            'sefaz_status_code' => '135',
        ]);
        $this->assertDatabaseHas('manifestation_attempts', [
            'agent_command_id' => null,
            'status' => ManifestationRecordStatus::Accepted->value,
            'sefaz_status_code' => '135',
        ]);
        $this->assertDatabaseHas('fiscal_documents', [
            'id' => $document->id,
            'manifestation_status' => ManifestationStatus::PendingFinalManifestation->value,
        ]);

        $this->assertSame(1, SefazRequest::query()->count());
        $this->assertSame(1, SefazResponse::query()->count());
        Http::assertSent(function (Request $request): bool {
            $body = $request->body();

            return str_contains($request->url(), 'nferecepcaoevento4')
                && str_contains($body, '<tpEvento>210210</tpEvento>')
                && str_contains($body, '<Signature xmlns="http://www.w3.org/2000/09/xmldsig#">')
                && ! str_contains($body, 'PRIVATE KEY');
        });
    }

    public function test_manifestation_uses_agent_command_when_company_has_no_active_a1_certificate(): void
    {
        Http::fake();

        $company = Company::factory()->create();
        $document = FiscalDocument::factory()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'manifestation_status' => ManifestationStatus::NoManifestation,
        ]);

        app(RequestManifestationAction::class)->execute(
            document: $document,
            eventType: ManifestationEventType::OperationAcknowledgement,
            justification: null,
            context: new ManifestationRequestContext,
            createdBy: null,
        );

        $this->assertSame(1, AgentCommand::query()->count());
        Http::assertNothingSent();
    }

    private function createStoredA1Certificate(Company $company): CompanyCertificate
    {
        $password = 'test-password';
        $pfxContents = $this->fakePfx($password);
        $path = 'certificates/a1/'.(string) Str::uuid().'.pfx.enc';
        Storage::disk('local')->put($path, Crypt::encryptString(base64_encode($pfxContents)));

        /** @var CompanyCertificate $certificate */
        $certificate = CompanyCertificate::factory()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'type' => CertificateType::A1,
            'status' => CertificateStatus::Active,
            'storage_disk' => 'local',
            'storage_path' => $path,
            'encrypted_password_payload' => Crypt::encryptString($password),
        ]);

        return $certificate;
    }

    private function fakePfx(string $password): string
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

        $pfx = '';

        openssl_pkcs12_export($certificate, $pfx, $privateKey, $password);

        return $pfx;
    }

    private function acceptedEventSoapResponse(): string
    {
        return <<<'XML'
<soap12:Envelope xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
  <soap12:Body>
    <nfeRecepcaoEventoResponse xmlns="http://www.portalfiscal.inf.br/nfe/wsdl/NFeRecepcaoEvento4">
      <nfeResultMsg>
        <retEnvEvento xmlns="http://www.portalfiscal.inf.br/nfe" versao="1.00">
          <idLote>1</idLote>
          <tpAmb>2</tpAmb>
          <verAplic>SP_NFE_PL_009_V4</verAplic>
          <cOrgao>35</cOrgao>
          <cStat>128</cStat>
          <xMotivo>Lote de evento processado</xMotivo>
          <retEvento versao="1.00">
            <infEvento>
              <tpAmb>2</tpAmb>
              <verAplic>SP_NFE_PL_009_V4</verAplic>
              <cOrgao>35</cOrgao>
              <cStat>135</cStat>
              <xMotivo>Evento registrado e vinculado a NF-e</xMotivo>
              <chNFe>35260512345678000195550010000000011000000010</chNFe>
              <tpEvento>210210</tpEvento>
              <nSeqEvento>1</nSeqEvento>
              <CNPJDest>12345678000195</CNPJDest>
              <dhRegEvento>2026-05-18T10:00:00-03:00</dhRegEvento>
              <nProt>135240000000001</nProt>
            </infEvento>
          </retEvento>
        </retEnvEvento>
      </nfeResultMsg>
    </nfeRecepcaoEventoResponse>
  </soap12:Body>
</soap12:Envelope>
XML;
    }
}
