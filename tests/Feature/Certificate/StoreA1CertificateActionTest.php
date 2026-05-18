<?php

namespace Tests\Feature\Certificate;

use App\Actions\Certificates\StoreA1CertificateAction;
use App\Models\Company;
use App\Services\Certificates\A1CertificateInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StoreA1CertificateActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_masked_certificate_cnpj_matches_unmasked_company_cnpj(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);
        $company = Company::factory()->create(['cnpj' => '12345678000195']);

        $certificate = $this->actionWithCnpj('12.345.678/0001-95')->execute(
            $company,
            UploadedFile::fake()->createWithContent('certificado.pfx', 'pfx'),
            'secret',
            'Certificado teste',
        );

        $this->assertIsArray($certificate->metadata);
        $this->assertSame('12345678000195', $certificate->metadata['cnpj']);
    }

    public function test_validation_message_formats_both_cnpjs_on_mismatch(): void
    {
        $company = Company::factory()->create(['cnpj' => '12345678000195']);

        try {
            $this->actionWithCnpj('98.765.432/0001-10')->execute(
                $company,
                UploadedFile::fake()->createWithContent('certificado.pfx', 'pfx'),
                'secret',
                null,
            );
            $this->fail('ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'CNPJ do certificado (98.765.432/0001-10) não corresponde à empresa selecionada (12.345.678/0001-95).',
                $exception->errors()['certificate_file'][0],
            );
        }
    }

    private function actionWithCnpj(?string $cnpj): StoreA1CertificateAction
    {
        $inspector = new class($cnpj) extends A1CertificateInspector
        {
            public function __construct(private readonly ?string $cnpj) {}

            public function inspect(UploadedFile $file, string $password): array
            {
                return [
                    'pfx_contents' => 'pfx',
                    'certificate_pem' => 'certificate',
                    'subject_name' => 'CN=Teste',
                    'issuer_name' => 'CN=ICP-Brasil',
                    'serial_number' => '01',
                    'thumbprint' => 'ABC123',
                    'cnpj' => $this->cnpj,
                    'valid_from' => Carbon::now()->subDay(),
                    'valid_until' => Carbon::now()->addYear(),
                ];
            }
        };

        return new StoreA1CertificateAction($inspector);
    }
}
