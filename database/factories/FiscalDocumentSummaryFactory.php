<?php

namespace Database\Factories;

use App\Models\FiscalDocument;
use App\Models\FiscalDocumentSummary;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FiscalDocumentSummary> */
class FiscalDocumentSummaryFactory extends Factory
{
    protected $model = FiscalDocumentSummary::class;

    public function definition(): array
    {
        $document = FiscalDocument::factory()->create();

        return [
            'tenant_id' => $document->tenant_id,
            'company_id' => $document->company_id,
            'fiscal_document_id' => $document->id,
            'storage_disk' => 'local',
            'storage_path' => 'xml/summaries/'.$document->access_key.'.xml',
            'content_hash' => fake()->sha256(),
            'summary_payload' => ['access_key' => $document->access_key],
            'received_at' => now(),
        ];
    }
}
