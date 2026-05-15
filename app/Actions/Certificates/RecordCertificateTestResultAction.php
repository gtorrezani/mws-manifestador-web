<?php

namespace App\Actions\Certificates;

use App\DTOs\Agent\CommandFailureData;
use App\DTOs\Agent\CommandResultData;
use App\Models\AgentCertificate;
use App\Models\AgentCommand;
use App\Models\CompanyCertificate;
use Illuminate\Support\Arr;

class RecordCertificateTestResultAction
{
    public function recordCompleted(AgentCommand $command, CommandResultData $data): void
    {
        $thumbprint = $this->thumbprint($command, $data);
        if ($thumbprint === null) {
            return;
        }

        $certificate = is_array(Arr::get($data->result ?? [], 'certificate'))
            ? Arr::get($data->result ?? [], 'certificate')
            : [];
        $isValid = Arr::get($certificate, 'is_valid', true) === true;
        $status = $isValid ? 'valid' : 'invalid';
        $message = is_string(Arr::get($certificate, 'validation_message'))
            ? (string) Arr::get($certificate, 'validation_message')
            : ($isValid ? 'Certificado validado com sucesso.' : 'Certificado inválido.');

        $this->updateByThumbprint($command, $thumbprint, $status, $message, null, $data->result);
    }

    public function recordFailed(AgentCommand $command, CommandFailureData $data): void
    {
        $thumbprint = $this->thumbprint($command);
        if ($thumbprint === null) {
            return;
        }

        $this->updateByThumbprint($command, $thumbprint, 'failed', $data->errorMessage, $data->errorCode, $data->errorDetails);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function updateByThumbprint(
        AgentCommand $command,
        string $thumbprint,
        string $status,
        string $message,
        ?string $errorCode,
        ?array $payload,
    ): void {
        $agentCertificateUuid = $this->stringPayload($command, 'agent_certificate_uuid');
        $companyCertificateUuid = $this->stringPayload($command, 'company_certificate_uuid');
        $storeLocation = $this->storeLocation($command);
        $sanitizedPayload = $this->sanitizePayload($payload);

        $agentCertificateQuery = AgentCertificate::query()
            ->where('tenant_id', $command->tenant_id)
            ->where('company_id', $command->company_id)
            ->where('agent_id', $command->agent_id)
            ->where('thumbprint', $thumbprint);

        if ($agentCertificateUuid !== null) {
            $agentCertificateQuery->where('uuid', $agentCertificateUuid);
        }

        if ($storeLocation !== null) {
            $agentCertificateQuery->where('store_location', $storeLocation);
        }

        $agentCertificateQuery->update([
            'last_tested_at' => now(),
            'last_test_status' => $status,
            'last_test_message' => $message,
            'last_test_error_code' => $errorCode,
            'last_test_payload' => $sanitizedPayload,
            'is_valid' => $status === 'valid',
            'validation_message' => $message,
        ]);

        $companyCertificateQuery = CompanyCertificate::query()
            ->where('tenant_id', $command->tenant_id)
            ->where('company_id', $command->company_id)
            ->where('agent_id', $command->agent_id)
            ->where('thumbprint', $thumbprint);

        if ($companyCertificateUuid !== null) {
            $companyCertificateQuery->where('uuid', $companyCertificateUuid);
        }

        $companyCertificateQuery->update([
            'last_tested_at' => now(),
            'last_test_status' => $status,
            'last_test_message' => $message,
            'last_test_error_code' => $errorCode,
            'last_test_payload' => $sanitizedPayload,
            'last_validated_at' => $status === 'valid' ? now() : null,
        ]);
    }

    private function storeLocation(AgentCommand $command): ?string
    {
        $value = $this->stringPayload($command, 'store_location');

        return match ($value) {
            'CurrentUser', 'LocalMachine' => $value,
            'current_user' => 'CurrentUser',
            'local_machine' => 'LocalMachine',
            default => null,
        };
    }

    private function thumbprint(AgentCommand $command, ?CommandResultData $data = null): ?string
    {
        $result = $data?->result ?? [];
        $value = $command->payload['thumbprint'] ?? Arr::get($result, 'certificate.thumbprint') ?? Arr::get($result, 'thumbprint');
        if (! is_scalar($value)) {
            return null;
        }

        $thumbprint = strtoupper(preg_replace('/\s+/', '', (string) $value) ?? '');

        return $thumbprint === '' ? null : $thumbprint;
    }

    private function stringPayload(AgentCommand $command, string $key): ?string
    {
        $value = $command->payload[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    private function sanitizePayload(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $sanitized = [];
        foreach ($payload as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                continue;
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $nested */
                $nested = $value;
                $sanitized[$key] = $this->sanitizePayload($nested);

                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        return str_contains($normalized, 'pin')
            || str_contains($normalized, 'password')
            || str_contains($normalized, 'private_key')
            || str_contains($normalized, 'secret')
            || str_contains($normalized, 'token');
    }
}
