<?php

namespace Tests\Feature\Fiscal;

use App\Actions\FiscalDocument\RecordManifestationResultAction;
use App\Actions\FiscalDocument\RequestManifestationAction;
use App\DTOs\Agent\CommandFailureData;
use App\DTOs\Agent\CommandResultData;
use App\Enums\CommandStatus;
use App\Enums\ManifestationEventType;
use App\Enums\ManifestationRecordStatus;
use App\Enums\ManifestationStatus;
use App\Models\AgentCommand;
use App\Models\FiscalDocument;
use App\Services\Fiscal\ManifestationRequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiscalManifestationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_acknowledgement_request_creates_command_attempt_and_requested_state(): void
    {
        $document = FiscalDocument::factory()->create([
            'manifestation_status' => ManifestationStatus::NoManifestation,
        ]);

        app(RequestManifestationAction::class)->execute(
            document: $document,
            eventType: ManifestationEventType::OperationAcknowledgement,
            justification: null,
            context: new ManifestationRequestContext,
            createdBy: null,
        );

        $this->assertDatabaseHas('fiscal_documents', [
            'id' => $document->id,
            'manifestation_status' => ManifestationStatus::AcknowledgementRequested->value,
        ]);

        $this->assertDatabaseHas('recipient_manifestations', [
            'fiscal_document_id' => $document->id,
            'event_type' => ManifestationEventType::OperationAcknowledgement->value,
            'status' => ManifestationRecordStatus::Pending->value,
        ]);

        $this->assertDatabaseHas('manifestation_attempts', [
            'previous_manifestation_status' => ManifestationStatus::NoManifestation->value,
            'new_manifestation_status' => ManifestationStatus::AcknowledgementRequested->value,
        ]);
    }

    public function test_accepted_acknowledgement_moves_document_to_pending_final_manifestation(): void
    {
        $document = FiscalDocument::factory()->create([
            'manifestation_status' => ManifestationStatus::NoManifestation,
        ]);

        app(RequestManifestationAction::class)->execute(
            document: $document,
            eventType: ManifestationEventType::OperationAcknowledgement,
            justification: null,
            context: new ManifestationRequestContext,
            createdBy: null,
        );

        $command = AgentCommand::query()->firstOrFail();
        $command->forceFill(['status' => CommandStatus::Processing])->save();

        app(RecordManifestationResultAction::class)->recordCompleted($command, new CommandResultData(
            result: [],
            sefaz: [],
            requestXml: null,
            responseXml: null,
            protocolNumber: '135240000000001',
            sefazStatusCode: '135',
            sefazMessage: 'Evento registrado e vinculado a NF-e',
            durationMs: 120,
        ));

        $this->assertDatabaseHas('fiscal_documents', [
            'id' => $document->id,
            'manifestation_status' => ManifestationStatus::PendingFinalManifestation->value,
        ]);
    }

    public function test_sefaz_rejection_does_not_mark_manifestation_as_success(): void
    {
        $document = FiscalDocument::factory()->create([
            'manifestation_status' => ManifestationStatus::NoManifestation,
        ]);

        app(RequestManifestationAction::class)->execute(
            document: $document,
            eventType: ManifestationEventType::OperationConfirmation,
            justification: null,
            context: new ManifestationRequestContext(explicitUserConfirmation: true),
            createdBy: null,
        );

        $command = AgentCommand::query()->firstOrFail();

        app(RecordManifestationResultAction::class)->recordCompleted($command, new CommandResultData(
            result: [],
            sefaz: [],
            requestXml: null,
            responseXml: null,
            protocolNumber: null,
            sefazStatusCode: '573',
            sefazMessage: 'Rejeição informada pela SEFAZ',
            durationMs: 120,
        ));

        $this->assertDatabaseHas('fiscal_documents', [
            'id' => $document->id,
            'manifestation_status' => ManifestationStatus::Rejected->value,
        ]);

        $this->assertDatabaseHas('recipient_manifestations', [
            'fiscal_document_id' => $document->id,
            'status' => ManifestationRecordStatus::Rejected->value,
        ]);
    }

    public function test_final_technical_failure_does_not_mark_manifestation_as_success(): void
    {
        $document = FiscalDocument::factory()->create([
            'manifestation_status' => ManifestationStatus::NoManifestation,
        ]);

        app(RequestManifestationAction::class)->execute(
            document: $document,
            eventType: ManifestationEventType::OperationConfirmation,
            justification: null,
            context: new ManifestationRequestContext(explicitUserConfirmation: true),
            createdBy: null,
        );

        $command = AgentCommand::query()->firstOrFail();

        app(RecordManifestationResultAction::class)->recordFailed($command, new CommandFailureData(
            errorCode: 'SEFAZ_TIMEOUT',
            errorMessage: 'Timeout ao consultar SEFAZ.',
            errorDetails: null,
            sefazStatusCode: null,
            sefazMessage: null,
            durationMs: 30000,
        ), isFinalFailure: true);

        $this->assertDatabaseHas('fiscal_documents', [
            'id' => $document->id,
            'manifestation_status' => ManifestationStatus::Failed->value,
        ]);
    }
}
