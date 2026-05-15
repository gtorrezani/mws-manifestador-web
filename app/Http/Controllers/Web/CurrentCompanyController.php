<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CurrentCompany\UpdateCurrentCompanyRequest;
use App\Support\CompanyContext\CompanyContextException;
use App\Support\CompanyContext\CurrentCompanyResolver;
use Illuminate\Http\RedirectResponse;

class CurrentCompanyController extends Controller
{
    public function update(UpdateCurrentCompanyRequest $request, CurrentCompanyResolver $resolver): RedirectResponse
    {
        $company = $resolver->availableCompanies()
            ->firstWhere('id', (int) $request->validated('company_id'));

        if ($company === null) {
            throw new CompanyContextException('The selected company is not available to the authenticated user.');
        }

        $resolver->setCompany($company);

        return redirect()
            ->back()
            ->with('success', 'Empresa selecionada alterada.');
    }
}
