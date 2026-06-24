<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public landing / storefront
Route::get('/', function () {
    return Inertia::render('Storefront/Index');
})->name('storefront.index');

// Guest-only pages (can't visit login/register if already authenticated)
Route::middleware('guest')->group(function () {
    Route::get('/login', function () {
        return Inertia::render('Auth/Login');
    })->name('login');

    Route::get('/register', function () {
        return Inertia::render('Auth/Register');
    })->name('register.show');

    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

// Authenticated-only actions
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Guest-role pages (cart, my orders)
    Route::get('/cart', function () {
        return Inertia::render('Storefront/Cart');
    })->name('cart.index');

    Route::get('/my-orders', function () {
        return Inertia::render('Storefront/MyOrders');
    })->name('orders.mine');
});

// Admin-only CMS pages
Route::middleware(['auth', 'role:admin'])->prefix('cms')->name('cms.')->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Cms/Dashboard');
    })->name('dashboard');

    Route::get('/users', function () {
        return Inertia::render('Cms/Users/Index');
    })->name('users.index');

    Route::get('/products', function () {
        return Inertia::render('Cms/Products/Index');
    })->name('products.index');

    Route::get('/orders', function () {
        return Inertia::render('Cms/Orders/Index');
    })->name('orders.index');
});