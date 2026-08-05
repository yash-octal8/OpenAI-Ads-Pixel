<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\ChargeController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\PixelController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ── Protected routes (require valid Shopify session) ─────────────────────────
Route::middleware(['verify.shopify'])->group(function () {

    // Plans & Billing
    Route::get('/plan', [PlanController::class, 'index']);
    Route::post('/plans/choose-plan/free', [PlanController::class, 'chooseFreePlan']);
    Route::get('/billing/{plan?}', [ChargeController::class, 'index']);

    // Settings
    Route::get('/settings', [SettingController::class, 'index']);
    Route::post('/settings', [SettingController::class, 'store']);

    // Pixel Helper & Tracking
    Route::get('/pixel', [PixelController::class, 'index']);
    Route::post('/pixel/settings', [PixelController::class, 'saveSettings']);
    Route::post('/pixel/track', [PixelController::class, 'trackEvent']);
    Route::post('/pixel/clear', [PixelController::class, 'clearEvents']);

    // ── Fallback for any unimplemented protected API route ──────────────────
    Route::fallback(function () {
        return response()->json([]);
    });
});

// ── Public Storefront / CORS-enabled endpoints ───────────────────────────────
Route::options('/events', [PixelController::class, 'options']);
Route::post('/events', [PixelController::class, 'publicTrackEvent']);
