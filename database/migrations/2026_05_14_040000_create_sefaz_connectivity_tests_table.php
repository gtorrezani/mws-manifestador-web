<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sefaz_connectivity_tests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_certificate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_command_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mode', 40);
            $table->string('environment', 40);
            $table->char('uf', 2);
            $table->string('endpoint')->nullable();
            $table->string('status', 40);
            $table->string('sefaz_status_code', 10)->nullable();
            $table->text('sefaz_message')->nullable();
            $table->string('error_code', 120)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('request_xml_storage_path')->nullable();
            $table->string('response_xml_storage_path')->nullable();
            $table->json('sanitized_payload')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable()->index();
            $table->timestampTz('requested_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'company_id', 'status'], 'sefaz_conn_tests_scope_status_idx');
            $table->index('company_certificate_id', 'sefaz_conn_tests_certificate_idx');
            $table->index('agent_command_id', 'sefaz_conn_tests_command_idx');
            $table->index('requested_at', 'sefaz_conn_tests_requested_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sefaz_connectivity_tests');
    }
};
