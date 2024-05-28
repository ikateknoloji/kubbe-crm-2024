<?php

namespace App\Http\Controllers\V1\Order;

use App\Events\AdminNotificationEvent;
use App\Events\CourierNotificationEvent;
use App\Events\CustomerNotificationEvent;
use App\Events\DesignerNotificationEvent;
use App\Events\ManufacturerNotificationEvent;
use App\Http\Controllers\Controller;
use App\Models\CancelledOrder;
use App\Models\Order;
use App\Models\OrderCancelRequest;
use App\Models\RejectedOrder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class RejectOrderController extends Controller
{
    /**
     * Belirli bir siparişi iptal etmek için kullanılır.
     *
     * @param Request $request İstek nesnesi
     * @return JsonResponse
     */
    public function cancelOrder(Request $request): JsonResponse
    {
        $rules = [
            'order_id' => 'required|integer',
            'title' => 'required|string',
            'reason' => 'required|string',
        ];

        $messages = [
            'order_id.required' => 'Sipariş ID alanı gereklidir.',
            'order_id.integer' => 'Sipariş ID alanı bir tamsayı olmalıdır.',
            'title.required' => 'Başlık alanı gereklidir.',
            'title.string' => 'Başlık alanı metin olmalıdır.',
            'reason.required' => 'Neden alanı gereklidir.',
            'reason.string' => 'Neden alanı metin olmalıdır.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        
        if ($validator->fails()) {
            // Hataları döndür
            return response()->json(['error' => $validator->errors()->first()], 400);
        }
    
        // İptal isteği oluştur ve veritabanına kaydet
        CancelledOrder::create([
            'order_id' => $request->input('order_id'),
            'title' => $request->input('title'),
            'reason' => $request->input('reason'),
        ]);

        $order = Order::where('id', $request->input('order_id'))->first();
        if (!$order) {
            return response()->json(['error' => 'Sipariş bulunamadı.'], 404);
        }

        $order->update(['is_rejected' => 'C']);

        $message = [
            'title' => 'Sipariş İptal Edildi',
            'body' => 'Siparişiniz iptal edildi.',
            'order' => $order
        ];

        // Bildirimi yayınla
        broadcast(new CustomerNotificationEvent($order->customer_id, $message));
    
        if ($order->manufacturer_id !== null) {
            $message = [
                'title' => 'Sipariş İptal Edildi',
                'body' => 'Siparişiniz iptal edildi.',
                'order' => $order
            ];

            // Bildirimi yayınla
            broadcast(new ManufacturerNotificationEvent($order->manufacturer_id, $message));
        }

        if ($order->status !== 'Sipariş Onayı') {
            $message = [
                'title' => 'Sipariş İptal Edildi',
                'body' => 'Siparişiniz iptal edildi.',
                'order' => $order
            ];

            // Bildirimi yayınla
            broadcast(new DesignerNotificationEvent($message));
        }

        
        if ($order->status == 'Ürün Hazır'|| $order->status == 'Ürün Transfer Aşaması') {
            $message = [
                'title' => 'Sipariş İptal Edildi',
                'body' => 'Siparişiniz iptal edildi.',
                'order' => $order
            ];

            // Bildirimi yayınla
            broadcast(new CourierNotificationEvent($message));
        }
        
        return response()->json(['message' => 'İptal isteği başarıyla oluşturuldu.']);
    }

    /**
     * Belirli bir siparişi iptal etmek için kullanılır.
     *
     * @param Request $request İstek nesnesi
     * @return JsonResponse
     */
    public function rejectOrder(Request $request): JsonResponse
    {
        $rules = [
            'order_id' => 'required|integer',
            'title' => 'required|string',
            'reason' => 'required|string',
        ];

        $messages = [
            'order_id.required' => 'Sipariş ID alanı gereklidir.',
            'order_id.integer' => 'Sipariş ID alanı bir tamsayı olmalıdır.',
            'title.required' => 'Başlık alanı gereklidir.',
            'title.string' => 'Başlık alanı metin olmalıdır.',
            'reason.required' => 'Neden alanı gereklidir.',
            'reason.string' => 'Neden alanı metin olmalıdır.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        
        if ($validator->fails()) {
            // Hataları döndür
            return response()->json(['error' => $validator->errors()->first()], 400);
        }
    
        $order = Order::where('id', $request->input('order_id'))->first();
        if (!$order) {
            return response()->json(['error' => 'Sipariş bulunamadı.'], 404);
        }

        $order->update(['is_rejected' => 'R']);

        // İptal isteği oluştur ve veritabanına kaydet
        RejectedOrder::create([
            'order_id' => $request->input('order_id'),
            'title' => $request->input('title'),
            'reason' => $request->input('reason'),
        ]);

        $message = [
            'title' => 'Sipariş red Edildi',
            'body' => 'Siparişiniz red edildi.',
            'order' => $order
        ];

        // Bildirimi yayınla
        broadcast(new CustomerNotificationEvent($order->customer_id, $message));
    
        if ($order->manufacturer_id !== null) {
            $message = [
                'title' => 'Sipariş İptal Edildi',
                'body' => 'Siparişiniz iptal edildi.',
                'order' => $order
            ];

            // Bildirimi yayınla
            broadcast(new ManufacturerNotificationEvent($order->manufacturer_id, $message));
        }

        if ($order->status !== 'Sipariş Onayı') {
            $message = [
                'title' => 'Sipariş İptal Edildi',
                'body' => 'Siparişiniz iptal edildi.',
                'order' => $order
            ];

            // Bildirimi yayınla
            broadcast(new DesignerNotificationEvent($message));
        }

        
        if ($order->status == 'Ürün Hazır'|| $order->status == 'Ürün Transfer Aşaması') {
            $message = [
                'title' => 'Sipariş İptal Edildi',
                'body' => 'Siparişiniz iptal edildi.',
                'order' => $order
            ];

            // Bildirimi yayınla
            broadcast(new CourierNotificationEvent($message));
        }
    
        return response()->json(['message' => 'İptal isteği başarıyla oluşturuldu.']);
    }

    /**
     * Belirli bir siparişi aktif hale getirir.
     *
     * @param int $orderId Sipariş ID'si
     * @return JsonResponse
     */
    public function activateOrder(int $orderId): JsonResponse
    {
        // Siparişi bul
        $order = Order::find($orderId);
    
        if (!$order) {
            return response()->json(['error' => 'Sipariş bulunamadı.'], 404);
        }
    
        // Siparişi aktif hale getir
        $order->is_rejected = 'A'; // Aktif
        $order->save();
    
        $message = [
            'title' => 'Sipariş Aktif Hale Getirildi',
            'body' => 'Sipariş artık aktif hale getirildi.',
            'order' => $order
        ];

        // Bildirimi yayınla
        broadcast(new CustomerNotificationEvent($order->customer_id, $message));
    
        if ($order->manufacturer_id !== null) {
            $message = [
                'title' => 'Sipariş İptal Edildi',
                'body' => 'Siparişiniz iptal edildi.',
                'order' => $order
            ];

            // Bildirimi yayınla
            broadcast(new ManufacturerNotificationEvent($order->manufacturer_id, $message));
        }

        if ($order->status !== 'Sipariş Onayı') {
            $message = [
                'title' => 'Sipariş İptal Edildi',
                'body' => 'Siparişiniz iptal edildi.',
                'order' => $order
            ];

            // Bildirimi yayınla
            broadcast(new DesignerNotificationEvent($message));
        }

        
        if ($order->status == 'Ürün Hazır'|| $order->status == 'Ürün Transfer Aşaması') {
            $message = [
                'title' => 'Sipariş İptal Edildi',
                'body' => 'Siparişiniz iptal edildi.',
                'order' => $order
            ];

            // Bildirimi yayınla
            broadcast(new CourierNotificationEvent($message));
        }

        // İlgili tablolardan bilgiyi sil
        $order->cancelledOrder()->delete();
        $order->rejectedOrder()->delete();
        $order->orderCancelRequest()->delete();
    
        return response()->json(['message' => 'Sipariş başarıyla aktif hale getirildi.']);
    }

    /**
     * Belirli bir sipariş için iptal isteği oluşturur.
     *
     * @param Request $request İstek nesnesi
     * @return JsonResponse
     */
    public function createPendingCancellation(Request $request): JsonResponse
    {
        $rules = [
            'order_id' => 'required|integer',
            'title' => 'required|string',
            'reason' => 'required|string',
        ];

        $messages = [
            'order_id.required' => 'Sipariş ID alanı gereklidir.',
            'order_id.integer' => 'Sipariş ID alanı bir tamsayı olmalıdır.',
            'title.required' => 'Başlık alanı gereklidir.',
            'title.string' => 'Başlık alanı metin olmalıdır.',
            'reason.required' => 'Neden alanı gereklidir.',
            'reason.string' => 'Neden alanı metin olmalıdır.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        
        if ($validator->fails()) {
            // Hataları döndür
            return response()->json(['error' => $validator->errors()->first()], 400);
        }
    
        // İptal isteği oluştur
        $cancellationRequest = new OrderCancelRequest([
            'order_id' => $request->input('order_id'),
            'title' => $request->input('title'),
            'reason' => $request->input('reason'),
        ]);
        
        $cancellationRequest->save();

        $order = Order::where('id', $request->input('order_id'))->first();
        if (!$order) {
            return response()->json(['error' => 'Sipariş bulunamadı.'], 404);
        }

        $order->update(['is_rejected' => 'P']);

        // Admin bildirimi gönder
        $message = [
            'title' => 'İptal isteği oluşturuldu',
            'body' => 'Lütfen iptal isteğini inceleyin onaylayın yada iptal edin.',
            'order' => $order,
        ];

        broadcast(new AdminNotificationEvent($message));

        return response()->json(['message' => 'İptal isteği başarıyla oluşturuldu ve sipariş bekleyen duruma alındı.']);
    }
}