<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\SefazConnectivityTest;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SefazConnectivityTest> */
class SefazConnectivityTestFactory extends Factory
{
    protected $model = SefazConnectivityTest::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $company = Company::factory()->create();

        return [
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'mode' => 'configuration_only',
            'environment' => $company->fiscal_environment->value,
            'uf' => $company->uf,
            'status' => 'pending',
            'requested_at' => now(),
        ];
    }
}
