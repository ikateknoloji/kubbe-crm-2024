<?php

namespace App\Http\Controllers\V1\Order;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GetRejectedController extends Controller
{
    /**
     * Admin tarafından reddedilen ('CR' ve 'MR') siparişleri getirir.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrdersByStatus($status)
    {
        // Oturum açmış kullanıcının ID'sini al
        $userId = Auth::id();
    
        // Yalnızca oturum açmış kullanıcının 'customer_id'sine sahip ve belirli bir 'status' değerine sahip siparişleri getir
        $orders = Order::where('customer_id', $userId)
            ->where('status', $status)
            ->with([
                'customer' => function ($query) {
                    $query->select('id', 'email', 'profile_photo');
                },
                'manufacturer' => function ($query) {
                    $query->select('id', 'email', 'profile_photo');
                },
                'cancelledOrder', 'rejectedOrder', 'orderCancelRequest'  
            ])
            ->orderByDesc('updated_at')
            ->paginate();
            
        return response()->json(['orders' => $orders], 200);
    }
    
    /**
     * Oturum açmış kullanıcının reddedilen siparişleri getir.
     *
     * @return \Illuminate\Http\JsonResponse
     */ 
    public function getCustomerRejectedOrders()
    {
        $userId = Auth::id(); // Oturum açmış kullanıcının ID'sini al
    
        $orders = Order::where('customer_id', $userId)
            ->whereIn('is_rejected', ['R'])
            ->with([
                'customer' => function ($query) {
                    $query->select('id', 'email', 'profile_photo');
                },
                'manufacturer' => function ($query) {
                    $query->select('id', 'email', 'profile_photo');
                },
                'rejectedOrder',
                'cancelledOrder',
                'orderCancelRequest',
            ])
            ->orderByDesc('updated_at')
            ->paginate();
            
        return response()->json(['orders' => $orders], 200);
    }

    /**
     * Parametre olarak verilen durum koduna sahip siparişleri getirir.
     *
     * @param string $status Durum kodu
     * @return JsonResponse
     */
    public function getRejectedOrdersByRejectedStatus(string $status): JsonResponse
    {
        // Verilen durum koduna sahip siparişleri al
        $orders = Order::where('is_rejected', $status)
            ->with([
                'customerInfo',
                'customer' => function ($query) {
                    // İlgili müşteri bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'manufacturer' => function ($query) {
                    // İlgili üretici bilgilerini getir
                    $query->select('id', 'name' ,'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'cancelledOrder', 'rejectedOrder', 'orderCancelRequest'  
                ])
            ->orderByDesc('updated_at') // En son güncellenenlere göre sırala
            ->paginate(12);

        return response()->json(['orders' => $orders]);
    }

    /**
     * Parametre olarak verilen durum koduna sahip siparişleri getirir.
     *
     * @param string $status Durum kodu
     * @return JsonResponse
     */
    public function getCustomerRejectedOrdersByRejectedStatus(string $status): JsonResponse
    {        
        // Verilen durum koduna sahip siparişleri al
        $orders = Order::where('is_rejected', $status)
            ->where('customer_id', Auth::id())
            ->with([
                'customerInfo',
                'customer' => function ($query) {
                    // İlgili müşteri bilgilerini getir
                    $query->select('id', 'name', 'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'manufacturer' => function ($query) {
                    // İlgili üretici bilgilerini getir
                    $query->select('id', 'name' ,'email', 'profile_photo'); // User modelinizdeki mevcut sütunlar
                },
                'cancelledOrder', 'rejectedOrder', 'orderCancelRequest'  
                ])
            ->orderByDesc('updated_at') // En son güncellenenlere göre sırala
            ->paginate(12);

        return response()->json(['orders' => $orders]);
    }
}