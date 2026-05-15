<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_certificates', function (Blueprint $table): void {
            $table->string('last_test_error_code')->nullable()->after('last_test_message');
            $table->json('last_test_payload')->nullable()->after('last_test_error_code');
        });

        Schema::table('company_certificates', function (Blueprint $table): void {
            $table->string('last_test_error_code')->nullable()->after('last_test_message');
            $table->json('last_test_payload')->nullable()->after('last_test_error_code');
        });
    }

    public function down(): void
    {
        Schema::table('agent_certificates', function (Blueprint $table): void {
            $table->dropColumn(['last_test_error_code', 'last_test_payload']);
        });

        Schema::table('company_certificates', function (Blueprint $table): void {
            $table->dropColumn(['last_test_error_code', 'last_test_payload']);
        });
    }
};
