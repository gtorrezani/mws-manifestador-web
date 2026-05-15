<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_certificates', function (Blueprint $table) {
            $table->string('subject')->nullable()->after('store_scope');
            $table->string('issuer')->nullable()->after('subject');
            $table->timestampTz('not_before')->nullable()->after('cnpj');
            $table->timestampTz('not_after')->nullable()->after('not_before');
            $table->string('store_location')->nullable()->after('has_private_key');
            $table->boolean('is_expired')->default(false)->after('store_location');
            $table->boolean('is_valid')->default(false)->after('is_expired');
            $table->text('validation_message')->nullable()->after('is_valid');
            $table->json('raw_payload')->nullable()->after('metadata');

            $table->unique(['tenant_id', 'company_id', 'agent_id', 'thumbprint', 'store_location'], 'agent_certs_company_agent_thumb_store_unique');
            $table->index(['company_id'], 'agent_certs_company_idx');
            $table->index(['agent_id'], 'agent_certs_agent_idx');
            $table->index(['not_after'], 'agent_certs_not_after_idx');
        });

        DB::table('agent_certificates')->whereNull('subject')->update([
            'subject' => DB::raw('subject_name'),
            'issuer' => DB::raw('issuer_name'),
            'not_before' => DB::raw('valid_from'),
            'not_after' => DB::raw('valid_until'),
        ]);

        DB::table('agent_certificates')->where('store_scope', 'current_user')->update(['store_location' => 'CurrentUser']);
        DB::table('agent_certificates')->where('store_scope', 'local_machine')->update(['store_location' => 'LocalMachine']);
    }

    public function down(): void
    {
        Schema::table('agent_certificates', function (Blueprint $table) {
            $table->dropUnique('agent_certs_company_agent_thumb_store_unique');
            $table->dropIndex('agent_certs_company_idx');
            $table->dropIndex('agent_certs_agent_idx');
            $table->dropIndex('agent_certs_not_after_idx');
            $table->dropColumn([
                'subject',
                'issuer',
                'not_before',
                'not_after',
                'store_location',
                'is_expired',
                'is_valid',
                'validation_message',
                'raw_payload',
            ]);
        });
    }
};
