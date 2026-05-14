<?php

namespace App\Http\Middleware;

use App\Services\Agent\AgentHmacAuthenticator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAgentHmac
{
    public function __construct(
        private readonly AgentHmacAuthenticator $authenticator,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $agent = $this->authenticator->authenticate($request);
        $request->attributes->set('agent', $agent);

        return $next($request);
    }
}
