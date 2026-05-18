<?php

namespace App\Services\Sefaz\Dto;

readonly class SefazEventResponse
{
    public function __construct(
        public ?string $batchStatusCode,
        public ?string $batchReason,
        public ?string $eventStatusCode,
        public ?string $eventReason,
        public ?string $eventProtocolNumber,
    ) {}
}
