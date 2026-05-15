<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSystemSettingsRequest;
use App\Models\Company;
use App\Models\SystemSetting;
use App\Support\CompanyContext\CurrentCompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(Request $request, CurrentCompanyContext $context): Response
    {
        $companies = $context->availableCompanies();

        $selectedCompany = $companies->firstWhere('id', $request->integer('company_id'))
            ?? $context->company();

        return Inertia::render('Settings/Edit', [
            'companies' => $companies,
            'selectedCompanyId' => $selectedCompany?->id,
            'settings' => $selectedCompany
                ? $this->settingsForCompany($selectedCompany)
                : [],
        ]);
    }

    public function update(UpdateSystemSettingsRequest $request, CurrentCompanyContext $context): RedirectResponse
    {
        $validated = $request->validated();
        $company = $context->availableCompanies()->firstWhere('id', (int) $validated['company_id']);

        if (! $company instanceof Company) {
            abort(404);
        }

        unset($validated['company_id']);

        SystemSetting::query()
            ->where('tenant_id', $company->tenant_id)
            ->where('company_id', $company->id)
            ->where('key', 'default_fiscal_environment')
            ->delete();

        foreach ($this->normalizeSettings($validated) as $key => $value) {
            SystemSetting::query()->updateOrCreate(
                [
                    'tenant_id' => $company->tenant_id,
                    'company_id' => $company->id,
                    'key' => $key,
                ],
                [
                    'value' => ['value' => $value],
                    'is_encrypted' => false,
                ],
            );
        }

        return redirect()
            ->route('settings.edit', ['company_id' => $company->id])
            ->with('success', 'Configuracoes atualizadas.');
    }

    /** @return array<string, mixed> */
    private function settingsForCompany(Company $company): array
    {
        return SystemSetting::query()
            ->where('tenant_id', $company->tenant_id)
            ->where('company_id', $company->id)
            ->whereIn('key', [
                'xml_storage_disk',
                'xml_retention_days',
                'sync_frequency_minutes',
                'automation_rules',
            ])
            ->get(['key', 'value'])
            ->mapWithKeys(fn (SystemSetting $setting): array => [
                $setting->key => $setting->value['value'] ?? null,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function normalizeSettings(array $settings): array
    {
        $settings['xml_retention_days'] = (int) $settings['xml_retention_days'];
        $settings['sync_frequency_minutes'] = (int) $settings['sync_frequency_minutes'];

        $automationRules = $settings['automation_rules'] ?? [];
        $settings['automation_rules'] = [
            'auto_acknowledge' => (bool) ($automationRules['auto_acknowledge'] ?? false),
            'auto_download_after_acknowledgement' => (bool) ($automationRules['auto_download_after_acknowledgement'] ?? false),
            'notify_failed_manifestations' => (bool) ($automationRules['notify_failed_manifestations'] ?? false),
        ];

        return $settings;
    }
}
