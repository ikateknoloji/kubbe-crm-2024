<?php

namespace App\Http\Controllers\V1\Order;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class OrderImageController extends Controller
{
    /**
     * Siparişe ait resmi indirme işlemi.
     * @param int $orderId
     * @param string $type
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadOrderImage($orderId, $type)
    {
        // Siparişe ait resmi bul
        $orderImage = OrderImage::where('order_id', $orderId)
            ->where('type', $type)
            ->firstOrFail();

        // Dosya yolunu belirle
        $filePath = str_replace('/storage', 'public', $orderImage->product_image);

        // Dosyanın var olup olmadığını kontrol et
        if (!Storage::exists($filePath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        // Dosyanın MIME tipini belirle
        $mimeType = Storage::mimeType($filePath);

        // Dosyayı indirmek için yanıtı döndür
        return Storage::download($filePath, basename($orderImage->product_image), [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . basename($orderImage->product_image) . '"'
        ]);
    }
}