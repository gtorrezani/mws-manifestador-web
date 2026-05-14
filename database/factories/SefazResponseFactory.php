<?php

namespace Database\Factories;

use App\Enums\SefazRequestStatus;
use App\Models\SefazRequest;
use App\Models\SefazResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SefazResponse> */
class SefazResponseFactory extends Factory
{
    protected $model = SefazResponse::class;

    public function definition(): array
    {
        $request = SefazRequest::factory()->create();

        return [
            'tenant_id' => $request->tenant_id,
            'company_id' => $request->company_id,
            'sefaz_request_id' => $request->id,
            'status' => SefazRequestStatus::Succeeded,
            'http_status_code' => 200,
            'sefaz_status_code' => '138',
            'sefaz_message' => 'Documento localizado',
            'response_xml_storage_disk' => 'local',
            'response_xml_storage_path' => 'soap/responses/'.fake()->uuid().'.xml',
            'response_hash' => fake()->sha256(),
            'received_at' => now(),
            'duration_ms' => fake()->numberBetween(100, 3000),
        ];
    }
}
