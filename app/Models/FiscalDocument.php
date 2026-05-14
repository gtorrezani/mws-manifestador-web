<?php

namespace App\Models;

use App\Enums\ManifestationStatus;
use App\Enums\XmlDownloadStatus;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\FiscalDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FiscalDocument extends Model
{
    /** @use HasFactory<FiscalDocumentFactory> */
    use HasFactory;

    use HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'issued_at' => 'immutable_datetime',
        'total_amount' => 'decimal:2',
        'manifestation_status' => ManifestationStatus::class,
        'xml_download_status' => XmlDownloadStatus::class,
    ];

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasOne<FiscalDocumentSummary, $this> */
    public function summary(): HasOne
    {
        return $this->hasOne(FiscalDocumentSummary::class);
    }

    /** @return HasMany<FiscalDocumentXml, $this> */
    public function xmls(): HasMany
    {
        return $this->hasMany(FiscalDocumentXml::class);
    }

    /** @return HasMany<RecipientManifestation, $this> */
    public function manifestations(): HasMany
    {
        return $this->hasMany(RecipientManifestation::class);
    }
}
