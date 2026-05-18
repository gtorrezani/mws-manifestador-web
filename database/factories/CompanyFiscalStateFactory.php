<?php

namespace Database\Factories;

use App\Enums\FiscalEnvironment;
use App\Models\Company;
use App\Models\CompanyFiscalState;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CompanyFiscalState> */
class CompanyFiscalStateFactory extends Factory
{
    protected $model = CompanyFiscalState::class;

    public function definition(): array
    {
        $company = Company::factory()->create();

        return [
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'environment' => FiscalEnvironment::Production->value,
            'uf' => $company->uf,
            'service' => 'nfe_distribution',
            'last_nsu' => '000000000000000',
            'max_nsu' => '000000000000000',
            'consecutive_distribution_failures' => 0,
        ];
    }
}
