<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSystemSettingsRequest;
use App\Models\SystemSetting;
use App\Support\CompanyContext\CurrentCompanyContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(CurrentCompanyContext $context): Response
    {
        $company = $context->company();

        return Inertia::render('Settings/Edit', [
            'settings' => SystemSetting::query()
                ->forCompany($company)
                ->get()
                ->keyBy('key'),
        ]);
    }

    public function update(UpdateSystemSettingsRequest $request, CurrentCompanyContext $context): RedirectResponse
    {
        $company = $context->company();

        foreach ($request->validated() as $key => $value) {
            SystemSetting::query()->updateOrCreate(
                ['tenant_id' => $company->tenant_id, 'company_id' => $company->id, 'key' => $key],
                [
                    'value' => ['value' => $value],
                    'is_encrypted' => false,
                ],
            );
        }

        return back()->with('success', 'Configurações atualizadas.');
    }
}
