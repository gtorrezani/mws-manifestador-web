<?php

namespace Tests\Feature\Company;

use App\Enums\FiscalEnvironment;
use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_is_created_with_production_environment_only(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($user)
            ->post(route('companies.store'), [
                'tenant_id' => $tenant->id,
                'legal_name' => 'Empresa Producao LTDA',
                'trade_name' => 'Producao',
                'cnpj' => '12345678000199',
                'state_registration' => null,
                'uf' => 'SP',
                'fiscal_environment' => 'legacy_non_production',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('companies', [
            'tenant_id' => $tenant->id,
            'cnpj' => '12345678000199',
            'fiscal_environment' => FiscalEnvironment::Production->value,
        ]);
    }

    public function test_company_update_keeps_production_environment_only(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $this->actingAs($user)
            ->put(route('companies.update', $company), [
                'tenant_id' => $company->tenant_id,
                'legal_name' => 'Empresa Atualizada LTDA',
                'trade_name' => 'Atualizada',
                'cnpj' => $company->cnpj,
                'state_registration' => null,
                'uf' => 'PR',
                'fiscal_environment' => 'legacy_non_production',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'legal_name' => 'Empresa Atualizada LTDA',
            'uf' => 'PR',
            'fiscal_environment' => FiscalEnvironment::Production->value,
        ]);
    }
}
