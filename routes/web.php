<?php

use App\Http\Controllers\Web\WebController;
use Illuminate\Support\Facades\Route;

Route::get('/run-migration-temp', function() {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        return "Migration Success: <pre>" . \Illuminate\Support\Facades\Artisan::output() . "</pre>";
    } catch (\Exception $e) {
        return "Migration Failed: " . $e->getMessage();
    }
});

Route::get('/force-add-column', function() {
    try {
        try {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE transactions ADD COLUMN is_transferred TINYINT(1) NOT NULL DEFAULT 0 AFTER transfer_proof_path");
        } catch (\Exception $e) {}

        try {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE transactions ADD COLUMN amount DECIMAL(15, 2) NULL AFTER is_transferred");
        } catch (\Exception $e) {}

        try {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE transactions ADD COLUMN transferred_amount DECIMAL(15, 2) NULL AFTER amount");
        } catch (\Exception $e) {}

        return "Success: Database columns (is_transferred, amount, transferred_amount) updated successfully!";
    } catch (\Exception $e) {
        return "Error: " . $e->getMessage();
    }
});

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
    Route::post('/transactions/{id}/transfer-reimburse', [WebController::class, 'transferReimbursement'])->name('web.transactions.transferReimbursement');
    Route::get('/export/csv', [WebController::class, 'exportCsv'])->name('web.export.csv');

    // Users & Roles Management
    Route::get('/settings/users', [WebController::class, 'indexUsers'])->name('web.users.index');
    Route::post('/settings/users', [WebController::class, 'storeUser'])->name('web.users.store');
    Route::put('/settings/users/{id}', [WebController::class, 'updateUser'])->name('web.users.update');

    // Settlement & Advance Payments
    Route::get('/settlements', [WebController::class, 'indexSettlements'])->name('web.settlements.index');
    Route::post('/settlements/advance', [WebController::class, 'storeAdvance'])->name('web.settlements.storeAdvance');
    Route::post('/settlements/{id}/settle', [WebController::class, 'settleAdvance'])->name('web.settlements.settle');
    Route::put('/settlements/{id}', [WebController::class, 'editSettlement'])->name('web.settlements.update');
    Route::delete('/settlements/bulk', [WebController::class, 'bulkDeleteSettlement'])->name('web.settlements.bulkDestroy');
    Route::delete('/settlements/{id}', [WebController::class, 'deleteSettlement'])->name('web.settlements.destroy');

    // Cash Advances (Pinjaman Karyawan)
    Route::post('/cash-advances', [WebController::class, 'storeLoan'])->name('web.cash_advances.store');
    Route::put('/cash-advances/{id}', [WebController::class, 'editLoan'])->name('web.cash_advances.update');
    Route::delete('/cash-advances/{id}', [WebController::class, 'deleteLoan'])->name('web.cash_advances.destroy');
    Route::post('/cash-advances/{id}/repay', [WebController::class, 'storeRepayment'])->name('web.cash_advances.repay');
    Route::delete('/cash-advances/repay/{repayment_id}', [WebController::class, 'deleteRepayment'])->name('web.cash_advances.destroyRepayment');
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

Route::get('/run-migrate', function () {
    try {
        echo "Running migrate...<br>";
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        echo "Running AccountSeeder...<br>";
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'AccountSeeder', '--force' => true]);
        return "Migration and Account Seeding completed successfully!";
    } catch (\Exception $e) {
        return "Migration failed: " . $e->getMessage() . "<br>Trace: " . $e->getTraceAsString();
    }
});

