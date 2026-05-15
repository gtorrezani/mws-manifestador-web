<?php

namespace App\Actions\Certificates;

use App\DTOs\Agent\CommandFailureData;
use App\DTOs\Agent\CommandResultData;
use App\Models\AgentCommand;
use App\Models\SefazConnectivityTest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class RecordSefazConnectivityTestResultAction
{
    public function markProcessing(AgentCommand $command): void
    {
        $this->queryForCommand($command)->update(['status' => 'processing']);
    }

    public function recordCompleted(AgentCommand $command, CommandResultData $data): void
    {
        $result = $data->result ?? [];

        $this->queryForCommand($command)->update([
            'status' => 'success',
            'endpoint' => $this->string($result, 'endpoint'),
            'sefaz_status_code' => $data->sefazStatusCode ?? $this->string($result, 'sefaz_status_code'),
            'sefaz_message' => $data->sefazMessage ?? $this->string($result, 'sefaz_message'),
            'duration_ms' => $data->durationMs ?? $this->int($result, 'duration_ms'),
            'request_xml_storage_path' => $data->requestXml['storage_path'] ?? null,
            'response_xml_storage_path' => $data->responseXml['storage_path'] ?? null,
            'sanitized_payload' => $this->sanitizePayload($result),
            'completed_at' => now(),
        ]);
    }

    public function recordFailed(AgentCommand $command, CommandFailureData $data): void
    {
        $this->queryForCommand($command)->update([
            'status' => 'failed',
            'error_code' => $data->errorCode,
            'error_message' => $data->errorMessage,
            'duration_ms' => $data->durationMs,
            'sanitized_payload' => $this->sanitizePayload($data->errorDetails),
            'completed_at' => now(),
        ]);
    }

    /** @return Builder<SefazConnectivityTest> */
    private function queryForCommand(AgentCommand $command)
    {
        $testUuid = $command->payload['sefaz_connectivity_test_uuid'] ?? null;

        return SefazConnectivityTest::query()
            ->where('tenant_id', $command->tenant_id)
            ->where('company_id', $command->company_id)
            ->where('agent_id', $command->agent_id)
            ->where('agent_command_id', $command->id)
            ->when(is_string($testUuid), fn ($query) => $query->where('uuid', $testUuid));
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

    /** @param array<string, mixed> $payload */
    private function string(array $payload, string $key): ?string
    {
        $value = Arr::get($payload, $key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string, mixed> $payload */
    private function int(array $payload, string $key): ?int
    {
        $value = Arr::get($payload, $key);

        return is_int($value) ? $value : null;
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
