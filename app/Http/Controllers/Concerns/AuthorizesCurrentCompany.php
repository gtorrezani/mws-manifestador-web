<?php

namespace App\Http\Controllers\Concerns;

use App\Support\CompanyContext\CurrentCompanyContext;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesCurrentCompany
{
    protected function abortUnlessBelongsToCurrentCompany(Model $model, CurrentCompanyContext $context): void
    {
        $companyId = $model->getAttribute('company_id');

        if (! is_numeric($companyId) || (int) $companyId !== $context->companyId()) {
            abort(404);
        }
    }
}
