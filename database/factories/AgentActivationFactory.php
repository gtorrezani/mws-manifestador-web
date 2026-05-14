<?php

namespace Database\Factories;

use App\Enums\ActivationStatus;
use App\Models\AgentActivation;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<AgentActivation> */
class AgentActivationFactory extends Factory
{
    protected $model = AgentActivation::class;

    public function definition(): array
    {
        $company = Company::factory()->create();

        return [
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'code_hash' => Hash::make(fake()->unique()->numerify('######')),
            'status' => ActivationStatus::Pending,
            'expires_at' => now()->addMinutes(30),
            'metadata' => [],
        ];
    }
}
