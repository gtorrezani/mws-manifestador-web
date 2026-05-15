<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CurrentCompany\UpdateCurrentCompanyRequest;
use App\Models\Company;
use App\Support\CompanyContext\CurrentCompanyResolver;
use Illuminate\Http\RedirectResponse;

class CurrentCompanyController extends Controller
{
    public function update(UpdateCurrentCompanyRequest $request, CurrentCompanyResolver $resolver): RedirectResponse
    {
        /** @var Company $company */
        $company = Company::query()
            ->where('is_active', true)
            ->findOrFail((int) $request->validated('company_id'));

        $resolver->setCompany($company);

        return redirect()
            ->back()
            ->with('success', 'Empresa selecionada alterada.');
    }
}
