<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_fiscal_states', function (Blueprint $table): void {
            $table->timestampTz('next_distribution_available_at')->nullable()->after('max_nsu');
            $table->timestampTz('distribution_blocked_until')->nullable()->after('next_distribution_available_at');
            $table->string('distribution_block_reason', 80)->nullable()->after('distribution_blocked_until');
            $table->string('last_distribution_status_code', 10)->nullable()->after('distribution_block_reason');
            $table->text('last_distribution_message')->nullable()->after('last_distribution_status_code');
            $table->timestampTz('last_distribution_attempt_at')->nullable()->after('last_distribution_message');
            $table->timestampTz('last_distribution_success_at')->nullable()->after('last_distribution_attempt_at');
            $table->timestampTz('last_distribution_error_at')->nullable()->after('last_distribution_success_at');
            $table->unsignedInteger('consecutive_distribution_failures')->default(0)->after('last_distribution_error_at');

            $table->index(['company_id', 'next_distribution_available_at'], 'company_fiscal_states_next_distribution_idx');
            $table->index(['company_id', 'distribution_blocked_until'], 'company_fiscal_states_distribution_block_idx');
        });
    }

    public function down(): void
    {
        Schema::table('company_fiscal_states', function (Blueprint $table): void {
            $table->dropIndex('company_fiscal_states_next_distribution_idx');
            $table->dropIndex('company_fiscal_states_distribution_block_idx');
            $table->dropColumn([
                'next_distribution_available_at',
                'distribution_blocked_until',
                'distribution_block_reason',
                'last_distribution_status_code',
                'last_distribution_message',
                'last_distribution_attempt_at',
                'last_distribution_success_at',
                'last_distribution_error_at',
                'consecutive_distribution_failures',
            ]);
        });
    }
};
