<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Support\CompanyContext\CurrentCompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_selects_first_active_company_when_session_is_empty(): void
    {
        $first = Company::factory()->create(['legal_name' => 'Alpha Company']);
        Company::factory()->create(['legal_name' => 'Beta Company']);

        $this->get('/')
            ->assertOk()
            ->assertSessionHas(CurrentCompanyContext::SESSION_KEY, $first->id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentCompany.id', $first->id)
                ->where('companyContext.has_company', true));
    }

    public function test_can_switch_current_company(): void
    {
        $first = Company::factory()->create(['legal_name' => 'Alpha Company']);
        $second = Company::factory()->create(['legal_name' => 'Beta Company']);

        $this
            ->withSession([CurrentCompanyContext::SESSION_KEY => $first->id])
            ->post('/current-company', ['company_id' => $second->id])
            ->assertRedirect()
            ->assertSessionHas(CurrentCompanyContext::SESSION_KEY, $second->id);
    }

    public function test_cannot_select_inactive_company(): void
    {
        $active = Company::factory()->create();
        $inactive = Company::factory()->create(['is_active' => false]);

        $this
            ->withSession([CurrentCompanyContext::SESSION_KEY => $active->id])
            ->post('/current-company', ['company_id' => $inactive->id])
            ->assertSessionHasErrors('company_id')
            ->assertSessionHas(CurrentCompanyContext::SESSION_KEY, $active->id);
    }

    public function test_invalid_session_selection_is_cleared_and_replaced(): void
    {
        $inactive = Company::factory()->create(['is_active' => false]);
        $active = Company::factory()->create(['legal_name' => 'Active Company']);

        $this
            ->withSession([CurrentCompanyContext::SESSION_KEY => $inactive->id])
            ->get('/')
            ->assertOk()
            ->assertSessionHas(CurrentCompanyContext::SESSION_KEY, $active->id)
            ->assertInertia(fn (Assert $page) => $page->where('currentCompany.id', $active->id));
    }

    public function test_inertia_shares_current_and_available_companies(): void
    {
        $first = Company::factory()->create(['legal_name' => 'Alpha Company']);
        Company::factory()->create(['legal_name' => 'Beta Company']);
        Company::factory()->create(['legal_name' => 'Inactive Company', 'is_active' => false]);

        $this
            ->withSession([CurrentCompanyContext::SESSION_KEY => $first->id])
            ->get('/companies')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentCompany.id', $first->id)
                ->has('availableCompanies', 2)
                ->where('companyContext.can_switch_company', true));
    }
}
