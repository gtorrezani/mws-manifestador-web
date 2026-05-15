<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AgentCommand;
use App\Models\SefazRequest;
use App\Support\CompanyContext\CurrentCompanyContext;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    public function __invoke(CurrentCompanyContext $context): Response
    {
        $company = $context->company();

        return Inertia::render('History/Index', [
            'commands' => AgentCommand::query()
                ->forCompany($company)
                ->with(['company:id,legal_name', 'attempts'])
                ->latest('created_at')
                ->paginate(20)
                ->withQueryString(),
            'sefazRequests' => SefazRequest::query()
                ->forCompany($company)
                ->with(['responses'])
                ->latest('sent_at')
                ->limit(20)
                ->get(),
        ]);
    }
}
