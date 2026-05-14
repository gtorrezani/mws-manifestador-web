<?php

namespace Database\Factories;

use App\Enums\ManifestationEventType;
use App\Enums\ManifestationRecordStatus;
use App\Models\FiscalDocument;
use App\Models\RecipientManifestation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RecipientManifestation> */
class RecipientManifestationFactory extends Factory
{
    protected $model = RecipientManifestation::class;

    public function definition(): array
    {
        $document = FiscalDocument::factory()->create();

        return [
            'tenant_id' => $document->tenant_id,
            'company_id' => $document->company_id,
            'fiscal_document_id' => $document->id,
            'event_type' => ManifestationEventType::OperationAcknowledgement,
            'status' => ManifestationRecordStatus::Pending,
            'occurred_at' => now(),
        ];
    }
}
