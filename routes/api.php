<?php

use App\Http\Controllers\V1\Auth\AdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\Auth\AuthController;
use App\Http\Controllers\V1\Auth\ProfileController;
use App\Http\Controllers\V1\Auth\UserController;
use App\Http\Controllers\V1\Category\CategoryController;
use App\Http\Controllers\V1\Category\TypeController;
use App\Http\Controllers\V1\Order\GetOrderController;
use App\Http\Controllers\V1\Order\StoreOrderController;

// Kullanıcı Oluşturma veya Giriş yapma
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->get('/validate-token', function (Request $request) {
    // Kullanıcı doğrulandıysa, token geçerlidir
    return response()->json(['message' => 'Token is valid'], 200);
});

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
Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('check.single.role:musteri')->group(function () {
        // Sipariş oluşturma rotası
        Route::post('/order-create', [StoreOrderController::class, 'createOrder']);
        // Form içeriklerinin validation işlemlerini yapıyoruz.
        Route::post('/validate-form', [StoreOrderController::class, 'validateForms']);
        // Form içeriklerinin validation işlemlerini yapıyoruz.
        Route::post('/validate-order-item', [StoreOrderController::class, 'validateOrderItem']);
    });
});

/** Sipariş görüntüleme rotaları. **/
Route::middleware('auth:sanctum')->group(function () {

Route::middleware('check.single.role:admin')->group(function () {
    // Aktif siparişleri getir
    Route::get('/orders/active', [GetOrderController::class, 'getActiveOrders']);
    // Belirtilen durumdaki siparişleri getir
    Route::get('/orders/status/{status}', [GetOrderController::class, 'getOrdersByStatus']);
    // Belirtilen ID'ye sahip siparişi getir
    Route::get('/orders/{id}', [GetOrderController::class, 'getOrderById']);
    // Gecikmiş siparişleri getir
    Route::get('/delayed/orders', [GetOrderController::class, 'getDelayedOrders']);
    // Faturalanan  siparişleri getir
    Route::get('/orders-billing', [GetOrderController::class, 'getBillingOrders']);
    // Arama İşlemlerini yapma
    Route::get('/admin/search', [GetOrderController::class, 'search']);
});

// Müşteri siparişleri getir
Route::middleware('check.single.role:musteri')->group(function () {
    // Müşteriye ait siparişleri getir
    Route::get('/customer/orders', [GetOrderController::class, 'getCustomerOrders']);
    // Müşteriye ait belirli durumdaki siparişleri getir
    Route::get('/customer/orders/status/{status}', [GetOrderController::class, 'getCustomerOrdersByStatus']);
    // Belirtilen ID'ye sahip müşteri siparişini getir
    Route::get('/orders/customer/{id}', [GetOrderController::class, 'getOrderByIdForCustomer']);
    // Müşteri sipariş geçmişini getir
    Route::get('/customer/order-history', [GetOrderController::class, 'getCustomerOrderHistory']);
    // Gecikmiş siparişleri getir
    Route::get('/customer/order-delayed', [GetOrderController::class, 'getCustomerDelayedOrders']);
    // Arama İşlemlerini yapma
    Route::get('/customer/search', [GetOrderController::class, 'customerSearch']);
});

/** Tasarımcı sipariş görüntüleme rotasıları. **/
Route::middleware('check.single.role:tasarimci')->group(function () {
    // Tasarım bekleyen siparişleri getir.
    Route::get('/orders/desinger/await-desing', [GetOrderController::class, 'getDesingerOrders']);
    // Tüm Tasarımıcı Siparişleri getir.
    Route::get('/orders/desinger/all-desing', [GetOrderController::class, 'getDesingerUpdateOrders']);
    // İlgili Siparişi getir.
    Route::get('/desinger/orders/{id}', [GetOrderController::class, 'getOrderByIdForDesinger']);
    // Arama İşlemlerini yapma
    Route::get('/desinger/search', [GetOrderController::class, 'desingerSearch']);
});

/** Kurye sipariş görüntüleme rotasıları. **/
Route::middleware('check.single.role:kurye')->group(function () {
    // Arama İşlemlerini yapma
    Route::get('/courier/search', [GetOrderController::class, 'courierSearch']);
    // Kargo gönderim şeklini al
    Route::get('/orders/courier/await-courier', [GetOrderController::class, 'getAwaitCourier']);
    // Kargo gönderim şeklini güncelle
    Route::get('/orders/courier/update-courier', [GetOrderController::class, 'getUpdateCourier']);
    // Kargo Siparişini al
    Route::get('/courier/order/{order}', [GetOrderController::class, 'getOrderByIdForCourier']);
    // QR code ile şipariş getirme
    Route::get('/courier/qr-code/{order_code}', [GetOrderController::class, 'getOrderByCode']);
});

// Üreticiye ait siparişleri getir
Route::middleware('check.single.role:uretici')->group(function () {
    // Üreticiye ait siparişleri getir
    Route::get('/manufacturer/orders', [GetOrderController::class, 'getManufacturerOrders']);
    // Belirtilen ID'ye sahip üretici siparişini getir
    Route::get('/manufacturer/orders/{id}', [GetOrderController::class, 'getOrderByIdForManufacturer']);
    // Üreticiye ait belirli durumdaki siparişleri getir
    Route::get('/manufacturer/orders/status/{status}', [GetOrderController::class, 'getManufacturerOrdersByStatus']);
    // Üretici sipariş geçmişini getir
    Route::get('/manufacturer/order-history', [GetOrderController::class, 'getManufacturerOrderHistory']);
    // Arama İşlemlerini yapma
    Route::get('/manufacturer/search', [GetOrderController::class, 'manufacturerSearch']);
    // Gecikmiş siparişleri getir
    Route::get('/manufacturer/order-delayed', [GetOrderController::class, 'getManufacturerDelayedOrders']);
});

});