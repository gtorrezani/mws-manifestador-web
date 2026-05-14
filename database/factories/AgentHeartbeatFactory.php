<?php

namespace Database\Factories;

use App\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\AgentHeartbeat;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AgentHeartbeat> */
class AgentHeartbeatFactory extends Factory
{
    protected $model = AgentHeartbeat::class;

    public function definition(): array
    {
        $agent = Agent::factory()->create();

        return [
            'tenant_id' => $agent->tenant_id,
            'agent_id' => $agent->id,
            'status' => AgentStatus::Online,
            'version' => $agent->version,
            'machine_name' => $agent->machine_name,
            'ip_address' => fake()->ipv4(),
            'payload' => ['certificate_store' => 'available'],
            'received_at' => now(),
        ];
    }
}
