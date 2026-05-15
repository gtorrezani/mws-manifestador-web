<?php

namespace App\Actions\Certificates;

use App\DTOs\Agent\CommandResultData;
use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Models\Agent;
use App\Models\AgentCertificate;
use App\Models\AgentCommand;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class RecordAgentCertificateInventoryAction
{
    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'pin',
        'a3_pin',
        'password',
        'a1_password',
        'certificate_password',
        'private_key',
        'secret',
        'token',
    ];

    public function execute(Agent $agent, AgentCommand $command, CommandResultData $data): void
    {
        $certificates = $data->result['certificates'] ?? null;
        if (! is_array($certificates)) {
            return;
        }

        foreach ($certificates as $item) {
            if (! is_array($item)) {
                continue;
            }

            $thumbprint = $this->normalizedThumbprint(Arr::get($item, 'thumbprint'));
            if ($thumbprint === null) {
                continue;
            }

            $storeLocation = $this->storeLocation(Arr::get($item, 'store_location', Arr::get($item, 'store_scope')));
            $storeScope = $this->storeScope($storeLocation);
            $notBefore = $this->dateValue(Arr::get($item, 'not_before'));
            $notAfter = $this->dateValue(Arr::get($item, 'not_after'));
            $subject = $this->stringValue(Arr::get($item, 'subject'));
            $issuer = $this->stringValue(Arr::get($item, 'issuer'));
            $documentType = $this->documentType(Arr::get($item, 'document_type'));
            $document = $documentType === null ? null : $this->digits(Arr::get($item, 'document'), $documentType === 'cnpj' ? 14 : 11);
            $cnpj = $this->digits(Arr::get($item, 'cnpj'), 14) ?? ($documentType === 'cnpj' ? $document : null);
            $hasPrivateKey = (bool) Arr::get($item, 'has_private_key', false);
            $isExpired = $this->booleanValue(Arr::get($item, 'is_expired')) ?? ($notAfter !== null && $notAfter->isPast());
            $isCertificateAuthority = (bool) Arr::get($item, 'is_certificate_authority', false);
            $isIcpBrasil = (bool) Arr::get($item, 'is_icp_brasil', false);
            $isUsableForClientAuth = (bool) Arr::get($item, 'is_usable_for_client_auth', false);
            $reportedClassification = $this->stringValue(Arr::get($item, 'classification')) ?? 'unknown';
            $isFiscalCandidate = $this->isFiscalCandidate(
                $hasPrivateKey,
                $isExpired,
                $isCertificateAuthority,
                $isIcpBrasil,
                $isUsableForClientAuth,
                $document,
                $storeLocation,
            );
            $classification = $this->classification(
                $reportedClassification,
                $isFiscalCandidate,
                $hasPrivateKey,
                $isExpired,
                $isCertificateAuthority,
                $isIcpBrasil,
                $subject,
                $issuer,
            );
            $rejectionReasons = $this->rejectionReasons(
                $this->stringList(Arr::get($item, 'rejection_reasons')),
                $hasPrivateKey,
                $isExpired,
                $isCertificateAuthority,
                $isIcpBrasil,
                $isUsableForClientAuth,
                $document,
                $storeLocation,
            );
            $reportedIsValid = $this->booleanValue(Arr::get($item, 'is_valid'));
            $isValid = $isFiscalCandidate && ($reportedIsValid ?? true);

            /** @var AgentCertificate $certificate */
            $certificate = AgentCertificate::query()->firstOrNew([
                'tenant_id' => $agent->tenant_id,
                'company_id' => $command->company_id,
                'agent_id' => $agent->id,
                'thumbprint' => $thumbprint,
                'store_location' => $storeLocation,
            ]);

            if (! $certificate->exists) {
                $certificate->uuid = (string) Str::uuid();
            }

            $certificate->fill([
                'type' => CertificateType::A3,
                'status' => $this->status($isExpired, $isValid),
                'store_scope' => $storeScope,
                'subject' => $subject,
                'subject_name' => $subject,
                'issuer' => $issuer,
                'issuer_name' => $issuer,
                'common_name' => $this->stringValue(Arr::get($item, 'common_name')),
                'serial_number' => $this->stringValue(Arr::get($item, 'serial_number')),
                'cnpj' => $cnpj,
                'document' => $document,
                'document_type' => $documentType,
                'not_before' => $notBefore,
                'not_after' => $notAfter,
                'valid_from' => $notBefore,
                'valid_until' => $notAfter,
                'has_private_key' => $hasPrivateKey,
                'store_name' => $this->stringValue(Arr::get($item, 'store_name')) ?? 'My',
                'is_expired' => $isExpired,
                'is_certificate_authority' => $isCertificateAuthority,
                'is_fiscal_candidate' => $isFiscalCandidate,
                'is_icp_brasil' => $isIcpBrasil,
                'is_usable_for_client_auth' => $isUsableForClientAuth,
                'classification' => $classification,
                'rejection_reasons' => $rejectionReasons,
                'warnings' => $this->stringList(Arr::get($item, 'warnings')),
                'is_valid' => $isValid,
                'validation_message' => $this->stringValue(Arr::get($item, 'validation_message')),
                'last_seen_at' => now(),
                'metadata' => [
                    'command_uuid' => $command->uuid,
                    'reference' => Arr::get($item, 'reference'),
                    'type_estimate' => 'a1_a3_unconfirmed',
                ],
                'raw_payload' => $this->sanitizePayload($item),
            ])->save();
        }
    }

    private function status(bool $isExpired, bool $isValid): CertificateStatus
    {
        if ($isExpired) {
            return CertificateStatus::Expired;
        }

        return $isValid ? CertificateStatus::Active : CertificateStatus::Invalid;
    }

    private function storeLocation(mixed $value): ?string
    {
        if (is_int($value)) {
            return match ($value) {
                1 => 'CurrentUser',
                2 => 'LocalMachine',
                default => null,
            };
        }

        $location = $this->stringValue($value);
        if ($location === null) {
            return null;
        }

        $normalized = Str::of($location)->replace(['-', '_', ' '], '')->lower()->toString();

        return match ($normalized) {
            'currentuser' => 'CurrentUser',
            'localmachine' => 'LocalMachine',
            default => $location,
        };
    }

    private function storeScope(?string $storeLocation): ?string
    {
        return match ($storeLocation) {
            'CurrentUser' => 'current_user',
            'LocalMachine' => 'local_machine',
            default => null,
        };
    }

    private function dateValue(mixed $value): ?Carbon
    {
        $date = $this->stringValue($value);
        if ($date === null) {
            return null;
        }

        return Carbon::parse($date);
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function normalizedThumbprint(mixed $value): ?string
    {
        $thumbprint = $this->stringValue($value);
        if ($thumbprint === null) {
            return null;
        }

        return strtoupper(preg_replace('/\s+/', '', $thumbprint) ?? $thumbprint);
    }

    private function digits(mixed $value, int $length): ?string
    {
        $string = $this->stringValue($value);
        if ($string === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $string);

        return is_string($digits) && strlen($digits) === $length ? $digits : null;
    }

    private function documentType(mixed $value): ?string
    {
        $type = $this->stringValue($value);

        return match ($type) {
            'cpf', 'cnpj' => $type,
            default => null,
        };
    }

    private function isFiscalCandidate(
        bool $hasPrivateKey,
        bool $isExpired,
        bool $isCertificateAuthority,
        bool $isIcpBrasil,
        bool $isUsableForClientAuth,
        ?string $document,
        ?string $storeLocation,
    ): bool {
        return $hasPrivateKey
            && ! $isExpired
            && ! $isCertificateAuthority
            && $isIcpBrasil
            && $isUsableForClientAuth
            && $document !== null
            && in_array($storeLocation, ['CurrentUser', 'LocalMachine'], true);
    }

    private function classification(
        string $reportedClassification,
        bool $isFiscalCandidate,
        bool $hasPrivateKey,
        bool $isExpired,
        bool $isCertificateAuthority,
        bool $isIcpBrasil,
        ?string $subject,
        ?string $issuer,
    ): string {
        if ($isFiscalCandidate) {
            return 'fiscal_candidate';
        }

        if ($isExpired && $isIcpBrasil) {
            return 'expired_fiscal';
        }

        if ($isCertificateAuthority) {
            return 'ca_certificate';
        }

        if (! $hasPrivateKey) {
            return 'missing_private_key';
        }

        if ($this->looksLikeSystemCertificate($subject, $issuer) || $reportedClassification === 'system_certificate') {
            return 'system_certificate';
        }

        return $reportedClassification === 'fiscal_candidate' ? 'unknown' : $reportedClassification;
    }

    /**
     * @param  list<string>  $reportedReasons
     * @return list<string>
     */
    private function rejectionReasons(
        array $reportedReasons,
        bool $hasPrivateKey,
        bool $isExpired,
        bool $isCertificateAuthority,
        bool $isIcpBrasil,
        bool $isUsableForClientAuth,
        ?string $document,
        ?string $storeLocation,
    ): array {
        $reasons = $reportedReasons;
        $this->addReason($reasons, ! $hasPrivateKey, 'Certificado sem chave privada.');
        $this->addReason($reasons, $isExpired, 'Certificado vencido.');
        $this->addReason($reasons, $isCertificateAuthority, 'Certificado de autoridade certificadora.');
        $this->addReason($reasons, ! $isIcpBrasil, 'Emissor/cadeia não indica ICP-Brasil.');
        $this->addReason($reasons, ! $isUsableForClientAuth, 'Uso do certificado não é compatível com autenticação/assinatura de cliente.');
        $this->addReason($reasons, $document === null, 'CPF/CNPJ não identificado no certificado.');
        $this->addReason($reasons, ! in_array($storeLocation, ['CurrentUser', 'LocalMachine'], true), 'Store do Windows não suportado para uso fiscal.');

        return array_values(array_unique($reasons));
    }

    /** @param list<string> $reasons */
    private function addReason(array &$reasons, bool $condition, string $message): void
    {
        if ($condition) {
            $reasons[] = $message;
        }
    }

    private function looksLikeSystemCertificate(?string $subject, ?string $issuer): bool
    {
        $text = strtolower(($subject ?? '').' '.($issuer ?? ''));

        return str_contains($text, 'microsoft')
            || str_contains($text, 'windows admin')
            || str_contains($text, 'windowsadmincenter')
            || str_contains($text, 'localhost')
            || str_contains($text, 'self-signed')
            || str_contains($text, 'remote desktop');
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            $string = $this->stringValue($item);
            if ($string !== null) {
                $items[] = $string;
            }
        }

        return $items;
    }

    private function booleanValue(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    private function sanitizePayload(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                unset($payload[$key]);

                continue;
            }

            if (is_array($value)) {
                $payload[$key] = $this->sanitizePayload($value);
            }
        }

        return $payload;
    }

    private function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if (str_contains(strtolower($key), $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }
}
