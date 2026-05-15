<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->usesMysqlEnum()) {
            return;
        }

        DB::statement("ALTER TABLE agents MODIFY status ENUM('pending','online','offline','outdated','error','revoked','service_stopped') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE agent_heartbeats MODIFY status ENUM('pending','online','offline','outdated','error','revoked','service_stopped') NOT NULL");
        DB::statement("ALTER TABLE agent_commands MODIFY type ENUM('sync_fiscal_documents','manifest_acknowledgement','manifest_confirmation','manifest_unknown','manifest_not_performed','download_xml_by_access_key','download_xml_by_period','export_xml_zip','test_certificate','list_certificates','test_sefaz_connectivity','agent_diagnostics_requested') NOT NULL");
    }

    public function down(): void
    {
        if (! $this->usesMysqlEnum()) {
            return;
        }

        DB::statement("ALTER TABLE agents MODIFY status ENUM('pending','online','offline','outdated','error','revoked') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE agent_heartbeats MODIFY status ENUM('pending','online','offline','outdated','error','revoked') NOT NULL");
        DB::statement("ALTER TABLE agent_commands MODIFY type ENUM('sync_fiscal_documents','manifest_acknowledgement','manifest_confirmation','manifest_unknown','manifest_not_performed','download_xml_by_access_key','download_xml_by_period','export_xml_zip','test_certificate','list_certificates','test_sefaz_connectivity') NOT NULL");
    }

    private function usesMysqlEnum(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
