<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSystemSettingsRequest;
use App\Models\SystemSetting;
use App\Services\Sefaz\DistributionStateService;
use App\Support\CompanyContext\CurrentCompanyContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(CurrentCompanyContext $context, DistributionStateService $distributionStateService): Response
    {
        $company = $context->company();

        return Inertia::render('Settings/Edit', [
            'settings' => SystemSetting::query()
                ->forCompany($company)
                ->get()
                ->keyBy('key'),
            'fiscalState' => $distributionStateService->stateForCompany($company),
        ]);
    }

    public function update(
        UpdateSystemSettingsRequest $request,
        CurrentCompanyContext $context,
        DistributionStateService $distributionStateService,
    ): RedirectResponse {
        $company = $context->company();
        $validated = $request->validated();
        $lastNsu = (string) $validated['last_nsu'];

        unset($validated['last_nsu']);

        foreach ($validated as $key => $value) {
            SystemSetting::query()->updateOrCreate(
                ['tenant_id' => $company->tenant_id, 'company_id' => $company->id, 'key' => $key],
                [
                    'value' => ['value' => $value],
                    'is_encrypted' => false,
                ],
            );
        }

        $distributionStateService->stateForCompany($company)
            ->forceFill(['last_nsu' => $lastNsu])
            ->save();

        return back()->with('success', 'Configurações atualizadas.');
    }
}
