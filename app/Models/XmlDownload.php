<?php

namespace App\Models;

use App\Enums\XmlDownloadStatus;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\XmlDownloadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XmlDownload extends Model
{
    /** @use HasFactory<XmlDownloadFactory> */
    use HasFactory;

    use HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => XmlDownloadStatus::class,
        'requested_at' => 'immutable_datetime',
        'completed_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<FiscalDocument, $this> */
    public function fiscalDocument(): BelongsTo
    {
        return $this->belongsTo(FiscalDocument::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
