<?php

namespace App\Http\Controllers\V1\Order;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Dompdf\Dompdf;
use Dompdf\Options;

class HistoryOrderController extends Controller
{
    /**
     * Aktif durumda olan ve belirli bir müşteriye ait teslim edilmemiş siparişleri getirir.
     *
     * @param int $customerId Müşteri ID'si
     * @return JsonResponse
     * Örnek İstek Yapısı
     * @GET /orders/active/{customerId}
     * Authorization: Bearer your-auth-token
     * Content-Type: application/json
     * {
     *   "page": 1,
     *   "per_page": 12
     * }    
     */
    public function getCustomerActiveOrders(int $customerId): JsonResponse
    {
        // 'A' (Active) durumuna sahip, belirli bir müşteriye ait ve teslim edilmemiş siparişleri al
        $orders = Order::where('is_rejected', 'A')
            ->where('customer_id', $customerId)
            ->where('status', '!=', 'PD')
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

    public function getCustomerOrderHistory(int $customerId): JsonResponse
    {
        // 'A' (Active) durumuna sahip, belirli bir müşteriye ait ve 'PD' (Paid) durumundaki siparişleri al
        $orders = Order::where('is_rejected', 'A')
            ->where('customer_id', $customerId)
            ->where('status', 'PD')
            ->with([
                'customerInfo',
                'customer' => function ($query) {
                    $query->select('id', 'name', 'email', 'profile_photo');
                },
                'manufacturer' => function ($query) {
                    $query->select('id', 'name', 'email', 'profile_photo');
                }
            ])
            ->orderBy('updated_at') // En eski tarihten itibaren sırala
            ->paginate(12);

        return response()->json(['orders' => $orders]);
    }

    /**
     * Aktif durumda olan ve belirli bir üreticiye ait teslim edilmemiş siparişleri getirir.
     *
     * @param int $manufacturerId Üretici ID'si
     * @return JsonResponse
     * Örnek İstek Yapısı
     * @GET /orders/active-manufacturer/{manufacturerId}
     * Authorization: Bearer your-auth-token
     * Content-Type: application/json
     * {
     *   "page": 1,
     *   "per_page": 12
     * }    
     */
    public function getManufacturerActiveOrders(int $manufacturerId): JsonResponse
    {
        // 'A' (Active) durumuna sahip, belirli bir üreticiye ait ve teslim edilmemiş siparişleri al
        $orders = Order::where('is_rejected', 'A')
            ->where('manufacturer_id', $manufacturerId)
            ->with([
                'customerInfo',
                'customer' => function ($query) {
                    $query->select('id', 'name', 'email', 'profile_photo');
                },
                'manufacturer' => function ($query) {
                    $query->select('id', 'name', 'email', 'profile_photo');
                }
            ])
            ->orderBy('updated_at') // En eski tarihten itibaren sırala
            ->paginate(12);

        return response()->json(['orders' => $orders]);
    }

    /**
     * Belirli bir üreticiye ait ve 'PD' (Paid) durumundaki sipariş geçmişini getirir.
     *
     * @param int $manufacturerId Üretici ID'si
     * @return JsonResponse
     * Örnek İstek Yapısı
     * @GET /orders/history-manufacturer/{manufacturerId}
     * Authorization: Bearer your-auth-token
     * Content-Type: application/json
     * {
     *   "page": 1,
     *   "per_page": 12
     * }    
     */
    public function getManufacturerOrderHistory(int $manufacturerId): JsonResponse
    {
        // 'A' (Active) durumuna sahip, belirli bir üreticiye ait ve 'PD' (Paid) durumundaki siparişleri al
        $orders = Order::where('is_rejected', 'A')
            ->where('manufacturer_id', $manufacturerId)
            ->where('status', 'PD')
            ->with([
                'customerInfo',
                'customer' => function ($query) {
                    $query->select('id', 'name', 'email', 'profile_photo');
                },
                'manufacturer' => function ($query) {
                    $query->select('id', 'name', 'email', 'profile_photo');
                }
            ])
            ->orderBy('updated_at') // En eski tarihten itibaren sırala
            ->paginate(12);

        return response()->json(['orders' => $orders]);
    }
}