<?php

use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\OrderAdminController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// ─── Přihlášení ──────────────────────────────────────────────────────────────
Route::get('/prihlaseni', [AuthController::class, 'loginForm'])->name('login');
Route::post('/prihlaseni', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.post');
Route::post('/odhlasit', [AuthController::class, 'logout'])->name('logout');
Route::get('/vstup/{token}', [AuthController::class, 'access'])
    ->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:20,1')->name('access');

Route::middleware(['auth', 'active'])->group(function () {
    // ─── Zakázky (kremace i dílna) ───────────────────────────────────────────
    Route::get('/', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/zakazky/nova', [OrderController::class, 'create'])->name('orders.create');
    Route::post('/zakazky', [OrderController::class, 'store'])->name('orders.store');
    Route::get('/zakazky/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/zakazky/{order}/upravit', [OrderController::class, 'edit'])->name('orders.edit');
    Route::put('/zakazky/{order}', [OrderController::class, 'update'])->name('orders.update');
    Route::post('/zakazky/{order}/zpravy', [MessageController::class, 'store'])->name('messages.store');
    Route::post('/jazyk', [ProfileController::class, 'locale'])->name('profile.locale');

    // ─── Fotky – jen přes aplikaci, nikdy přímo ze storage ───────────────────
    Route::get('/fotky/{photo}/{variant?}', [PhotoController::class, 'show'])
        ->whereIn('variant', ['thumb', 'large', 'original'])->name('photos.show');
    Route::delete('/fotky/{photo}', [PhotoController::class, 'destroy'])->name('photos.destroy');
    Route::get('/dreviny/{woodType}/foto/{variant?}', [PhotoController::class, 'wood'])
        ->whereIn('variant', ['thumb', 'large'])->name('woods.photo');

    // ─── Dílna ───────────────────────────────────────────────────────────────
    Route::middleware('admin')->group(function () {
        Route::post('/zakazky/{order}/stav', [OrderAdminController::class, 'updateStatus'])->name('orders.status');
        Route::post('/zakazky/{order}/upozorneni', [OrderAdminController::class, 'updateNotify'])->name('orders.notify');
        Route::get('/zakazky/{order}/fotky.zip', [OrderAdminController::class, 'downloadZip'])->name('orders.zip');

        Route::get('/prehled', [ReportController::class, 'index'])->name('report.index');
        Route::get('/prehled/export.csv', [ReportController::class, 'csv'])->name('report.csv');

        Route::get('/uzivatele', [UserController::class, 'index'])->name('users.index');
        Route::post('/uzivatele', [UserController::class, 'store'])->name('users.store');
        Route::put('/uzivatele/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/uzivatele/{user}/odkaz', [UserController::class, 'issueLink'])->name('users.link');
        Route::delete('/uzivatele/{user}/odkaz', [UserController::class, 'revokeLink'])->name('users.link.revoke');

        Route::get('/ciselniky', [CatalogController::class, 'index'])->name('catalog.index');
        Route::post('/ciselniky/dreviny', [CatalogController::class, 'storeWood'])->name('catalog.wood.store');
        Route::put('/ciselniky/dreviny/{woodType}', [CatalogController::class, 'updateWood'])->name('catalog.wood.update');
    });
});
