<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountDeletionController;

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

Route::get('/', function () {
    return view('welcome');
});

// Routes de suppression de compte
Route::get('/account/delete', [AccountDeletionController::class, 'showDeletionPage'])->name('account.delete');
Route::post('/account/delete', [AccountDeletionController::class, 'deleteAccount'])->name('account.delete.confirm');
