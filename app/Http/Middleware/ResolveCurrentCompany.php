<?php

namespace App\Http\Middleware;

use App\Support\CompanyContext\CurrentCompanyResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCurrentCompany
{
    public function __construct(private readonly CurrentCompanyResolver $resolver) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $this->resolver->resolve();

        return $next($request);
    }
}
