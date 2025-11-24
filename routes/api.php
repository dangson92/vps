<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\VpsController;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\SslController;
use App\Http\Controllers\DnsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\FolderController;

// API routes (prefixed with 'api' via RouteServiceProvider)

// Auth
Route::post('login', [AuthController::class, 'login']);

Route::middleware('admin.token')->group(function () {
    // VPS Management
    Route::apiResource('vps', VpsController::class);

    // Website Management
    Route::apiResource('websites', WebsiteController::class);
    Route::post('websites/{website}/deploy', [WebsiteController::class, 'deploy']);
    Route::post('websites/{website}/deactivate', [WebsiteController::class, 'deactivate']);
    Route::post('websites/{website}/redeploy-pages', [WebsiteController::class, 'redeployPages']);
    Route::post('websites/{website}/redeploy-template-assets', [WebsiteController::class, 'redeployTemplateAssets']);
    Route::post('websites/{website}/update-pages-template', [WebsiteController::class, 'updatePagesTemplate']);
    Route::post('websites/{website}/ssl', [SslController::class, 'generate']);

    // Page Management
    Route::apiResource('websites.pages', PageController::class)->shallow();

    // Folder Management
    Route::get('websites/{website}/folders', [FolderController::class, 'index']);
    Route::post('websites/{website}/folders', [FolderController::class, 'store']);
    Route::put('websites/{website}/folders/{folder}', [FolderController::class, 'update']);
    Route::delete('websites/{website}/folders/{folder}', [FolderController::class, 'destroy']);

    // DNS Management
    Route::post('websites/{website}/dns', [DnsController::class, 'createRecord']);
    Route::delete('dns/{recordId}', [DnsController::class, 'deleteRecord']);

    // Monitoring
    Route::get('monitoring/uptime', [MonitoringController::class, 'uptime']);
    Route::get('monitoring/stats/{website}', [MonitoringController::class, 'websiteStats']);

    // Settings
    Route::get('settings', [SettingsController::class, 'index']);
    Route::put('settings', [SettingsController::class, 'update']);
});

// Worker API
Route::prefix('worker')->middleware('worker.auth')->group(function () {
    Route::post('command', [VpsController::class, 'executeCommand']);
    Route::post('status', [VpsController::class, 'updateStatus']);
});