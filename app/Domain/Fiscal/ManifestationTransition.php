<?php

namespace App\Domain\Fiscal;

use App\Enums\CommandType;
use App\Enums\ManifestationEventType;
use App\Enums\ManifestationStatus;

readonly class ManifestationTransition
{
    public function __construct(
        public ManifestationEventType $eventType,
        public CommandType $commandType,
        public ManifestationStatus $previousStatus,
        public ManifestationStatus $requestedStatus,
        public ManifestationStatus $acceptedStatus,
    ) {}
}
