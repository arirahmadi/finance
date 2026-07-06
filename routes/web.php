<?php

use App\Http\Controllers\Web\WebController;
use Illuminate\Support\Facades\Route;

// Auth Routes
Route::get('/login', [WebController::class, 'showLogin'])->name('login');
Route::post('/login', [WebController::class, 'login']);
Route::post('/logout', [WebController::class, 'logout'])->name('logout');

// Dashboard Routes
Route::middleware('auth')->group(function () {
    Route::get('/', [WebController::class, 'index'])->name('dashboard');
    Route::post('/transactions', [WebController::class, 'storeTransaction'])->name('web.transactions.store');
    Route::delete('/transactions/bulk', [WebController::class, 'bulkDeleteTransaction'])->name('web.transactions.bulkDestroy');
    Route::put('/transactions/{id}', [WebController::class, 'editTransaction'])->name('web.transactions.update');
    Route::delete('/transactions/{id}', [WebController::class, 'deleteTransaction'])->name('web.transactions.destroy');
    Route::get('/export/csv', [WebController::class, 'exportCsv'])->name('web.export.csv');

    // Users & Roles Management
    Route::get('/settings/users', [WebController::class, 'indexUsers'])->name('web.users.index');
    Route::post('/settings/users', [WebController::class, 'storeUser'])->name('web.users.store');
    Route::put('/settings/users/{id}', [WebController::class, 'updateUser'])->name('web.users.update');

    // Settlement & Advance Payments
    Route::get('/settlements', [WebController::class, 'indexSettlements'])->name('web.settlements.index');
    Route::post('/settlements/advance', [WebController::class, 'storeAdvance'])->name('web.settlements.storeAdvance');
    Route::post('/settlements/{id}/settle', [WebController::class, 'settleAdvance'])->name('web.settlements.settle');
});
