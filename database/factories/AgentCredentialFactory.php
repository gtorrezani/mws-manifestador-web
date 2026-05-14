<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\AgentCredential;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<AgentCredential> */
class AgentCredentialFactory extends Factory
{
    protected $model = AgentCredential::class;

    public function definition(): array
    {
        $agent = Agent::factory()->create();
        $secret = fake()->sha256();

        return [
            'tenant_id' => $agent->tenant_id,
            'agent_id' => $agent->id,
            'credential_id' => fake()->uuid(),
            'secret_hash' => Hash::make($secret),
            'encrypted_secret_payload' => Crypt::encryptString($secret),
            'last_rotated_at' => now(),
        ];
    }
}
