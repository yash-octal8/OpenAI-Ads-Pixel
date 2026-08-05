<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| All routes here serve the embedded Shopify SPA. The React app handles
| its own client-side routing via react-router-dom, so every path that
| belongs to the SPA must return the same Blade view.
|
| All /api/* routes are defined in routes/api.php
|
*/

// ── Embedded SPA entry-point ──────────────────────────────────────────────────
Route::get('/', fn () => view('app'))
    ->middleware(['verify.shopify'])
    ->name('home');

// ── Catch-all: let React Router handle all other SPA paths ────────────────────
Route::get('/{any?}', fn () => view('app'))
    ->where('any', '.*')
    ->middleware(['verify.shopify']);
