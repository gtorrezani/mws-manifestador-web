<?php

namespace Tests\Feature\Company;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyDocumentNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_cnpj_is_stored_without_mask(): void
    {
        $company = Company::factory()->create(['cnpj' => '12.345.678/0001-95']);

        $this->assertSame('12345678000195', $company->cnpj);
        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'cnpj' => '12345678000195',
        ]);
    }
}
