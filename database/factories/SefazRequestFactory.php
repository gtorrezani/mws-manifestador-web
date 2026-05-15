<?php

namespace Database\Factories;

use App\Enums\FiscalEnvironment;
use App\Models\Company;
use App\Models\SefazRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SefazRequest> */
class SefazRequestFactory extends Factory
{
    protected $model = SefazRequest::class;

    public function definition(): array
    {
        $company = Company::factory()->create();

        return [
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'service' => 'NFeDistribuicaoDFe',
            'environment' => FiscalEnvironment::Homologation,
            'endpoint' => 'https://hom.nfe.fazenda.gov.br/NFeDistribuicaoDFe/NFeDistribuicaoDFe.asmx',
            'request_xml_storage_disk' => 'local',
            'request_xml_storage_path' => 'soap/requests/'.fake()->uuid().'.xml',
            'request_hash' => fake()->sha256(),
            'correlation_id' => fake()->uuid(),
            'sent_at' => now(),
        ];
    }
}
