<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\Products\ProductController;
use App\Http\Controllers\Purchasing\PurchasingController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Settings\BackupController;
use App\Http\Controllers\Settings\SettingsController;
use Illuminate\Support\Facades\Route;

// Auth & One-Time Owner Setup
Route::middleware('guest')->group(function () {
    Route::get('/setup', [AuthController::class, 'showSetup'])->name('setup.show');
    Route::post('/setup', [AuthController::class, 'storeSetup'])->middleware('throttle:login')->name('setup.store');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.attempt');
});

Route::middleware(['auth', 'branch.scope'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/branch/switch', [AuthController::class, 'switchBranch'])->name('branch.switch');

    Route::get('/', function () {
        return auth()->user()->isGudang()
            ? redirect()->route('inventory.index')
            : redirect()->route('pos.index');
    })->name('home');

    // Modul 3.4: Kasir (POS)
    Route::middleware('module:pos')->prefix('pos')->name('pos.')->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('index');
        Route::post('/checkout', [PosController::class, 'checkout'])->middleware('open.shift')->name('checkout');
        Route::post('/hold', [PosController::class, 'hold'])->name('hold');
        Route::delete('/hold/{heldSale}', [PosController::class, 'deleteHold'])->name('hold.delete');
        Route::post('/void', [PosController::class, 'void'])->middleware('throttle:admin-pin')->name('void');
        Route::post('/refund', [PosController::class, 'refund'])->middleware('throttle:admin-pin')->name('refund');
        Route::post('/shifts/open', [PosController::class, 'openShift'])->name('shifts.open');
        Route::post('/shifts/close', [PosController::class, 'closeShift'])->name('shifts.close');
        Route::get('/qz/cert', [PosController::class, 'qzCert'])->name('qz.cert');
        Route::post('/qz/sign', [PosController::class, 'qzSign'])->name('qz.sign');
    });

    // Modul 3.1: Master Data & Produk
    Route::middleware('module:products')->prefix('products')->name('products.')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->name('index');
        Route::post('/', [ProductController::class, 'store'])->name('store');
        Route::put('/variants/{variant}', [ProductController::class, 'updateVariant'])->name('variants.update');
        Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');
        Route::get('/import/template', [ProductController::class, 'downloadTemplate'])->name('import.template');
        Route::post('/import', [ProductController::class, 'importCsv'])->name('import');
        Route::get('/barcode-labels', [ProductController::class, 'printBarcodeLabels'])->name('barcode.labels');
        Route::post('/categories', [ProductController::class, 'storeCategory'])->name('categories.store');
        Route::post('/units', [ProductController::class, 'storeUnit'])->name('units.store');
        Route::post('/suppliers', [ProductController::class, 'storeSupplier'])->name('suppliers.store');
        Route::post('/customers', [ProductController::class, 'storeCustomer'])->name('customers.store');
    });

    // Modul 3.2: Inventori
    Route::middleware('module:inventory')->prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/', [InventoryController::class, 'index'])->name('index');
        Route::post('/adjust', [InventoryController::class, 'adjust'])->name('adjust');
        Route::post('/opname', [InventoryController::class, 'opname'])->name('opname');
        Route::post('/transfers', [InventoryController::class, 'sendTransfer'])->name('transfers.send');
        Route::post('/transfers/{transfer}/receive', [InventoryController::class, 'receiveTransfer'])->name('transfers.receive');
    });

    // Modul 3.3: Pembelian
    Route::middleware('module:purchasing')->prefix('purchasing')->name('purchasing.')->group(function () {
        Route::get('/', [PurchasingController::class, 'index'])->name('index');
        Route::post('/orders', [PurchasingController::class, 'storePo'])->name('orders.store');
        Route::post('/orders/{purchaseOrder}/receive', [PurchasingController::class, 'receive'])->name('orders.receive');
        Route::post('/orders/{purchaseOrder}/pay', [PurchasingController::class, 'paySupplier'])->name('orders.pay');
        Route::post('/returns', [PurchasingController::class, 'storeReturn'])->name('returns.store');
    });

    // Modul 3.6: Laporan & Ekspor
    Route::middleware('module:reports')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/export/excel', [ReportController::class, 'exportExcel'])->name('export.excel');
        Route::get('/export/pdf', [ReportController::class, 'exportPdf'])->name('export.pdf');
    });

    // Pengaturan, Cabang, User & Activity Log
    Route::middleware('module:settings')->prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::put('/branch-setting', [SettingsController::class, 'updateBranchSetting'])->name('branch.update');
        Route::post('/branches', [SettingsController::class, 'storeBranch'])->name('branches.store');
        Route::put('/branches/{branch}', [SettingsController::class, 'updateBranch'])->name('branches.update');
        Route::post('/users', [SettingsController::class, 'storeUser'])->name('users.store');
        Route::put('/users/{managedUser}', [SettingsController::class, 'updateUser'])->name('users.update');
        Route::patch('/users/{managedUser}/toggle', [SettingsController::class, 'toggleUserActive'])->name('users.toggle');

        // Backup & Restore Database (Khusus Owner)
        Route::post('/backups', [BackupController::class, 'store'])->name('backups.store');
        Route::get('/backups/{filename}/download', [BackupController::class, 'download'])->name('backups.download');
        Route::post('/backups/{filename}/restore', [BackupController::class, 'restore'])->middleware('throttle:admin-pin')->name('backups.restore');
        Route::post('/backups/upload-restore', [BackupController::class, 'uploadRestore'])->middleware('throttle:admin-pin')->name('backups.upload-restore');
        Route::delete('/backups/{filename}', [BackupController::class, 'destroy'])->name('backups.destroy');
    });
});
