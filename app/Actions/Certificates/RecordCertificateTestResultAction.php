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

        $this->updateByThumbprint($command, $thumbprint, 'valid', 'Certificado validado com sucesso.');
    }

    public function recordFailed(AgentCommand $command, CommandFailureData $data): void
    {
        $thumbprint = $this->thumbprint($command);
        if ($thumbprint === null) {
            return;
        }

        $this->updateByThumbprint($command, $thumbprint, 'invalid', $data->errorMessage);
    }

    private function updateByThumbprint(AgentCommand $command, string $thumbprint, string $status, string $message): void
    {
        AgentCertificate::query()
            ->where('tenant_id', $command->tenant_id)
            ->where('agent_id', $command->agent_id)
            ->where('thumbprint', $thumbprint)
            ->update([
                'last_tested_at' => now(),
                'last_test_status' => $status,
                'last_test_message' => $message,
            ]);

        CompanyCertificate::query()
            ->where('tenant_id', $command->tenant_id)
            ->where('company_id', $command->company_id)
            ->where('thumbprint', $thumbprint)
            ->update([
                'last_tested_at' => now(),
                'last_test_status' => $status,
                'last_test_message' => $message,
                'last_validated_at' => $status === 'valid' ? now() : null,
            ]);
    }

    private function thumbprint(AgentCommand $command, ?CommandResultData $data = null): ?string
    {
        $result = $data?->result ?? [];
        $value = $command->payload['thumbprint'] ?? Arr::get($result, 'thumbprint');
        if (! is_scalar($value)) {
            return null;
        }

        $thumbprint = strtoupper(preg_replace('/\s+/', '', (string) $value) ?? '');

        return $thumbprint === '' ? null : $thumbprint;
    }
}
