<?php

namespace App\Services\Certificates;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class A1CertificateInspector
{
    /**
     * @return array{
     *     pfx_contents: string,
     *     certificate_pem: string,
     *     subject_name: string|null,
     *     issuer_name: string|null,
     *     serial_number: string|null,
     *     thumbprint: string,
     *     cnpj: string|null,
     *     valid_from: Carbon|null,
     *     valid_until: Carbon|null
     * }
     */
    public function inspect(UploadedFile $file, string $password): array
    {
        $path = $file->getRealPath();
        $contents = is_string($path) ? file_get_contents($path) : false;
        if (! is_string($contents) || $contents === '') {
            throw ValidationException::withMessages([
                'certificate_file' => 'Arquivo de certificado inválido.',
            ]);
        }

        $certificates = [];
        if (! openssl_pkcs12_read($contents, $certificates, $password)) {
            throw ValidationException::withMessages([
                'password' => 'Senha do certificado inválida ou arquivo PFX/P12 corrompido.',
            ]);
        }

        $certificatePem = $certificates['cert'] ?? null;
        if (! is_string($certificatePem) || $certificatePem === '') {
            throw ValidationException::withMessages([
                'certificate_file' => 'O arquivo não contém certificado público.',
            ]);
        }

        if (! isset($certificates['pkey'])) {
            throw ValidationException::withMessages([
                'certificate_file' => 'O arquivo não contém chave privada.',
            ]);
        }

        $parsed = openssl_x509_parse($certificatePem);
        if (! is_array($parsed)) {
            throw ValidationException::withMessages([
                'certificate_file' => 'Não foi possível ler os dados do certificado.',
            ]);
        }

        $thumbprint = openssl_x509_fingerprint($certificatePem, 'sha1');
        if (! is_string($thumbprint) || $thumbprint === '') {
            throw ValidationException::withMessages([
                'certificate_file' => 'Não foi possível calcular o thumbprint do certificado.',
            ]);
        }

        return [
            'pfx_contents' => $contents,
            'certificate_pem' => $certificatePem,
            'subject_name' => $this->name($parsed['subject'] ?? null),
            'issuer_name' => $this->name($parsed['issuer'] ?? null),
            'serial_number' => isset($parsed['serialNumberHex']) && is_string($parsed['serialNumberHex'])
                ? $parsed['serialNumberHex']
                : null,
            'thumbprint' => strtoupper(str_replace(':', '', $thumbprint)),
            'cnpj' => $this->extractCnpj($parsed, $certificatePem),
            'valid_from' => $this->timestamp($parsed['validFrom_time_t'] ?? null),
            'valid_until' => $this->timestamp($parsed['validTo_time_t'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function extractCnpj(array $parsed, string $certificatePem): ?string
    {
        $text = json_encode($parsed, JSON_THROW_ON_ERROR).' '.$certificatePem;
        preg_match('/(?<!\d)\d{14}(?!\d)/', $text, $matches);

        return isset($matches[0]) ? $matches[0] : null;
    }

    private function timestamp(mixed $value): ?Carbon
    {
        if (! is_int($value)) {
            return null;
        }

        return Carbon::createFromTimestamp($value);
    }

    private function name(mixed $name): ?string
    {
        if (is_string($name)) {
            return $name;
        }

        if (! is_array($name)) {
            return null;
        }

        return collect($name)
            ->map(fn (mixed $value, mixed $key): ?string => is_scalar($value) && is_scalar($key) ? $key.'='.$value : null)
            ->filter()
            ->implode(', ');
    }
}
