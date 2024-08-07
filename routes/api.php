<?php

use App\Http\Controllers\V1\Auth\AdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\Auth\AuthController;
use App\Http\Controllers\V1\Auth\ProfileController;
use App\Http\Controllers\V1\Auth\UserController;
use App\Http\Controllers\V1\Basket\OrderBasketController;
use App\Http\Controllers\V1\Category\CategoryController;
use App\Http\Controllers\V1\Category\TypeController;
use App\Http\Controllers\V1\Manage\ManageOrderController;
use App\Http\Controllers\V1\Notification\NotificationController;
use App\Http\Controllers\V1\Notification\NotificationReadController;
use App\Http\Controllers\V1\Order\GetOrderController;
use App\Http\Controllers\V1\Order\GetRejectedController;
use App\Http\Controllers\V1\Order\HistoryOrderController;
use App\Http\Controllers\V1\Order\OrderImageController;
use App\Http\Controllers\V1\Order\OrderManageController;
use App\Http\Controllers\V1\Order\RejectOrderController;
use App\Http\Controllers\V1\Order\StoreOrderController;
use App\Http\Controllers\V1\Role\GetUserInfoController;
use App\Http\Controllers\V1\Role\RoleController;
use App\Http\Controllers\V1\Update\UpdateOrderController;

Route::get('/monthly-items', [GetOrderController::class, 'getPDFMonthlyOrderItemsCustomer']);

// Kullanıcı Oluşturma veya Giriş yapma
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/dowload-logo-file', [StoreOrderController::class, 'download']);

Route::get('/order-for-customer-by-code/{order_code}', [GetOrderController::class, 'getOrderByCodeForCustomer']);

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
        // Sipariş Kalemi için validation işlemlerini yapıyoruz.
        Route::post('/validate-order-item-single', [StoreOrderController::class, 'validateItem']);

        Route::post('/upload-logo-file', [StoreOrderController::class, 'upload']);
        Route::post('/delete-logo-file', [StoreOrderController::class, 'revert']);
    });
});

