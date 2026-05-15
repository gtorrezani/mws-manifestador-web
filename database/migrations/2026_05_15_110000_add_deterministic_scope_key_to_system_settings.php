<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        if (! Schema::hasColumn('system_settings', 'scope_key')) {
            Schema::table('system_settings', function (Blueprint $table): void {
                $table->string('scope_key', 220)->default('global');
                $table->index('scope_key', 'system_settings_scope_key_idx');
            });
        }

        $this->backfillScopeKeys();
        $this->assertNoDuplicateLogicalSettings();

        Schema::table('system_settings', function (Blueprint $table): void {
            $table->unique(['scope_key', 'key'], 'system_settings_scope_key_deterministic_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_settings') || ! Schema::hasColumn('system_settings', 'scope_key')) {
            return;
        }

        Schema::table('system_settings', function (Blueprint $table): void {
            $table->dropUnique('system_settings_scope_key_deterministic_unique');
            $table->dropIndex('system_settings_scope_key_idx');
            $table->dropColumn('scope_key');
        });
    }

    private function backfillScopeKeys(): void
    {
        DB::table('system_settings')
            ->select(['id', 'tenant_id', 'company_id'])
            ->orderBy('id')
            ->chunkById(500, function ($settings): void {
                foreach ($settings as $setting) {
                    DB::table('system_settings')
                        ->where('id', $setting->id)
                        ->update([
                            'scope_key' => $this->makeScopeKey($setting->tenant_id, $setting->company_id),
                        ]);
                }
            });
    }

    private function assertNoDuplicateLogicalSettings(): void
    {
        $duplicate = DB::table('system_settings')
            ->select(['scope_key', 'key'])
            ->selectRaw('count(*) as aggregate')
            ->groupBy('scope_key', 'key')
            ->havingRaw('count(*) > 1')
            ->first();

        if ($duplicate === null) {
            return;
        }

        throw new RuntimeException(
            'Cannot add deterministic system_settings uniqueness: duplicate logical settings exist for at least one scope_key/key pair. Clean duplicate settings before rerunning this migration.'
        );
    }

    private function makeScopeKey(mixed $tenantId, mixed $companyId): string
    {
        if ($companyId !== null) {
            return 'company:'.(int) $companyId;
        }

        if ($tenantId !== null) {
            return 'tenant:'.(int) $tenantId;
        }

        return 'global';
    }
};
