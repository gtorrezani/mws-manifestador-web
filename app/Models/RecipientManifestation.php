<?php

namespace App\Models;

use App\Enums\ManifestationEventType;
use App\Enums\ManifestationRecordStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\RecipientManifestationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecipientManifestation extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RecipientManifestationFactory> */
    use HasFactory;

    use HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'event_type' => ManifestationEventType::class,
        'status' => ManifestationRecordStatus::class,
        'occurred_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<FiscalDocument, $this> */
    public function fiscalDocument(): BelongsTo
    {
        return $this->belongsTo(FiscalDocument::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<ManifestationAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(ManifestationAttempt::class);
    }
}