/** Sipariş görüntüleme rotaları. **/
Route::middleware('auth:sanctum')->group(function () {

    Route::middleware('check.single.role:admin')->group(function () {
        // Şifre güncelleme fonksiyonu
        Route::get('/update-password', [UserController::class, 'updatePasswordAdmin']);
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
        // Üretim Tasarımı Ekleme
        Route::get('/production-stage-p', [GetOrderController::class, 'getProductionStagePOrders']);
        // Üretime gönderilen siparişler
        Route::get('/production-stage-completed', [GetOrderController::class, 'getProductionStageCompletedOrders']);
        // Üretime gidecek siparişleri getir
        Route::get('/production-status-orders', [GetOrderController::class, 'getProductionStatusOrders']);
        // Üretime gidecek siparişleri getir
        Route::get('/orders-production-update-status', [OrderManageController::class, 'updateProductionStatus']);
        // Üretimi Tamamla
        Route::post('/mark-completed-orders/{orderId}', [OrderManageController::class, 'markOrderAsCompleted']);

        Route::post('/orders/{order}/select-manufacturer', [OrderManageController::class, 'selectManufacturer']);
    });

    /** Kurye sipariş görüntüleme rotasıları. **/
    Route::middleware('check.single.role:kurye')->group(function () {
        // Arama İşlemlerini yapma
        Route::get('/courier/search', [GetOrderController::class, 'courierSearch']);
        // Kargo gönderim şeklini al
        Route::get('/orders/courier/await-courier', [GetOrderController::class, 'getAwaitCourierA']);
        // Gönderici Ödemeler
        Route::get('/orders/courier/g-courier', [GetOrderController::class, 'getAwaitCourierTypeG']);
        // Ofis Teslim Olanlar
        Route::get('/orders/courier/t-courier', [GetOrderController::class, 'getAwaitCourierTypeT']);
        // Kargo gönderim şeklini güncelle
        Route::get('/orders/courier/update-courier', [GetOrderController::class, 'getUpdateCourier']);
        // Kargo Siparişini al
        Route::get('/courier/order/{order}', [GetOrderController::class, 'getOrderByIdForCourier']);

        // QR code ile şipariş getirme
        Route::get('/courier/qr-code/{order_code}', [GetOrderController::class, 'getOrderByCode']);
        
        // Sipariş Hazır
        Route::get('/courier/ready-order', [GetOrderController::class, 'getProductStatusOrder']);
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
        Route::get('/history-orders/customer-active/{customerId}', [HistoryOrderController::class, 'getCustomerActiveOrders']);
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
        // Route::post('/orders/{order}/select-manufacturer', [OrderManageController::class, 'selectManufacturer']);
        // Fatura bilgilerini ekleme
        Route::post('/orders/add-bill/{order}', [OrderManageController::class, 'addBill']);
    });

    Route::middleware('check.single.role:musteri')->group(function () {
        // Ödeme Onayını ve İlerlemeyi Gerçekleştirme rotası
        Route::post('/orders/{order}/approve-payment-and-proceed', [OrderManageController::class, 'approvePaymentAndProceed']);
    });

    Route::middleware('check.single.role:tasarimci')->group(function () {
        // Ödeme Onayını ve İlerlemeyi Gerçekleştirme rotası
        Route::post('/upload-production-image/{order}', [OrderManageController::class, 'uploadProductionImage']);
        // Tasrım Ekle
        Route::post('/approve-design/{order}', [OrderManageController::class, 'approveDesign']);
        // 'update-design/{order}' rotasını tanımlayın
        Route::post('/update-design/{order}', [UpdateOrderController::class, 'updateDesign']);
    });

    Route::middleware('check.single.role:uretici')->group(function () {
        // Üretim Sürecini Başlatma rotası
        Route::post('/orders/{order}/start-production', [OrderManageController::class, 'startProduction']);
    });

    Route::middleware('check.single.role:kurye')->group(function () {
        // Ürünün Kargo Aşamasında Olduğunu Belirtme ve Resim Ekleme rotası
        Route::post('/order/mark-product-in-transition/{order}', [OrderManageController::class, 'markProductInTransition']);
        Route::post('/order/teslim-transition/{order}', [OrderManageController::class, 'markProductAsPickedUpFromOffice']);
        // Ürünün Hazır Olduğunu Belirtme ve Resim Yükleme rotası
        Route::post('/orders/{order}/mark-product-ready', [OrderManageController::class, 'markProductReady']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/download-order-image/{orderId}/{type}', [OrderImageController::class, 'downloadOrderImage']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware(['check.single.role:admin', 'check.single.role:musteri', 'check.single.role:kurye'])->group(function () {
        // 'update-logo-image/{orderId}' rotasını tanımlayın
        Route::post('/update-logo-image/{orderId}', [UpdateOrderController::class, 'updateLogoImage']);

        // 'update-customer-info/{orderId}' rotasını tanımlayın
        Route::post('/update-customer-info/{orderId}', [UpdateOrderController::class, 'updateCustomerInfo']);

        // 'update-customer-info/{orderId}' rotasını tanımlayın
        Route::post('/update-note-info/{orderId}', [UpdateOrderController::class, 'updateOrderNote']);

        // 'update-logo-image/{orderId}' rotasını tanımlayın
        Route::post('/update-logo-image/{orderId}', [UpdateOrderController::class, 'updateLogoImage']);
        // 'update-customer-info/{orderId}' rotasını tanımlayın
        Route::post('/update-customer-info/{orderId}', [UpdateOrderController::class, 'updateCustomerInfo']);
        // 'update-order-address/{orderId}' rotasını tanımlayın
        Route::post('/update-order-address/{orderId}', [UpdateOrderController::class, 'updateOrderAddress']);
        // 'update-invoice-info/{orderId}' rotasını tanımlayın
        Route::post('/update-invoice-info/{order}', [UpdateOrderController::class, 'updateInvoiceInfo']);
        // 'update-payment/{order}' rotasını tanımlayın
        Route::post('/update-payment/{order}', [UpdateOrderController::class, 'updatePayment']);
        // 'update-manufacturer/{order}' rotasını tanımlayın
        Route::post('/update-manufacturer/{order}', [UpdateOrderController::class, 'updateManufacturer']);
        // 'update-product-ready-image/{order}' rotasını tanımlayın
        Route::post('/update-product-ready-image/{order}', [UpdateOrderController::class, 'updateProductReadyImage']);
        Route::post('/update-cargo-code/{order}', [UpdateOrderController::class, 'updateMarkProductInTransition']);
        // 'update-order-item-and-total-offer-price/{orderItemId}' rotasını tanımlayın
        Route::post('/update-order-item-and-total-offer-price/{orderItemId}', [UpdateOrderController::class, 'updateOrderItemAndTotalOfferPrice']);
        // 'mark-product-in-transition/{order}' rotasını tanımlayın
        Route::post('/mark-product-in-transition/{order}', [UpdateOrderController::class, 'markProductInTransition']);
        // ödme tutarını güncelleme rotası
        Route::post('/update-payment-amount/{order}', [UpdateOrderController::class, 'updatePaymentAmount']);
        // ödme tutarını güncelleme rotası
        Route::get('/close-account/{order}', [UpdateOrderController::class, 'closeAccount']);
        // Siparişi kargoya gönder rotası
        Route::post('/ship-order/{order}', [OrderManageController::class, 'shipOrder']);
        // Siparişin kargoya gönderilmesini geri al rotası
        Route::post('/unship-order/{order}', [OrderManageController::class, 'unshipOrder']);
    });
});

// Bildirim görüntüleme rotaları.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/notifications/admin', [NotificationController::class, 'getAdminNotifications']);
    Route::get('/notifications/user', [NotificationController::class, 'getUserNotifications']);
    Route::get('/notifications/courier', [NotificationController::class, 'getCourierNotifications']);
    Route::get('/notifications/designer', [NotificationController::class, 'getDesignerNotifications']);
    Route::get('/notifications/manufacturer', [NotificationController::class, 'getManufacturerNotifications']);

    Route::post('/notifications/admin/{id}/read', [NotificationReadController::class, 'markAdminNotificationAsRead']);
    Route::post('/notifications/courier/{id}/read', [NotificationReadController::class, 'markCourierNotificationAsRead']);
    Route::post('/notifications/designer/{id}/read', [NotificationReadController::class, 'markDesignerNotificationAsRead']);
    Route::post('/notifications/user/{id}/read', [NotificationReadController::class, 'markUserNotificationAsRead']);
    Route::post('/notifications/manufacturer/{id}/read', [NotificationReadController::class, 'markManufacturerNotificationAsRead']);
});


Route::get('/order-baskets/{id}', [OrderBasketController::class, 'getBasketById']);
Route::post('/delete-basket-items/{id}', [OrderBasketController::class, 'deleteBasketItem']);
Route::post('/add-basket-items/{id}', [OrderBasketController::class, 'addOrderItems']);

