<?php

namespace App\Services\Agent;

use App\DTOs\Agent\PollCommandsData;
use App\Enums\CommandStatus;
use App\Models\Agent;
use App\Models\AgentCommand;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AgentCommandQueueService
{
    /** @return Collection<int, AgentCommand> */
    public function poll(Agent $agent, PollCommandsData $data): Collection
    {
        $lockSeconds = (int) config('agent.commands.lock_seconds', 300);

        return DB::transaction(function () use ($agent, $data, $lockSeconds): Collection {
            $query = AgentCommand::query()
                ->where('tenant_id', $agent->tenant_id)
                ->where(function ($query) use ($agent): void {
                    $query->whereNull('agent_id')->orWhere('agent_id', $agent->id);
                })
                ->whereIn('status', [CommandStatus::Pending->value, CommandStatus::Locked->value])
                ->where(function ($query): void {
                    $query
                        ->whereNull('available_at')
                        ->orWhere('available_at', '<=', now());
                })
                ->where(function ($query): void {
                    $query
                        ->where('status', CommandStatus::Pending->value)
                        ->orWhere('lock_expires_at', '<=', now());
                });

            if ($agent->company_id) {
                $query->where('company_id', $agent->company_id);
            }

            if ($data->capabilities) {
                $query->whereIn('type', $data->capabilities);
            }

            /** @var Collection<int, AgentCommand> $commands */
            $commands = $query
                ->orderBy('priority')
                ->orderBy('created_at')
                ->lockForUpdate()
                ->limit($data->limit)
                ->get();

            foreach ($commands as $command) {
                $command->forceFill([
                    'status' => CommandStatus::Locked,
                    'locked_at' => now(),
                    'locked_by_agent_id' => $agent->id,
                    'lock_expires_at' => now()->addSeconds($lockSeconds),
                ])->save();
            }

            return $commands->fresh();
        });
    }

    /** @return array<string, mixed> */
    public function serialize(AgentCommand $command): array
    {
        return [
            'uuid' => $command->uuid,
            'type' => $command->type->value,
            'priority' => $command->priority,
            'payload' => $command->payload,
            'idempotency_key' => $command->idempotency_key,
            'available_at' => $command->available_at?->toISOString(),
            'lock_expires_at' => $command->lock_expires_at?->toISOString(),
            'attempts_count' => $command->attempts_count,
            'max_attempts' => $command->max_attempts,
        ];
    }
}
