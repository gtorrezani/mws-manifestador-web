<?php

namespace Database\Factories;

use App\Enums\ManifestationStatus;
use App\Enums\XmlDownloadStatus;
use App\Models\Company;
use App\Models\FiscalDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FiscalDocument> */
class FiscalDocumentFactory extends Factory
{
    protected $model = FiscalDocument::class;

    public function definition(): array
    {
        $company = Company::factory()->create();

        return [
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'access_key' => fake()->unique()->numerify(str_repeat('#', 44)),
            'nsu' => (string) fake()->unique()->numberBetween(1, 999999999),
            'schema_version' => '4.00',
            'issuer_cnpj' => fake()->numerify('##############'),
            'issuer_name' => fake()->company(),
            'recipient_cnpj' => $company->cnpj,
            'number' => (string) fake()->numberBetween(1, 999999),
            'series' => (string) fake()->numberBetween(1, 999),
            'issued_at' => now()->subDays(fake()->numberBetween(1, 30)),
            'total_amount' => fake()->randomFloat(2, 10, 50000),
            'manifestation_status' => ManifestationStatus::NoManifestation,
            'xml_download_status' => XmlDownloadStatus::NotRequested,
        ];
    }
}
