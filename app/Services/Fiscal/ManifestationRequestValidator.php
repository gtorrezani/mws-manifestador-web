<?php

namespace App\Services\Fiscal;

use App\Domain\Fiscal\ManifestationTransition;
use App\Enums\ManifestationEventType;
use App\Models\FiscalDocument;

class ManifestationRequestValidator
{
    public function __construct(
        private readonly ManifestationTransitionGuard $transitionGuard,
    ) {}

    public function validate(
        FiscalDocument $document,
        ManifestationEventType $eventType,
        ?string $justification,
        ManifestationRequestContext $context,
    ): ManifestationTransition {
        return $this->transitionGuard->transitionFor($document, $eventType, $justification, $context);
    }
}
