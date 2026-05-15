<?php

namespace Tests\Feature\Settings;

use App\Models\Company;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_screen_loads_values_for_selected_company(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        SystemSetting::factory()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'key' => 'xml_storage_disk',
            'value' => ['value' => 'local'],
        ]);

        $this->actingAs($user)
            ->get(route('settings.edit', ['company_id' => $company->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Settings/Edit')
                ->where('selectedCompanyId', $company->id)
                ->where('settings.xml_storage_disk', 'local'));
    }

    public function test_settings_are_saved_at_company_level(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        SystemSetting::factory()->create([
            'tenant_id' => $otherCompany->tenant_id,
            'company_id' => $otherCompany->id,
            'key' => 'xml_storage_disk',
            'value' => ['value' => 's3'],
        ]);
        SystemSetting::factory()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'key' => 'default_fiscal_environment',
            'value' => ['value' => 'legacy_non_production'],
        ]);

        $this->actingAs($user)
            ->put(route('settings.update'), [
                'company_id' => $company->id,
                'xml_storage_disk' => 'local',
                'xml_retention_days' => '365',
                'sync_frequency_minutes' => '15',
                'automation_rules' => [
                    'auto_acknowledge' => true,
                    'auto_download_after_acknowledgement' => false,
                    'notify_failed_manifestations' => true,
                ],
            ])
            ->assertRedirect(route('settings.edit', ['company_id' => $company->id]));

        $this->assertSettingValue($company, 'xml_storage_disk', 'local');
        $this->assertSettingValue($company, 'xml_retention_days', 365);
        $this->assertSettingValue($company, 'sync_frequency_minutes', 15);
        $this->assertSettingValue($company, 'automation_rules', [
            'auto_acknowledge' => true,
            'auto_download_after_acknowledgement' => false,
            'notify_failed_manifestations' => true,
        ]);
        $this->assertSettingValue($otherCompany, 'xml_storage_disk', 's3');
        $this->assertFalse(SystemSetting::query()
            ->where('tenant_id', $company->tenant_id)
            ->where('company_id', $company->id)
            ->where('key', 'default_fiscal_environment')
            ->exists());
    }

    private function assertSettingValue(Company $company, string $key, mixed $expected): void
    {
        $setting = SystemSetting::query()
            ->where('tenant_id', $company->tenant_id)
            ->where('company_id', $company->id)
            ->where('key', $key)
            ->firstOrFail();

        $value = $setting->value;

        if (! is_array($value) || ! array_key_exists('value', $value)) {
            $this->fail("Setting {$key} does not have a stored value.");
        }

        $this->assertSame($expected, $value['value']);
    }
}
