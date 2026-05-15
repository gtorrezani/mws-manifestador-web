<?php

namespace App\Support\CompanyContext;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;

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

        $user = Auth::user();

        if (! $user instanceof User) {
            $this->availableCompanies = new EloquentCollection;

            return $this->availableCompanies;
        }

        $this->availableCompanies = $user->companies()
            ->where('companies.is_active', true)
            ->orderBy('companies.legal_name')
            ->get([
                'companies.id',
                'companies.uuid',
                'companies.tenant_id',
                'companies.legal_name',
                'companies.trade_name',
                'companies.cnpj',
                'companies.uf',
                'companies.fiscal_environment',
                'companies.is_active',
            ]);

        return $this->availableCompanies;
    }

    public function setCompany(Company $company): void
    {
        if (! $company->is_active) {
            throw new CompanyContextException('The selected company is inactive.');
        }

        if (! $this->availableCompanies()->contains('id', $company->id)) {
            throw new CompanyContextException('The authenticated user cannot access the selected company.');
        }

        $this->company = $company;
        $this->session->put(self::SESSION_KEY, $company->id);
    }

    public function clear(): void
    {
        $this->company = null;
        $this->session->forget(self::SESSION_KEY);
    }
}
