<?php

namespace Database\Factories;

use App\Models\FiscalDocument;
use App\Models\FiscalDocumentXml;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FiscalDocumentXml> */
class FiscalDocumentXmlFactory extends Factory
{
    protected $model = FiscalDocumentXml::class;

    public function definition(): array
    {
        $document = FiscalDocument::factory()->create();

        return [
            'tenant_id' => $document->tenant_id,
            'company_id' => $document->company_id,
            'fiscal_document_id' => $document->id,
            'storage_disk' => 'local',
            'storage_path' => 'xml/full/'.$document->access_key.'.xml',
            'content_hash' => fake()->sha256(),
            'size_bytes' => fake()->numberBetween(2000, 500000),
            'schema_version' => '4.00',
            'source' => 'distribution',
            'downloaded_at' => now(),
        ];
    }
}
