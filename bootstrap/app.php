<?php

use App\Http\Middleware\EnsureCurrentCompanySelected;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveCurrentCompany;
use App\Support\CompanyContext\CurrentCompanyContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            ResolveCurrentCompany::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'company.selected' => EnsureCurrentCompanySelected::class,
        ]);
    })
    ->registered(function (Application $app): void {
        $app->scoped(CurrentCompanyContext::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
