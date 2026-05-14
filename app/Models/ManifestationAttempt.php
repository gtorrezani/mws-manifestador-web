<?php

namespace App\Models;

use App\Enums\ManifestationRecordStatus;
use App\Enums\ManifestationStatus;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\ManifestationAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManifestationAttempt extends Model
{
    /** @use HasFactory<ManifestationAttemptFactory> */
    use HasFactory;

    use HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => ManifestationRecordStatus::class,
        'previous_manifestation_status' => ManifestationStatus::class,
        'new_manifestation_status' => ManifestationStatus::class,
        'started_at' => 'immutable_datetime',
        'finished_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<RecipientManifestation, $this> */
    public function manifestation(): BelongsTo
    {
        return $this->belongsTo(RecipientManifestation::class, 'recipient_manifestation_id');
    }
}
