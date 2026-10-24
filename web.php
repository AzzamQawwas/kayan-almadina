<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProductUnitController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/setup', [SetupController::class, 'create'])
        ->middleware('first-admin')->name('setup.create');
    Route::post('/setup', [SetupController::class, 'store'])
        ->middleware(['first-admin', 'throttle:5,1'])->name('setup.store');
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/avatar', [ProfileController::class, 'avatar'])->name('profile.avatar');
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')->name('dashboard');

    Route::get('/orders', [OrderController::class, 'index'])
        ->middleware('permission:orders.view')->name('orders.index');
    Route::get('/orders/create', [OrderController::class, 'create'])
        ->middleware('permission:orders.create')->name('orders.create');
    Route::post('/orders', [OrderController::class, 'store'])
        ->middleware('permission:orders.create')->name('orders.store');
    Route::get('/orders/{order}', [OrderController::class, 'show'])
        ->middleware('permission:orders.view')->name('orders.show');
    Route::get('/orders/{order}/delivery-photos/{photo}', [OrderController::class, 'deliveryPhoto'])
        ->middleware('permission:orders.view')->name('orders.delivery-photo');
    Route::get('/orders/{order}/edit', [OrderController::class, 'edit'])
        ->middleware('permission:orders.edit')->name('orders.edit');
    Route::put('/orders/{order}', [OrderController::class, 'update'])
        ->middleware('permission:orders.edit')->name('orders.update');
    Route::delete('/orders/{order}', [OrderController::class, 'destroy'])
        ->middleware('permission:orders.delete')->name('orders.destroy');
    Route::post('/orders/{order}/purchase', [OrderController::class, 'confirmPurchase'])
        ->middleware('permission:orders.purchase')->name('orders.purchase');
    Route::post('/orders/{order}/shortage', [OrderController::class, 'reportShortage'])
        ->middleware('permission:orders.report_shortage')->name('orders.shortage');
    Route::post('/orders/{order}/resolve-shortage', [OrderController::class, 'resolveShortage'])
        ->middleware('permission:orders.resolve_shortage')->name('orders.resolve-shortage');
    Route::post('/orders/{order}/close', [OrderController::class, 'close'])
        ->middleware('permission:orders.close')->name('orders.close');

    Route::get('/customers', [CustomerController::class, 'index'])
        ->middleware('permission:customers.view')->name('customers.index');
    Route::get('/customers/search', [CustomerController::class, 'search'])
        ->middleware('permission:customers.view')->name('customers.search');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])
        ->middleware('permission:customers.view')->name('customers.show');
    Route::post('/customers/quick', [CustomerController::class, 'quickStore'])
        ->middleware('permission:customers.create')->name('customers.quick-store');
    Route::post('/customers', [CustomerController::class, 'store'])
        ->middleware('permission:customers.create')->name('customers.store');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])
        ->middleware('permission:customers.update')->name('customers.update');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])
        ->middleware('permission:customers.delete')->name('customers.destroy');

    Route::get('/reports', [ReportController::class, 'index'])
        ->middleware('permission:reports.view')->name('reports.index');
    Route::get('/suppliers', [SupplierController::class, 'index'])
        ->middleware('permission:suppliers.view')->name('suppliers.index');
    Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])
        ->middleware('permission:suppliers.view')->name('suppliers.show');
    Route::post('/suppliers', [SupplierController::class, 'store'])
        ->middleware('permission:suppliers.create')->name('suppliers.store');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])
        ->middleware('permission:suppliers.update')->name('suppliers.update');
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])
        ->middleware('permission:suppliers.delete')->name('suppliers.destroy');
    Route::get('/users', [UserController::class, 'index'])
        ->middleware('permission:users.view')->name('users.index');
    Route::post('/users', [UserController::class, 'store'])
        ->middleware('permission:users.create')->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])
        ->middleware('permission:users.update')->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])
        ->middleware('permission:users.update')->name('users.update');
    Route::put('/users/{user}/access', [UserController::class, 'updateAccess'])
        ->middleware('permission:users.manage')->name('users.access');
    Route::get('/users/{user}/permissions', [UserController::class, 'editPermissions'])
        ->middleware('permission:users.permissions')->name('users.permissions.edit');
    Route::put('/users/{user}/permissions', [UserController::class, 'updatePermissions'])
        ->middleware('permission:users.permissions')->name('users.permissions.update');

    Route::get('/units', [ProductUnitController::class, 'index'])
        ->middleware('permission:units.view')->name('units.index');
    Route::post('/units', [ProductUnitController::class, 'store'])
        ->middleware('permission:units.create')->name('units.store');
    Route::put('/units/{unit}', [ProductUnitController::class, 'update'])
        ->middleware('permission:units.update')->name('units.update');
    Route::delete('/units/{unit}', [ProductUnitController::class, 'destroy'])
        ->middleware('permission:units.delete')->name('units.destroy');
});
