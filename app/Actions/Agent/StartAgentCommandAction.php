<?php

namespace App\Actions\Agent;

use App\Actions\Certificates\RecordSefazConnectivityTestResultAction;
use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\ManifestationRecordStatus;
use App\Enums\ManifestationStatus;
use App\Models\Agent;
use App\Models\AgentCommand;
use App\Models\AgentCommandAttempt;
use App\Models\ManifestationAttempt;
use App\Models\RecipientManifestation;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StartAgentCommandAction
{
    public function __construct(
        private readonly RecordSefazConnectivityTestResultAction $recordSefazConnectivityTestResultAction,
    ) {}

    /** @return array{status: string, idempotent?: bool, attempt_number?: int, lock_expires_at?: string|null} */
    public function execute(Agent $agent, string $commandUuid): array
    {
        return DB::transaction(function () use ($agent, $commandUuid): array {
            /** @var AgentCommand|null $command */
            $command = AgentCommand::query()
                ->where('uuid', $commandUuid)
                ->lockForUpdate()
                ->first();

            if (! $command) {
                throw new NotFoundHttpException('Command not found.');
            }

            if ($command->tenant_id !== $agent->tenant_id || $command->locked_by_agent_id !== $agent->id) {
                throw new AccessDeniedHttpException('Command is not locked by this agent.');
            }

            if ($command->status === CommandStatus::Completed) {
                return ['status' => CommandStatus::Completed->value, 'idempotent' => true];
            }

            if ($command->lock_expires_at?->isPast()) {
                throw new ConflictHttpException('Command lock has expired.');
            }

            if ($command->status !== CommandStatus::Locked) {
                throw new ConflictHttpException('Command cannot be started from current status.');
            }

            $attemptNumber = $command->attempts_count + 1;

            $command->forceFill([
                'status' => CommandStatus::Processing,
                'attempts_count' => $attemptNumber,
            ])->save();

            AgentCommandAttempt::query()->create([
                'tenant_id' => $command->tenant_id,
                'agent_command_id' => $command->id,
                'agent_id' => $agent->id,
                'attempt_number' => $attemptNumber,
                'status' => CommandStatus::Processing,
                'started_at' => now(),
            ]);

            $this->startManifestationAttempt($command, $agent, $attemptNumber);
            if ($command->type === CommandType::TestSefazConnectivity) {
                $this->recordSefazConnectivityTestResultAction->markProcessing($command);
            }

            return [
                'status' => CommandStatus::Processing->value,
                'attempt_number' => $attemptNumber,
                'lock_expires_at' => $command->lock_expires_at?->toISOString(),
            ];
        });
    }

    private function startManifestationAttempt(AgentCommand $command, Agent $agent, int $attemptNumber): void
    {
        $manifestationUuid = $command->payload['recipient_manifestation_uuid'] ?? null;
        if (! is_string($manifestationUuid) || $manifestationUuid === '') {
            return;
        }

        $manifestation = RecipientManifestation::query()->where('uuid', $manifestationUuid)->first();
        if (! $manifestation) {
            return;
        }

        ManifestationAttempt::query()->updateOrCreate(
            [
                'recipient_manifestation_id' => $manifestation->id,
                'attempt_number' => $attemptNumber,
            ],
            [
                'tenant_id' => $manifestation->tenant_id,
                'agent_command_id' => $command->id,
                'agent_id' => $agent->id,
                'status' => ManifestationRecordStatus::Processing,
                'previous_manifestation_status' => $command->payload['previous_manifestation_status'] ?? ManifestationStatus::NoManifestation->value,
                'new_manifestation_status' => $command->payload['requested_manifestation_status'] ?? null,
                'started_at' => now(),
            ],
        );
    }
}
