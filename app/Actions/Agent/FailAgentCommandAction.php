<?php

namespace App\Actions\Agent;

use App\Actions\Certificates\RecordCertificateTestResultAction;
use App\Actions\Certificates\RecordSefazConnectivityTestResultAction;
use App\Actions\FiscalDocument\RecordFiscalDocumentSyncResultAction;
use App\Actions\FiscalDocument\RecordManifestationResultAction;
use App\DTOs\Agent\CommandFailureData;
use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Models\Agent;
use App\Models\AgentCommand;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FailAgentCommandAction
{
    public function __construct(
        private readonly RecordManifestationResultAction $recordManifestationResultAction,
        private readonly RecordFiscalDocumentSyncResultAction $recordFiscalDocumentSyncResultAction,
        private readonly RecordCertificateTestResultAction $recordCertificateTestResultAction,
        private readonly RecordSefazConnectivityTestResultAction $recordSefazConnectivityTestResultAction,
    ) {}

    /** @return array{status: string, idempotent?: bool, final?: bool, available_at?: string|null} */
    public function execute(Agent $agent, string $commandUuid, CommandFailureData $data): array
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

            if ($command->tenant_id !== $agent->tenant_id || $command->locked_by_agent_id !== $agent->id) {
                throw new AccessDeniedHttpException('Command is not owned by this agent.');
            }

            if ($command->status === CommandStatus::Completed) {
                return ['status' => CommandStatus::Completed->value, 'idempotent' => true];
            }

            if ($command->status !== CommandStatus::Processing) {
                throw new ConflictHttpException('Command must be processing before failure.');
            }

            $isFinalFailure = $command->attempts_count >= $command->max_attempts
                || ($command->type === CommandType::SyncFiscalDocuments && $this->isDistributionConsumptionDenied($data));
            $nextStatus = $isFinalFailure ? CommandStatus::Failed : CommandStatus::Pending;

            $command->forceFill([
                'status' => $nextStatus,
                'available_at' => $isFinalFailure ? $command->available_at : now()->addSeconds($this->retryDelaySeconds($command)),
                'failed_at' => $isFinalFailure ? now() : null,
                'locked_at' => null,
                'locked_by_agent_id' => null,
                'lock_expires_at' => null,
            ])->save();

            $attempt = $command->attempts()
                ->where('attempt_number', $command->attempts_count)
                ->latest('id')
                ->first();

            $attempt?->forceFill([
                'status' => CommandStatus::Failed,
                'finished_at' => now(),
                'duration_ms' => $data->durationMs,
                'error_code' => $data->errorCode,
                'error_message' => $data->errorMessage,
                'result_payload' => [
                    'error_details' => $data->errorDetails,
                    'sefaz_status_code' => $data->sefazStatusCode,
                    'sefaz_message' => $data->sefazMessage,
                ],
            ])->save();

            $this->recordManifestationResultAction->recordFailed($command, $data, $isFinalFailure);
            if ($command->type === CommandType::SyncFiscalDocuments && $isFinalFailure) {
                $this->recordFiscalDocumentSyncResultAction->recordFailed($command, $data);
            }

            if ($command->type === CommandType::TestCertificate) {
                $this->recordCertificateTestResultAction->recordFailed($command, $data);
            }
            if ($command->type === CommandType::TestSefazConnectivity) {
                $this->recordSefazConnectivityTestResultAction->recordFailed($command, $data);
            }

            return [
                'status' => $nextStatus->value,
                'final' => $isFinalFailure,
                'available_at' => $command->available_at?->toISOString(),
            ];
        });
    }

    private function retryDelaySeconds(AgentCommand $command): int
    {
        return min(900, 30 * max(1, $command->attempts_count));
    }

    private function isDistributionConsumptionDenied(CommandFailureData $data): bool
    {
        return $data->errorCode === 'SEFAZ_DISTRIBUTION_CONSUMPTION_DENIED'
            || $data->sefazStatusCode === '656';
    }
}
