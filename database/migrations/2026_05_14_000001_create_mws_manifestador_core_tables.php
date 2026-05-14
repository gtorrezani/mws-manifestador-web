<?php

use App\Enums\ActivationStatus;
use App\Enums\AgentStatus;
use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\FiscalEnvironment;
use App\Enums\ManifestationEventType;
use App\Enums\ManifestationRecordStatus;
use App\Enums\ManifestationStatus;
use App\Enums\SefazRequestStatus;
use App\Enums\XmlDownloadStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->char('cnpj', 14);
            $table->string('state_registration', 32)->nullable();
            $table->char('uf', 2);
            $table->enum('fiscal_environment', $this->enumValues(FiscalEnvironment::class))
                ->default(FiscalEnvironment::Production->value);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'cnpj'], 'companies_tenant_cnpj_unique');
            $table->index(['tenant_id', 'is_active'], 'companies_tenant_active_idx');
            $table->index(['tenant_id', 'uf'], 'companies_tenant_uf_idx');
        });

        Schema::create('company_certificates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->enum('type', $this->enumValues(CertificateType::class));
            $table->enum('status', $this->enumValues(CertificateStatus::class))
                ->default(CertificateStatus::PendingValidation->value);
            $table->string('name')->nullable();
            $table->string('subject_name')->nullable();
            $table->string('issuer_name')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('thumbprint')->nullable();
            $table->timestampTz('valid_from')->nullable();
            $table->timestampTz('valid_until')->nullable();
            $table->string('storage_disk')->nullable()->comment('Encrypted A1 certificate object disk.');
            $table->string('storage_path')->nullable()->comment('Encrypted A1 certificate object path.');
            $table->text('encrypted_password_payload')->nullable()->comment('Encrypted A1 password payload. Never store plaintext passwords.');
            $table->json('metadata')->nullable()->comment('A3 PIN is intentionally not stored.');
            $table->timestampTz('last_validated_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['tenant_id', 'company_id', 'type'], 'certs_tenant_company_type_idx');
            $table->index(['tenant_id', 'status'], 'certs_tenant_status_idx');
            $table->index('thumbprint', 'certs_thumbprint_idx');
        });

        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('machine_name')->nullable();
            $table->string('installation_id');
            $table->string('version', 40)->nullable();
            $table->enum('status', $this->enumValues(AgentStatus::class))->default(AgentStatus::Pending->value);
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'installation_id'], 'agents_tenant_installation_unique');
            $table->index(['tenant_id', 'company_id'], 'agents_tenant_company_idx');
            $table->index(['tenant_id', 'status'], 'agents_tenant_status_idx');
            $table->index('last_seen_at', 'agents_last_seen_idx');
        });

        Schema::create('agent_activations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('used_by_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->unsignedBigInteger('requested_by')->nullable()->index();
            $table->string('code_hash')->unique();
            $table->enum('status', $this->enumValues(ActivationStatus::class))->default(ActivationStatus::Pending->value);
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'company_id', 'status'], 'activations_scope_status_idx');
            $table->index('expires_at', 'activations_expires_at_idx');
        });

        Schema::create('agent_credentials', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('credential_id')->unique();
            $table->string('secret_hash')->comment('Agent shared secret hash. Never store plaintext credentials.');
            $table->text('encrypted_secret_payload')->comment('Encrypted HMAC secret used to verify signed agent requests.');
            $table->string('pending_secret_hash')->nullable()->comment('Hash for a rotated secret waiting for agent acknowledgement.');
            $table->text('pending_encrypted_secret_payload')->nullable()->comment('Encrypted rotated HMAC secret waiting for promotion.');
            $table->timestampTz('pending_secret_expires_at')->nullable();
            $table->timestampTz('last_rotated_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'revoked_at'], 'agent_credentials_tenant_revoked_idx');
        });

        Schema::create('agent_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->enum('status', $this->enumValues(AgentStatus::class));
            $table->string('version', 40)->nullable();
            $table->string('machine_name')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->json('payload')->nullable();
            $table->timestampTz('received_at');
            $table->timestampsTz();

            $table->index(['tenant_id', 'agent_id', 'received_at'], 'heartbeats_agent_received_idx');
            $table->index(['tenant_id', 'status'], 'heartbeats_tenant_status_idx');
        });

        Schema::create('agent_commands', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', $this->enumValues(CommandType::class));
            $table->enum('status', $this->enumValues(CommandStatus::class))->default(CommandStatus::Pending->value);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->json('payload');
            $table->timestampTz('available_at')->nullable();
            $table->timestampTz('locked_at')->nullable();
            $table->foreignId('locked_by_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->timestampTz('lock_expires_at')->nullable();
            $table->unsignedInteger('attempts_count')->default(0);
            $table->unsignedInteger('max_attempts')->default(3);
            $table->string('idempotency_key', 120)->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'idempotency_key'], 'commands_tenant_idempotency_unique');
            $table->index(['tenant_id', 'company_id', 'status'], 'commands_scope_status_idx');
            $table->index(['tenant_id', 'agent_id', 'status'], 'commands_agent_status_idx');
            $table->index(['status', 'available_at', 'priority'], 'commands_polling_idx');
            $table->index('type', 'commands_type_idx');
        });

        Schema::create('agent_command_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_command_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->enum('status', $this->enumValues(CommandStatus::class));
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('result_payload')->nullable();
            $table->timestampsTz();

            $table->unique(['agent_command_id', 'attempt_number'], 'command_attempts_number_unique');
            $table->index(['tenant_id', 'status'], 'command_attempts_tenant_status_idx');
        });

        Schema::create('fiscal_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->char('access_key', 44);
            $table->string('nsu', 32)->nullable();
            $table->string('schema_version', 20)->nullable();
            $table->char('issuer_cnpj', 14)->nullable();
            $table->string('issuer_name')->nullable();
            $table->char('recipient_cnpj', 14);
            $table->string('number', 20)->nullable();
            $table->string('series', 10)->nullable();
            $table->timestampTz('issued_at')->nullable();
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->enum('manifestation_status', $this->enumValues(ManifestationStatus::class))
                ->default(ManifestationStatus::NoManifestation->value);
            $table->enum('xml_download_status', $this->enumValues(XmlDownloadStatus::class))
                ->default(XmlDownloadStatus::NotRequested->value);
            $table->string('last_sefaz_status_code', 10)->nullable();
            $table->text('last_sefaz_message')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'access_key'], 'fiscal_docs_scope_access_key_unique');
            $table->unique(['tenant_id', 'company_id', 'nsu'], 'fiscal_docs_scope_nsu_unique');
            $table->index(['tenant_id', 'company_id'], 'fiscal_docs_scope_idx');
            $table->index('access_key', 'fiscal_docs_access_key_idx');
            $table->index('nsu', 'fiscal_docs_nsu_idx');
            $table->index(['tenant_id', 'manifestation_status'], 'fiscal_docs_manifestation_idx');
            $table->index(['tenant_id', 'xml_download_status'], 'fiscal_docs_xml_status_idx');
        });

        Schema::create('fiscal_document_summaries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_document_id')->constrained()->cascadeOnDelete();
            $table->string('storage_disk')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('content_hash', 128)->nullable();
            $table->json('summary_payload')->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->timestampsTz();

            $table->unique('fiscal_document_id', 'fiscal_doc_summaries_doc_unique');
            $table->index(['tenant_id', 'company_id'], 'fiscal_doc_summaries_scope_idx');
        });

        Schema::create('fiscal_document_xmls', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_document_id')->constrained()->cascadeOnDelete();
            $table->string('storage_disk');
            $table->string('storage_path');
            $table->string('content_hash', 128);
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('schema_version', 20)->nullable();
            $table->string('source', 40)->default('distribution');
            $table->timestampTz('downloaded_at')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'fiscal_document_id', 'content_hash'], 'fiscal_doc_xmls_hash_unique');
            $table->index(['tenant_id', 'company_id'], 'fiscal_doc_xmls_scope_idx');
            $table->index('content_hash', 'fiscal_doc_xmls_hash_idx');
        });

        Schema::create('recipient_manifestations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_document_id')->constrained()->cascadeOnDelete();
            $table->enum('event_type', $this->enumValues(ManifestationEventType::class));
            $table->enum('status', $this->enumValues(ManifestationRecordStatus::class))
                ->default(ManifestationRecordStatus::Pending->value);
            $table->text('justification')->nullable();
            $table->string('protocol_number', 80)->nullable();
            $table->string('sefaz_status_code', 10)->nullable();
            $table->text('sefaz_message')->nullable();
            $table->timestampTz('occurred_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestampsTz();

            $table->index(['tenant_id', 'company_id', 'status'], 'manifestations_scope_status_idx');
            $table->index(['fiscal_document_id', 'event_type'], 'manifestations_document_event_idx');
            $table->index('protocol_number', 'manifestations_protocol_idx');
        });

        Schema::create('manifestation_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_manifestation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_command_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->enum('status', $this->enumValues(ManifestationRecordStatus::class));
            $table->enum('previous_manifestation_status', $this->enumValues(ManifestationStatus::class));
            $table->enum('new_manifestation_status', $this->enumValues(ManifestationStatus::class))->nullable();
            $table->string('protocol_number', 80)->nullable();
            $table->string('sefaz_status_code', 10)->nullable();
            $table->text('sefaz_message')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();

            $table->unique(['recipient_manifestation_id', 'attempt_number'], 'manifestation_attempts_number_unique');
            $table->index(['tenant_id', 'status'], 'manifestation_attempts_status_idx');
        });

        Schema::create('xml_downloads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_command_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', $this->enumValues(XmlDownloadStatus::class))->default(XmlDownloadStatus::Pending->value);
            $table->string('nsu', 32)->nullable();
            $table->string('protocol_number', 80)->nullable();
            $table->string('sefaz_status_code', 10)->nullable();
            $table->text('sefaz_message')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable()->index();
            $table->timestampTz('requested_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'company_id', 'status'], 'xml_downloads_scope_status_idx');
            $table->index(['fiscal_document_id', 'status'], 'xml_downloads_document_status_idx');
        });

        Schema::create('sefaz_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_command_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service', 80);
            $table->enum('environment', $this->enumValues(FiscalEnvironment::class));
            $table->string('endpoint');
            $table->string('soap_action')->nullable();
            $table->string('request_xml_storage_disk')->nullable();
            $table->string('request_xml_storage_path')->nullable();
            $table->string('request_hash', 128)->nullable();
            $table->string('correlation_id', 120)->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'company_id', 'service'], 'sefaz_requests_scope_service_idx');
            $table->index('correlation_id', 'sefaz_requests_correlation_idx');
            $table->index('sent_at', 'sefaz_requests_sent_at_idx');
        });

        Schema::create('sefaz_responses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sefaz_request_id')->constrained()->cascadeOnDelete();
            $table->enum('status', $this->enumValues(SefazRequestStatus::class));
            $table->unsignedSmallInteger('http_status_code')->nullable();
            $table->string('sefaz_status_code', 10)->nullable();
            $table->text('sefaz_message')->nullable();
            $table->string('response_xml_storage_disk')->nullable();
            $table->string('response_xml_storage_path')->nullable();
            $table->string('response_hash', 128)->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'company_id', 'status'], 'sefaz_responses_scope_status_idx');
            $table->index(['sefaz_status_code'], 'sefaz_responses_status_code_idx');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('event', 120);
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->index(['tenant_id', 'company_id', 'event'], 'audit_logs_scope_event_idx');
            $table->index(['auditable_type', 'auditable_id'], 'audit_logs_auditable_idx');
            $table->index('occurred_at', 'audit_logs_occurred_at_idx');
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 160);
            $table->json('value')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->text('description')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'company_id', 'key'], 'system_settings_scope_key_unique');
            $table->index(['tenant_id', 'company_id'], 'system_settings_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('sefaz_responses');
        Schema::dropIfExists('sefaz_requests');
        Schema::dropIfExists('xml_downloads');
        Schema::dropIfExists('manifestation_attempts');
        Schema::dropIfExists('recipient_manifestations');
        Schema::dropIfExists('fiscal_document_xmls');
        Schema::dropIfExists('fiscal_document_summaries');
        Schema::dropIfExists('fiscal_documents');
        Schema::dropIfExists('agent_command_attempts');
        Schema::dropIfExists('agent_commands');
        Schema::dropIfExists('agent_heartbeats');
        Schema::dropIfExists('agent_credentials');
        Schema::dropIfExists('agent_activations');
        Schema::dropIfExists('agents');
        Schema::dropIfExists('company_certificates');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('tenants');
    }

    /**
     * @param  class-string<BackedEnum>  $enum
     * @return array<int, string>
     */
    private function enumValues(string $enum): array
    {
        return array_map(
            static fn (BackedEnum $case): string => $case->value,
            $enum::cases(),
        );
    }
};
