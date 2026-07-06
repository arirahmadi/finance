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

// Temporary setup route for production (delete after running)
Route::get('/run-setup', function () {
    try {
        echo "Generating app key...<br>";
        \Illuminate\Support\Facades\Artisan::call('key:generate', ['--force' => true]);
        
        echo "Running migrate:fresh --seed...<br>";
        \Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true, '--seed' => true]);
        
        echo "Running storage:link...<br>";
        try {
            $target = storage_path('app/public');
            $link = public_path('storage');
            if (!file_exists($link)) {
                @symlink($target, $link);
                echo "Symlink created successfully!<br>";
            } else {
                echo "Symlink already exists.<br>";
            }
        } catch (\Throwable $e) {
            echo "Storage link note: " . $e->getMessage() . "<br>";
        }
        
        return "Setup successfully completed! Please delete this route from routes/web.php for security.";
    } catch (\Exception $e) {
        return "Setup failed: " . $e->getMessage();
    }
});
