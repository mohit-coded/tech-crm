<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FunnelController;
use App\Http\Controllers\FunnelPublicController;
use App\Http\Controllers\LocationSwitchController;
use App\Http\Controllers\OpportunityBoardController;
use App\Http\Controllers\OpportunityStageController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/locations/switch/{location}', [LocationSwitchController::class, 'switch'])
        ->name('locations.switch');

    Route::resource('contacts', ContactController::class)->except('show');

    Route::resource('funnels', FunnelController::class)->except('show');

    Route::get('/opportunities', [OpportunityBoardController::class, 'index'])
        ->name('opportunities.index');

    // Same-origin fetch() from board.blade.php, session-authenticated —
    // belongs here, not in api.php (see CLAUDE.md routing convention).
    Route::patch('/api/opportunities/{opportunity}/stage', [OpportunityStageController::class, 'update']);
});

// Public funnel pages — anonymous visitors, deliberately outside the
// 'auth' group. FunnelPublicController resolves tenancy explicitly via
// the funnel's location_id rather than relying on BelongsToLocation's
// scope, which is inert with no authenticated user.
Route::get('/f/{slug}', [FunnelPublicController::class, 'show'])->name('funnels.public.show');
Route::post('/f/{slug}/submit', [FunnelPublicController::class, 'store'])->name('funnels.public.submit');

require __DIR__.'/auth.php';
