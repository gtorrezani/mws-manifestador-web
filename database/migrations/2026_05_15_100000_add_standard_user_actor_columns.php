<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<array{table: string, legacy: string, column: string, index: string, foreign: string}> */
    private array $standardUserColumns = [
        [
            'table' => 'agent_activations',
            'legacy' => 'requested_by',
            'column' => 'requested_by_user_id',
            'index' => 'agent_activations_requested_by_user_id_idx',
            'foreign' => 'agent_activations_requested_by_user_id_fk',
        ],
        [
            'table' => 'agent_commands',
            'legacy' => 'created_by',
            'column' => 'created_by_user_id',
            'index' => 'agent_commands_created_by_user_id_idx',
            'foreign' => 'agent_commands_created_by_user_id_fk',
        ],
        [
            'table' => 'recipient_manifestations',
            'legacy' => 'created_by',
            'column' => 'created_by_user_id',
            'index' => 'recipient_manifestations_created_by_user_id_idx',
            'foreign' => 'recipient_manifestations_created_by_user_id_fk',
        ],
        [
            'table' => 'xml_downloads',
            'legacy' => 'requested_by',
            'column' => 'requested_by_user_id',
            'index' => 'xml_downloads_requested_by_user_id_idx',
            'foreign' => 'xml_downloads_requested_by_user_id_fk',
        ],
        [
            'table' => 'sefaz_connectivity_tests',
            'legacy' => 'requested_by',
            'column' => 'requested_by_user_id',
            'index' => 'sefaz_connectivity_tests_requested_by_user_id_idx',
            'foreign' => 'sefaz_connectivity_tests_requested_by_user_id_fk',
        ],
    ];

    public function up(): void
    {
        foreach ($this->standardUserColumns as $definition) {
            $this->addStandardUserColumn($definition);
            $this->backfillFromLegacyColumn($definition['table'], $definition['legacy'], $definition['column']);
            $this->addUserForeignKeyIfSafe($definition['table'], $definition['column'], $definition['foreign']);
        }

        $this->addUserForeignKeyIfSafe('audit_logs', 'actor_user_id', 'audit_logs_actor_user_id_fk');
    }

    public function down(): void
    {
        $this->dropUserForeignKeyIfPresent('audit_logs', 'audit_logs_actor_user_id_fk');

        foreach (array_reverse($this->standardUserColumns) as $definition) {
            if (! Schema::hasTable($definition['table']) || ! Schema::hasColumn($definition['table'], $definition['column'])) {
                continue;
            }

            $this->dropUserForeignKeyIfPresent($definition['table'], $definition['foreign']);

            Schema::table($definition['table'], function (Blueprint $table) use ($definition): void {
                $table->dropIndex($definition['index']);
                $table->dropColumn($definition['column']);
            });
        }
    }

    /**
     * @param  array{table: string, legacy: string, column: string, index: string, foreign: string}  $definition
     */
    private function addStandardUserColumn(array $definition): void
    {
        if (! Schema::hasTable($definition['table']) || Schema::hasColumn($definition['table'], $definition['column'])) {
            return;
        }

        Schema::table($definition['table'], function (Blueprint $table) use ($definition): void {
            $table->foreignId($definition['column'])
                ->nullable()
                ->index($definition['index']);
        });
    }

    private function backfillFromLegacyColumn(string $table, string $legacyColumn, string $newColumn): void
    {
        if (! Schema::hasTable($table)
            || ! Schema::hasColumn($table, $legacyColumn)
            || ! Schema::hasColumn($table, $newColumn)
        ) {
            return;
        }

        DB::table($table)
            ->whereNull($newColumn)
            ->whereNotNull($legacyColumn)
            ->whereIn($legacyColumn, DB::table('users')->select('id'))
            ->update([$newColumn => DB::raw($legacyColumn)]);
    }

    private function addUserForeignKeyIfSafe(string $table, string $column, string $foreignName): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $hasOrphanedValues = DB::table($table)
            ->whereNotNull($column)
            ->whereNotIn($column, DB::table('users')->select('id'))
            ->exists();

        if ($hasOrphanedValues) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($column, $foreignName): void {
            $table->foreign($column, $foreignName)
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    private function dropUserForeignKeyIfPresent(string $table, string $foreignName): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $table) use ($foreignName): void {
                $table->dropForeign($foreignName);
            });
        } catch (Throwable) {
            // The up migration intentionally skips a foreign key when existing data is orphaned.
        }
    }
};
