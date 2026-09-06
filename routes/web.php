<?php

use App\Http\Controllers\LocationSwitchController;
use App\Http\Controllers\OpportunityStageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/locations/switch/{location}', [LocationSwitchController::class, 'switch'])
    ->middleware('auth')
    ->name('locations.switch');

Route::patch('/api/opportunities/{opportunity}/stage', [OpportunityStageController::class, 'update'])
    ->middleware('auth')
    ->name('opportunities.stage.update');