Route::get('/check-deploy', function () {
    $viewPath = resource_path('views/dashboard.blade.php');
    $fileExists = file_exists($viewPath);
    $fileSize = $fileExists ? filesize($viewPath) : 0;
    $lastModified = $fileExists ? date('Y-m-d H:i:s', filemtime($viewPath)) : 'N/A';
    
    $hasReimburse = $fileExists ? (strpos(file_get_contents($viewPath), 'is_reimbursement') !== false) : false;
    $hasCashAdvance = $fileExists ? (strpos(file_get_contents($viewPath), 'cash_advance') !== false) : false;
    $hasReimburseModal = $fileExists ? (strpos(file_get_contents($viewPath), 'reimburseTransferModal') !== false) : false;
    
    // Check compiled views
    $compiledDir = storage_path('framework/views');
    $compiledFiles = glob($compiledDir . '/*.php') ?: [];
    $compiledCount = count($compiledFiles);
    
    // Check if any compiled view contains old content (no reimbursement)
    $compiledHasReimburse = false;
    $compiledFileSizes = [];
    foreach ($compiledFiles as $cf) {
        $content = file_get_contents($cf);
        if (strpos($content, 'is_reimbursement') !== false) {
            $compiledHasReimburse = true;
        }
        $compiledFileSizes[] = basename($cf) . ': ' . filesize($cf) . 'B, mod=' . date('Y-m-d H:i:s', filemtime($cf));
    }
    
    // Check database columns
    try {
        $cols = \Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM transactions");
        $colNames = array_map(fn($c) => $c->Field, $cols);
        $hasReimburseCol = in_array('is_reimbursement', $colNames);
        $hasTransferProof = in_array('transfer_proof_path', $colNames);
    } catch (\Exception $e) {
        $hasReimburseCol = false;
        $hasTransferProof = false;
    }
    
    // OPcache status
    $opcacheEnabled = function_exists('opcache_get_status') ? (opcache_get_status(false)['opcache_enabled'] ?? false) : false;
    $opcacheScripts = 0;
    if ($opcacheEnabled && function_exists('opcache_get_status')) {
        $status = opcache_get_status(true);
        $opcacheScripts = count($status['scripts'] ?? []);
    }
    
    // Check WebController.php for reimbursement
    $controllerPath = app_path('Http/Controllers/Web/WebController.php');
    $controllerHasReimburse = file_exists($controllerPath) ? (strpos(file_get_contents($controllerPath), 'is_reimbursement') !== false) : false;
    $controllerSize = file_exists($controllerPath) ? filesize($controllerPath) : 0;
    $controllerMod = file_exists($controllerPath) ? date('Y-m-d H:i:s', filemtime($controllerPath)) : 'N/A';
    
    return response()->json([
        'blade_view' => [
            'exists' => $fileExists,
            'size' => $fileSize,
            'last_modified' => $lastModified,
            'has_is_reimbursement' => $hasReimburse,
            'has_cash_advance' => $hasCashAdvance,
            'has_reimburse_modal' => $hasReimburseModal,
        ],
        'compiled_views' => [
            'count' => $compiledCount,
            'has_reimbursement' => $compiledHasReimburse,
            'files' => $compiledFileSizes,
        ],
        'controller' => [
            'has_reimbursement' => $controllerHasReimburse,
            'size' => $controllerSize,
            'last_modified' => $controllerMod,
        ],
        'database' => [
            'has_is_reimbursement_col' => $hasReimburseCol,
            'has_transfer_proof_col' => $hasTransferProof,
        ],
        'opcache' => [
            'enabled' => $opcacheEnabled,
            'cached_scripts' => $opcacheScripts,
        ],
        'server' => [
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'server_time' => date('Y-m-d H:i:s'),
        ],
    ]);
});



Route::get('/clear-cache', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('view:clear');
        echo "View cache cleared.<br>";
        \Illuminate\Support\Facades\Artisan::call('config:clear');
        echo "Config cache cleared.<br>";
        \Illuminate\Support\Facades\Artisan::call('route:clear');
        echo "Route cache cleared.<br>";
        \Illuminate\Support\Facades\Artisan::call('cache:clear');
        echo "Application cache cleared.<br>";
        if (function_exists('opcache_reset')) {
            opcache_reset();
            echo "OPcache cleared.<br>";
        }
        return "All caches cleared successfully!";
    } catch (\Exception $e) {
        return "Cache clear failed: " . $e->getMessage();
    }
});

