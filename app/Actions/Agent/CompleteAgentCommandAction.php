<?php

namespace App\Actions\Agent;

use App\Actions\Certificates\RecordAgentCertificateInventoryAction;
use App\Actions\Certificates\RecordCertificateTestResultAction;
use App\Actions\Certificates\RecordSefazConnectivityTestResultAction;
use App\Actions\FiscalDocument\RecordFiscalDocumentSyncResultAction;
use App\Actions\FiscalDocument\RecordManifestationResultAction;
use App\DTOs\Agent\CommandResultData;
use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Models\Agent;
use App\Models\AgentCommand;
use App\Models\AuditLog;
use App\Services\Agent\SefazResultRecorder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CompleteAgentCommandAction
{
    public function __construct(
        private readonly SefazResultRecorder $sefazResultRecorder,
        private readonly RecordManifestationResultAction $recordManifestationResultAction,
        private readonly RecordFiscalDocumentSyncResultAction $recordFiscalDocumentSyncResultAction,
        private readonly RecordAgentCertificateInventoryAction $recordAgentCertificateInventoryAction,
        private readonly RecordCertificateTestResultAction $recordCertificateTestResultAction,
        private readonly RecordSefazConnectivityTestResultAction $recordSefazConnectivityTestResultAction,
    ) {}

    /** @return array{status: string, idempotent?: bool, completed_at?: string|null} */
    public function execute(Agent $agent, string $commandUuid, CommandResultData $data): array
    {
        return DB::transaction(function () use ($agent, $commandUuid, $data): array {
            /** @var AgentCommand|null $command */
            $command = AgentCommand::query()
                ->where('uuid', $commandUuid)
                ->lockForUpdate()
                ->first();

            if (! $command) {
                throw new NotFoundHttpException('Command not found.');
            }

            if ($command->status === CommandStatus::Completed) {
                return ['status' => CommandStatus::Completed->value, 'idempotent' => true];
            }

            if ($command->tenant_id !== $agent->tenant_id || $command->locked_by_agent_id !== $agent->id) {
                throw new AccessDeniedHttpException('Command is not owned by this agent.');
            }

            if ($command->status !== CommandStatus::Processing) {
                throw new ConflictHttpException('Command must be processing before completion.');
            }

            $command->forceFill([
                'status' => CommandStatus::Completed,
                'completed_at' => now(),
                'locked_at' => null,
                'locked_by_agent_id' => null,
                'lock_expires_at' => null,
            ])->save();

            $attempt = $command->attempts()
                ->where('attempt_number', $command->attempts_count)
                ->latest('id')
                ->first();

            $attempt?->forceFill([
                'status' => CommandStatus::Completed,
                'finished_at' => now(),
                'duration_ms' => $data->durationMs,
                'result_payload' => $data->result,
            ])->save();

            $this->sefazResultRecorder->recordSuccessfulResult($command, $data);
            $this->recordManifestationResultAction->recordCompleted($command, $data);
            if ($command->type === CommandType::SyncFiscalDocuments) {
                $this->recordFiscalDocumentSyncResultAction->recordCompleted($command, $data);
            }

            if ($command->type === CommandType::ListCertificates) {
                $this->recordAgentCertificateInventoryAction->execute($agent, $command, $data);
            }

            if ($command->type === CommandType::TestCertificate) {
                $this->recordCertificateTestResultAction->recordCompleted($command, $data);
            }

            if ($command->type === CommandType::TestSefazConnectivity) {
                $this->recordSefazConnectivityTestResultAction->recordCompleted($command, $data);
            }

            if ($command->type === CommandType::AgentDiagnosticsRequested) {
                AuditLog::query()->create([
                    'tenant_id' => $agent->tenant_id,
                    'company_id' => $agent->company_id,
                    'agent_id' => $agent->id,
                    'event' => 'agent.diagnostics.command_completed',
                    'metadata' => [
                        'result' => $data->result,
                        'command_uuid' => $command->uuid,
                    ],
                    'occurred_at' => now(),
                ]);
            }

            return [
                'status' => CommandStatus::Completed->value,
                'completed_at' => $command->completed_at?->toISOString(),
            ];
        });
    }
}
