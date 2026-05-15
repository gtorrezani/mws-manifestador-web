<?php

namespace Database\Factories;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Models\Agent;
use App\Models\AgentCertificate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AgentCertificate> */
class AgentCertificateFactory extends Factory
{
    protected $model = AgentCertificate::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $agent = Agent::factory()->create();

        return [
            'tenant_id' => $agent->tenant_id,
            'agent_id' => $agent->id,
            'company_id' => $agent->company_id,
            'type' => CertificateType::A3,
            'status' => CertificateStatus::Active,
            'store_scope' => 'current_user',
            'store_location' => 'CurrentUser',
            'subject' => 'CN=Empresa Teste:12345678000195',
            'subject_name' => 'CN=Empresa Teste:12345678000195',
            'issuer' => 'CN=AC Teste',
            'issuer_name' => 'CN=AC Teste',
            'serial_number' => fake()->sha1(),
            'thumbprint' => strtoupper(fake()->sha1()),
            'cnpj' => '12345678000195',
            'not_before' => now()->subMonth(),
            'not_after' => now()->addYear(),
            'valid_from' => now()->subMonth(),
            'valid_until' => now()->addYear(),
            'has_private_key' => true,
            'is_expired' => false,
            'is_valid' => true,
            'last_seen_at' => now(),
        ];
    }
}
