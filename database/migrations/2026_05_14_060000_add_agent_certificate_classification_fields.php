<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_certificates', function (Blueprint $table): void {
            $table->string('common_name')->nullable()->after('issuer');
            $table->string('document', 20)->nullable()->after('cnpj');
            $table->string('document_type', 10)->nullable()->after('document');
            $table->string('store_name')->nullable()->after('store_location');
            $table->boolean('is_certificate_authority')->default(false)->after('is_expired');
            $table->boolean('is_fiscal_candidate')->default(false)->after('is_certificate_authority');
            $table->boolean('is_icp_brasil')->default(false)->after('is_fiscal_candidate');
            $table->boolean('is_usable_for_client_auth')->default(false)->after('is_icp_brasil');
            $table->string('classification')->default('unknown')->after('is_usable_for_client_auth');
            $table->json('rejection_reasons')->nullable()->after('classification');
            $table->json('warnings')->nullable()->after('rejection_reasons');

            $table->index(['tenant_id', 'company_id', 'is_fiscal_candidate'], 'agent_certs_fiscal_candidate_idx');
            $table->index(['tenant_id', 'company_id', 'classification'], 'agent_certs_classification_idx');
        });
    }

    public function down(): void
    {
        Schema::table('agent_certificates', function (Blueprint $table): void {
            $table->dropIndex('agent_certs_fiscal_candidate_idx');
            $table->dropIndex('agent_certs_classification_idx');
            $table->dropColumn([
                'common_name',
                'document',
                'document_type',
                'store_name',
                'is_certificate_authority',
                'is_fiscal_candidate',
                'is_icp_brasil',
                'is_usable_for_client_auth',
                'classification',
                'rejection_reasons',
                'warnings',
            ]);
        });
    }
};
