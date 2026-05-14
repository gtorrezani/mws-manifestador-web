<?php

namespace Database\Factories;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Models\Company;
use App\Models\CompanyCertificate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CompanyCertificate> */
class CompanyCertificateFactory extends Factory
{
    protected $model = CompanyCertificate::class;

    public function definition(): array
    {
        $company = Company::factory()->create();

        return [
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'type' => CertificateType::A1,
            'status' => CertificateStatus::PendingValidation,
            'name' => 'Certificate '.fake()->word(),
            'subject_name' => fake()->company(),
            'issuer_name' => fake()->company(),
            'serial_number' => fake()->sha1(),
            'thumbprint' => fake()->sha1(),
            'valid_from' => now()->subMonth(),
            'valid_until' => now()->addYear(),
            'storage_disk' => 'local',
            'storage_path' => 'certificates/'.fake()->uuid().'.pfx.enc',
            'encrypted_password_payload' => fake()->sha256(),
        ];
    }
}
