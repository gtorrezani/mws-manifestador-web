<?php

namespace App\Policies;

use App\Enums\ManifestationEventType;
use App\Models\FiscalDocument;
use App\Services\Fiscal\ManifestationRequestContext;
use App\Services\Fiscal\ManifestationTransitionGuard;
use Illuminate\Validation\ValidationException;

class FiscalDocumentPolicy
{
    public function __construct(
        private readonly ManifestationTransitionGuard $transitionGuard,
    ) {}

    public function view(mixed $user, FiscalDocument $document): bool
    {
        return true;
    }

    public function downloadXml(mixed $user, FiscalDocument $document): bool
    {
        return true;
    }

    public function manifest(
        mixed $user,
        FiscalDocument $document,
        ManifestationEventType $eventType,
        ?string $justification = null,
        bool $confirmed = false,
    ): bool {
        try {
            $this->transitionGuard->transitionFor(
                $document,
                $eventType,
                $justification,
                new ManifestationRequestContext(explicitUserConfirmation: $confirmed),
            );
        } catch (ValidationException) {
            return false;
        }

        return true;
    }
}
