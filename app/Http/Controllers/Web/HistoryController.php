<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AgentCommand;
use App\Models\SefazRequest;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('History/Index', [
            'commands' => AgentCommand::query()
                ->with(['company:id,legal_name', 'attempts'])
                ->latest('created_at')
                ->paginate(20)
                ->withQueryString(),
            'sefazRequests' => SefazRequest::query()
                ->with(['responses'])
                ->latest('sent_at')
                ->limit(20)
                ->get(),
        ]);
    }
}
