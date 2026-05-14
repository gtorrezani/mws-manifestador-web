<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\FiscalDocumentXmlFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalDocumentXml extends Model
{
    /** @use HasFactory<FiscalDocumentXmlFactory> */
    use HasFactory;

    use HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'downloaded_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<FiscalDocument, $this> */
    public function fiscalDocument(): BelongsTo
    {
        return $this->belongsTo(FiscalDocument::class);
    }
}
