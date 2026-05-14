<?php

namespace Database\Seeders;

use App\Enums\FiscalEnvironment;
use App\Models\Company;
use App\Models\SystemSetting;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MwsBaselineSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'default'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Default Tenant',
                'is_active' => true,
            ],
        );

        Company::query()->firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'cnpj' => '00000000000000',
            ],
            [
                'uuid' => (string) Str::uuid(),
                'legal_name' => 'Empresa de Homologacao',
                'trade_name' => 'Homologacao',
                'state_registration' => null,
                'uf' => 'SP',
                'fiscal_environment' => FiscalEnvironment::Homologation,
                'is_active' => true,
            ],
        );

        SystemSetting::query()->firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'company_id' => null,
                'key' => 'agent.polling_interval_seconds',
            ],
            [
                'uuid' => (string) Str::uuid(),
                'value' => ['value' => 30],
                'is_encrypted' => false,
                'description' => 'Default interval used by local agents to poll pending commands.',
            ],
        );

        SystemSetting::query()->firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'company_id' => null,
                'key' => 'agent.command_lock_seconds',
            ],
            [
                'uuid' => (string) Str::uuid(),
                'value' => ['value' => 300],
                'is_encrypted' => false,
                'description' => 'Default lock time for commands pulled by an agent.',
            ],
        );
    }
}
