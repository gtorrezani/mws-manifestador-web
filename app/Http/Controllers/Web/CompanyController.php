<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreCompanyRequest;
use App\Models\Agent;
use App\Models\Company;
use App\Models\CompanyCertificate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $companyIds = $user instanceof User
            ? $user->companies()->pluck('companies.id')
            : collect();

        return Inertia::render('Companies/Index', [
            'companies' => Company::query()
                ->whereIn('id', $companyIds)
                ->with(['agents:id,company_id,name,status,last_seen_at'])
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'agents' => Agent::query()
                ->whereIn('company_id', $companyIds)
                ->get(['id', 'name', 'status', 'company_id']),
            'certificates' => CompanyCertificate::query()
                ->whereIn('company_id', $companyIds)
                ->get(['id', 'company_id', 'name', 'type', 'status', 'valid_until']),
        ]);
    }

    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        $company = Company::query()->create($request->validated());

        if ($request->user() instanceof User) {
            $request->user()->companies()->syncWithoutDetaching([$company->id]);
        }

        return back()->with('success', 'Empresa cadastrada com sucesso.');
    }

    public function update(StoreCompanyRequest $request, Company $company): RedirectResponse
    {
        abort_unless(
            $request->user() instanceof User
            && $request->user()->companies()->whereKey($company->id)->exists(),
            404,
        );

        $company->update($request->validated());

        return back()->with('success', 'Empresa atualizada com sucesso.');
    }
}
