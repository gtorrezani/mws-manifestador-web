<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_document_summaries', function (Blueprint $table): void {
            $table->longText('summary_xml')->nullable()->after('storage_path');
        });

        Schema::table('fiscal_document_xmls', function (Blueprint $table): void {
            $table->longText('xml_content')->nullable()->after('storage_path');
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_document_xmls', function (Blueprint $table): void {
            $table->dropColumn('xml_content');
        });

        Schema::table('fiscal_document_summaries', function (Blueprint $table): void {
            $table->dropColumn('summary_xml');
        });
    }
};
