<?php

namespace App\Http\Controllers\V1\Manage;


use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderCancelRequest;
use App\Models\RejectedOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ManageOrderController extends Controller
{
    /**
     * Bir sipariş için iptal isteği oluşturur ve siparişin durumunu 'P' olarak günceller.
     *
     * @param Request $request
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @POST /order-cancel-requests/{orderId}
     * {
     *   "title": "İptal isteği",
     *   "reason": "Red sebebi"
     * }
     */
    public function createCancelRequestAndUpdateStatus(Request $request, $orderId)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'reason' => 'required|string|max:65535',
        ], [
            'title.required' => 'Başlık alanı zorunludur.',
            'title.string' => 'Başlık bir metin olmalıdır.',
            'title.max' => 'Başlık en fazla 255 karakter olabilir.',
            'reason.required' => 'Sebep alanı zorunludur.',
            'reason.string' => 'Sebep bir metin olmalıdır.',
            'reason.max' => 'Sebep en fazla 65535 karakter olabilir.',
        ]);

        // Doğrulama başarısızsa hata mesajlarını döndür
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        // Siparişi bul
        $order = Order::findOrFail($orderId);

        // Siparişin 'is_rejected' değerini kontrol et
        if ($order->is_rejected !== 'A') {
            // Eğer 'is_rejected' değeri 'A' değilse işlemi gerçekleştirme
            return response()->json(['error' => 'Bu sipariş reddedilemez durumda.'], 403);
        }

        // İptal isteği oluştur
        $cancelRequest = OrderCancelRequest::create([
            'order_id' => $order->id,
            'title' => $request->input('title'),
            'reason' => $request->input('reason'),
        ]);

        // Siparişin 'is_rejected' durumunu 'P' olarak güncelle
        $order->update(['is_rejected' => 'P']);


        // Başarılı yanıt döndür
        return response()->json([
            'message' => 'İptal isteği oluşturuldu ve sipariş durumu güncellendi.',
            'orderCancelRequest' => $cancelRequest
        ], 200);
    }

    /**
     * Bir sipariş için reddedilmiş olan sipariş kaydını oluşturur ve siparişin durumunu 'R' olarak günceller.
     *
     * @param Request $request
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @POST /order-cancel-requests/{orderId}
     * {
     *   "title": "İptal isteği",
     *   "reason": "Red sebebi"
     * }
     */
    public function rejectOrder(Request $request, $orderId)
    {
        // Gelen isteği doğrula
        $validator = Validator::make($request->all(), 
            [
                'title' => 'required|string|max:255',
                'reason' => 'required|string|max:65535',
            ], 
            [
                'title.required' => 'Başlık alanı gereklidir.',
                'title.string' => 'Başlık metin tipinde olmalıdır.',
                'title.max' => 'Başlık çok uzun. Maksimum 255 karakter olmalıdır.',
                'reason.required' => 'Red sebebi alanı gereklidir.',
                'reason.string' => 'Red sebebi metin tipinde olmalıdır.',
                'reason.max' => 'Red sebebi çok uzun. Maksimum 65535 karakter olmalıdır.',
            ]
        );
    
        // Doğrulama başarısızsa hata mesajlarını döndür
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }
    
        // Siparişi bul
        $order = Order::findOrFail($orderId);
    
        // Siparişin 'is_rejected' değerini kontrol et
        if ($order->is_rejected !== 'A') {
            // Eğer 'is_rejected' değeri 'A' değilse işlemi gerçekleştirme
            return response()->json(['error' => 'Bu sipariş reddedilemez durumda.'], 403);
        }

        // Reddedilen sipariş kaydını oluştur
        $rejectedOrder = RejectedOrder::create([
            'order_id' => $order->id,
            'title' => $request->input('title'),
            'reason' => $request->input('reason'),
        ]);
    
        // Sipariş durumunu güncelle (örneğin, 'rejected' olarak güncelle)
        $order->update(['is_rejected' => 'R']);
    

        // Başarılı yanıt döndür
        return response()->json([
            'mesaj' => 'Sipariş başarıyla reddedildi.',
            'rejectedOrder' => $rejectedOrder
        ], 200);
    }

    /**
     * Bir sipariş için iptal isteği oluşturur ve siparişin durumunu 'P' olarak günceller.
     *
     * @param Request $request
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @POST /order-cancel-requests/{orderId}
     * {
     *   "title": "İptal isteği",
     *   "reason": "Red sebebi"
     * }
     */
    public function processCancellation($orderId)
    {
        // Siparişi bul
        $order = Order::findOrFail($orderId);
    
        // Siparişin iptal durumunu kontrol et
        if ($order->is_rejected === 'P') {
            // İptal işlemini gerçekleştir ve durumu 'C' olarak güncelle
            $order->update([
                'is_rejected' => 'C'
            ]);
    
            
            // İptal işlemi başarılı yanıtını döndür
            return response()->json([
                'mesaj' => 'Sipariş iptali başarıyla gerçekleştirildi.',
                'order' => $order
            ], 200);

        } else {
            // İptal işlemine izin verilmiyorsa hata mesajı döndür
            return response()->json(['hata' => 'Sipariş iptali için uygun durumda değil.'], 403);
        }
    }   

    /**
     * Bir sipariş için reddedilmiş olan sipariş kaydını oluşturur ve siparişin durumunu 'R' olarak günceller.
     * @param Request $request
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @POST /order-cancel-requests/{orderId}
     * {
     *   "title": "İptal isteği",
     *   "reason": "Red sebebi"
     * }
     */
    public function activateOrder($orderId)
    {
        // Siparişi bul
        $order = Order::findOrFail($orderId);

        // Siparişin 'is_rejected' değerini kontrol et
        if ($order->is_rejected === 'A') {
            // Eğer 'is_rejected' değeri 'A' değilse işlemi gerçekleştirme
            return response()->json(['error' => 'Bu sipariş reddedilemez durumda.'], 403);
        }

        // Siparişin 'is_rejected' durumunu 'A' olarak güncelle
        $order->update(['is_rejected' => 'A']);

        // İlgili reddedilmiş sipariş kaydını bul ve sil
        $rejectedOrder = RejectedOrder::where('order_id', $orderId)->first();
        if ($rejectedOrder) {
            $rejectedOrder->delete();
        }


        // Başarılı yanıt döndür
        return response()->json([
            'mesaj' => 'Sipariş başarıyla aktif hale getirildi ve ilişkili red mesajları silindi.',
            'order' => $order
        ], 200);
    }

    /**
     * Bir sipariş için reddedilmiş olan sipariş kaydını oluşturur ve siparişin durumunu 'R' olarak günceller.
     * @param Request $request
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @POST /order-cancel-requests/{orderId}
     */
    public function activateOrderAndRemoveCancellationRequest($orderId)
    {
        // Siparişi bul
        $order = Order::findOrFail($orderId);

        // Siparişin iptal durumunu kontrol et
        if ($order->is_rejected === 'P') {
            // Siparişi aktif hale getir ve durumu 'A' olarak güncelle
            $order->update(['is_rejected' => 'A']);

            // İlgili iptal isteğini bul ve sil
            $cancelRequest = OrderCancelRequest::where('order_id', $orderId)->first();
            if ($cancelRequest) {
                $cancelRequest->delete();
            }


            // Başarılı yanıt döndür
            return response()->json([
                'mesaj' => 'Sipariş başarıyla aktif hale getirildi ve iptal isteği kaldırıldı.',
                'order' => $order
            ], 200);
        } else {
            // İptal isteği bulunamazsa veya sipariş zaten aktifse hata mesajı döndür
            return response()->json(['hata' => 'Sipariş zaten aktif veya iptal isteği bulunamadı.'], 404);
        }
    }

}