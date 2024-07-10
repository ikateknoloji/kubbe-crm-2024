<?php

namespace App\Http\Controllers\V1\Basket;

use App\Http\Controllers\Controller;
use App\Models\OrderBasket;
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
}