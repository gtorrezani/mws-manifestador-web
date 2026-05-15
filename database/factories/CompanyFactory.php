<?php

namespace Database\Factories;

use App\Enums\FiscalEnvironment;
use App\Models\Company;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Company> */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'legal_name' => fake()->company(),
            'trade_name' => fake()->optional()->companySuffix(),
            'cnpj' => fake()->unique()->numerify('##############'),
            'state_registration' => fake()->optional()->numerify('#########'),
            'uf' => fake()->randomElement(['SP', 'PR', 'SC', 'RS', 'MG']),
            'fiscal_environment' => FiscalEnvironment::Homologation,
            'is_active' => true,
        ];
    }
}
