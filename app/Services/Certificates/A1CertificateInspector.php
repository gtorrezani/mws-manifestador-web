<?php

namespace App\Services\Certificates;

use App\Support\Cnpj;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class A1CertificateInspector
{
    /** @var list<string> */
    private const ICP_BRASIL_KEYWORDS = [
        'ICP-Brasil',
        'AC SOLUTI',
        'Receita Federal',
        'RFB',
        'Serasa',
        'Certisign',
        'Valid',
        'Safeweb',
        'SERPRO',
        'Fenacon',
        'DigitalSign',
        'PRODEMGE',
        'Autoridade Certificadora',
    ];

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

        $cnpj = $this->extractCnpj($parsed, $certificatePem);
        $validUntil = $this->timestamp($parsed['validTo_time_t'] ?? null);
        $this->assertFiscalCertificate($parsed, $certificatePem, $cnpj, $validUntil);

        return [
            'pfx_contents' => $contents,
            'certificate_pem' => $certificatePem,
            'subject_name' => $this->name($parsed['subject'] ?? null),
            'issuer_name' => $this->name($parsed['issuer'] ?? null),
            'serial_number' => isset($parsed['serialNumberHex']) && is_string($parsed['serialNumberHex'])
                ? $parsed['serialNumberHex']
                : null,
            'thumbprint' => strtoupper(str_replace(':', '', $thumbprint)),
            'cnpj' => $cnpj,
            'valid_from' => $this->timestamp($parsed['validFrom_time_t'] ?? null),
            'valid_until' => $validUntil,
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function assertFiscalCertificate(array $parsed, string $certificatePem, ?string $cnpj, ?Carbon $validUntil): void
    {
        $text = json_encode($parsed, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).' '.$certificatePem;

        if ($validUntil !== null && $validUntil->isPast()) {
            throw ValidationException::withMessages([
                'certificate_file' => 'Certificado vencido.',
            ]);
        }

        if ($cnpj === null) {
            throw ValidationException::withMessages([
                'certificate_file' => 'CPF/CNPJ não identificado no certificado.',
            ]);
        }

        if (! $this->containsIcpBrasilSignal($text)) {
            throw ValidationException::withMessages([
                'certificate_file' => 'Emissor/cadeia não indica certificado fiscal ICP-Brasil.',
            ]);
        }

        if ($this->isCertificateAuthority($parsed)) {
            throw ValidationException::withMessages([
                'certificate_file' => 'Certificado de autoridade certificadora não pode ser vinculado à empresa.',
            ]);
        }

        if (! $this->hasCompatibleUsage($parsed)) {
            throw ValidationException::withMessages([
                'certificate_file' => 'Uso do certificado não é compatível com autenticação/assinatura de cliente.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function extractCnpj(array $parsed, string $certificatePem): ?string
    {
        $text = json_encode($parsed, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).' '.$certificatePem;
        preg_match_all('/(?<!\d)(?:\d{14}|\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2})(?!\d)/', $text, $matches);

        foreach ($matches[0] as $candidate) {
            $cnpj = Cnpj::normalize($candidate);
            if (strlen($cnpj) === 14) {
                return $cnpj;
            }
        }

        return null;
    }

    private function timestamp(mixed $value): ?Carbon
    {
        if (! is_int($value)) {
            return null;
        }

        return Carbon::createFromTimestamp($value);
    }

    private function containsIcpBrasilSignal(string $text): bool
    {
        foreach (self::ICP_BRASIL_KEYWORDS as $keyword) {
            if (str_contains(strtolower($text), strtolower($keyword))) {
                return true;
            }
        }

        return str_contains($text, '2.16.76.1.');
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function isCertificateAuthority(array $parsed): bool
    {
        $basicConstraints = $this->extension($parsed, 'basicConstraints');

        return is_string($basicConstraints) && str_contains(strtoupper($basicConstraints), 'CA:TRUE');
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function hasCompatibleUsage(array $parsed): bool
    {
        $extendedKeyUsage = $this->extension($parsed, 'extendedKeyUsage');
        if (is_string($extendedKeyUsage) && $extendedKeyUsage !== '') {
            $normalized = strtolower($extendedKeyUsage);
            if (! str_contains($normalized, 'client') && ! str_contains($normalized, 'autentica')) {
                return false;
            }
        }

        $keyUsage = $this->extension($parsed, 'keyUsage');
        if (is_string($keyUsage) && $keyUsage !== '') {
            $normalized = strtolower($keyUsage);

            return str_contains($normalized, 'digital signature') ||
                str_contains($normalized, 'non repudiation') ||
                str_contains($normalized, 'content commitment');
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function extension(array $parsed, string $name): mixed
    {
        $extensions = $parsed['extensions'] ?? null;

        return is_array($extensions) ? ($extensions[$name] ?? null) : null;
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
