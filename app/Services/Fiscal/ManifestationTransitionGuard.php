<?php

namespace App\Services\Fiscal;

use App\Domain\Fiscal\ManifestationTransition;
use App\Enums\CommandType;
use App\Enums\ManifestationEventType;
use App\Enums\ManifestationStatus;
use App\Models\FiscalDocument;
use Illuminate\Validation\ValidationException;

class ManifestationTransitionGuard
{
    public function transitionFor(
        FiscalDocument $document,
        ManifestationEventType $eventType,
        ?string $justification,
        ManifestationRequestContext $context,
    ): ManifestationTransition {
        $previousStatus = $document->manifestation_status;

        if (! $previousStatus instanceof ManifestationStatus) {
            $previousStatus = ManifestationStatus::from((string) $previousStatus);
        }

        $this->validateRequestState($previousStatus, $eventType, $justification, $context);

        return new ManifestationTransition(
            eventType: $eventType,
            commandType: $this->commandTypeFor($eventType),
            previousStatus: $previousStatus,
            requestedStatus: $this->requestedStatusFor($eventType),
            acceptedStatus: $this->acceptedStatusFor($eventType),
        );
    }

    public function statusAfterRejection(ManifestationStatus $previousStatus): ManifestationStatus
    {
        return $previousStatus->isConclusive()
            ? $previousStatus
            : ManifestationStatus::Rejected;
    }

    public function statusAfterTechnicalFailure(ManifestationStatus $previousStatus): ManifestationStatus
    {
        return $previousStatus->isConclusive()
            ? $previousStatus
            : ManifestationStatus::Failed;
    }

    private function validateRequestState(
        ManifestationStatus $previousStatus,
        ManifestationEventType $eventType,
        ?string $justification,
        ManifestationRequestContext $context,
    ): void {
        $errors = [];

        if ($previousStatus->isRequested()) {
            $errors['event_type'][] = 'Já existe uma manifestação em processamento para este documento.';
        }

        if ($eventType === ManifestationEventType::OperationNotPerformed && trim((string) $justification) === '') {
            $errors['justification'][] = 'Operação Não Realizada exige justificativa.';
        }

        if ($this->isConclusive($eventType) && ! $context->explicitUserConfirmation && ! $this->hasAdministrativeAutomationApproval($context)) {
            $errors['confirmed'][] = 'Eventos conclusivos exigem confirmação explícita.';
        }

        if ($this->isConclusive($eventType) && $context->isAutomatic && ! $this->hasAdministrativeAutomationApproval($context)) {
            $errors['event_type'][] = 'Evento conclusivo automático exige regra configurada e confirmação administrativa.';
        }

        if ($previousStatus->isConclusive() && ! $context->allowRepeatConclusive) {
            $errors['event_type'][] = 'Não é permitido repetir evento conclusivo sem autorização explícita.';
        }

        if ($this->sameConclusiveEventAlreadyAccepted($previousStatus, $eventType) && ! $context->allowRepeatConclusive) {
            $errors['event_type'][] = 'Este evento conclusivo já foi aceito para o documento.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function isConclusive(ManifestationEventType $eventType): bool
    {
        return $eventType !== ManifestationEventType::OperationAcknowledgement;
    }

    private function hasAdministrativeAutomationApproval(ManifestationRequestContext $context): bool
    {
        return $context->automaticRuleConfigured && $context->administrativelyConfirmed;
    }

    private function sameConclusiveEventAlreadyAccepted(ManifestationStatus $status, ManifestationEventType $eventType): bool
    {
        return match ($eventType) {
            ManifestationEventType::OperationConfirmation => $status === ManifestationStatus::Confirmed,
            ManifestationEventType::OperationUnknown => $status === ManifestationStatus::Unknown,
            ManifestationEventType::OperationNotPerformed => $status === ManifestationStatus::NotPerformed,
            ManifestationEventType::OperationAcknowledgement => false,
        };
    }

    public function acceptedStatusFor(ManifestationEventType $eventType): ManifestationStatus
    {
        return match ($eventType) {
            ManifestationEventType::OperationAcknowledgement => ManifestationStatus::PendingFinalManifestation,
            ManifestationEventType::OperationConfirmation => ManifestationStatus::Confirmed,
            ManifestationEventType::OperationUnknown => ManifestationStatus::Unknown,
            ManifestationEventType::OperationNotPerformed => ManifestationStatus::NotPerformed,
        };
    }

    private function requestedStatusFor(ManifestationEventType $eventType): ManifestationStatus
    {
        return match ($eventType) {
            ManifestationEventType::OperationAcknowledgement => ManifestationStatus::AcknowledgementRequested,
            ManifestationEventType::OperationConfirmation => ManifestationStatus::ConfirmationRequested,
            ManifestationEventType::OperationUnknown => ManifestationStatus::UnknownRequested,
            ManifestationEventType::OperationNotPerformed => ManifestationStatus::NotPerformedRequested,
        };
    }

    private function commandTypeFor(ManifestationEventType $eventType): CommandType
    {
        return match ($eventType) {
            ManifestationEventType::OperationAcknowledgement => CommandType::ManifestAcknowledgement,
            ManifestationEventType::OperationConfirmation => CommandType::ManifestConfirmation,
            ManifestationEventType::OperationUnknown => CommandType::ManifestUnknown,
            ManifestationEventType::OperationNotPerformed => CommandType::ManifestNotPerformed,
        };
    }
}
