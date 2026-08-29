<?php

use App\Http\Controllers\Api\LeadController;
use Illuminate\Support\Facades\Route;

Route::post('/leads', [LeadController::class, 'store'])
    ->middleware('throttle:leads')
    ->name('api.leads.store');
