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
        $companyCnpj = $agent->company?->cnpj ?? '12345678000195';

        return [
            'tenant_id' => $agent->tenant_id,
            'agent_id' => $agent->id,
            'company_id' => $agent->company_id,
            'type' => CertificateType::A3,
            'status' => CertificateStatus::Active,
            'store_scope' => 'current_user',
            'store_location' => 'CurrentUser',
            'subject' => 'CN=Empresa Teste:'.$companyCnpj,
            'subject_name' => 'CN=Empresa Teste:'.$companyCnpj,
            'issuer' => 'CN=AC Teste',
            'issuer_name' => 'CN=AC Teste',
            'common_name' => 'Empresa Teste',
            'serial_number' => fake()->sha1(),
            'thumbprint' => strtoupper(fake()->sha1()),
            'cnpj' => $companyCnpj,
            'document' => $companyCnpj,
            'document_type' => 'cnpj',
            'not_before' => now()->subMonth(),
            'not_after' => now()->addYear(),
            'valid_from' => now()->subMonth(),
            'valid_until' => now()->addYear(),
            'has_private_key' => true,
            'store_name' => 'My',
            'is_expired' => false,
            'is_certificate_authority' => false,
            'is_fiscal_candidate' => true,
            'is_icp_brasil' => true,
            'is_usable_for_client_auth' => true,
            'classification' => 'fiscal_candidate',
            'rejection_reasons' => [],
            'warnings' => ['Tipo A1/A3 nao confirmado automaticamente.'],
            'is_valid' => true,
            'last_seen_at' => now(),
        ];
    }
}
