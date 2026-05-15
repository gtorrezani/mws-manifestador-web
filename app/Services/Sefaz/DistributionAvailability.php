<?php

namespace App\Services\Sefaz;

use DateTimeInterface;

readonly class DistributionAvailability
{
    public function __construct(
        public bool $allowed,
        public ?string $reason,
        public string $message,
        public ?DateTimeInterface $availableAt,
    ) {}

    /** @return array{allowed: bool, reason: string|null, message: string, available_at: string|null} */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'reason' => $this->reason,
            'message' => $this->message,
            'available_at' => $this->availableAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
