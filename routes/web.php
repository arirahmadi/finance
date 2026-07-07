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

// Fallback route for viewing attachments (since PHP symlink() is disabled on Hostinger)
Route::get('/attachments/{path}', function ($path) {
    if (str_contains($path, '..')) {
        abort(403, 'Akses Ditolak.');
    }
    $filePath = storage_path('app/public/' . $path);
    if (!file_exists($filePath)) {
        abort(404);
    }
    return response()->file($filePath);
})->where('path', '.*')->name('web.attachments.show');

// Temporary setup route for production (delete after running)
Route::get('/run-setup', function () {
    try {
        echo "Generating app key...<br>";
        \Illuminate\Support\Facades\Artisan::call('key:generate', ['--force' => true]);
        
        echo "Running migrate:fresh --seed...<br>";
        \Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true, '--seed' => true]);
        
        echo "Creating storage directories...<br>";
        $receiptsDir = storage_path('app/public/receipts');
        if (!is_dir($receiptsDir)) {
            mkdir($receiptsDir, 0755, true);
            echo "Created receipts directory.<br>";
        } else {
            echo "Receipts directory already exists.<br>";
        }
        
        echo "Running storage:link...<br>";
        try {
            $target = storage_path('app/public');
            $link = public_path('storage');
            if (is_link($link)) {
                unlink($link);
            }
            if (!file_exists($link)) {
                @symlink($target, $link);
                echo "Symlink created successfully!<br>";
            } else {
                echo "Symlink/directory already exists.<br>";
            }
        } catch (\Throwable $e) {
            echo "Storage link note: " . $e->getMessage() . "<br>";
        }
        
        // Verify storage link
        echo "<br><strong>Verification:</strong><br>";
        echo "Storage path: " . storage_path('app/public') . "<br>";
        echo "Public storage: " . public_path('storage') . "<br>";
        echo "Symlink exists: " . (is_link(public_path('storage')) ? 'YES' : 'NO') . "<br>";
        echo "Receipts dir exists: " . (is_dir($receiptsDir) ? 'YES' : 'NO') . "<br>";
        echo "Storage writable: " . (is_writable(storage_path('app/public')) ? 'YES' : 'NO') . "<br>";
        
        return "<br><strong>Setup successfully completed!</strong> Please delete this route from routes/web.php for security.";
    } catch (\Exception $e) {
        return "Setup failed: " . $e->getMessage() . "<br>Trace: " . $e->getTraceAsString();
    }
});
