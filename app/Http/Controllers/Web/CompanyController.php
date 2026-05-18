<?php

namespace App\Http\Controllers\Web;

use App\Enums\FiscalEnvironment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\StoreCompanyUserRequest;
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
            'companyUsers' => User::query()
                ->whereHas('companies', fn ($query) => $query->whereIn('companies.id', $companyIds))
                ->with(['companies' => fn ($query) => $query->whereIn('companies.id', $companyIds)->select('companies.id')])
                ->orderBy('name')
                ->get(['id', 'name', 'cpf', 'is_active', 'blocked_at', 'last_login_at']),
        ]);
    }

    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        $company = Company::query()->create($this->companyData($request));

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

        $company->update($this->companyData($request));

        return back()->with('success', 'Empresa atualizada com sucesso.');
    }

    public function storeUser(StoreCompanyUserRequest $request, Company $company): RedirectResponse
    {
        abort_unless(
            $request->user() instanceof User
            && $request->user()->companies()->whereKey($company->id)->exists(),
            404,
        );

        /** @var User $user */
        $user = User::query()->create([
            'name' => (string) $request->validated('name'),
            'cpf' => (string) $request->validated('cpf'),
            'password' => (string) $request->validated('password'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $user->companies()->syncWithoutDetaching([$company->id]);

        return back()->with('success', 'Usuário cadastrado e vinculado à empresa.');
    }

    /** @return array<string, mixed> */
    private function companyData(StoreCompanyRequest $request): array
    {
        return [
            ...$request->validated(),
            'fiscal_environment' => FiscalEnvironment::Production,
        ];
    }
}
