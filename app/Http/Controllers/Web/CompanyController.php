<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreCompanyRequest;
use App\Models\Agent;
use App\Models\Company;
use App\Models\CompanyCertificate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Companies/Index', [
            'companies' => Company::query()
                ->with(['agents:id,company_id,name,status,last_seen_at'])
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'agents' => Agent::query()->get(['id', 'name', 'status', 'company_id']),
            'certificates' => CompanyCertificate::query()->get(['id', 'company_id', 'name', 'type', 'status', 'valid_until']),
        ]);
    }

    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        Company::query()->create($request->validated());

        return back()->with('success', 'Empresa cadastrada com sucesso.');
    }

    public function update(StoreCompanyRequest $request, Company $company): RedirectResponse
    {
        $company->update($request->validated());

        return back()->with('success', 'Empresa atualizada com sucesso.');
    }
}
