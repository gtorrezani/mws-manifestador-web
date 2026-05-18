<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('companies')->update(['fiscal_environment' => 'production']);

        DB::table('system_settings')
            ->where('key', 'default_fiscal_environment')
            ->delete();

        DB::table('company_fiscal_states')
            ->where('environment', '!=', 'production')
            ->orderBy('id')
            ->get()
            ->each(function (object $state): void {
                $existing = DB::table('company_fiscal_states')
                    ->where('tenant_id', $state->tenant_id)
                    ->where('company_id', $state->company_id)
                    ->where('environment', 'production')
                    ->where('uf', $state->uf)
                    ->where('service', $state->service)
                    ->first();

                if ($existing !== null) {
                    DB::table('company_fiscal_states')->where('id', $state->id)->delete();

                    return;
                }

                DB::table('company_fiscal_states')
                    ->where('id', $state->id)
                    ->update(['environment' => 'production']);
            });
    }

    public function down(): void
    {
        // Production is now the only supported fiscal environment.
    }
};
