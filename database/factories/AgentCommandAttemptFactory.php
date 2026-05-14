<?php

namespace Database\Factories;

use App\Enums\CommandStatus;
use App\Models\AgentCommand;
use App\Models\AgentCommandAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AgentCommandAttempt> */
class AgentCommandAttemptFactory extends Factory
{
    protected $model = AgentCommandAttempt::class;

    public function definition(): array
    {
        $command = AgentCommand::factory()->create();

        return [
            'tenant_id' => $command->tenant_id,
            'agent_command_id' => $command->id,
            'attempt_number' => 1,
            'status' => CommandStatus::Processing,
            'started_at' => now(),
        ];
    }
}
