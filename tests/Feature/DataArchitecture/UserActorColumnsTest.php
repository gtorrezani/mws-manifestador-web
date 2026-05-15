<?php

namespace Tests\Feature\DataArchitecture;

use App\Actions\Agent\CreateAgentActivationCodeAction;
use App\Actions\FiscalDocument\CreateFiscalCommandAction;
use App\Actions\FiscalDocument\RequestManifestationAction;
use App\Enums\CommandType;
use App\Enums\ManifestationEventType;
use App\Models\AgentCommand;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\FiscalDocument;
use App\Models\RecipientManifestation;
use App\Models\SefazConnectivityTest;
use App\Models\User;
use App\Models\XmlDownload;
use App\Services\Fiscal\ManifestationRequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserActorColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_user_actor_columns_are_added_without_dropping_legacy_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('agent_activations', 'requested_by'));
        $this->assertTrue(Schema::hasColumn('agent_activations', 'requested_by_user_id'));
        $this->assertTrue(Schema::hasColumn('agent_commands', 'created_by'));
        $this->assertTrue(Schema::hasColumn('agent_commands', 'created_by_user_id'));
        $this->assertTrue(Schema::hasColumn('recipient_manifestations', 'created_by'));
        $this->assertTrue(Schema::hasColumn('recipient_manifestations', 'created_by_user_id'));
        $this->assertTrue(Schema::hasColumn('xml_downloads', 'requested_by'));
        $this->assertTrue(Schema::hasColumn('xml_downloads', 'requested_by_user_id'));
        $this->assertTrue(Schema::hasColumn('sefaz_connectivity_tests', 'requested_by'));
        $this->assertTrue(Schema::hasColumn('sefaz_connectivity_tests', 'requested_by_user_id'));
        $this->assertTrue(Schema::hasColumn('audit_logs', 'actor_user_id'));
    }

    public function test_activation_action_writes_legacy_and_standard_requested_user_columns(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $result = app(CreateAgentActivationCodeAction::class)->execute($company, $user->id);
        $activation = $result['activation']->refresh();

        $this->assertSame($user->id, $activation->requested_by);
        $this->assertSame($user->id, $activation->getAttribute('requested_by_user_id'));

        $requestedByUser = $activation->requestedByUser;
        $this->assertInstanceOf(User::class, $requestedByUser);
        $this->assertTrue($requestedByUser->is($user));
    }

    public function test_command_action_writes_legacy_and_standard_created_user_columns(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $command = app(CreateFiscalCommandAction::class)->execute(
            company: $company,
            type: CommandType::SyncFiscalDocuments,
            payload: ['correlation_id' => 'actor-column-test'],
            createdBy: $user->id,
        )->refresh();

        $this->assertSame($user->id, $command->created_by);
        $this->assertSame($user->id, $command->getAttribute('created_by_user_id'));

        $createdByUser = $command->createdByUser;
        $this->assertInstanceOf(User::class, $createdByUser);
        $this->assertTrue($createdByUser->is($user));
    }

    public function test_manifestation_action_writes_legacy_and_standard_created_user_columns(): void
    {
        $user = User::factory()->create();
        $document = FiscalDocument::factory()->create();

        $manifestation = app(RequestManifestationAction::class)->execute(
            document: $document,
            eventType: ManifestationEventType::OperationAcknowledgement,
            justification: null,
            context: new ManifestationRequestContext,
            createdBy: $user->id,
        )->refresh();

        $this->assertSame($user->id, $manifestation->created_by);
        $this->assertSame($user->id, $manifestation->getAttribute('created_by_user_id'));

        $createdByUser = $manifestation->createdByUser;
        $this->assertInstanceOf(User::class, $createdByUser);
        $this->assertTrue($createdByUser->is($user));

        $command = AgentCommand::query()->firstOrFail();
        $this->assertSame($user->id, $command->created_by);
        $this->assertSame($user->id, $command->getAttribute('created_by_user_id'));
    }

    public function test_standard_user_actor_relationships_work_for_related_models(): void
    {
        $user = User::factory()->create();

        $xmlDownload = XmlDownload::factory()->create([
            'requested_by' => $user->id,
            'requested_by_user_id' => $user->id,
        ]);
        $sefazConnectivityTest = SefazConnectivityTest::factory()->create([
            'requested_by' => $user->id,
            'requested_by_user_id' => $user->id,
        ]);
        $auditLog = AuditLog::factory()->create([
            'actor_user_id' => $user->id,
        ]);

        $xmlDownloadRequestedByUser = $xmlDownload->requestedByUser;
        $sefazConnectivityRequestedByUser = $sefazConnectivityTest->requestedByUser;
        $auditLogActorUser = $auditLog->actorUser;

        $this->assertInstanceOf(User::class, $xmlDownloadRequestedByUser);
        $this->assertInstanceOf(User::class, $sefazConnectivityRequestedByUser);
        $this->assertInstanceOf(User::class, $auditLogActorUser);
        $this->assertTrue($xmlDownloadRequestedByUser->is($user));
        $this->assertTrue($sefazConnectivityRequestedByUser->is($user));
        $this->assertTrue($auditLogActorUser->is($user));
    }

    public function test_recipient_manifestation_factory_accepts_standard_created_user_column(): void
    {
        $user = User::factory()->create();

        $manifestation = RecipientManifestation::factory()->create([
            'created_by' => $user->id,
            'created_by_user_id' => $user->id,
        ]);

        $createdByUser = $manifestation->createdByUser;
        $this->assertInstanceOf(User::class, $createdByUser);
        $this->assertTrue($createdByUser->is($user));
    }
}
