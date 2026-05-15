<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\CompanyContext\CurrentCompanyContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(private readonly CurrentCompanyContext $context) {}

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'currentCompany' => fn (): ?array => $this->context->hasCompany()
                ? $this->serializeCompany($this->context->company())
                : null,
            'availableCompanies' => fn (): array => $this->context->availableCompanies()
                ->map(fn (Company $company): array => $this->serializeCompany($company))
                ->values()
                ->all(),
            'companyContext' => fn (): array => [
                'has_company' => $this->context->hasCompany(),
                'can_switch_company' => $this->context->availableCompanies()->count() > 1,
            ],
        ];
    }

    /** @return array{id: int, uuid: string, tenant_id: int, legal_name: string, trade_name: string|null, cnpj: string, uf: string, fiscal_environment: string, is_active: bool} */
    private function serializeCompany(Company $company): array
    {
        return [
            'id' => $company->id,
            'uuid' => $company->uuid,
            'tenant_id' => $company->tenant_id,
            'legal_name' => $company->legal_name,
            'trade_name' => $company->trade_name,
            'cnpj' => $company->cnpj,
            'uf' => $company->uf,
            'fiscal_environment' => $company->fiscal_environment->value,
            'is_active' => $company->is_active,
        ];
    }
}
