<?php

namespace Tests\Unit\Fiscal;

use App\Enums\ManifestationEventType;
use App\Enums\ManifestationStatus;
use App\Models\FiscalDocument;
use App\Services\Fiscal\ManifestationRequestContext;
use App\Services\Fiscal\ManifestationTransitionGuard;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ManifestationTransitionGuardTest extends TestCase
{
    private ?ManifestationTransitionGuard $guard = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guard = new ManifestationTransitionGuard;
    }

    public function test_acknowledgement_is_not_conclusive_and_points_to_pending_final_manifestation(): void
    {
        $document = $this->documentWithStatus(ManifestationStatus::NoManifestation);

        $transition = $this->guard()->transitionFor(
            $document,
            ManifestationEventType::OperationAcknowledgement,
            null,
            new ManifestationRequestContext,
        );

        $this->assertSame(ManifestationStatus::AcknowledgementRequested, $transition->requestedStatus);
        $this->assertSame(ManifestationStatus::PendingFinalManifestation, $transition->acceptedStatus);
    }

    public function test_conclusive_event_requires_explicit_confirmation(): void
    {
        $this->expectException(ValidationException::class);

        $document = $this->documentWithStatus(ManifestationStatus::NoManifestation);

        $this->guard()->transitionFor(
            $document,
            ManifestationEventType::OperationConfirmation,
            null,
            new ManifestationRequestContext(explicitUserConfirmation: false),
        );
    }

    public function test_operation_not_performed_requires_justification(): void
    {
        $this->expectException(ValidationException::class);

        $document = $this->documentWithStatus(ManifestationStatus::NoManifestation);

        $this->guard()->transitionFor(
            $document,
            ManifestationEventType::OperationNotPerformed,
            null,
            new ManifestationRequestContext(explicitUserConfirmation: true),
        );
    }

    public function test_automatic_conclusive_event_requires_administrative_approval(): void
    {
        $this->expectException(ValidationException::class);

        $document = $this->documentWithStatus(ManifestationStatus::PendingFinalManifestation);

        $this->guard()->transitionFor(
            $document,
            ManifestationEventType::OperationConfirmation,
            null,
            new ManifestationRequestContext(
                isAutomatic: true,
                automaticRuleConfigured: true,
                administrativelyConfirmed: false,
            ),
        );
    }

    public function test_same_conclusive_event_cannot_be_repeated_without_override(): void
    {
        $this->expectException(ValidationException::class);

        $document = $this->documentWithStatus(ManifestationStatus::Confirmed);

        $this->guard()->transitionFor(
            $document,
            ManifestationEventType::OperationConfirmation,
            null,
            new ManifestationRequestContext(explicitUserConfirmation: true),
        );
    }

    private function documentWithStatus(ManifestationStatus $status): FiscalDocument
    {
        $document = new FiscalDocument;
        $document->setRawAttributes([
            'manifestation_status' => $status->value,
        ], true);

        return $document;
    }

    private function guard(): ManifestationTransitionGuard
    {
        self::assertInstanceOf(ManifestationTransitionGuard::class, $this->guard);

        return $this->guard;
    }
}
