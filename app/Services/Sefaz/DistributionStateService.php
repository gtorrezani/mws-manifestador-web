<?php

namespace App\Services\Sefaz;

use App\DTOs\Agent\CommandFailureData;
use App\Models\AgentCommand;
use App\Models\Company;
use App\Models\CompanyFiscalState;
use Illuminate\Support\Arr;

class DistributionStateService
{
    public const SERVICE = 'nfe_distribution';

    /** @param array<string, mixed> $payload */
    public function stateForCommand(AgentCommand $command, array $payload = []): CompanyFiscalState
    {
        /** @var CompanyFiscalState $state */
        $state = CompanyFiscalState::query()->firstOrCreate(
            [
                'tenant_id' => $command->tenant_id,
                'company_id' => $command->company_id,
                'environment' => $this->string($payload, 'environment') ?? ($command->payload['environment'] ?? 'homologation'),
                'uf' => $this->string($payload, 'uf') ?? ($command->payload['uf'] ?? 'SP'),
                'service' => $this->string($payload, 'service') ?? self::SERVICE,
            ],
            [
                'last_nsu' => '000000000000000',
                'max_nsu' => '000000000000000',
                'consecutive_distribution_failures' => 0,
            ],
        );

        return $state;
    }

    public function stateForCompany(Company $company): CompanyFiscalState
    {
        /** @var CompanyFiscalState $state */
        $state = CompanyFiscalState::query()->firstOrCreate(
            [
                'tenant_id' => $company->tenant_id,
                'company_id' => $company->id,
                'environment' => $company->fiscal_environment->value,
                'uf' => $company->uf,
                'service' => self::SERVICE,
            ],
            [
                'last_nsu' => '000000000000000',
                'max_nsu' => '000000000000000',
                'consecutive_distribution_failures' => 0,
            ],
        );

        return $state;
    }

    public function availability(CompanyFiscalState $state): DistributionAvailability
    {
        $now = now();

        if ($state->distribution_blocked_until !== null && $state->distribution_blocked_until->isFuture()) {
            return new DistributionAvailability(
                false,
                $state->distribution_block_reason,
                'Consulta bloqueada temporariamente pela SEFAZ por consumo indevido. Tente novamente após '.$state->distribution_blocked_until->timezone(config('app.timezone'))->format('H:i').'.',
                $state->distribution_blocked_until,
            );
        }

        if ($state->next_distribution_available_at !== null && $state->next_distribution_available_at->greaterThan($now)) {
            return new DistributionAvailability(
                false,
                $state->distribution_block_reason ?? 'cooldown',
                'Nenhum documento localizado. Nova consulta disponível a partir de '.$state->next_distribution_available_at->timezone(config('app.timezone'))->format('H:i').'.',
                $state->next_distribution_available_at,
            );
        }

        return new DistributionAvailability(true, null, 'Consulta SEFAZ disponível.', null);
    }

    /** @param array<string, mixed> $result */
    public function recordCompleted(CompanyFiscalState $state, AgentCommand $command, array $result, ?string $statusCode, ?string $message): void
    {
        $now = now();
        $lastNsu = in_array($statusCode, ['137', '138'], true)
            ? $this->nsu($result, 'last_nsu', $state->last_nsu)
            : $state->last_nsu;
        $maxNsu = in_array($statusCode, ['137', '138'], true)
            ? $this->nsu($result, 'max_nsu', $state->max_nsu)
            : $state->max_nsu;
        $consecutiveFailures = in_array($statusCode, ['137', '138'], true)
            ? 0
            : max(0, (int) ($state->getAttribute('consecutive_distribution_failures') ?? 0));

        $state->forceFill([
            'last_nsu' => $lastNsu,
            'max_nsu' => $maxNsu,
            'last_status_code' => $statusCode,
            'last_message' => $message,
            'last_distribution_status_code' => $statusCode,
            'last_distribution_message' => $message,
            'last_distribution_attempt_at' => $now,
            'last_distribution_success_at' => in_array($statusCode, ['137', '138'], true) ? $now : $state->last_distribution_success_at,
            'last_distribution_error_at' => in_array($statusCode, ['137', '138'], true) ? null : $now,
            'last_success_at' => in_array($statusCode, ['137', '138'], true) ? $now : $state->last_success_at,
            'last_error_at' => in_array($statusCode, ['137', '138'], true) ? null : $now,
            'consecutive_distribution_failures' => $consecutiveFailures,
            'metadata' => [
                'command_uuid' => $command->uuid,
                'documents_count' => is_array($result['documents'] ?? null) ? count($result['documents']) : 0,
                'endpoint' => $this->string($result, 'endpoint'),
                'distribution_result' => $this->distributionResult($statusCode),
            ],
        ]);

        if ($statusCode === '137') {
            $this->applyCooldown($state, 'no_documents', $this->noDocumentsCooldownMinutes());
        } elseif ($statusCode === '138') {
            $this->applyDocumentsFoundWindow($state, $lastNsu, $maxNsu);
        }

        $state->save();
    }

