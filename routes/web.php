<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommandeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FlaconController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProduitController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\UtilisateurController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('produits', ProduitController::class)->except(['show', 'create']);
    Route::resource('flacons', FlaconController::class)->except(['show', 'create']);

    Route::get('/stock/entrees', [StockController::class, 'entrees'])->name('stock.entrees');
    Route::post('/stock/entrees', [StockController::class, 'storeEntree'])->name('stock.entrees.store');
    Route::get('/stock/sorties', [StockController::class, 'sorties'])->name('stock.sorties');

    Route::get('/commandes', [CommandeController::class, 'index'])->name('commandes.index');
    Route::post('/commandes', [CommandeController::class, 'store'])->name('commandes.store');

    Route::resource('utilisateurs', UtilisateurController::class)->except(['show']);
});

