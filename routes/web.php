<?php

use App\Http\Controllers\InstallationController;
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

// /shopify/auth
Route::prefix('shopify')->group(function () {
    Route::get('auth', [InstallationController::class, 'startInstallation']);
    Route::get('auth/redirect', [InstallationController::class, 'handleRedirect'])->name('app_install_redirect');
});
