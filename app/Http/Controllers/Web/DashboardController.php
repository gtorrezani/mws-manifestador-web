<?php

namespace App\Http\Controllers\Web;

use App\Enums\AgentStatus;
use App\Enums\ManifestationRecordStatus;
use App\Enums\ManifestationStatus;
use App\Enums\XmlDownloadStatus;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentCommand;
use App\Models\CompanyCertificate;
use App\Models\FiscalDocument;
use App\Models\RecipientManifestation;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Dashboard/Index', [
            'metrics' => [
                'documentsFound' => FiscalDocument::query()->count(),
                'xmlDownloaded' => FiscalDocument::query()->where('xml_download_status', XmlDownloadStatus::Available)->count(),
                'pendingAcknowledgement' => FiscalDocument::query()->where('manifestation_status', ManifestationStatus::NoManifestation)->count(),
                'pendingConclusiveManifestation' => FiscalDocument::query()->whereIn('manifestation_status', [
                    ManifestationStatus::PendingFinalManifestation,
                    ManifestationStatus::Rejected,
                    ManifestationStatus::Failed,
                ])->count(),
                'manifestationErrors' => RecipientManifestation::query()->whereIn('status', [
                    ManifestationRecordStatus::Rejected,
                    ManifestationRecordStatus::Failed,
                ])->count(),
                'agentsOnline' => Agent::query()->where('status', AgentStatus::Online)->count(),
                'agentsOffline' => Agent::query()->where('status', AgentStatus::Offline)->count(),
                'expiringCertificates' => CompanyCertificate::query()->whereBetween('valid_until', [now(), now()->addDays(30)])->count(),
            ],
            'latestSyncs' => AgentCommand::query()
                ->with(['company:id,legal_name'])
                ->latest('created_at')
                ->limit(8)
                ->get(['id', 'uuid', 'company_id', 'type', 'status', 'created_at', 'completed_at', 'failed_at']),
        ]);
    }
}
