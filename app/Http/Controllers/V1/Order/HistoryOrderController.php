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
            ->whereDoesntHave('orderItems', function ($query) {
                $query->where('status', 'PD'); // 'PD' durumundaki orderItems'ı hariç tut
            })
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
                'logoImage' => function ($query) {
                    $query->select('id', 'order_id', 'product_image', 'type');
                }       
                ])
            ->orderByDesc('updated_at') // En son güncellenenlere göre sırala
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
                    $query->select('id', 'name' ,'email', 'profile_photo');
                },
                'logoImage' => function ($query) {
                    $query->select('id', 'order_id', 'product_image', 'type');
                }       
            ])
            ->orderByDesc('updated_at')
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
            ->whereDoesntHave('orderItems', function ($query) {
                $query->where('status', 'PD'); // 'PD' durumundaki orderItems'ı hariç tut
            })
            ->with([
                'customerInfo',
                'customer' => function ($query) {
                    $query->select('id', 'name', 'email', 'profile_photo');
                },
                'manufacturer' => function ($query) {
                    $query->select('id', 'name' ,'email', 'profile_photo');
                },
                'logoImage' => function ($query) {
                    $query->select('id', 'order_id', 'product_image', 'type');
                }       
            ])
            ->orderByDesc('updated_at')
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
                    $query->select('id', 'name' ,'email', 'profile_photo');
                },
                'logoImage' => function ($query) {
                    $query->select('id', 'order_id', 'product_image', 'type');
                }       
            ])
            ->orderByDesc('updated_at')
            ->paginate(12); 

        return response()->json(['orders' => $orders]);
    }


        /**
     * Belirli bir müşteriye ait, belirli bir ay ve yıl içinde oluşturulmuş,
     * durum kodu 'PD' olan ve 'is_rejected' değeri 'A' olarak işaretlenmiş siparişleri ve
     * bu siparişlere ait sipariş kalemlerini getirir.
     * Her sipariş kalemi için birim maliyeti kullanarak toplam maliyeti hesaplar ve
     * bu maliyeti siparişin teklif fiyatından düşerek net teklif fiyatını bulur.
     * Tüm siparişlerin sipariş kalemlerindeki miktarların toplamını,
     * tüm teklif fiyatlarının toplamını ve net teklif fiyatlarının toplamını hesaplar.
     * Bu bilgileri bir JSON nesnesi olarak döndürür.
     *
     * @param Request $request HTTP isteği, ay ve yıl bilgilerini içerir.
     * @param int $customerId Müşteri ID'si.
     * @return \Illuminate\Http\JsonResponse Siparişler ve hesaplanan toplamlar içeren JSON cevabı.
     */
    public function getCustomerOrdersWithCosts(Request $request, $customerId)
    {
        // Girdi parametrelerini al veya mevcut ay ve yıl bilgilerini kullan
        $month = $request->input('month', Carbon::now()->month);
        $year = $request->input('year', Carbon::now()->year);
        $unitCost = 500; // Birim başı maliyet

        // Siparişleri ve sipariş kalemlerini getir
        $orders = Order::where('customer_id', $customerId)
            ->whereMonth('created_at', '=', $month)
            ->whereYear('created_at', '=', $year)
            ->where('status', 'PD') // Durum kodu PD olan siparişler
            ->where('is_rejected', 'A') // is_rejected değeri A olan siparişler
            ->with(['orderItems' => function($query) use ($unitCost) {
                $query->with(['productCategory' => function($query) {
                    $query->select('id', 'category');
                }])
                ->select('id', 'order_id', 'product_type_id', 'product_category_id', 'quantity', 'color', 'unit_price', 'type', 'created_at', 'updated_at');
            }])
            ->get(['id', 'order_name', 'customer_id', 'order_code', 'status', 'offer_price'])
            ->map(function ($order) use ($unitCost) {
                // Sipariş kalemleri için toplam maliyeti hesapla
                $totalCost = $order->orderItems->sum(function ($item) use ($unitCost) {
                    return $item->quantity * $unitCost;
                });
                $order->net_offer_price = $order->offer_price - $totalCost;
                return $order;
            });

        // Yeni toplamları hesapla
        $totalQuantities = $orders->sum(function ($order) {
            return $order->orderItems->sum('quantity');
        });

        $totalOfferPrice = $orders->sum('offer_price');
        $totalNetOfferPrice = $orders->sum('net_offer_price');

        // Toplamları bir değişkende sakla
        $totals = [
            'total_quantities' => $totalQuantities,
            'total_offer_price' => $totalOfferPrice,
            'total_net_offer_price' => $totalNetOfferPrice
        ];

        // Siparişler ve toplamları ile birlikte JSON olarak döndür
        return response()->json([
            'orders' => $orders,
            'totals' => $totals
        ]);
    }

        /**
     * Belirli bir üreticiye ait, belirli bir ay ve yıl içinde oluşturulmuş,
     * durum kodu 'PD' olan ve 'is_rejected' değeri 'A' olarak işaretlenmiş siparişlerin
     * sipariş kalemlerindeki miktarların toplamını döndürür.
     *
     * @param Request $request HTTP isteği, ay ve yıl bilgilerini içerir.
     * @param int $manufacturerId Üretici ID'si.
     * @return \Illuminate\Http\JsonResponse Sipariş kalemlerinin toplam miktarı içeren JSON cevabı.
     */
    public function getManufacturerOrdersQuantities(Request $request, $manufacturerId)
    {
        // Girdi parametrelerini al
        $month = $request->input('month', Carbon::now()->month);
        $year = $request->input('year', Carbon::now()->year);

        // Siparişleri ve sipariş kalemlerini getir
        $orders = Order::with(['orderItems' => function($query) {
            $query->with(['productCategory' => function($query) {
                $query->select('id', 'name');
            }])
            ->select('id', 'order_id', 'product_type_id', 'product_category_id', 'quantity', 'color', 'type', 'created_at', 'updated_at');
        }])
        ->where('manufacturer_id', $manufacturerId) // Üretici ID'sine göre sorgula
        ->whereMonth('created_at', '=', $month)
        ->whereYear('created_at', '=', $year)
        ->where('status', 'PD') // Durum kodu PD olan siparişler
        ->where('is_rejected', 'A') // is_rejected değeri A olan siparişler
        ->get(['id', 'order_name', 'manufacturer_id', 'order_code', 'status']);

        // Sipariş kalemlerinin toplam miktarını hesapla
        $totalQuantities = $orders->sum(function ($order) {
            return $order->orderItems->sum('quantity');
        });

        // Toplam miktarı JSON olarak döndür
        return response()->json([
            'total_quantities' => $totalQuantities
        ]);
    }

        /**
     * Belirli bir ay ve yıl içinde oluşturulmuş,
     * durum kodu 'PD' olan ve 'is_rejected' değeri 'A' olarak işaretlenmiş siparişleri ve
     * bu siparişlere ait sipariş kalemlerini getirir.
     * Sipariş kalemlerindeki miktarların toplamını hesaplar ve
     * bu bilgiyi bir JSON nesnesi olarak döndürür.
     *
     * @param Request $request HTTP isteği, ay ve yıl bilgilerini içerir.
     * @return \Illuminate\Http\JsonResponse Sipariş kalemlerinin toplam miktarı içeren JSON cevabı.
     */
    public function getMonthlyOrderInfo(Request $request)
    {
        // Girdi parametrelerini al
        $month = $request->input('month');
        $year = $request->input('year');

        // Siparişleri ve sipariş kalemlerini getir
        $orders = Order::whereMonth('created_at', '=', $month)
        ->whereYear('created_at', '=', $year)
        ->where('status', 'PD') // Durum kodu PD olan siparişler
        ->where('is_rejected', 'A') // is_rejected değeri A olan siparişler
        ->with(['orderItems' => function($query) {
            $query->with(['productCategory' => function($query) {
                $query->select('id', 'name');
            }])
            ->select('id', 'order_id', 'product_type_id', 'product_category_id', 'quantity', 'color', 'type', 'created_at', 'updated_at');
        }])
        ->get(['id', 'order_name', 'order_code', 'status'])
        ->map(function ($order) {
            // Sipariş kalemlerinin toplam miktarını hesapla
            $order->total_quantity = $order->orderItems->sum('quantity');
            return $order;
        });

        // Toplam miktarları hesapla
        $totalQuantities = $orders->sum('total_quantity');

        // Toplam miktarı JSON olarak döndür
        return response()->json([
            'total_quantities' => $totalQuantities
        ]);
    }

    /**
     * Belirli bir müşteriye ait, belirli bir ay ve yıl içinde oluşturulmuş,
     * durum kodu 'PD' olan ve 'is_rejected' değeri 'A' olarak işaretlenmiş siparişleri ve
     * bu siparişlere ait sipariş kalemlerini getirir.
     * Her sipariş kalemi için birim maliyeti kullanarak toplam maliyeti hesaplar ve
     * bu maliyeti siparişin teklif fiyatından düşerek net teklif fiyatını bulur.
     * Tüm siparişlerin sipariş kalemlerindeki miktarların toplamını,
     * tüm teklif fiyatlarının toplamını ve net teklif fiyatlarının toplamını hesaplar.
     * Bu bilgileri bir JSON nesnesi olarak döndürür.
     *
     * @param Request $request HTTP isteği, ay ve yıl bilgilerini içerir.
     * @param int $customerId Müşteri ID'si.
     * @return \Illuminate\Http\JsonResponse Siparişler ve hesaplanan toplamlar içeren JSON cevabı.
     */
    public function getCustomerOrderPDF(Request $request, $customerId)
    {
        // Girdi parametrelerini al veya mevcut ay ve yıl bilgilerini kullan
        $month = $request->input('month', Carbon::now()->month);
        $year = $request->input('year', Carbon::now()->year);
        $unitCost = 500; // Birim başı maliyet

        // Siparişleri ve sipariş kalemlerini getir
        $orders = Order::where('customer_id', $customerId)
            ->whereMonth('created_at', '=', $month)
            ->whereYear('created_at', '=', $year)
            ->where('status', 'PD') // Durum kodu PD olan siparişler
            ->where('is_rejected', 'A') // is_rejected değeri A olan siparişler
            ->with(['orderItems' => function($query) use ($unitCost) {
                $query->with(['productCategory' => function($query) {
                    $query->select('id', 'category');
                }])
                ->select('id', 'order_id', 'product_type_id', 'product_category_id', 'quantity', 'color', 'unit_price', 'type', 'created_at', 'updated_at');
            }])
            ->get(['id', 'order_name', 'customer_id', 'order_code', 'status', 'offer_price'])
            ->map(function ($order) use ($unitCost) {
                // Sipariş kalemleri için toplam maliyeti hesapla
                $totalCost = $order->orderItems->sum(function ($item) use ($unitCost) {
                    return $item->quantity * $unitCost;
                });
                $order->net_offer_price = $order->offer_price - $totalCost;
                return $order;
            });

        // Yeni toplamları hesapla
        $totalQuantities = $orders->sum(function ($order) {
            return $order->orderItems->sum('quantity');
        });

        $totalOfferPrice = $orders->sum('offer_price');
        $totalNetOfferPrice = $orders->sum('net_offer_price');

        // Toplamları bir değişkende sakla
        $totals = [
            'total_quantities' => $totalQuantities,
            'total_offer_price' => $totalOfferPrice,
            'total_net_offer_price' => $totalNetOfferPrice
        ];

        // PDF'yi oluştur
        $data = [
            'orders' => $orders,
            'totals' => $totals
        ];

        // PDF oluşturma ve saklama
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
            
        $view = view('orders_pdf', $data)->render();
        $dompdf->loadHtml($view);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
            
        $output = $dompdf->output();
        $fileName = 'orders_report_' . time() . '.pdf';
        $filePath = 'pdf/' . $fileName;
            
        // Storage diskine storeAs yöntemi ile kaydetme (public disk)
        Storage::disk('public')->put($filePath, $output);
            
        return response()->json([
            'message' => 'PDF successfully created',
            'pdf_url' => Storage::url($filePath)
        ]);
    }
}