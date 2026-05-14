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
    public function execute(Agent $agent, AgentCommand $command, CommandResultData $data): void
    {
        $certificates = $data->result['certificates'] ?? null;
        if (! is_array($certificates)) {
            return;
        }

        $seenThumbprints = [];

        foreach ($certificates as $item) {
            if (! is_array($item)) {
                continue;
            }

            $thumbprint = $this->stringValue(Arr::get($item, 'thumbprint'));
            if ($thumbprint === null) {
                continue;
            }

            $storeScope = $this->storeScope(Arr::get($item, 'store_scope'));
            $validUntil = $this->dateValue(Arr::get($item, 'not_after'));
            $hasPrivateKey = (bool) Arr::get($item, 'has_private_key', false);

            /** @var AgentCertificate $certificate */
            $certificate = AgentCertificate::query()->firstOrNew([
                'tenant_id' => $agent->tenant_id,
                'agent_id' => $agent->id,
                'thumbprint' => $thumbprint,
                'store_scope' => $storeScope,
            ]);

            if (! $certificate->exists) {
                $certificate->uuid = (string) Str::uuid();
            }

            $certificate->fill([
                'company_id' => $agent->company_id,
                'type' => CertificateType::A3,
                'status' => $this->status($validUntil, $hasPrivateKey),
                'subject_name' => $this->stringValue(Arr::get($item, 'subject')),
                'issuer_name' => $this->stringValue(Arr::get($item, 'issuer')),
                'serial_number' => $this->stringValue(Arr::get($item, 'serial_number')),
                'cnpj' => $this->digits(Arr::get($item, 'cnpj'), 14),
                'valid_from' => $this->dateValue(Arr::get($item, 'not_before')),
                'valid_until' => $validUntil,
                'has_private_key' => $hasPrivateKey,
                'last_seen_at' => now(),
                'metadata' => [
                    'command_uuid' => $command->uuid,
                    'reference' => Arr::get($item, 'reference'),
                ],
            ])->save();

            $seenThumbprints[] = $certificate->thumbprint;
        }

        if ($seenThumbprints !== []) {
            AgentCertificate::query()
                ->where('agent_id', $agent->id)
                ->whereNotIn('thumbprint', $seenThumbprints)
                ->update(['last_seen_at' => null]);
        }
    }

    private function status(?Carbon $validUntil, bool $hasPrivateKey): CertificateStatus
    {
        if (! $hasPrivateKey) {
            return CertificateStatus::Invalid;
        }

        if ($validUntil !== null && $validUntil->isPast()) {
            return CertificateStatus::Expired;
        }

        return CertificateStatus::Active;
    }

    private function storeScope(mixed $value): ?string
    {
        if (is_int($value)) {
            return match ($value) {
                1 => 'current_user',
                2 => 'local_machine',
                default => null,
            };
        }

        $scope = $this->stringValue($value);
        if ($scope === null) {
            return null;
        }

        return Str::of($scope)->snake()->lower()->toString();
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

    private function digits(mixed $value, int $length): ?string
    {
        $string = $this->stringValue($value);
        if ($string === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $string);

        return is_string($digits) && strlen($digits) === $length ? $digits : null;
    }
}
