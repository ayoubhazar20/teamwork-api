<?php

use App\Http\Controllers\Auth\NewUserAuthController;
use App\Http\Controllers\ChartController;
use App\Livewire\CompanyDashboard;
use App\Livewire\UserList;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard/companies', CompanyDashboard::class)->name('companies.dashboard');
     Route::get('/dashboard/users', UserList::class)->name('users.dashboard');

});

Route::get('/chart', [ChartController::class, 'showChart']);
Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');
Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__ . '/auth.php';