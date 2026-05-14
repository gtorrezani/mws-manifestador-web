<?php

namespace Database\Factories;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Models\AgentCommand;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AgentCommand> */
class AgentCommandFactory extends Factory
{
    protected $model = AgentCommand::class;

    public function definition(): array
    {
        $company = Company::factory()->create();

        return [
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'type' => CommandType::SyncFiscalDocuments,
            'status' => CommandStatus::Pending,
            'priority' => 100,
            'payload' => ['last_nsu' => '0'],
            'available_at' => now(),
            'max_attempts' => 3,
            'idempotency_key' => fake()->uuid(),
        ];
    }
}
