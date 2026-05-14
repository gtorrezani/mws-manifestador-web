<?php

use App\Http\Controllers\Web\AgentController;
use App\Http\Controllers\Web\CompanyController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\FiscalDocumentController;
use App\Http\Controllers\Web\HistoryController;
use App\Http\Controllers\Web\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

Route::resource('companies', CompanyController::class)->only(['index', 'store', 'update']);

Route::get('agents', [AgentController::class, 'index'])->name('agents.index');
Route::post('agents/activation-code', [AgentController::class, 'activate'])->name('agents.activate');
Route::post('agents/{agent}/revoke', [AgentController::class, 'revoke'])->name('agents.revoke');
Route::get('agents/{agent}/diagnostics', [AgentController::class, 'diagnostics'])->name('agents.diagnostics');

Route::get('fiscal-documents', [FiscalDocumentController::class, 'index'])->name('fiscal-documents.index');
Route::post('fiscal-documents/{document}/manifest', [FiscalDocumentController::class, 'manifest'])->name('fiscal-documents.manifest');
Route::post('fiscal-documents/{document}/download-xml', [FiscalDocumentController::class, 'downloadXml'])->name('fiscal-documents.download-xml');
Route::post('fiscal-documents/bulk', [FiscalDocumentController::class, 'bulk'])->name('fiscal-documents.bulk');

Route::get('history', HistoryController::class)->name('history.index');

Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
