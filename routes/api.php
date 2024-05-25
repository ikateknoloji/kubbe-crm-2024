<?php

use App\Http\Controllers\V1\Auth\AdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\Auth\AuthController;
use App\Http\Controllers\V1\Auth\ProfileController;
use App\Http\Controllers\V1\Auth\UserController;
use App\Http\Controllers\V1\Category\CategoryController;
use App\Http\Controllers\V1\Category\TypeController;
use App\Http\Controllers\V1\Order\StoreOrderController;

// Kullanıcı Oluşturma veya Giriş yapma
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


Route::middleware('auth:sanctum')->group(function () {

    /**Kullanıcı oluşturma veya giriş yapma gibi işlemlerin başlangıcı**/
    
    // Şifre yenileme
    Route::post('/password/reset', [UserController::class, 'resetPassword']);
    Route::post('/password-update', [UserController::class, 'updatePassword']);
    Route::post('/email-update', [UserController::class, 'updateEmail']);

    // Kulanıcı çıkış yapma
    Route::post('/logout', [AuthController::class, 'logout']);

    // Kullanıcı profilini güncelleme ve yükleme
    Route::post('/user/{user}/uploadProfilePhoto', [ProfileController::class, 'uploadProfilePhoto']);
    Route::put('/user/{user}/updateProfilePhoto', [ProfileController::class, 'updateProfilePhoto']);

    // Kullanıcı silme
    Route::middleware('check.single.role:musteri')->group(function () {
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);
    });

    /**Kullanıcı oluşturma veya giriş yapma gibi işlemlerin sonu**/


    // Kategori ve ürün tipleri ile ilgili kategori ve ürün tiplerini görüntüleme
    Route::get('/product_types/{category}', [TypeController::class, 'showByCategory']);
    Route::get('/product_categories', [CategoryController::class, 'index']);
    
});

/** sipariş oluşturma ve validation işlemlerini yapıyoruz. **/
Route::middleware('check.single.role:musteri')->group(function () {
        // Sipariş oluşturma rotası
        Route::post('/order-create', [StoreOrderController::class, 'createOrder']);
        // Form içeriklerinin validation işlemlerini yapıyoruz.
        Route::post('/validate-form', [StoreOrderController::class, 'validateForms']);
        // Form içeriklerinin validation işlemlerini yapıyoruz.
        Route::post('/validate-order-item', [StoreOrderController::class, 'validateOrderItem']);
});