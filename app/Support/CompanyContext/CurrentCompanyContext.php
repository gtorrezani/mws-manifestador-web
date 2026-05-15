<?php

namespace App\Support\CompanyContext;

use App\Models\Company;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Session\Store;

class CurrentCompanyContext
{
    public const SESSION_KEY = 'current_company_id';

    private ?Company $company = null;

    /** @var EloquentCollection<int, Company>|null */
    private ?EloquentCollection $availableCompanies = null;

    public function __construct(private readonly Store $session) {}

    public function resolve(): void
    {
        $availableCompanies = $this->availableCompanies();
        $selectedCompanyId = $this->session->get(self::SESSION_KEY);

        if (is_numeric($selectedCompanyId)) {
            $selectedCompany = $availableCompanies->firstWhere('id', (int) $selectedCompanyId);

            if ($selectedCompany instanceof Company) {
                $this->company = $selectedCompany;

                return;
            }
        }

        $this->clear();

        $firstCompany = $availableCompanies->first();
        if ($firstCompany instanceof Company) {
            $this->setCompany($firstCompany);
        }
    }

    public function company(): Company
    {
        if (! $this->company instanceof Company) {
            throw new CompanyContextException('No current company is selected.');
        }

        return $this->company;
    }

    public function companyId(): int
    {
        return $this->company()->id;
    }

    public function hasCompany(): bool
    {
        return $this->company instanceof Company;
    }

    /** @return EloquentCollection<int, Company> */
    public function availableCompanies(): EloquentCollection
    {
        if ($this->availableCompanies instanceof EloquentCollection) {
            return $this->availableCompanies;
        }

        // TODO auth/rbac: restrict available companies by authenticated user and tenant membership.
        $this->availableCompanies = Company::query()
            ->where('is_active', true)
            ->orderBy('legal_name')
            ->get(['id', 'uuid', 'tenant_id', 'legal_name', 'trade_name', 'cnpj', 'uf', 'fiscal_environment', 'is_active']);

        return $this->availableCompanies;
    }

    public function setCompany(Company $company): void
    {
        if (! $company->is_active) {
            throw new CompanyContextException('The selected company is inactive.');
        }

        // TODO auth/rbac: verify the authenticated user may operate this company.
        $this->company = $company;
        $this->session->put(self::SESSION_KEY, $company->id);
    }

    public function clear(): void
    {
        $this->company = null;
        $this->session->forget(self::SESSION_KEY);
    }
}
