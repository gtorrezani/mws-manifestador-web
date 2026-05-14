<?php

namespace Database\Factories;

use App\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Agent> */
class AgentFactory extends Factory
{
    protected $model = Agent::class;

    public function definition(): array
    {
        $company = Company::factory()->create();

        return [
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'name' => 'Agent '.$company->trade_name,
            'machine_name' => strtoupper(fake()->bothify('MWS-###')),
            'installation_id' => fake()->uuid(),
            'version' => '1.0.0',
            'status' => AgentStatus::Offline,
        ];
    }
}
