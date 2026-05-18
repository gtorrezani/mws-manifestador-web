<?php

namespace Tests\Feature\Fiscal;

use App\Enums\CommandType;
use App\Enums\ManifestationEventType;
use App\Enums\ManifestationStatus;
use App\Models\Company;
use App\Models\FiscalDocument;
use App\Models\User;
use App\Support\CompanyContext\CurrentCompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FiscalDocumentBulkManifestationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  non-empty-string|null  $justification
     */
    #[DataProvider('bulkManifestationActions')]
    public function test_bulk_manifestation_actions_create_expected_commands(
        string $action,
        ManifestationEventType $eventType,
        CommandType $commandType,
        ManifestationStatus $requestedStatus,
        ?string $justification,
    ): void {
        $company = Company::factory()->create();
        $document = FiscalDocument::factory()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'manifestation_status' => ManifestationStatus::NoManifestation,
        ]);

        $payload = [
            'action' => $action,
            'document_ids' => [$document->id],
        ];

        if ($action !== 'acknowledge') {
            $payload['confirmed'] = true;
        }

        if ($justification !== null) {
            $payload['justification'] = $justification;
        }

        $this
            ->withCurrentCompany($company)
            ->post('/fiscal-documents/bulk', $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('recipient_manifestations', [
            'fiscal_document_id' => $document->id,
            'event_type' => $eventType->value,
            'justification' => $justification,
        ]);
        $this->assertDatabaseHas('agent_commands', [
            'company_id' => $company->id,
            'type' => $commandType->value,
        ]);
        $this->assertDatabaseHas('fiscal_documents', [
            'id' => $document->id,
            'manifestation_status' => $requestedStatus->value,
        ]);
    }

    public function test_bulk_conclusive_manifestation_requires_confirmation(): void
    {
        $company = Company::factory()->create();
        $document = FiscalDocument::factory()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
        ]);

        $this
            ->withCurrentCompany($company)
            ->post('/fiscal-documents/bulk', [
                'action' => 'manifest_unknown',
                'document_ids' => [$document->id],
            ])
            ->assertSessionHasErrors('confirmed');
    }

    public function test_bulk_operation_not_performed_requires_justification(): void
    {
        $company = Company::factory()->create();
        $document = FiscalDocument::factory()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
        ]);

        $this
            ->withCurrentCompany($company)
            ->post('/fiscal-documents/bulk', [
                'action' => 'manifest_not_performed',
                'document_ids' => [$document->id],
                'confirmed' => true,
            ])
            ->assertSessionHasErrors('justification');
    }

    /**
     * @return iterable<string, array{string, ManifestationEventType, CommandType, ManifestationStatus, non-empty-string|null}>
     */
    public static function bulkManifestationActions(): iterable
    {
        yield 'acknowledgement' => [
            'acknowledge',
            ManifestationEventType::OperationAcknowledgement,
            CommandType::ManifestAcknowledgement,
            ManifestationStatus::AcknowledgementRequested,
            null,
        ];

        yield 'confirmation' => [
            'manifest_confirmation',
            ManifestationEventType::OperationConfirmation,
            CommandType::ManifestConfirmation,
            ManifestationStatus::ConfirmationRequested,
            null,
        ];

        yield 'unknown' => [
            'manifest_unknown',
            ManifestationEventType::OperationUnknown,
            CommandType::ManifestUnknown,
            ManifestationStatus::UnknownRequested,
            null,
        ];

        yield 'not_performed' => [
            'manifest_not_performed',
            ManifestationEventType::OperationNotPerformed,
            CommandType::ManifestNotPerformed,
            ManifestationStatus::NotPerformedRequested,
            'Operacao comercial nao reconhecida pela empresa.',
        ];
    }

    private function withCurrentCompany(Company $company): self
    {
        $user = User::factory()->create();
        $user->companies()->attach($company->id);

        return $this
            ->actingAs($user)
            ->withSession([CurrentCompanyContext::SESSION_KEY => $company->id]);
    }
}
