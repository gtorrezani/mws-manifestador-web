<?php

namespace Database\Factories;

use App\Enums\XmlDownloadStatus;
use App\Models\FiscalDocument;
use App\Models\XmlDownload;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<XmlDownload> */
class XmlDownloadFactory extends Factory
{
    protected $model = XmlDownload::class;

    public function definition(): array
    {
        $document = FiscalDocument::factory()->create();

        return [
            'tenant_id' => $document->tenant_id,
            'company_id' => $document->company_id,
            'fiscal_document_id' => $document->id,
            'status' => XmlDownloadStatus::Pending,
            'nsu' => $document->nsu,
            'requested_at' => now(),
        ];
    }
}
