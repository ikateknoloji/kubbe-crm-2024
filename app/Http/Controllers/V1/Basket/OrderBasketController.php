<?php

namespace App\Http\Controllers\V1\Basket;

use App\Http\Controllers\Controller;
use App\Models\OrderBasket;
use App\Models\OrderItem;
use Illuminate\Http\Request;

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

        // Sipariş kalemi bulunamazsa, hata mesajı döndür
        if (!$orderItem) {
            return response()->json(['message' => 'Order item not found'], 404);
        }

        // Sipariş kalemini sil
        $orderItem->delete();

        // Başarılı bir şekilde silindiğinde, başarılı mesajı döndür
        return response()->json(['message' => 'Order item deleted successfully']);
    }
}