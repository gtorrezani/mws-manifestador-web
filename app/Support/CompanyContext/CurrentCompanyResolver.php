<?php

namespace App\Support\CompanyContext;

use App\Models\Company;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class CurrentCompanyResolver
{
    public function __construct(private readonly CurrentCompanyContext $context) {}

    public function resolve(): CurrentCompanyContext
    {
        $this->context->resolve();

        return $this->context;
    }

    public function setCompany(Company $company): void
    {
        $this->context->setCompany($company);
    }

    public function clear(): void
    {
        $this->context->clear();
    }

    /** @return EloquentCollection<int, Company> */
    public function availableCompanies(): EloquentCollection
    {
        return $this->context->availableCompanies();
    }
}
