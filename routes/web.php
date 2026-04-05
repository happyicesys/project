<?php

use App\Http\Controllers\DashboardPageController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard page — loads with server-side props
    Route::get('dashboard', [DashboardPageController::class, 'show'])->name('dashboard');

    // JSON endpoint for client-side auto-refresh (30s polling)
    Route::get('dashboard/overview', [DashboardPageController::class, 'overview'])->name('dashboard.overview');
});

require __DIR__.'/settings.php';
