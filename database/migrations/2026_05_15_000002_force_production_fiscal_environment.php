<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('companies')
            ->where('fiscal_environment', '!=', 'production')
            ->update(['fiscal_environment' => 'production']);

        DB::table('sefaz_requests')
            ->where('environment', '!=', 'production')
            ->update(['environment' => 'production']);

        DB::table('system_settings')
            ->where('key', 'default_fiscal_environment')
            ->delete();
    }

    public function down(): void
    {
        //
    }
};
