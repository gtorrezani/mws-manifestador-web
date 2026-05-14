<?php

namespace App\Models;

use App\Enums\SefazRequestStatus;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\SefazResponseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SefazResponse extends Model
{
    /** @use HasFactory<SefazResponseFactory> */
    use HasFactory;

    use HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => SefazRequestStatus::class,
        'received_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<SefazRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(SefazRequest::class, 'sefaz_request_id');
    }
}
