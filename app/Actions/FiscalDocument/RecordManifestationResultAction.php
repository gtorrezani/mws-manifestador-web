<?php

namespace App\Actions\FiscalDocument;

use App\DTOs\Agent\CommandFailureData;
use App\DTOs\Agent\CommandResultData;
use App\Enums\CommandType;
use App\Enums\ManifestationEventType;
use App\Enums\ManifestationRecordStatus;
use App\Models\AgentCommand;
use App\Models\FiscalDocument;
use App\Models\ManifestationAttempt;
use App\Models\RecipientManifestation;
use App\Services\Fiscal\ManifestationTransitionGuard;

class RecordManifestationResultAction
{
    private const ACCEPTED_SEFAZ_STATUS_CODES = ['135', '136', '155'];

    public function __construct(
        private readonly ManifestationTransitionGuard $transitionGuard,
    ) {}

    public function recordCompleted(AgentCommand $command, CommandResultData $data): void
    {
        if (! $this->isManifestationCommand($command->type)) {
            return;
        }

        $manifestation = $this->findManifestation($command);
        if (! $manifestation) {
            return;
        }

        $attempt = $this->attemptFor($manifestation, $command);
        $this->recordCompletedManifestation($manifestation, $attempt, $data);
    }

    public function recordDirectCompleted(RecipientManifestation $manifestation, ManifestationAttempt $attempt, CommandResultData $data): void
    {
        $this->recordCompletedManifestation($manifestation, $attempt, $data);
    }

    public function recordFailed(AgentCommand $command, CommandFailureData $data, bool $isFinalFailure): void
    {
        if (! $this->isManifestationCommand($command->type)) {
            return;
        }

        $manifestation = $this->findManifestation($command);
        if (! $manifestation) {
            return;
        }

        $attempt = $this->attemptFor($manifestation, $command);
        $this->recordFailedManifestation($manifestation, $attempt, $data, $isFinalFailure);
    }

    public function recordDirectFailed(
        RecipientManifestation $manifestation,
        ManifestationAttempt $attempt,
        CommandFailureData $data,
        bool $isFinalFailure,
    ): void {
        $this->recordFailedManifestation($manifestation, $attempt, $data, $isFinalFailure);
    }

    private function findManifestation(AgentCommand $command): ?RecipientManifestation
    {
        $uuid = $command->payload['recipient_manifestation_uuid'] ?? null;

        if (is_string($uuid) && $uuid !== '') {
            return RecipientManifestation::query()->where('uuid', $uuid)->first();
        }

        return ManifestationAttempt::query()
            ->with('manifestation')
            ->where('agent_command_id', $command->id)
            ->latest('id')
            ->first()
            ?->manifestation;
    }

    private function attemptFor(RecipientManifestation $manifestation, AgentCommand $command): ?ManifestationAttempt
    {
        return ManifestationAttempt::query()
            ->where('recipient_manifestation_id', $manifestation->id)
            ->where('agent_command_id', $command->id)
            ->latest('attempt_number')
            ->first();
    }

    private function isManifestationCommand(CommandType $type): bool
    {
        return $this->eventTypeFor($type) !== null;
    }

    private function eventTypeFor(CommandType $type): ?ManifestationEventType
    {
        return match ($type) {
            CommandType::ManifestAcknowledgement => ManifestationEventType::OperationAcknowledgement,
            CommandType::ManifestConfirmation => ManifestationEventType::OperationConfirmation,
            CommandType::ManifestUnknown => ManifestationEventType::OperationUnknown,
            CommandType::ManifestNotPerformed => ManifestationEventType::OperationNotPerformed,
            default => null,
        };
    }

    private function recordCompletedManifestation(
        RecipientManifestation $manifestation,
        ?ManifestationAttempt $attempt,
        CommandResultData $data,
    ): void {
        $document = $manifestation->fiscalDocument()->lockForUpdate()->first();
        if (! $document instanceof FiscalDocument) {
            return;
        }

        $previousStatus = $attempt?->previous_manifestation_status ?? $document->manifestation_status;
        $eventType = $manifestation->event_type;
        $accepted = in_array((string) $data->sefazStatusCode, self::ACCEPTED_SEFAZ_STATUS_CODES, true);

        $newDocumentStatus = $accepted
            ? $this->transitionGuard->acceptedStatusFor($eventType)
            : $this->transitionGuard->statusAfterRejection($previousStatus);

        $manifestation->forceFill([
            'status' => $accepted ? ManifestationRecordStatus::Accepted : ManifestationRecordStatus::Rejected,
            'protocol_number' => $data->protocolNumber,
            'sefaz_status_code' => $data->sefazStatusCode,
            'sefaz_message' => $data->sefazMessage,
            'occurred_at' => now(),
        ])->save();

        $attempt?->forceFill([
            'status' => $accepted ? ManifestationRecordStatus::Accepted : ManifestationRecordStatus::Rejected,
            'new_manifestation_status' => $newDocumentStatus,
            'protocol_number' => $data->protocolNumber,
            'sefaz_status_code' => $data->sefazStatusCode,
            'sefaz_message' => $data->sefazMessage,
            'finished_at' => now(),
        ])->save();

        $document->forceFill([
            'manifestation_status' => $newDocumentStatus,
            'last_sefaz_status_code' => $data->sefazStatusCode,
            'last_sefaz_message' => $data->sefazMessage,
        ])->save();
    }

    private function recordFailedManifestation(
        RecipientManifestation $manifestation,
        ?ManifestationAttempt $attempt,
        CommandFailureData $data,
        bool $isFinalFailure,
    ): void {
        $document = $manifestation->fiscalDocument()->lockForUpdate()->first();
        if (! $document instanceof FiscalDocument) {
            return;
        }

        $previousStatus = $attempt?->previous_manifestation_status ?? $document->manifestation_status;
        $newDocumentStatus = $this->transitionGuard->statusAfterTechnicalFailure($previousStatus);

        $attempt?->forceFill([
            'status' => ManifestationRecordStatus::Failed,
            'sefaz_status_code' => $data->sefazStatusCode,
            'sefaz_message' => $data->sefazMessage ?: $data->errorMessage,
            'finished_at' => now(),
        ])->save();

        if (! $isFinalFailure) {
            return;
        }

        $manifestation->forceFill([
            'status' => ManifestationRecordStatus::Failed,
            'sefaz_status_code' => $data->sefazStatusCode,
            'sefaz_message' => $data->sefazMessage ?: $data->errorMessage,
        ])->save();

        $attempt?->forceFill([
            'new_manifestation_status' => $newDocumentStatus,
        ])->save();

        $document->forceFill([
            'manifestation_status' => $newDocumentStatus,
            'last_sefaz_status_code' => $data->sefazStatusCode,
            'last_sefaz_message' => $data->sefazMessage ?: $data->errorMessage,
        ])->save();
    }
}