    public function recordFailed(CompanyFiscalState $state, AgentCommand $command, CommandFailureData $data): void
    {
        $now = now();
        $isConsumptionDenied = $data->errorCode === 'SEFAZ_DISTRIBUTION_CONSUMPTION_DENIED' || $data->sefazStatusCode === '656';
        $message = $this->failureMessage($data);

        $state->forceFill([
            'last_status_code' => $data->sefazStatusCode,
            'last_message' => $message,
            'last_distribution_status_code' => $data->sefazStatusCode,
            'last_distribution_message' => $message,
            'last_distribution_attempt_at' => $now,
            'last_distribution_error_at' => $now,
            'last_error_at' => $now,
            'consecutive_distribution_failures' => ((int) ($state->consecutive_distribution_failures ?? 0)) + 1,
            'metadata' => [
                'command_uuid' => $command->uuid,
                'error_code' => $data->errorCode,
                'error_message' => $data->errorMessage,
                'distribution_result' => $isConsumptionDenied ? 'consumption_denied' : 'technical_error',
                'validation_errors' => $this->validationErrors($data->errorDetails),
            ],
        ]);

        if ($isConsumptionDenied) {
            $state->forceFill([
                'distribution_blocked_until' => $now->copy()->addMinutes($this->consumptionDeniedCooldownMinutes()),
                'distribution_block_reason' => 'consumption_denied',
                'next_distribution_available_at' => null,
            ]);
        } else {
            $this->applyCooldown($state, 'technical_error', $this->technicalErrorBackoffMinutes());
        }

        $state->save();
    }

    private function applyDocumentsFoundWindow(CompanyFiscalState $state, string $lastNsu, string $maxNsu): void
    {
        if ($this->allowImmediateContinueWhenNsuPending() && strcmp($lastNsu, $maxNsu) < 0) {
            $state->forceFill([
                'next_distribution_available_at' => null,
                'distribution_blocked_until' => null,
                'distribution_block_reason' => null,
            ]);

            return;
        }

        $this->applyCooldown($state, 'documents_found_completed', $this->noDocumentsCooldownMinutes());
    }

    private function applyCooldown(CompanyFiscalState $state, string $reason, int $minutes): void
    {
        $state->forceFill([
            'next_distribution_available_at' => now()->addMinutes($minutes),
            'distribution_blocked_until' => null,
            'distribution_block_reason' => $reason,
        ]);
    }

    private function failureMessage(CommandFailureData $data): string
    {
        if ($data->errorCode === 'SEFAZ_XML_SCHEMA_INVALID') {
            return 'XML rejeitado pela validação técnica antes do envio à SEFAZ.';
        }

        if ($data->errorCode === 'SEFAZ_DISTRIBUTION_CONSUMPTION_DENIED' || $data->sefazStatusCode === '656') {
            return $data->sefazMessage ?? $data->errorMessage;
        }

        return $data->sefazMessage ?? $data->errorMessage;
    }

    private function distributionResult(?string $statusCode): ?string
    {
        return match ($statusCode) {
            '137' => 'no_documents',
            '138' => 'documents_found',
            '656' => 'consumption_denied',
            default => null,
        };
    }

    /** @param array<string, mixed> $payload */
    private function string(array $payload, string $key): ?string
    {
        $value = Arr::get($payload, $key);

        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    /** @param array<string, mixed> $payload */
    private function nsu(array $payload, string $key, ?string $fallback): string
    {
        $value = $this->digits($payload[$key] ?? null, 15);

        return $value ?? $fallback ?? '000000000000000';
    }

    private function digits(mixed $value, int $length): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $value);

        return is_string($digits) && strlen($digits) === $length ? $digits : null;
    }

    /**
     * @param  array<string, mixed>|null  $errorDetails
     * @return array<int, mixed>|null
     */
    private function validationErrors(?array $errorDetails): ?array
    {
        $errors = $errorDetails['validation_errors'] ?? null;

        return is_array($errors) ? array_values($errors) : null;
    }

    private function noDocumentsCooldownMinutes(): int
    {
        return (int) config('sefaz.distribution.no_documents_cooldown_minutes', 60);
    }

    private function consumptionDeniedCooldownMinutes(): int
    {
        return (int) config('sefaz.distribution.consumption_denied_cooldown_minutes', 60);
    }

    private function technicalErrorBackoffMinutes(): int
    {
        return (int) config('sefaz.distribution.technical_error_backoff_minutes', 5);
    }

    private function allowImmediateContinueWhenNsuPending(): bool
    {
        return (bool) config('sefaz.distribution.allow_immediate_continue_when_nsu_pending', true);
    }
}
