<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\FiscalDocumentSummaryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalDocumentSummary extends Model
{
    /** @use HasFactory<FiscalDocumentSummaryFactory> */
    use HasFactory;

    use HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'summary_payload' => 'array',
        'received_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<FiscalDocument, $this> */
    public function fiscalDocument(): BelongsTo
    {
        return $this->belongsTo(FiscalDocument::class);
    }
}
