<?php

use App\Http\Controllers\LocationSwitchController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/locations/switch/{location}', [LocationSwitchController::class, 'switch'])
    ->middleware('auth')
    ->name('locations.switch');
