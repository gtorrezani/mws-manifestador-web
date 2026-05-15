<?php

namespace Tests\Feature\DataArchitecture;

use App\Models\Company;
use App\Models\SystemSetting;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SystemSettingsScopeKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_settings_have_a_deterministic_scope_key_column(): void
    {
        $this->assertTrue(Schema::hasColumn('system_settings', 'scope_key'));

        $tenant = Tenant::factory()->create();
        $company = Company::factory()->create(['tenant_id' => $tenant->id]);

        $global = SystemSetting::factory()->create([
            'tenant_id' => null,
            'company_id' => null,
            'key' => 'global.setting',
        ]);
        $tenantSetting = SystemSetting::factory()->create([
            'tenant_id' => $tenant->id,
            'company_id' => null,
            'key' => 'tenant.setting',
        ]);
        $companySetting = SystemSetting::factory()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'key' => 'company.setting',
        ]);

        $this->assertSame('global', $global->scope_key);
        $this->assertSame('tenant:'.$tenant->id, $tenantSetting->scope_key);
        $this->assertSame('company:'.$company->id, $companySetting->scope_key);
    }

    public function test_global_settings_with_the_same_key_cannot_duplicate(): void
    {
        SystemSetting::factory()->create([
            'tenant_id' => null,
            'company_id' => null,
            'key' => 'agent.polling_interval_seconds',
        ]);

        $this->expectException(QueryException::class);

        SystemSetting::factory()->create([
            'tenant_id' => null,
            'company_id' => null,
            'key' => 'agent.polling_interval_seconds',
        ]);
    }

    public function test_tenant_settings_with_the_same_key_are_unique_per_tenant_scope(): void
    {
        $firstTenant = Tenant::factory()->create();
        $secondTenant = Tenant::factory()->create();

        SystemSetting::factory()->create([
            'tenant_id' => $firstTenant->id,
            'company_id' => null,
            'key' => 'agent.command_lock_seconds',
        ]);
        SystemSetting::factory()->create([
            'tenant_id' => $secondTenant->id,
            'company_id' => null,
            'key' => 'agent.command_lock_seconds',
        ]);

        $this->assertDatabaseHas('system_settings', [
            'scope_key' => 'tenant:'.$firstTenant->id,
            'key' => 'agent.command_lock_seconds',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'scope_key' => 'tenant:'.$secondTenant->id,
            'key' => 'agent.command_lock_seconds',
        ]);

        $this->expectException(QueryException::class);

        SystemSetting::factory()->create([
            'tenant_id' => $firstTenant->id,
            'company_id' => null,
            'key' => 'agent.command_lock_seconds',
        ]);
    }

    public function test_company_settings_with_the_same_key_are_unique_per_company_scope(): void
    {
        $tenant = Tenant::factory()->create();
        $firstCompany = Company::factory()->create(['tenant_id' => $tenant->id]);
        $secondCompany = Company::factory()->create(['tenant_id' => $tenant->id]);

        SystemSetting::factory()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $firstCompany->id,
            'key' => 'sync_frequency_minutes',
        ]);
        SystemSetting::factory()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $secondCompany->id,
            'key' => 'sync_frequency_minutes',
        ]);

        $this->assertDatabaseHas('system_settings', [
            'scope_key' => 'company:'.$firstCompany->id,
            'key' => 'sync_frequency_minutes',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'scope_key' => 'company:'.$secondCompany->id,
            'key' => 'sync_frequency_minutes',
        ]);

        $this->expectException(QueryException::class);

        SystemSetting::factory()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $firstCompany->id,
            'key' => 'sync_frequency_minutes',
        ]);
    }

    public function test_scope_key_is_recalculated_when_scope_columns_change(): void
    {
        $tenant = Tenant::factory()->create();
        $company = Company::factory()->create(['tenant_id' => $tenant->id]);

        $setting = SystemSetting::factory()->create([
            'tenant_id' => null,
            'company_id' => null,
            'key' => 'scope.recalculation',
        ]);

        $setting->forceFill([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
        ])->save();

        $this->assertSame('company:'.$company->id, $setting->refresh()->scope_key);
    }
}
