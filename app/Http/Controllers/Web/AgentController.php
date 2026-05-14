<?php

namespace App\Http\Controllers\Web;

use App\Actions\Agent\CreateAgentActivationCodeAction;
use App\Enums\AgentStatus;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentActivation;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Agents/Index', [
            'agents' => Agent::query()
                ->with(['company:id,legal_name,cnpj'])
                ->latest('last_seen_at')
                ->paginate(15)
                ->withQueryString(),
            'companies' => Company::query()->where('is_active', true)->get(['id', 'legal_name', 'cnpj']),
            'latestActivations' => AgentActivation::query()
                ->with(['company:id,legal_name,cnpj'])
                ->latest('created_at')
                ->limit(5)
                ->get(),
        ]);
    }

    public function activate(Request $request, CreateAgentActivationCodeAction $action): RedirectResponse
    {
        $data = $request->validate(['company_id' => ['required', 'integer', 'exists:companies,id']]);
        /** @var Company $company */
        $company = Company::query()->findOrFail((int) $data['company_id']);
        $result = $action->execute($company, $this->authenticatedUserId($request));

        return back()->with('activationCode', [
            'code' => $result['code'],
            'expires_at' => $result['activation']->expires_at,
        ]);
    }

    public function revoke(Agent $agent): RedirectResponse
    {
        $agent->forceFill([
            'status' => AgentStatus::Revoked,
            'revoked_at' => now(),
        ])->save();

        return back()->with('success', 'Agente revogado.');
    }

    public function diagnostics(Agent $agent): Response
    {
        return Inertia::render('Agents/Diagnostics', [
            'agent' => $agent->load('company:id,legal_name,cnpj'),
            'diagnostics' => AuditLog::query()
                ->where('agent_id', $agent->id)
                ->where('event', 'like', 'agent.diagnostics.%')
                ->latest('occurred_at')
                ->paginate(20),
        ]);
    }

    private function authenticatedUserId(Request $request): ?int
    {
        $user = $request->user();

        return $user instanceof User ? $user->id : null;
    }
}
