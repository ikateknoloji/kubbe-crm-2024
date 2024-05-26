<?php

use App\Http\Controllers\V1\Auth\AdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\Auth\AuthController;
use App\Http\Controllers\V1\Auth\ProfileController;
use App\Http\Controllers\V1\Auth\UserController;
use App\Http\Controllers\V1\Category\CategoryController;
use App\Http\Controllers\V1\Category\TypeController;
use App\Http\Controllers\V1\Manage\ManageOrderController;
use App\Http\Controllers\V1\Order\GetOrderController;
use App\Http\Controllers\V1\Order\GetRejectedController;
use App\Http\Controllers\V1\Order\HistoryOrderController;
use App\Http\Controllers\V1\Order\OrderImageController;
use App\Http\Controllers\V1\Order\OrderManageController;
use App\Http\Controllers\V1\Order\RejectOrderController;
use App\Http\Controllers\V1\Order\StoreOrderController;
use App\Http\Controllers\V1\Role\GetUserInfoController;
use App\Http\Controllers\V1\Role\RoleController;

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
    // Kullanıcı rollerini al
    Route::post('/roles/select-role', [GetUserInfoController::class, 'getUsersByRole']);
    
    // Kullanıcı rollerini ekleme veya kaldırma
    Route::post('/user/{user}/addRole', [RoleController::class, 'addRoleToUser']);
    Route::delete('/user/{user}/removeRole', [RoleController::class, 'removeRoleFromUser']);
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
    // Reddedilen , İptal Edilen ve Red Bekleyen siparişleri getir
    Route::get('/orders/rejected-status/{status}', [GetRejectedController::class, 'getRejectedOrdersByRejectedStatus']);
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
    // Reddedilen , İptal Edilen ve Red Bekleyen siparişleri getir
    Route::get('/customer/order-rejected/{status}', [GetRejectedController::class, 'getCustomerRejectedOrdersByRejectedStatus']);
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

// Şipariş Red , Iptal isteği oluşturma ve iptal isteği kaldırma
Route::middleware('auth:sanctum')->group(function () {
    Route::middleware(['check.single.role:admin', 'check.single.role:musteri'])->group(function () {
        Route::post('/order-cancel-requests/{orderId}', [ManageOrderController::class, 'createCancelRequestAndUpdateStatus']);
        Route::post('/rejected-orders/{orderId}', [ManageOrderController::class, 'rejectOrder']);
        Route::post('/process-cancellation/{orderId}', [ManageOrderController::class, 'processCancellation']);
        Route::post('/activate-order/{orderId}', [ManageOrderController::class, 'activateOrder']);
        Route::post('/activate-order-cancellation/{orderId}', [ManageOrderController::class, 'activateOrderAndRemoveCancellationRequest']);
    });
});

// Şipariş Red , Iptal isteği görüntüleme rotaları.
Route::middleware('auth:sanctum')->group(function () {
    Route::middleware(['check.single.role:admin', 'check.single.role:musteri'])->group(function () {
        Route::post('/orders-canceled/status/{status}', [GetRejectedController::class, 'getOrdersByStatus']);
        Route::post('/customer-orders-canceled/status/{status}', [GetRejectedController::class, 'getCustomerRejectedOrders']);
    });
});

// Müşteri ve Üretici sipariş geçmişlerini görüntüleme rotaları.
Route::middleware('auth:sanctum')->group(function () {
 Route::middleware('check.single.role:admin')->group(function () {

    // Müşterinin Aktif siparişleri getir
    Route::get('/orders/customer-active/{customerId}', [HistoryOrderController::class, 'getCustomerActiveOrders']);
    // Müşterinin Geçmiş siparişleri getir
    Route::get('/orders/customer-history/{customerId}', [HistoryOrderController::class, 'getCustomerOrderHistory']);
    // Üreticinin Aktif siparişleri getir
    Route::get('/orders/manufacturer-active/{manufacturerId}', [HistoryOrderController::class, 'getManufacturerActiveOrders']);
    // Üreticinin Geçmiş siparişleri getir
    Route::get('/orders/manufacturer-history/{manufacturerId}', [HistoryOrderController::class, 'getManufacturerOrderHistory']);
 });
});

// Sipariş için iptal isteği oluşturma ve iptal isteği kaldırma
Route::middleware('auth:sanctum')->group(function () {
 Route::middleware('check.single.role:admin')->group(function () {
    // Sipariş için iptal isteği oluşturma
    Route::post('/orders-cancel', [RejectOrderController::class, 'cancelOrder']);
    // Sipariş red olarak gönderir
    Route::post('/orders-reject', [RejectOrderController::class, 'rejectOrder']);
    // Sipariş Aktif hale getirme
    Route::post('/orders-activate/{orderId}', [RejectOrderController::class, 'activateOrder']);
 });
});

// Müşteri ve üretici için iptal isteği oluşturma.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/orders/pending-cancellation', [RejectOrderController::class, 'createPendingCancellation']);
});

Route::middleware('auth:sanctum')->group(function () {

 Route::middleware('check.single.role:admin')->group(function () {
     // Sipariş Durumunu Tasarım Aşamasına Geçirme rotası
     Route::post('/orders/{order}/transition-to-design', [OrderManageController::class, 'transitionToDesignPhase']);
     // Ödemeyi Doğrulama rotası
     Route::post('/orders/{order}/verify-payment', [OrderManageController::class, 'verifyPayment']);
     // Üretici Seçimi İşlemini Gerçekleştirme rotası
     Route::post('/orders/{order}/select-manufacturer', [OrderManageController::class, 'selectManufacturer']);
     // Fatura bilgilerini ekleme
     Route::post('/orders/add-bill/{order}', [OrderManageController::class, 'addBill']);
 });

 Route::middleware('check.single.role:musteri')->group(function () {
    // Ödeme Onayını ve İlerlemeyi Gerçekleştirme rotası
    Route::post('/orders/{order}/approve-payment-and-proceed', [OrderManageController::class, 'approvePaymentAndProceed']);
 });

 Route::middleware('check.single.role:tasarimci')->group(function () {
    // Ödeme Onayını ve İlerlemeyi Gerçekleştirme rotası
    Route::post('/orders/{order}/approve-design', [OrderManageController::class, 'approveDesign']);
 });

 Route::middleware('check.single.role:uretici')->group(function () {
     // Üretim Sürecini Başlatma rotası
     Route::post('/orders/{order}/start-production', [OrderManageController::class, 'startProduction']);
     // Ürünün Hazır Olduğunu Belirtme ve Resim Yükleme rotası
     Route::post('/orders/{order}/mark-product-ready', [OrderManageController::class, 'markProductReady']);
 });

 Route::middleware('check.single.role:kurye')->group(function () {
     // Ürünün Kargo Aşamasında Olduğunu Belirtme ve Resim Ekleme rotası
     Route::post('/orders/{order}/mark-product-in-transition', [OrderManageController::class, 'markProductInTransition']);
 });

});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/download-order-image/{orderId}/{type}', [OrderImageController::class, 'downloadOrderImage']);
});