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
            $hasPrivateKey = (bool) Arr::get($item, 'has_private_key', false);
            $isExpired = $this->booleanValue(Arr::get($item, 'is_expired')) ?? ($notAfter !== null && $notAfter->isPast());
            $isValid = $this->booleanValue(Arr::get($item, 'is_valid')) ?? ($hasPrivateKey && ! $isExpired);

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

            $subject = $this->stringValue(Arr::get($item, 'subject'));
            $issuer = $this->stringValue(Arr::get($item, 'issuer'));

            $certificate->fill([
                'type' => CertificateType::A3,
                'status' => $this->status($isExpired, $isValid),
                'store_scope' => $storeScope,
                'subject' => $subject,
                'subject_name' => $subject,
                'issuer' => $issuer,
                'issuer_name' => $issuer,
                'serial_number' => $this->stringValue(Arr::get($item, 'serial_number')),
                'cnpj' => $this->digits(Arr::get($item, 'cnpj'), 14),
                'not_before' => $notBefore,
                'not_after' => $notAfter,
                'valid_from' => $notBefore,
                'valid_until' => $notAfter,
                'has_private_key' => $hasPrivateKey,
                'is_expired' => $isExpired,
                'is_valid' => $isValid,
                'validation_message' => $this->stringValue(Arr::get($item, 'validation_message')),
                'last_seen_at' => now(),
                'metadata' => [
                    'command_uuid' => $command->uuid,
                    'reference' => Arr::get($item, 'reference'),
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
