<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\SettingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TeamworkController;
use App\Http\Controllers\TeamWorkControllerV2;

// Ensure the TeamworkController class exists in the specified namespace
// If it doesn't exist, create the file at app/Http/Controllers/TeamworkController.php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


Route::get('/teamwork', [TeamworkController::class, 'fetchProjectData']);
Route::get('/teamworkV2', [TeamWorkControllerV2::class, 'fetchProjectData']);


Route::get('/auth/hubspot', [AuthController::class, 'redirectToHubSpot']);
Route::get('/auth/hubspot/callback', [AuthController::class, 'handleHubSpotCallback']);
// In routes/web.php
Route::get('/users', [AuthController::class, 'fetchUsers']);
Route::post('/token/refresh', [AuthController::class, 'refreshAccessToken']);
Route::get('/settingsbutton', [SettingsController::class, 'settingsButton']);



Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});