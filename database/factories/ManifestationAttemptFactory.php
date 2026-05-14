<?php

namespace Database\Factories;

use App\Enums\ManifestationRecordStatus;
use App\Enums\ManifestationStatus;
use App\Models\ManifestationAttempt;
use App\Models\RecipientManifestation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ManifestationAttempt> */
class ManifestationAttemptFactory extends Factory
{
    protected $model = ManifestationAttempt::class;

    public function definition(): array
    {
        $manifestation = RecipientManifestation::factory()->create();

        return [
            'tenant_id' => $manifestation->tenant_id,
            'recipient_manifestation_id' => $manifestation->id,
            'attempt_number' => 1,
            'status' => ManifestationRecordStatus::Processing,
            'previous_manifestation_status' => ManifestationStatus::NoManifestation,
            'new_manifestation_status' => ManifestationStatus::AcknowledgementRequested,
            'started_at' => now(),
        ];
    }
}
