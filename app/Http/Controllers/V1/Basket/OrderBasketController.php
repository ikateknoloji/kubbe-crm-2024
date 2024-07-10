<?php

namespace App\Http\Controllers\V1\Basket;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderBasket;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderBasketController extends Controller
{
    /**
     * Belirtilen 'basket_id' değerine sahip sepet ve ilgili ürün bilgilerini getirir.
     *
     * @param  int  $basketId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBasketById($basketId)
    {
        $orderBasket = OrderBasket::with([
            'items.productType',
            'items.productCategory'
        ])->find($basketId);

        if (!$orderBasket) {
            return response()->json(['message' => 'Basket not found'], 404);
        }

        return response()->json(['orderBasket' => $orderBasket]);
    }

    /**
     * Belirtilen 'order_item_id' değerine sahip sipariş kalemini siler.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteBasketItem($id)
    {
        // Belirtilen ID'ye sahip sipariş kalemini bul
        $orderItem = OrderItem::find($id);

        $order = Order::find($orderItem->order_basket_id);

        $order->offer_price -= $orderItem->quantity * $orderItem->unit_price;

        // Sipariş kalemi bulunamazsa, hata mesajı döndür
        if (!$orderItem) {
            return response()->json(['message' => 'Order item not found'], 404);
        }

        // Sipariş kalemini sil
        $orderItem->delete();

        // Başarılı bir şekilde silindiğinde, başarılı mesajı döndür
        return response()->json(['message' => 'Order item deleted successfully']);
    }

    public function addOrderItems(Request $request, $id)
    {
        // Türkçe hata mesajlarıyla birlikte validate edin
        $messages = [
            'product_type_id.integer' => 'Ürün tipi kimliği geçerli bir tamsayı olmalıdır.',
            'product_category_id.required' => 'Ürün kategorisi kimliği gereklidir.',
            'product_category_id.integer' => 'Ürün kategorisi kimliği geçerli bir tamsayı olmalıdır.',
            'quantity.required' => 'Ürün miktarı gereklidir.',
            'quantity.integer' => 'Ürün miktarı geçerli bir tamsayı olmalıdır.',
            'quantity.min' => 'Ürün miktarı en az bir olmalıdır.',
            'color.required' => 'Ürün rengi gereklidir.',
            'color.string' => 'Ürün rengi geçerli bir metin olmalıdır.',
            'color.max' => 'Ürün rengi en fazla 50 karakter olabilir.',
            'unit_price.required' => 'Ürün birim fiyatı gereklidir.',
            'unit_price.numeric' => 'Ürün birim fiyatı geçerli bir sayı olmalıdır.',
            'unit_price.min' => 'Ürün birim fiyatı en az 25 TL olmalıdır.',
            'type.string' => 'Ürün tipi geçerli bir metin olmalıdır.',
        ];
    
        // Veri doğrulamasını Validator sınıfı ile yapın
        $validator = Validator::make($request->all(), [
            'product_type_id' => 'nullable|integer',
            'product_category_id' => 'required|integer',
            'quantity' => 'required|integer|min:1',
            'color' => 'required|string|max:50',
            'unit_price' => 'required|numeric|min:25',
            'type' => 'nullable|string',
        ], $messages);
    
        // İlk hata mesajını alın
        if ($validator->fails()) {
            $errors = $validator->errors();
            $firstError = $errors->first();
            return response()->json(['error' => $firstError], 422);
        }
    
        try {
               
            // İlgili order_basket kaydını bulun
            $orderBasket = OrderBasket::find($id);
            if (!$orderBasket) {
                return response()->json(['error' => 'Order basket not found'], 404);
            }

            // Siparişin toplam teklif fiyatını güncelleyin
            $order = Order::find($orderBasket->order_id);
            if (!$order) {
                return response()->json(['error' => 'Order not found'], 404);
            }
            $order->offer_price += $request->input('quantity') * $request->input('unit_price');
            $order->save();

            // Order item'i oluşturun veya veritabanına kaydedin
            $orderItem = OrderItem::create([
                'product_type_id' => $request->input('product_type_id'),
                'product_category_id' => $request->input('product_category_id'),
                'quantity' => $request->input('quantity'),
                'color' => $request->input('color'),
                'unit_price' => $request->input('unit_price'),
                'type' => $request->input('type'),
                'order_basket_id' => $id,
            ]);

            // Başarılı yanıt döndür (opsiyonel, validasyon başarılı olursa)
            return response()->json(['message' => 'Başarıyla Sipariş Kalemi Eklendi', 'data' => $orderItem], 200);
        } catch (\Exception $e) {
            // Hata durumunda yakalayın ve hata yanıtını döndürün
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}