<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Web\AgentController;
use App\Http\Controllers\Web\CertificateController;
use App\Http\Controllers\Web\CompanyController;
use App\Http\Controllers\Web\CurrentCompanyController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\FiscalDocumentController;
use App\Http\Controllers\Web\HistoryController;
use App\Http\Controllers\Web\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::post('current-company', [CurrentCompanyController::class, 'update'])->name('current-company.update');

    Route::resource('companies', CompanyController::class)->only(['index', 'store', 'update']);

    Route::middleware('company.selected')->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('agents', [AgentController::class, 'index'])->name('agents.index');
        Route::get('agents/installer/download', [AgentController::class, 'downloadInstaller'])->name('agents.installer.download');
        Route::post('agents/activation-code', [AgentController::class, 'activate'])->name('agents.activate');
        Route::post('agents/{agent}/revoke', [AgentController::class, 'revoke'])->name('agents.revoke');
        Route::post('agents/{agent}/diagnostics/request', [AgentController::class, 'requestDiagnostics'])->name('agents.diagnostics.request');
        Route::get('agents/{agent}/diagnostics', [AgentController::class, 'diagnostics'])->name('agents.diagnostics');

        Route::get('certificates', [CertificateController::class, 'index'])->name('certificates.index');
        Route::post('certificates/agent/{agent}/list', [CertificateController::class, 'requestAgentInventory'])->name('certificates.agent.list');
        Route::post('certificates/agent-certificate/{certificate}/test', [CertificateController::class, 'testAgentCertificate'])->name('certificates.agent-certificate.test');
        Route::post('certificates/a3/link', [CertificateController::class, 'linkA3'])->name('certificates.a3.link');
        Route::post('certificates/a1', [CertificateController::class, 'storeA1'])->name('certificates.a1.store');
        Route::post('certificates/{certificate}/test', [CertificateController::class, 'test'])->name('certificates.test');
        Route::post('certificates/{certificate}/test-sefaz-connectivity', [CertificateController::class, 'testSefazConnectivity'])->name('certificates.test-sefaz-connectivity');

        Route::get('fiscal-documents', [FiscalDocumentController::class, 'index'])->name('fiscal-documents.index');
        Route::post('fiscal-documents/sync', [FiscalDocumentController::class, 'sync'])->name('fiscal-documents.sync');
        Route::post('fiscal-documents/{document}/manifest', [FiscalDocumentController::class, 'manifest'])->name('fiscal-documents.manifest');
        Route::post('fiscal-documents/{document}/download-xml', [FiscalDocumentController::class, 'downloadXml'])->name('fiscal-documents.download-xml');
        Route::post('fiscal-documents/bulk', [FiscalDocumentController::class, 'bulk'])->name('fiscal-documents.bulk');

        Route::get('history', HistoryController::class)->name('history.index');

        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    });
});
