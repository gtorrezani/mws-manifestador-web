<?php

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_certificates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', [CertificateType::A3->value])->default(CertificateType::A3->value);
            $table->enum('status', $this->enumValues(CertificateStatus::class))->default(CertificateStatus::PendingValidation->value);
            $table->string('store_scope')->nullable()->comment('Windows certificate store scope reported by the agent.');
            $table->string('subject_name')->nullable();
            $table->string('issuer_name')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('thumbprint');
            $table->string('cnpj', 14)->nullable();
            $table->timestampTz('valid_from')->nullable();
            $table->timestampTz('valid_until')->nullable();
            $table->boolean('has_private_key')->default(false);
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampTz('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable();
            $table->text('last_test_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'agent_id', 'thumbprint', 'store_scope'], 'agent_certs_unique_store_thumbprint');
            $table->index(['tenant_id', 'agent_id', 'status'], 'agent_certs_agent_status_idx');
            $table->index(['tenant_id', 'cnpj'], 'agent_certs_tenant_cnpj_idx');
            $table->index('thumbprint', 'agent_certs_thumbprint_idx');
        });

        Schema::table('company_certificates', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_certificate_id')->nullable()->constrained('agent_certificates')->nullOnDelete();
            $table->string('store_scope')->nullable()->comment('Windows certificate store scope for A3 certificates.');
            $table->timestampTz('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable();
            $table->text('last_test_message')->nullable();

            $table->index(['tenant_id', 'agent_id'], 'certs_tenant_agent_idx');
            $table->index(['tenant_id', 'agent_certificate_id'], 'certs_tenant_agent_cert_idx');
        });
    }

    public function down(): void
    {
        Schema::table('company_certificates', function (Blueprint $table) {
            $table->dropIndex('certs_tenant_agent_idx');
            $table->dropIndex('certs_tenant_agent_cert_idx');
            $table->dropConstrainedForeignId('agent_id');
            $table->dropConstrainedForeignId('agent_certificate_id');
            $table->dropColumn([
                'store_scope',
                'last_tested_at',
                'last_test_status',
                'last_test_message',
            ]);
        });

        Schema::dropIfExists('agent_certificates');
    }

    /**
     * @param  class-string  $enum
     * @return list<string>
     */
    private function enumValues(string $enum): array
    {
        return array_map(
            static fn ($case): string => $case->value,
            $enum::cases(),
        );
    }
};
