<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('cpf', 11)->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('blocked_at')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
