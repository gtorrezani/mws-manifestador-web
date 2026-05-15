<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Support\CompanyContext\CurrentCompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_routes_redirect_guests_to_login(): void
    {
        $this->get('/companies')->assertRedirect('/login');
        $this->get('/')->assertRedirect('/login');
    }

    public function test_selects_first_active_company_when_session_is_empty(): void
    {
        $user = $this->actingUser();
        $first = Company::factory()->create(['legal_name' => 'Alpha Company']);
        $second = Company::factory()->create(['legal_name' => 'Beta Company']);
        $user->companies()->attach([$first->id, $second->id]);

        $this->get('/')
            ->assertOk()
            ->assertSessionHas(CurrentCompanyContext::SESSION_KEY, $first->id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentCompany.id', $first->id)
                ->where('companyContext.has_company', true));
    }

    public function test_can_switch_current_company(): void
    {
        $user = $this->actingUser();
        $first = Company::factory()->create(['legal_name' => 'Alpha Company']);
        $second = Company::factory()->create(['legal_name' => 'Beta Company']);
        $user->companies()->attach([$first->id, $second->id]);

        $this
            ->withSession([CurrentCompanyContext::SESSION_KEY => $first->id])
            ->post('/current-company', ['company_id' => $second->id])
            ->assertRedirect()
            ->assertSessionHas(CurrentCompanyContext::SESSION_KEY, $second->id);
    }

    public function test_cannot_select_inactive_company(): void
    {
        $user = $this->actingUser();
        $active = Company::factory()->create();
        $inactive = Company::factory()->create(['is_active' => false]);
        $user->companies()->attach([$active->id, $inactive->id]);

        $this
            ->withSession([CurrentCompanyContext::SESSION_KEY => $active->id])
            ->post('/current-company', ['company_id' => $inactive->id])
            ->assertSessionHasErrors('company_id')
            ->assertSessionHas(CurrentCompanyContext::SESSION_KEY, $active->id);
    }

    public function test_invalid_session_selection_is_cleared_and_replaced(): void
    {
        $user = $this->actingUser();
        $inactive = Company::factory()->create(['is_active' => false]);
        $active = Company::factory()->create(['legal_name' => 'Active Company']);
        $user->companies()->attach([$inactive->id, $active->id]);

        $this
            ->withSession([CurrentCompanyContext::SESSION_KEY => $inactive->id])
            ->get('/')
            ->assertOk()
            ->assertSessionHas(CurrentCompanyContext::SESSION_KEY, $active->id)
            ->assertInertia(fn (Assert $page) => $page->where('currentCompany.id', $active->id));
    }

    public function test_inertia_shares_current_and_available_companies(): void
    {
        $user = $this->actingUser();
        $first = Company::factory()->create(['legal_name' => 'Alpha Company']);
        $second = Company::factory()->create(['legal_name' => 'Beta Company']);
        $inactive = Company::factory()->create(['legal_name' => 'Inactive Company', 'is_active' => false]);
        $user->companies()->attach([$first->id, $second->id, $inactive->id]);

        $this
            ->withSession([CurrentCompanyContext::SESSION_KEY => $first->id])
            ->get('/companies')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentCompany.id', $first->id)
                ->has('availableCompanies', 2)
                ->where('companyContext.can_switch_company', true));
    }

    public function test_user_only_sees_companies_linked_to_them(): void
    {
        $user = $this->actingUser();
        $linked = Company::factory()->create(['legal_name' => 'Linked Company']);
        Company::factory()->create(['legal_name' => 'Unlinked Company']);
        $user->companies()->attach($linked->id);

        $this
            ->get('/companies')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('companies.data', 1)
                ->where('companies.data.0.id', $linked->id)
                ->has('availableCompanies', 1)
                ->where('availableCompanies.0.id', $linked->id));
    }

    public function test_cannot_switch_to_company_not_linked_to_user(): void
    {
        $user = $this->actingUser();
        $linked = Company::factory()->create(['legal_name' => 'Linked Company']);
        $unlinked = Company::factory()->create(['legal_name' => 'Unlinked Company']);
        $user->companies()->attach($linked->id);

        $this
            ->withSession([CurrentCompanyContext::SESSION_KEY => $linked->id])
            ->post('/current-company', ['company_id' => $unlinked->id])
            ->assertSessionHasErrors('company_id')
            ->assertSessionHas(CurrentCompanyContext::SESSION_KEY, $linked->id);
    }

    private function actingUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }
}
