<?php

namespace Tests\Feature\Company;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_company_and_is_linked_to_it(): void
    {
        $existingCompany = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($existingCompany->id);

        $this
            ->actingAs($user)
            ->post('/companies', [
                'tenant_id' => $existingCompany->tenant_id,
                'legal_name' => 'Nova Empresa Teste Ltda',
                'trade_name' => 'Nova Empresa',
                'cnpj' => '12.345.678/0001-95',
                'state_registration' => '123456789',
                'uf' => 'SP',
                'is_active' => true,
            ])
            ->assertRedirect();

        $company = Company::query()
            ->where('cnpj', '12345678000195')
            ->firstOrFail();

        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_authenticated_user_can_create_user_linked_to_their_company(): void
    {
        $company = Company::factory()->create();
        $owner = User::factory()->create();
        $owner->companies()->attach($company->id);

        $this
            ->actingAs($owner)
            ->post("/companies/{$company->id}/users", [
                'name' => 'Usuario Empresa',
                'cpf' => '529.982.247-25',
                'password' => 'senha-segura',
                'is_active' => true,
            ])
            ->assertRedirect();

        $user = User::query()->where('cpf', '52998224725')->firstOrFail();

        $this->assertTrue(Hash::check('senha-segura', $user->password));
        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_user_cannot_create_user_for_unlinked_company(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->post("/companies/{$company->id}/users", [
                'name' => 'Usuario Empresa',
                'cpf' => '52998224725',
                'password' => 'senha-segura',
                'is_active' => true,
            ])
            ->assertNotFound();
    }

    public function test_companies_page_includes_users_for_linked_companies(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['name' => 'A Owner']);
        $linkedUser = User::factory()->create(['name' => 'B Usuario Vinculado']);
        $user->companies()->attach($company->id);
        $linkedUser->companies()->attach($company->id);

        $this
            ->actingAs($user)
            ->withSession(['current_company_id' => $company->id])
            ->get('/companies')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Companies/Index')
                ->has('companyUsers', 2)
                ->where('companyUsers.1.name', 'B Usuario Vinculado'));
    }
}
