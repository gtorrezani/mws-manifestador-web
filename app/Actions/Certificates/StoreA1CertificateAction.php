<?php

namespace App\Actions\Certificates;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Models\Company;
use App\Models\CompanyCertificate;
use App\Services\Certificates\A1CertificateInspector;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreA1CertificateAction
{
    public function __construct(
        private readonly A1CertificateInspector $inspector,
    ) {}

    public function execute(Company $company, UploadedFile $file, string $password, ?string $name): CompanyCertificate
    {
        $inspected = $this->inspector->inspect($file, $password);
        if ($inspected['cnpj'] !== $company->cnpj) {
            throw ValidationException::withMessages([
                'certificate_file' => 'CNPJ do certificado não corresponde à empresa selecionada.',
            ]);
        }

        $disk = (string) config('filesystems.default', 'local');
        $path = 'certificates/a1/'.(string) Str::uuid().'.pfx.enc';

        Storage::disk($disk)->put($path, Crypt::encryptString(base64_encode($inspected['pfx_contents'])));

        /** @var CompanyCertificate $certificate */
        $certificate = CompanyCertificate::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'type' => CertificateType::A1,
            'status' => $this->status($inspected['valid_until']),
            'name' => $name ?: 'Certificado A1 '.$company->legal_name,
            'subject_name' => $inspected['subject_name'],
            'issuer_name' => $inspected['issuer_name'],
            'serial_number' => $inspected['serial_number'],
            'thumbprint' => $inspected['thumbprint'],
            'valid_from' => $inspected['valid_from'],
            'valid_until' => $inspected['valid_until'],
            'storage_disk' => $disk,
            'storage_path' => $path,
            'encrypted_password_payload' => Crypt::encryptString($password),
            'metadata' => [
                'cnpj' => $inspected['cnpj'],
                'source' => 'web_upload',
            ],
            'last_validated_at' => now(),
            'last_tested_at' => now(),
            'last_test_status' => 'valid',
            'last_test_message' => 'Certificado A1 validado no cadastro.',
        ]);

        return $certificate;
    }

    private function status(?Carbon $validUntil): CertificateStatus
    {
        if ($validUntil !== null && $validUntil->isPast()) {
            return CertificateStatus::Expired;
        }

        return CertificateStatus::Active;
    }
}
