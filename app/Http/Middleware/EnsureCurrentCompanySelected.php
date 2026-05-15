<?php

namespace App\Http\Middleware;

use App\Support\CompanyContext\CurrentCompanyContext;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentCompanySelected
{
    public function __construct(private readonly CurrentCompanyContext $context) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        if ($this->context->hasCompany()) {
            return $next($request);
        }

        return redirect()
            ->route('companies.index')
            ->with('success', 'Cadastre ou ative uma empresa antes de acessar as telas operacionais.');
    }
}
