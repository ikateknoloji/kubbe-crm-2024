<?php

namespace App\Http\Controllers\V1\Order;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderImage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;

class GetOrderController extends Controller
{
    /**
     * Aktif durumda olan ve teslim edilmemiş siparişleri getirir.
     *
     * @return JsonResponse
     * Örnek İstek Yapısı
     * @GET /orders/active
     * Authorization: Bearer your-auth-token
     * Content-Type: application/json
     * {
     *   "page": 1,
     *   "per_page": 12
     * }    
     */
    public function getActiveOrders(): JsonResponse
    {
        // 'A' (Active) durumuna sahip ve teslim edilmemiş siparişleri al
        $orders = Order::where('is_rejected', 'A')
            ->where('status', '!=', 'PD') // 'PD' durumundaki siparişleri hariç tut
            ->with([
                'customerInfo',
                'customer' => function ($query) {
                    // İlgili müşteri bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'manufacturer' => function ($query) {
                    // İlgili üretici bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
            ])
            ->orderBy('updated_at') // En eski tarihten itibaren sırala
            ->orderByRaw("FIELD(status, 'OC', 'DP', 'DA', 'P', 'PA', 'MS', 'PP', 'PR', 'PIT', 'PD')") // Enum sırasına göre sırala
            ->paginate(12);

        return response()->json(['orders' => $orders]);
    }

    /**
     * Belirtilen 'status' değerine sahip siparişleri getirir.
     *
     * @param  string  $status
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrdersByStatus($status): JsonResponse
    {
        // Belirtilen 'status' değerine sahip siparişleri al
        $orders = Order::where('status', $status)
            ->where('is_rejected', 'A')
            ->with([
                'customer' => function ($query) {
                    // İlgili müşteri bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'manufacturer' => function ($query) {
                    // İlgili üretici bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'customerInfo', // customerInfo ilişkisini ekledik
            ])
            ->orderBy('updated_at') // En eski tarihten itibaren sırala
            ->paginate(9);

        return response()->json(['orders' => $orders]);
    }

    /**
     * Belirtilen müşteri 'id' değerine sahip siparişleri getirir.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCustomerOrders(): JsonResponse
    {
        $customerId = Auth::id();

        // Belirtilen müşteri 'id' değerine sahip siparişleri al
        $orders = Order::where('customer_id', $customerId)
            ->with([
                'manufacturer' => function ($query) {
                    // İlgili üretici bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'customerInfo'
            ]) // customerInfo ilişkisini ekledik
            ->orderBy('updated_at') // En eski tarihten itibaren sırala
            ->orderByRaw("FIELD(status, 'OC', 'DP', 'DA', 'P', 'PA', 'MS', 'PP', 'PR', 'PIT', 'PD')") // Enum sırasına göre sırala
            ->paginate(6);

        return response()->json(['orders' => $orders]);
    }

    /**
     * Belirtilen üretici 'id' değerine sahip siparişleri getirir.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getManufacturerOrders()
    {
        $manufacturerId = Auth::id();

        // Belirtilen üretici 'id' değerine sahip siparişleri al
        $orders = Order::where('manufacturer_id', $manufacturerId)
            ->orderBy('updated_at') // En eski tarihten itibaren sırala
            ->with(
                [
                    'customer' => function ($query) {
                        // İlgili müşteri bilgilerini getir
                        $query->select('id', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                    },
                ]
            ) // customerInfo ilişkisini ekledik
            ->orderByRaw("FIELD(status, 'OC', 'DP', 'DA', 'P', 'PA', 'MS', 'PP', 'PR', 'PIT', 'PD')") // Enum sırasına göre sırala
            ->paginate(6);


        return response()->json(['orders' => $orders], 200);
    }

    /**
     * Belirtilen 'id' değerine sahip tekil siparişi getirir.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrderById($id)
    {
        $order = Order::with([
            'customer' => function ($query) {
                // İlgili müşteri bilgilerini getir
                $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
            },
            'manufacturer' => function ($query) {
                // İlgili üretici bilgilerini getir
                $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
            },
            'baskets.items.productType',
            'baskets.items.productCategory',
            'baskets.logos',
            'orderImages',
            'customerInfo',
            'invoiceInfo',
            'orderAddress',
            'designImages',
            'cancelledOrder', 'rejectedOrder', 'orderCancelRequest'
        ])->find($id);

        // Siparişin admin tarafından okunduğunu belirt
        if ($order && !$order->admin_read) {
            $order->admin_read = true;
            $order->save();
        }

        // İlgili resim tiplerini filtreleme
        $filteredImages = $order->orderImages
            ->whereIn('type', ['D', 'P', 'PR', 'SC', 'PL'])
            ->groupBy('type')
            ->map(function ($images) {
                return $images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'order_id' => $image->order_id,
                        'type' => $image->type,
                        'image_url' => asset($image->product_image),
                        'mime_type' => $image->mime_type,
                        'created_at' => $image->created_at,
                        'updated_at' => $image->updated_at,
                    ];
                })->first(); // Sadece ilk resmi al
            });

        // Dönüştürülmüş resimleri, sipariş nesnesine ekleyin
        $order->formatted_order_images = $filteredImages->toArray();

        // Sipariş bulunamazsa, null yanıtı döndür
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        // Dönüştürülmüş sipariş nesnesini kullanabilirsiniz
        return response()->json(['order' => $order]);
    }

    /**
     * Belirtilen 'id' değerine sahip tekil siparişi getirir.
     * Ancak, siparişin 'customer_id' değeri, Auth bilgileri ile aynı olmalıdır.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrderByIdForCustomer($id)
    {
        // Auth bilgilerini al
        $user = Auth::user();

        $order = Order::with([
            'manufacturer' => function ($query) {
                // İlgili üretici bilgilerini getir
                $query->select('id', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
            },
            'baskets.items.productType',
            'baskets.items.productCategory',
            'baskets.logos',
            'orderImages',
            'customerInfo',
            'invoiceInfo',
            'orderAddress',
            'designImages',
            'cancelledOrder', 'rejectedOrder', 'orderCancelRequest'
        ])->find($id);

        // Siparişin 'customer_id' değeri, Auth bilgileri ile aynı olmalıdır
        if ($order->customer_id != $user->id) {
            return response()->json(['error' => 'Bu siparişi görüntüleme yetkiniz yok.'], 403);
        }

        // Siparişin müşteri tarafından okunduğunu belirt
        if (!$order->customer_read) {
            $order->customer_read = true;
            $order->save();
        }

        // İlgili resim tiplerini filtreleme
        $filteredImages = $order->orderImages
            ->whereIn('type', ['D', 'P', 'PR', 'SC', 'PL'])
            ->groupBy('type')
            ->map(function ($images) {
                return $images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'order_id' => $image->order_id,
                        'type' => $image->type,
                        'image_url' => asset($image->product_image),
                        'mime_type' => $image->mime_type,
                        'created_at' => $image->created_at,
                        'updated_at' => $image->updated_at,
                    ];
                })->first(); // Sadece ilk resmi al
            });

        // Dönüştürülmüş resimleri, sipariş nesnesine ekleyin
        $order->formatted_order_images = $filteredImages->toArray();

        // Dönüştürülmüş sipariş nesnesini kullanabilirsiniz
        return response()->json(['order' => $order], 200);
    }

    /**
     * Belirtilen 'id' değerine sahip tekil siparişi getirir.
     * Ancak, siparişin 'manufacturer_id' değeri, Auth bilgileri ile aynı olmalıdır.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrderByIdForManufacturer($id)
    {
        // Auth bilgilerini al
        $manufacturerId = Auth::id();

        $order = Order::where('manufacturer_id', $manufacturerId)->with([
            'manufacturer' => function ($query) {
                // İlgili üretici bilgilerini getir
                $query->select('id', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
            },
            'customer' => function ($query) {
                // İlgili müşteri bilgilerini getir
                $query->select('id', 'name', 'email', 'profile_photo');
            },
            'baskets.items.productType',
            'baskets.items.productCategory',
            'baskets.logos',
            'orderImages',
            'customerInfo',
            'invoiceInfo',
            'orderAddress',
            'designImages',
            'cancelledOrder', 'rejectedOrder', 'orderCancelRequest'
        ])->find($id);

        // Sipariş bulunamazsa veya üreticiye ait değilse hata döndür
        if ($order === null) {
            return response()->json(['message' => 'Order not found or access denied'], 404);
        }

        // İlgili resim tiplerini filtreleme
        $filteredImages = $order->orderImages
            ->whereIn('type', ['D', 'P', 'PR', 'SC', 'PL'])
            ->groupBy('type')
            ->map(function ($images) {
                return $images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'order_id' => $image->order_id,
                        'type' => $image->type,
                        'image_url' => asset($image->product_image),
                        'mime_type' => $image->mime_type,
                        'created_at' => $image->created_at,
                        'updated_at' => $image->updated_at,
                    ];
                })->first(); // Sadece ilk resmi al
            });

        // Dönüştürülmüş resimleri, sipariş nesnesine ekleyin
        $order->formatted_order_images = $filteredImages->toArray();

        // Dönüştürülmüş sipariş nesnesini kullanabilirsiniz
        return response()->json(['order' => $order], 200);
    }

    /**
     * Belirtilen müşteri 'id' değerine sahip ve belirtilen 'status' değerine sahip siparişleri getirir.
     *
     * @param  string  $status
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCustomerOrdersByStatus($status)
    {
        $customerId = Auth::id();

        // Belirtilen müşteri 'id' değerine sahip ve belirtilen 'status' değerine sahip siparişleri al
        $orders = Order::where('customer_id', $customerId)
            ->where('status', $status)
            ->with(
                [
                    'customerInfo',
                    'manufacturer' => function ($query) {
                        // İlgili üretici bilgilerini getir
                        $query->select('id', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                    },
                ],
            ) // customerInfo ilişkisini ekledik
            ->orderBy('updated_at') // En eski tarihten itibaren sırala
            ->paginate(9);

        return response()->json(['orders' => $orders], 200);
    }

    /**
     * Belirtilen üretici 'id' değerine sahip ve belirtilen 'status' değerine sahip siparişleri getirir.
     *
     * @param  string  $status
     * @return \Illuminate\Http\JsonResponse
     */
    public function getManufacturerOrdersByStatus($status)
    {
        $manufacturerId = Auth::id();

        // Belirtilen üretici 'id' değerine sahip ve belirtilen 'status' değerine sahip siparişleri al
        $orders = Order::where('manufacturer_id', $manufacturerId)
            ->where('status', $status)
            ->with([
                'customerInfo',
                'customer' => function ($query) {
                    // İlgili üretici bilgilerini getir
                    $query->select('id', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
            ]) // customerInfo ilişkisini ekledik
            ->orderBy('updated_at') // En eski tarihten itibaren sırala
            ->paginate(5);

        return response()->json(['orders' => $orders], 200);
    }

    /**
     * Belirtilen üretici 'id' değerine sahip ve belirtilen 'status' değerine sahip siparişleri getirir.
     *
     * @param  string  $status
     * @return \Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @GET /orders/active
     * Authorization: Bearer your-auth-token
     * Content-Type: application/json
     * {
     *   "page": 1,
     *   "per_page": 12
     * }
     */
    public function getManufacturerOrderHistory()
    {
        $manufacturerId = Auth::id();

        // Belirtilen üretici 'id' değerine sahip ve 'production_date' değeri null olmayan siparişleri al
        $orders = Order::where('manufacturer_id', $manufacturerId)
            ->whereNotNull('production_date')
            ->with([
                'customer' => function ($query) {
                    // İlgili müşteri bilgilerini getir
                    $query->select('id', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'customerInfo', // customerInfo ilişkisini ekledik
            ])
            ->orderBy('updated_at') // En eski tarihten itibaren sırala
            ->paginate(6);

        return response()->json(['order_history' => $orders], 200);
    }

    /**
     * Belirtilen müşteri 'id' değerine sahip ve belirtilen 'status' değerine sahip siparişleri getirir.
     * 
     * @param  string  $status
     * @return \Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @GET /orders/active
     * Authorization: Bearer your-auth-token
     * Content-Type: application/json
     * {
     *   "page": 1,
     *   "per_page": 12
     * }
     */
    public function getCustomerOrderHistory()
    {
        $customerId = Auth::id();

        // Belirtilen müşteri 'id' değerine sahip ve 'production_date' değeri null olmayan siparişleri al
        $orders = Order::where('customer_id', $customerId)
            ->whereNotNull('production_date')
            ->with([
                'customerInfo',
                'manufacturer' => function ($query) {
                    // İlgili üretici bilgilerini getir
                    $query->select('id', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
            ]) // customerInfo ilişkisini ekledik
            ->orderBy('updated_at') // En eski tarihten itibaren sırala
            ->paginate(6);

        return response()->json(['order_history' => $orders], 200);
    }

    /**
     * 'status' değeri 'PP' olan, 'estimated_production_date' değeri güncel tarih bilgisinden geri olan ve 'production_date' değeri <null>< /null> olan siparişleri getirir.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDelayedOrders()
    {
        // Belirtilen 'status' değeri 'PP' olan, 'estimated_production_date' değeri güncel tarih bilgisinden geri olan ve 'production_date' değeri null olan siparişleri al
        $orders = Order::where('status', 'PP')
            ->where('is_rejected', 'A')
            ->where('production_date', null)
            ->where('estimated_production_date', '<', now())
            ->with([
                'customer' => function ($query) {
                    // İlgili müşteri bilgilerini getir
                    $query->select('id', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'manufacturer' => function ($query) {
                    // İlgili üretici bilgilerini getir
                    $query->select('id', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'customerInfo', // customerInfo ilişkisini ekledik
            ])
            ->orderBy('updated_at') // En eski tarihten itibaren sırala
            ->paginate(9);


        return response()->json(['orders' => $orders], 200);
    }

    /*
     * Aktif durumda olan ve teslim edilmemiş siparişleri getirir.
     *
     * @return JsonResponse
     * Örnek İstek Yapısı
     * @GET /orders/active
     * Authorization: Bearer your-auth-token
     * Content-Type: application/json
     * {
     *   "page": 1,
     *   "per_page": 12
     * }    
     */
    public function getBillingOrders(): JsonResponse
    {
        // 'A' (Active) durumuna sahip ve teslim edilmemiş siparişleri al
        $orders = Order::where('is_rejected', 'A')
            ->where(function ($query) {
                $query->where('status', 'PD')
                    ->orWhere('status', 'PR');
            })
            ->whereDoesntHave('orderImages', function ($query) {
                $query->where('type', 'I'); // 'I' tipinde resme sahip siparişleri hariç tut
            })
            ->where('invoice_type', 'C')
            ->with([
                'customerInfo',
                'customer' => function ($query) {
                    // İlgili müşteri bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'manufacturer' => function ($query) {
                    // İlgili üretici bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
            ])
            ->orderBy('updated_at') // En eski tarihten itibaren sırala
            ->paginate(12);

        return response()->json(['orders' => $orders]);
    }

    /**
     * 'status' değeri 'PP' olan, 'estimated_production_date' değeri güncel tarih bilgisinden geri olan ve 'production_date' değeri null <></   olan> siparişleri getirir.
     * Ayrıca, Auth::id() ile alınan kullanıcı ID'sine göre 'customer_id' filtresi uygular.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCustomerDelayedOrders()
    {
        // Kullanıcı ID'sini al
        $userId = Auth::id();

        // Belirtilen 'status' değeri 'PP' olan, 'estimated_production_date' değeri güncel tarih bilgisinden geri olan ve 'production_date' değ<></  eri> null olan siparişleri al
        // Ayrıca, 'customer_id' değeri Auth::id() ile alınan kullanıcı ID'sine eşit olan siparişleri filtrele
        $orders = Order::where('status', 'PP')
            ->where('is_rejected', 'A')
            ->where('production_date', null)
            ->where('estimated_production_date', '<', now())
            ->where('customer_id', $userId) // Burada 'customer_id' filtresini ekledik
            ->with([
                'customer' => function ($query) {
                    $query->select('id', 'email', 'profile_photo');
                },
                'manufacturer' => function ($query) {
                    $query->select('id', 'email', 'profile_photo');
                },
                'customerInfo',
            ])
            ->orderBy('updated_at') // En eski tarihten itibaren sırala
            ->paginate(9);

        return response()->json(['orders' => $orders], 200);
    }

    /**
     * 'status' değeri 'PP' olan, 'estimated_production_date' değeri güncel tarih bilgisinden geri olan ve 'production_date' değeri null <></   olan> siparişleri getirir.
     * Ayrıca, Auth::id() ile alınan kullanıcı ID'sine göre 'manufacturer_id' filtresi uygular.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getManufacturerDelayedOrders()
    {
        // Kullanıcı ID'sini al
        $userId = Auth::id();

        // Belirtilen 'status' değeri 'PP' olan, 'estimated_production_date' değeri güncel tarih bilgisinden geri olan ve 'production_date' değeri  null olan siparişleri al
        // Ayrıca, 'manufacturer_id' değeri Auth::id() ile alınan kullanıcı ID'sine eşit olan siparişleri filtrele
        $orders = Order::where('status', 'PP')
            ->where('is_rejected', 'A')
            ->where('production_date', null)
            ->where('estimated_production_date', '<', now())
            ->where('manufacturer_id', $userId) // Burada 'manufacturer_id' filtresini ekledik
            ->with([
                'customer' => function ($query) {
                    $query->select('id', 'email', 'profile_photo');
                },
                'manufacturer' => function ($query) {
                    $query->select('id', 'email', 'profile_photo');
                },
                'customerInfo',
            ])
            ->orderBy('updated_at') // En eski tarihten itibaren sırala
            ->paginate(9);

        return response()->json(['orders' => $orders], 200);
    }

    /*
     * Aktif durumda olan ve teslim edilmemiş siparişleri getirir.
     *
     * @return JsonResponse
     * Örnek İstek Yapısı
     * @GET /orders/active
     * Authorization: Bearer your-auth-token
     * Content-Type: application/json
     * {
     *   "page": 1,
     *   "per_page": 12
     * }    
     */
    public function getDesingerOrders(): JsonResponse
    {
        $orders = Order::where('status', 'DP')
            ->where('is_rejected', 'A')
            ->with([
                'customer' => function ($query) {
                    // İlgili müşteri bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'manufacturer' => function ($query) {
                    // İlgili üretici bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'customerInfo', // customerInfo ilişkisini ekledik  
            ])
            ->orderBy('updated_at') // En eski tarihten itibaren sırala
            ->paginate(9);

        return response()->json(['orders' => $orders]);
    }

    /**
     * Aktif durumda olan ve teslim edilmemiş siparişleri getirir.
     *
     * @return JsonResponse
     * Örnek İstek Yapısı
     * @GET /orders/active
     * Authorization: Bearer your-auth-token
     * Content-Type: application/json
     * {
     *   "page": 1,
     *   "per_page": 12
     * }    
     */
    public function getDesingerUpdateOrders(): JsonResponse
    {
        $orders = Order::whereNotIn('status', ['OC', 'DP']) // 'OC' ve 'DP' dışındaki durumları getir
            ->where('is_rejected', 'A')
            ->with([
                'customer' => function ($query) {
                    // İlgili müşteri bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'manufacturer' => function ($query) {
                    // İlgili üretici bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'customerInfo', // customerInfo ilişkisini ekledik 
            ])
            ->orderBy('updated_at') // En eski tarihten itibaren sırala
            ->paginate(9);

        return response()->json(['orders' => $orders]);
    }

    /**
     * Belirtilen 'id' değerine sahip tekil siparişi getirir.
     * Ancak, siparişin 'designer_id' değeri, Auth bilgileri ile aynı olmalıdır.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrderByIdForDesinger($id)
    {
        $order = Order::with([
            'customer' => function ($query) {
                // İlgili müşteri bilgilerini getir
                $query->select('id', 'name', 'email', 'profile_photo');
            },
            'manufacturer' => function ($query) {
                // İlgili üretici bilgilerini getir
                $query->select('id', 'name', 'email', 'profile_photo');
            },
            'baskets.items.productType',
            'baskets.items.productCategory',
            'baskets.logos',
            'customerInfo',
            'invoiceInfo',
            'orderAddress',
            'productionImages',
            'cancelledOrder', 'rejectedOrder', 'orderCancelRequest'
        ])->find($id);

        // Sipariş bulunamazsa veya tasarımcıya ait değilse hata döndür
        if ($order === null) {
            return response()->json(['message' => 'Order not found or access denied'], 404);
        }

        // İlgili resim tiplerini filtreleme
        $filteredImages = $order->orderImages
            ->whereIn('type', ['D', 'P', 'PR', 'SC', 'PL'])
            ->groupBy('type')
            ->map(function ($images) {
                return $images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'order_id' => $image->order_id,
                        'type' => $image->type,
                        'image_url' => asset($image->product_image),
                        'mime_type' => $image->mime_type,
                        'created_at' => $image->created_at,
                        'updated_at' => $image->updated_at,
                    ];
                })->first(); // Sadece ilk resmi al
            });

        // Dönüştürülmüş resimleri, sipariş nesnesine ekleyin
        $order->formatted_order_images = $filteredImages->toArray();

        // Dönüştürülmüş sipariş nesnesini kullanabilirsiniz
        return response()->json(['order' => $order], 200);
    }

    /**
     * Belirtilen 'id' değerine sahip tekil siparişi getirir.
     * Ancak, siparişin 'courier_id' değeri, Auth bilgileri ile aynı olmalıdır.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrderByIdForCourier($id)
    {

        $order = Order::with([
            'customer' => function ($query) {
                // İlgili müşteri bilgilerini getir
                $query->select('id', 'name', 'email', 'profile_photo');
            },
            'manufacturer' => function ($query) {
                // İlgili üretici bilgilerini getir
                $query->select('id', 'name', 'email', 'profile_photo');
            },
            'baskets.items.productType',
            'baskets.items.productCategory',
            'baskets.logos',
            'orderImages',
            'customerInfo',
            'invoiceInfo',
            'orderAddress',
            'cancelledOrder', 'rejectedOrder', 'orderCancelRequest'
        ])->find($id);

        // Sipariş bulunamazsa veya kuryeye ait değilse hata döndür
        if ($order === null) {
            return response()->json(['message' => 'Order not found or access denied'], 404);
        }

        // İlgili resim tiplerini filtreleme
        $filteredImages = $order->orderImages
            ->whereIn('type', ['D', 'P', 'PR', 'SC', 'PL'])
            ->groupBy('type')
            ->map(function ($images) {
                return $images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'order_id' => $image->order_id,
                        'type' => $image->type,
                        'image_url' => asset($image->product_image),
                        'mime_type' => $image->mime_type,
                        'created_at' => $image->created_at,
                        'updated_at' => $image->updated_at,
                    ];
                })->first(); // Sadece ilk resmi al
            });

        // Dönüştürülmüş resimleri, sipariş nesnesine ekleyin
        $order->formatted_order_images = $filteredImages->toArray();

        // Dönüştürülmüş sipariş nesnesini kullanabilirsiniz
        return response()->json(['order' => $order], 200);
    }

    /**
     * Aktif durumda olan ve teslim edilmemiş siparişleri getirir.
     *
     * @return JsonResponse
     * Örnek İstek Yapısı
     * @GET /orders/active
     * Authorization: Bearer your-auth-token
     * Content-Type: application/json
     * {
     *   "page": 1,
     *   "per_page": 12
     * }    
     */
    public function getUpdateCourier(): JsonResponse
    {
        $orders = Order::where('status', ['PD']) // 'OC' ve 'DP' dışındaki durumları getir
            ->where('is_rejected', 'A')
            ->where('shipping_status', 'Y') // 'Y' (kargoya verilmiş) durumundaki siparişleri getir
            ->with([
                'customer' => function ($query) {
                    // İlgili müşteri bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'manufacturer' => function ($query) {
                    // İlgili üretici bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'orderImages' => function ($query) {
                    // 'D' tipindeki resimleri getir
                    $query->where('type', 'D');
                },
                'customerInfo', // customerInfo ilişkisini ekledik
            ])
            ->orderBy('updated_at') // En eski tarihten itibaren sırala
            ->paginate(9);

        return response()->json(['orders' => $orders]);
    }

    /**
     * Aktif durumda olan ve teslim edilmemiş siparişleri getirir.
     *
     * @return JsonResponse
     * Örnek İstek Yapısı
     * @GET /orders/active
     * Authorization: Bearer your-auth-token
     * Content-Type: application/json
     * {
     *   "page": 1,
     *   "per_page": 12
     * }    
     */

    public function getAwaitCourierA(): JsonResponse
    {
        $orders = Order::where('status', ['PR']) // 'OC' ve 'DP' dışındaki durumları getir
            ->where('is_rejected', 'A')
            ->where('shipping_status', 'Y') // 'Y' (kargoya verilmiş) durumundaki siparişleri getir
            ->where('shipping_type', 'A') // 'A' tipindeki siparişleri getir
            ->when(request('invoice_type') === 'C', function ($query) {
                $query->whereHas('orderImages', function ($subQuery) {
                    $subQuery->where('type', 'I');
                });
            })
            ->when(request('invoice_type') === 'I', function ($query) {
                // 'I' fatura tipi için ekstra bir koşul uygulamıyoruz
            })
            ->with([
                'customer' => function ($query) {
                    // İlgili müşteri bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'manufacturer' => function ($query) {
                    // İlgili üretici bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'orderImages' => function ($query) {
                    // 'D' tipindeki resimleri getir
                    $query->where('type', 'D');
                },
                'customerInfo', // customerInfo ilişkisini ekledik
            ])
            ->orderByDesc('updated_at') // En son güncellenenlere göre sırala
            ->paginate(9);

        return response()->json(['orders' => $orders]);
    }

    /**
     * Aktif durumda olan ve teslim edilmemiş siparişleri getirir.
     *
     * @return JsonResponse
     * Örnek İstek Yapısı
     * @GET /orders/active
     * Authorization: Bearer your-auth-token
     * Content-Type: application/json
     * {
     *   "page": 1,
     *   "per_page": 12
     * }    
     */
    public function getAwaitCourierTypeG(): JsonResponse
    {
        $orders = Order::where('status', ['PR']) // 'OC' ve 'DP' dışındaki durumları getir
            ->where('is_rejected', 'A')
            ->where('shipping_status', 'Y') // 'Y' (kargoya verilmiş) durumundaki siparişleri getir
            ->where('shipping_type', 'G') // 'A' tipindeki siparişleri getir
            ->when(request('invoice_type') === 'C', function ($query) {
                $query->whereHas('orderImages', function ($subQuery) {
                    $subQuery->where('type', 'I');
                });
            })
            ->when(request('invoice_type') === 'I', function ($query) {
                // 'I' fatura tipi için ekstra bir koşul uygulamıyoruz
            })
            ->with([
                'customer' => function ($query) {
                    // İlgili müşteri bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'manufacturer' => function ($query) {
                    // İlgili üretici bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'orderImages' => function ($query) {
                    // 'D' tipindeki resimleri getir
                    $query->where('type', 'D');
                },
                'customerInfo', // customerInfo ilişkisini ekledik
            ])
            ->orderByDesc('updated_at') // En son güncellenenlere göre sırala
            ->paginate(9);

        return response()->json(['orders' => $orders]);
    }

    public function getAwaitCourierTypeT(): JsonResponse
    {
        $orders = Order::where('status', 'PR') // 'PR' (in_progress) durumundaki siparişleri getir
            ->where('is_rejected', 'A') // Onaylanmış siparişleri getir
            ->where('shipping_status', 'Y') // Kargoya verilmiş siparişleri getir
            ->where('shipping_type', 'T') // 'T' tipindeki siparişleri getir
            ->when(request('invoice_type') === 'C', function ($query) {
                $query->whereHas('orderImages', function ($subQuery) {
                    $subQuery->where('type', 'I');
                });
            })
            ->when(request('invoice_type') === 'I', function ($query) {
                // 'I' fatura tipi için ekstra bir koşul uygulamıyoruz
            })
            ->with([
                'customer:id,name,email,profile_photo', // İlgili müşteri bilgilerini getir
                'manufacturer:id,name,email,profile_photo', // İlgili üretici bilgilerini getir
                'orderImages' => function ($query) {
                    $query->where('type', 'D'); // 'D' tipindeki resimleri getir
                },
                'customerInfo', // customerInfo ilişkisini ekledik
            ])
            ->orderByDesc('updated_at') // En son güncellenenlere göre sırala
            ->paginate(9);
            
        return response()->json(['orders' => $orders]);
    }

    /**
     * 'PR' (Hazırlanıyor) ve 'PD' (Paketlendi) durumlarındaki siparişleri getirir.
     *
     * @return JsonResponse
     * Örnek İstek Yapısı
     * @GET /orders/pending
     * Authorization: Bearer your-auth-token
     * Content-Type: application/json
     * {
     *   "page": 1,
     *   "per_page": 12
     * }    
     */

    public function getPendingOrders(): JsonResponse
    {
        $orders = Order::whereIn('status', ['PR', 'PD']) // Sadece 'PR' ve 'PD' durumlarındaki siparişleri getir
            ->where('is_rejected', 'A')
            ->when(request('invoice_type') === 'C', function ($query) {
                $query->whereHas('orderImages', function ($subQuery) {
                    $subQuery->where('type', 'I');
                });
            })
            ->when(request('invoice_type') === 'I', function ($query) {
                // 'I' fatura tipi için ekstra bir koşul uygulamıyoruz
            })
            ->with([
                'customer' => function ($query) {
                    // İlgili müşteri bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'manufacturer' => function ($query) {
                    // İlgili üretici bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'customerInfo', // customerInfo ilişkisini ekledik  
            ])
            ->orderBy('updated_at') // En eski tarihten itibaren sırala
            ->paginate(request('per_page', 12)); // Varsayılan olarak sayfa başına 12 kayıt getir

        return response()->json(['orders' => $orders]);
    }

    public function getOrderByCode($order_code): JsonResponse
    {
        try {

            $order = Order::where('order_code', $order_code)
                ->where('is_rejected', 'A')
                ->with([
                    'customer' => function ($query) {
                        $query->select('id', 'name', 'email', 'profile_photo');
                    },
                    'manufacturer' => function ($query) {
                        $query->select('id', 'name', 'email', 'profile_photo');
                    },
                    'baskets.items.productType',
                    'baskets.items.productCategory',
                    'baskets.logos',
                    'orderImages',
                    'customerInfo',
                    'invoiceInfo',
                    'orderAddress',
                    'cancelledOrder', 'rejectedOrder', 'orderCancelRequest'
                ])
                ->first(); // Eşleşen ilk kaydı getir veya null dön

            if ($order) {
                // İlgili resim tiplerini filtreleme
                $filteredImages = $order->orderImages
                    ->whereIn('type', ['L', 'D', 'P', 'PR', 'SC', 'PL'])
                    ->groupBy('type')
                    ->map(function ($images) {
                        return $images->map(function ($image) {
                            return [
                                'id' => $image->id,
                                'order_id' => $image->order_id,
                                'type' => $image->type,
                                'image_url' => asset($image->product_image),
                                'mime_type' => $image->mime_type,
                                'created_at' => $image->created_at,
                                'updated_at' => $image->updated_at,
                            ];
                        })->first(); // Sadece ilk resmi al
                    });


                // Dönüştürülmüş resimleri, sipariş nesnesine ekleyin
                $order->formatted_order_images = $filteredImages->toArray();
            }

            return response()->json(['order' => $order]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Kayıt bulunamadığında kullanıcıya döndürülecek yanıt
            return response()->json(['message' => 'Sipariş bulunamadı veya geçersiz.'], 404);
        }
    }

    public function search(Request $request)
    {
        // Arama sorgusu için gelen 'q' parametresini alın
        $query = $request->query('q');

        // 'orders' tablosunda 'order_name' ve 'order_code' sütunlarına göre arama yapın
        $orders = Order::query()
            ->where('order_name', 'LIKE', "%{$query}%")
            ->orWhere('order_code', 'LIKE', "%{$query}%")
            ->with([
                'customerInfo',
                'customer' => function ($query) {
                    // İlgili müşteri bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'manufacturer' => function ($query) {
                    // İlgili üretici bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
            ])
            ->orderBy('updated_at') // En eski tarihten itibaren sırala
            ->paginate(12)
            ->withQueryString();


        // Sonuçları JSON olarak döndürün
        return response()->json(['orders' => $orders]);
    }

    public function customerSearch(Request $request)
    {
        $query = $request->query('q');
        $userId = Auth::id(); // Oturum açmış kullanıcının ID'sini al

        $orders = Order::query()
            ->where('customer_id', $userId) // Yalnızca oturum açmış kullanıcının siparişlerini al
            ->where('order_name', 'LIKE', "%{$query}%")
            ->orWhere('order_code', 'LIKE', "%{$query}%")
            ->with([
                'customerInfo',
                'customer' => function ($query) {
                    // İlgili müşteri bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'manufacturer' => function ($query) {
                    // İlgili üretici bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
            ])
            ->orderBy('updated_at') // En eski tarihten itibaren sırala
            ->paginate(12)
            ->withQueryString();

        return response()->json(['orders' => $orders]);
    }

    public function manufacturerSearch(Request $request)
    {
        $query = $request->query('q');
        $userId = Auth::id(); // Oturum açmış kullanıcının ID'sini al

        // manufacturer_id değerine sahip tüm siparişleri getir
        $orders = Order::query()
            ->where('manufacturer_id', $userId) // Yalnızca oturum açmış üreticinin siparişlerini al
            ->where('order_name', 'LIKE', "%{$query}%")
            ->orWhere('order_code', 'LIKE', "%{$query}%")
            ->where('is_rejected', 'A')
            ->with([
                'customerInfo',
                'customer' => function ($query) {
                    // İlgili müşteri bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'manufacturer' => function ($query) {
                    // İlgili üretici bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
            ])
            ->orderBy('updated_at') // En eski tarihten itibaren sırala
            ->paginate(12)
            ->withQueryString();


        return response()->json(['orders' => $orders]);
    }

    public function desingerSearch(Request $request)
    {
        // Arama sorgusu için gelen 'q' parametresini alın
        $query = $request->query('q');

        // 'orders' tablosunda 'order_name' ve 'order_code' sütunlarına göre arama yapın
        $orders = Order::query()
            ->where('status', '!=', 'OC')
            ->where('order_name', 'LIKE', "%{$query}%")
            ->orWhere('order_code', 'LIKE', "%{$query}%")
            ->with([
                'customerInfo',
                'customer' => function ($query) {
                    // İlgili müşteri bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'manufacturer' => function ($query) {
                    // İlgili üretici bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
            ])
            ->orderBy('updated_at') // En eski tarihten itibaren sırala
            ->paginate(12)
            ->withQueryString();



        // Sonuçları JSON olarak döndürün
        return response()->json(['orders' => $orders]);
    }

    public function courierSearch(Request $request)
    {
        // Arama sorgusu için gelen 'q' parametresini alın
        $query = $request->query('q');

        // 'orders' tablosunda 'order_name' ve 'order_code' sütunlarına göre arama yapın
        $orders = Order::query()
            ->where('status',  ['PR', 'PD'])
            ->where('order_name', 'LIKE', "%{$query}%")
            ->orWhere('order_code', 'LIKE', "%{$query}%")
            ->with([
                'customerInfo',
                'customer' => function ($query) {
                    // İlgili müşteri bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'manufacturer' => function ($query) {
                    // İlgili üretici bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'orderImages' => function ($query) {
                    // 'D' tipindeki resimleri getir
                    $query->where('type', 'D');
                },
            ])
            ->orderBy('updated_at') // En eski tarihten itibaren sırala
            ->paginate(12)
            ->withQueryString();


        // Sonuçları JSON olarak döndürün
        return response()->json(['orders' => $orders]);
    }

    public function getProductionStagePOrders(): JsonResponse
    {
        $orders = Order::where('production_status', 'in_progress') // production_stage değeri 'P' olanları getir
            ->whereNotIn('status', ['OC', 'DP']) // 'OC' ve 'DP' dışındaki durumları getir
            ->where('is_rejected', 'A')
            ->with([
                'customer' => function ($query) {
                    // İlgili müşteri bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'manufacturer' => function ($query) {
                    // İlgili üretici bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'orderImages' => function ($query) {
                    // 'D' tipindeki resimleri getir
                    $query->where('type', 'D');
                },
                'customerInfo', // customerInfo ilişkisini ekledik
            ])
            ->orderBy('updated_at') // En eski tarihten itibaren sırala
            ->paginate(9);

        return response()->json(['orders' => $orders]);
    }
    
    public function getProductionStageCompletedOrders(): JsonResponse
    {
        $orders = Order::where('production_status', 'completed') // production_stage değeri 'P' olanları getir
            ->where('is_rejected', 'A')
            ->where('status', 'MS')
            ->with([
                'customer' => function ($query) {
                    // İlgili müşteri bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'manufacturer' => function ($query) {
                    // İlgili üretici bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'orderImages' => function ($query) {
                    // 'D' tipindeki resimleri getir
                    $query->where('type', 'D');
                },
                'customerInfo', // customerInfo ilişkisini ekledik
            ])
            ->orderBy('updated_at') // En eski tarihten itibaren sırala
            ->paginate(9);

        return response()->json(['orders' => $orders]);
    }


    public function getProductionStatusOrders(): JsonResponse
    {
        $orders = Order::where('production_status', 'in_progress') // production_status değeri 'in_progress' olanları getir
            ->where('is_rejected', 'A')
            ->with([
                'customer' => function ($query) {
                    // İlgili müşteri bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'manufacturer' => function ($query) {
                    // İlgili üretici bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'customerInfo', // customerInfo ilişkisini ekledik
                'orderItems.productType',
                'orderItems.productCategory',
                'productionImages',
            ])
            ->orderBy('updated_at') // En eski tarihten itibaren sırala
            ->get(); // Tüm sonuçları getir

        return response()->json(['orders' => $orders]);
    }

    /**
     * Order_code değerine göre siparişi getirir.
     *
     * @param  string  $order_code
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrderByCodeForCustomer($order_code): JsonResponse
    {
        // Siparişi order_code ile bul
        $order = Order::where('order_code', $order_code)->with([
            'customer' => function ($query) {
                $query->select('id', 'name', 'email', 'profile_photo');
            },
            'manufacturer' => function ($query) {
                $query->select('id', 'name', 'email', 'profile_photo');
            },
            'baskets.items.productType',
            'baskets.items.productCategory',
            'baskets.logos',
            'orderImages',
            'customerInfo',
            'invoiceInfo',
            'orderAddress',
            'designImages',
            'cancelledOrder',
            'rejectedOrder',
            'orderCancelRequest'
        ])->first();

        // Sipariş bulunamazsa hata döndür
        if ($order === null) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        // İlgili resim tiplerini filtreleme
        $filteredImages = $order->orderImages
            ->whereIn('type', ['L', 'D', 'P', 'PR', 'SC', 'PL'])
            ->groupBy('type')
            ->map(function ($images) {
                return $images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'order_id' => $image->order_id,
                        'type' => $image->type,
                        'image_url' => asset($image->product_image),
                        'mime_type' => $image->mime_type,
                        'created_at' => $image->created_at,
                        'updated_at' => $image->updated_at,
                    ];
                })->first(); // Sadece ilk resmi al
            });

        // Dönüştürülmüş resimleri, sipariş nesnesine ekleyin
        $order->formatted_order_images = $filteredImages->toArray();

        // Dönüştürülmüş sipariş nesnesini kullanabilirsiniz
        return response()->json(['order' => $order], 200);
    }
    
    // İsteğe bağlı olarak müşteri ID ve tarih aralıklarını alıyoruz
    public function getMonthlyOrderItemsCustomer(Request $request)
    {
        $customerId = $request->input('customer_id');
        $month = $request->input('month');
        $year = $request->input('year');

        // Ayın başlangıç ve bitiş tarihlerini hesapla
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        // Siparişleri ve ilişkili baskets ve items'ları al
        $orders = Order::where('customer_id', $customerId)
        ->where('status', 'PD')
        ->whereBetween('created_at', [$startDate, $endDate])
        ->with(['orderItems'])
        ->get();

        // Eğer $orders null veya boşsa
        if ($orders->isEmpty()) {
            return response()->json([]);
        }



        return response()->json($orders);
    }

    // İsteğe bağlı olarak müşteri ID ve tarih aralıklarını alıyoruz
    public function getMonthlyOrderItemsManufacturer(Request $request)
    {
        $customerId = $request->input('manufacturer_id');
        $month = $request->input('month');
        $year = $request->input('year');

        // Ayın başlangıç ve bitiş tarihlerini hesapla
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        // Siparişleri ve ilişkili baskets ve items'ları al
        $orders = Order::where('manufacturer_id', $customerId)
        ->where('status', 'PD')
        ->whereBetween('created_at', [$startDate, $endDate])
        ->with(['orderItems'])
        ->get();

        // Eğer $orders null veya boşsa
        if ($orders->isEmpty()) {
            return response()->json([]);
        }

        return response()->json($orders);
    }

    public function getMonthlyOrderItemsAll(Request $request)
    {
        $month = $request->input('month');
        $year = $request->input('year');

        // Ayın başlangıç ve bitiş tarihlerini hesapla
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        // Siparişleri ve ilişkili baskets ve items'ları al
        $orders = Order::where('status', 'PD')
        ->whereBetween('created_at', [$startDate, $endDate])
        ->with(['orderItems'])
        ->get();

        // Eğer $orders null veya boşsa
        if ($orders->isEmpty()) {
            return response()->json([]);
        }



        return response()->json($orders);
    }

    // İsteğe bağlı olarak müşteri ID ve tarih aralıklarını alıyoruz
    public function getPDFMonthlyOrderItemsCustomer(Request $request)
    {
        $customerId = $request->input('customer_id');
        $month = $request->input('month');
        $year = $request->input('year');

        // Ayın başlangıç ve bitiş tarihlerini hesapla
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        // Siparişleri ve ilişkili baskets ve items'ları al
        $orders = Order::where('customer_id', $customerId)
        ->where('status', 'PD')
        ->whereBetween('created_at', [$startDate, $endDate])
        ->with([
            'orderItems' => function ($query) {
                $query->with(['productType' => function ($query) {
                        $query->select('id', 'product_type as type', 'product_category_id')
                            ->where('product_category_id', 1); // Örneğin product_category_id 1 olanları getir
                    }]);
            }
        ])
        ->get();


        // Eğer $orders null veya boşsa
        if ($orders->isEmpty()) {
            return response()->json([]);
        }

        $this->generatePDF($orders);


        return response()->json($orders);
    }

    public function generatePDF($orders)
    {
   // Dompdf ayarları
    $options = new Options();
    $options->set('defaultFont', 'Arial');

    // Dompdf oluştur
    $dompdf = new Dompdf($options);

    // HTML içeriği oluştur (örneğin pdf/orders.blade.php view dosyasını kullanabiliriz)
    $html = view('pdf.orders', compact('orders'))->render();

    // PDF oluştur
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    // Dosya sistemine kaydetmek için yol oluştur
    $filePath = storage_path('app/public/monthly_orders.pdf');

    // Dosyayı kaydet
    file_put_contents($filePath, $dompdf->output());

    // Dosya yolunu döndür
    return $filePath;
    }
}