<?php

use App\Http\Controllers\OpportunityStageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth')->group(function () {
    Route::patch('/opportunities/{opportunity}/stage', [OpportunityStageController::class, 'update']);
});
