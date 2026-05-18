<?php

namespace App\Services\Sefaz;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Models\CompanyCertificate;
use App\Services\Sefaz\Dto\A1CertificateMaterial;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class A1CertificateMaterialLoader
{
    public function load(CompanyCertificate $certificate): A1CertificateMaterial
    {
        if ($certificate->type !== CertificateType::A1 || $certificate->status !== CertificateStatus::Active) {
            throw new RuntimeException('Certificado A1 ativo não encontrado para a empresa.');
        }

        if (! is_string($certificate->storage_disk) || ! is_string($certificate->storage_path)) {
            throw new RuntimeException('Certificado A1 não possui referência de armazenamento.');
        }

        if (! is_string($certificate->encrypted_password_payload) || $certificate->encrypted_password_payload === '') {
            throw new RuntimeException('Certificado A1 não possui senha armazenada de forma criptografada.');
        }

        $encryptedContents = Storage::disk($certificate->storage_disk)->get($certificate->storage_path);
        if (! is_string($encryptedContents) || $encryptedContents === '') {
            throw new RuntimeException('Arquivo do certificado A1 não foi encontrado.');
        }

        $pfxContents = base64_decode(Crypt::decryptString($encryptedContents), true);
        if (! is_string($pfxContents) || $pfxContents === '') {
            throw new RuntimeException('Conteúdo do certificado A1 não pôde ser decodificado.');
        }

        $password = Crypt::decryptString($certificate->encrypted_password_payload);
        $certificates = [];

        if (! openssl_pkcs12_read($pfxContents, $certificates, $password)) {
            throw new RuntimeException('Certificado A1 não pôde ser aberto para assinatura.');
        }

        $certificatePem = $certificates['cert'] ?? null;
        $privateKeyPem = $certificates['pkey'] ?? null;

        if (! is_string($certificatePem) || $certificatePem === '' || ! is_string($privateKeyPem) || $privateKeyPem === '') {
            throw new RuntimeException('Certificado A1 não contém certificado público e chave privada acessíveis.');
        }

        return new A1CertificateMaterial($certificatePem, $privateKeyPem);
    }
}
