<?php

namespace App\Models;

use App\Enums\FiscalEnvironment;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\SefazRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SefazRequest extends Model
{
    /** @use HasFactory<SefazRequestFactory> */
    use HasFactory;

    use HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'environment' => FiscalEnvironment::class,
        'sent_at' => 'immutable_datetime',
    ];

    /** @return HasMany<SefazResponse, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(SefazResponse::class);
    }
}
