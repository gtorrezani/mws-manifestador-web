<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSystemSettingsRequest;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Settings/Edit', [
            'settings' => SystemSetting::query()->whereNull('company_id')->get()->keyBy('key'),
        ]);
    }

    public function update(UpdateSystemSettingsRequest $request): RedirectResponse
    {
        foreach ($request->validated() as $key => $value) {
            SystemSetting::query()->updateOrCreate(
                ['tenant_id' => null, 'company_id' => null, 'key' => $key],
                [
                    'value' => ['value' => $value],
                    'is_encrypted' => false,
                ],
            );
        }

        return back()->with('success', 'Configurações atualizadas.');
    }
}
