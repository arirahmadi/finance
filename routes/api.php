<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/accounts', [AccountController::class, 'index']);
    
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);
    Route::put('/transactions/{id}', [TransactionController::class, 'update']);
    Route::delete('/transactions/{id}', [TransactionController::class, 'destroy']);
    Route::post('/transactions/{id}/mark-transferred', [TransactionController::class, 'markTransferred']);

    // Settlement (Advance / Uang Muka)
    Route::post('/settlements/advance', [TransactionController::class, 'storeAdvance']);
    Route::post('/settlements/{id}/settle', [TransactionController::class, 'settleAdvance']);

    // Cash Advance (Pinjaman Karyawan)
    Route::post('/cash-advances', [TransactionController::class, 'storeLoan']);
    Route::put('/cash-advances/{id}', [TransactionController::class, 'updateLoan']);
    Route::post('/cash-advances/{id}/repay', [TransactionController::class, 'storeRepayment']);

    // General Ledger (Buku Besar)
    Route::get('/ledger', [TransactionController::class, 'ledger']);

    // User & Roles (Owner-only)
    Route::get('/users', [AuthController::class, 'users']);
    Route::put('/users/{id}/role', [AuthController::class, 'updateUserRole']);
});
