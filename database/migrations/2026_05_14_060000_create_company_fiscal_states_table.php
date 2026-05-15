<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_fiscal_states', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('environment', 20);
            $table->char('uf', 2);
            $table->string('service', 80);
            $table->string('last_nsu', 15)->default('000000000000000');
            $table->string('max_nsu', 15)->default('000000000000000');
            $table->string('last_status_code', 10)->nullable();
            $table->text('last_message')->nullable();
            $table->timestampTz('last_success_at')->nullable();
            $table->timestampTz('last_error_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'environment', 'uf', 'service'], 'company_fiscal_states_unique_scope');
            $table->index(['tenant_id', 'company_id', 'service'], 'company_fiscal_states_scope_service_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_fiscal_states');
    }
};
