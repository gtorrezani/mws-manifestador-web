<?php

namespace App\Models\Concerns;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToCompany
{
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForCompany(Builder $query, Company|int $company): Builder
    {
        $companyId = $company instanceof Company ? $company->id : $company;

        return $query->where($this->qualifyColumn('company_id'), $companyId);
    }
}
