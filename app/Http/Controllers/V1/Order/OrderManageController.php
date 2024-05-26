<?php

namespace App\Http\Controllers\V1\Order;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class OrderManageController extends Controller
{
    /**
     * İş günü hesaplama yardımcı fonksiyonu.
     * @param \DateTime $date
     * @param int $addDays
     * @return \DateTime
     * {
     *   "date": "2023-01-01",
     *   "addDays": 3
     *  }
     */
    private function calculateNextBusinessDay(\DateTime $date, $addDays = 1) {
        for ($i = 0; $i < $addDays; $i++) {
            $date->modify('+1 day');
            if ($date->format('N') >= 6) {
                $date->modify('next Monday');
            }
        }
        return $date;
    }
    
        /**
     * Sipariş Durumunu Tasarım Aşamasına Geçir.
     * ? admin rotası
     * @param \App\Models\Order $order
     * @return mixed|\Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @POST 
     * {
     *   "order_id": 1
     *  }
     */
    public function transitionToDesignPhase(Order $order){
        // Sipariş durumunu kontrol et, sadece 'OC' durumundakileri güncelle
        if ($order->status === 'Sipariş Onayı') {
            // Sipariş durumunu 'DP' (Tasarım Aşaması) olarak güncelle
            $order->update(['status' => 'DP', 'customer_read' => false]);

            return response()->json(['message' => 'Sipariş tasarım aşamasına geçirildi.'], 200);
        }

        // Sipariş Onayında değilse hata mesajı döndürür
        return response()->json(['error' => 'Sipariş durumu ' . $order->status . ' olduğu için güncellenemiyor.'], 400);
    }

    /**
     * Tasarım ekle ve Resmi Kaydet.
     * @param \App\Models\Order $order
     * @param \Illuminate\Http\Request $request
     * Örnek İstek Yapısı
     * @POST 
     * {
     *   "design_image": "image.jpg"
     *  }
     */
    public function approveDesign(Request $request, Order $order)
    {
        try {
            // Gelen resim dosyasını kontrol et
            $request->validate([
                'design_image' => 'required|file|mimes:jpeg,png,jpg,gif,svg,pdf',
            ], [
                'design_image.required' => 'Lütfen bir tasarım resmi yükleyin.',
                'design_image.file' => 'Dosya bir resim dosyası olmalıdır.',
                'design_image.mimes' => 'Dosya formatı jpeg, png, jpg, gif, svg veya pdf olmalıdır.',
            ]);

            // Sipariş durumunu kontrol et, sadece 'DP' durumundakileri güncelle
            if ($order->status === 'DP') { // 'DP' ile eşleştiğini kontrol et

                $image = $request->file('design_image');
                $imageName  = 'design_' . $order->id . '.' . $image->getClientOriginalExtension();
                $filepath = $image->storeAs('public/designs', $imageName);

                // URL oluştur
                $productImageUrl = Storage::url($filepath);

                // MIME tipini al
                $mime_type = $image->getClientMimeType();

                // OrderImage modeline order_id'yi ekleyerek kaydet
                $orderImage = new OrderImage([
                    'type' => 'D', // Tasarım tipi
                    'product_image' => $productImageUrl,
                    'mime_type' => $mime_type, // MIME tipini kaydet
                    'order_id' => $order->id,
                ]);

                $orderImage->save();

                // Sipariş durumunu 'DA' (Tasarım Onay) olarak güncelle
                $order->update(['status' => 'DA', 'customer_read' => false]);

                return response()->json(['message' => 'Tasarım onaylandı ve kaydedildi.'], 200);
            } else {
                return response()->json(['message' => 'Sipariş durumu tasarım aşamasında değil.'], 400);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {

            $firstErrorMessage = head($e->validator->errors()->all());

            return response()->json(['error' => $firstErrorMessage], 422);
        }
    }

    /**
     * Tasarım Onayını ve Ödemeyi Gerçekleştir.
     * @param \App\Models\Order $order
     * @param \Illuminate\Http\Request $request
     * @return mixed|\Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @POST 
     * {
     *   "payment_proof": "image.jpg"
     *  }
     */
    public function approvePaymentAndProceed(Request $request, Order $order)
    {
        try {
            // Sipariş durumunu kontrol et, sadece 'DA' durumundakileri güncelle
            if ($order->status === 'DA') { // 'DA' ile eşleştiğini kontrol et
            
                $request->validate([
                    'payment_proof' => 'required|mimes:jpeg,png,jpg,gif,svg,pdf|max:10000',
                ], [
                    'payment_proof.required' => 'Ödeme kanıtı dosyası gereklidir.',
                    'payment_proof.mimes' => 'Dosya formatı jpeg, png, jpg, gif, svg veya pdf olmalıdır.',
                    'payment_proof.max' => 'Dosya boyutu maksimum 10000 kilobayt olmalıdır.',
                ]);
            
                // Resim dosyasını yükle ve bilgileri al
                $file = $request->file('payment_proof');
                $imageName = 'payment_' . $order->id . '.' . $file->getClientOriginalExtension();
                $filepath = $file->storeAs('public/payments', $imageName);
            
                // URL oluştur
                $productImageUrl = Storage::url($filepath);
            
                // MIME tipini al
                $mime_type = $file->getClientMimeType();
            
                // OrderImage modeline order_id'yi ekleyerek kaydet
                $orderImage = new OrderImage([
                    'type' => 'P',
                    'mime_type' => $mime_type,
                    'product_image' => $productImageUrl, // Burada uygun bir değer sağlayın
                    'order_id' => $order->id,
                ]);
            
                // OrderImage modelini veritabanına kaydet
                $orderImage->save();
            
                // Sipariş durumunu 'P' (Ödeme Onayı) olarak güncelle
                $order->update(['status' => 'P', 'admin_read' => false]);
            
            
                return response()->json(['message' => 'Ödeme dosyası yüklendi ve Ödeme Onayı Bekliyor.'], 200);
            }
        
            return response()->json(['error' => 'Sipariş durumu ' . $order->status . ' olduğu için işlem gerçekleştirilemiyor.'], 400);
        
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

        /**
     * Ödemeyi Doğrula.
     * ? admin
     * @param \App\Models\Order $order
     * @param \Illuminate\Http\Request $request
     * @return mixed|\Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @POST 
     * {
     *   "payment_proof": "image.jpg"
     *  }
     */
    public function verifyPayment(Order $order)
    {
        // Sipariş durumunu kontrol et, sadece 'P' durumundakileri doğrula
        if ($order->status === 'Ödeme Aşaması') {
            // Ödeme durumunu 'PA' (Ödeme Onaylandı) olarak güncelle
            $order->update(['status' => 'PA', 'customer_read' => false]);

            return response()->json(['message' => 'Ödeme doğrulandı.'], 200);
        }

        return response()->json(['error' => 'Sipariş durumu ' . $order->status . ' olduğu için ödeme doğrulanamıyor.'], 400);
    }

    /**
     * Üretici Seçimi İşlemini Gerçekleştir.
     * ? admin
     * @param \App\Models\Order $order
     * @param \Illuminate\Http\Request $request
     * @return mixed|\Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @POST 
     * {
     *   "manufacturer_id": 1
     *  }
     */
    public function selectManufacturer(Request $request, Order $order)
    {
        try {
         // Sipariş durumunu kontrol et, sadece 'PA' durumundakileri işle
         if ($order->status === 'Ödeme Alındı') {
            // Gelen üretici bilgilerini kontrol et
            $request->validate([
                'manufacturer_id' => 'required|exists:users,id',
            ]);

            // Güncel tarihten bir iş günü sonrasını hesapla
            $date = new \DateTime(); // Şu anki tarihi al
            $date = $this->calculateNextBusinessDay($date); // Bir iş günü ekle

            // Tahmini üretim tarihini hesapla
            $estimated_date = $this->calculateNextBusinessDay(clone $date, 3); // 3 iş günü ekle


            // Üreticiyi seç, sipariş durumunu 'MS' (Üretici Seçimi) olarak güncelle ve üretim başlangıç tarihini ayarla
            $order->update([
                'manufacturer_id' => $request->input('manufacturer_id'),
                'status' => 'MS',
                'production_start_date' => $date->format('Y-m-d H:i:s'), // Üretim başlangıç tarihini MySQL uyumlu formatla ayarla
                'estimated_production_date' => $estimated_date->format('Y-m-d H:i:s'), // Tahmini üretim tarihi
            ]);


            return response()->json(['message' => 'Üretici seçimi yapıldı.'], 200);
        }

         return response()->json(['error' => 'Sipariş durumu ' . $order->status . ' olduğu için üretici seçimi yapılamıyor.'], 400);
        } 
        catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }   

    /**
     * Üretici onayından sonra üretim sürecini başlat.
     * ? manufacturer
     * @param \App\Models\Order $order
     * @param \Illuminate\Http\Request $request
     * @return mixed|\Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @POST 
     * {
     *   "manufacturer_id": 1
     *  }
     */
    public function startProduction(Request $request, Order $order)
    {
        // Giriş yapan kullanıcının üretici olup olmadığını kontrol et
        $manufacturerId = Auth::id();   

        // Order'ın manufacturer_id'si ile giriş yapan üreticinin user_id'sini kontrol et
        if ($order->manufacturer_id != $manufacturerId) {
            return response()->json(['error' => 'Bu işlemi sadece ilgili üretici gerçekleştirebilir.'], 403);
        }   

        // Sipariş durumunu kontrol et, sadece 'OA' (Teklifi Onayı) durumundakileri güncelle
        if ($order->status === 'Üretici Seçimi') {
            // Sipariş durumunu 'PP' (Üretimde) olarak güncelle
            $order->update(['status' => 'PP', 'admin_read' => false]);

            // ? Bildirim mesajını döndürür

            return response()->json(['message' => 'Üretim süreci başlatıldı.'], 200);
        }   

        return response()->json(['error' => 'Sipariş durumu ' . $order->status . ' olduğu için üretim süreci başlatılamıyor.'], 400);
    }

    /**
     * Ürünün hazır olduğunu belirtir ve resim yükler.
     * @param \App\Models\Order $order
     * @param \Illuminate\Http\Request $request
     * @return mixed|\Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @POST 
     * {
     *   "product_ready_image": "image.jpg"
     *  }
     */
    public function markProductReady(Request $request, Order $order)
    {
        try {
            // Sipariş durumunu kontrol et, sadece 'PP' durumundakileri güncelle
            if ($order->status === 'PP') { // 'PP' ile eşleştiğini kontrol et
            
                // Gelen resim dosyasını kontrol et
                $request->validate([
                    'product_ready_image' => 'required|file|mimes:jpeg,png,jpg,gif,svg|max:10000',
                ], [
                    'product_ready_image.required' => 'Ürün hazır resmi gereklidir.',
                    'product_ready_image.file' => 'Dosya bir resim olmalıdır.',
                    'product_ready_image.mimes' => 'Dosya formatı jpeg, png, jpg, gif veya svg olmalıdır.',
                    'product_ready_image.max' => 'Dosya boyutu maksimum 10000 kilobayt olmalıdır.',
                ]);
            
                // Resim dosyasını yükle ve bilgileri al
                $image = $request->file('product_ready_image');
                $imageName = 'product_ready_' . $order->id . '.' . $image->getClientOriginalExtension();
                $path = $image->storeAs('public/product_ready', $imageName);
            
                // URL oluştur
                $productImageUrl = Storage::url($path);
            
                // MIME tipini al
                $mime_type = $image->getClientMimeType();
            
                // OrderImage modeline order_id'yi ekleyerek kaydet
                $orderImage = new OrderImage([
                    'type' => 'PR', // Product Ready tipi
                    'product_image' => $productImageUrl, // Resim dosyasının URL'si
                    'mime_type' => $mime_type, // MIME tipini kaydet
                    'order_id' => $order->id,
                ]);
            
                $order->orderImages()->save($orderImage);
            
                // Sipariş durumunu 'PR' (Product Ready) olarak güncelle
                $order->update([
                    'status' => 'PR',
                    'admin_read' => false,
                    'customer_read' => false,
                    'production_date' => now(), // Üretim tarihini ayarla
                ]);
            
                return response()->json(['message' => 'Ürün hazırlandı ve kaydedildi.'], 200);
            }
        
            return response()->json(['error' => 'Sipariş durumu ' . $order->status . ' olduğu için ürün hazırlandı olarak işaretlenemiyor.'],400);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    /**
     * Ürünün kargo aşamasında olduğunu belirtir ve resim ekler.
     * @param \App\Models\Order $order
     * @param \Illuminate\Http\Request $request
     * @return mixed|\Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @POST 
     * {
     *   "product_in_transition_image": "image.jpg"
     * }
     */
    public function markProductInTransition(Request $request, Order $order)
    {
        try {
            // Sipariş durumunu kontrol et, sadece 'PR' durumundakileri güncelle
            if ($order->status === 'PR') { // 'PR' ile eşleştiğini kontrol et
            
                // Gelen resim dosyasını kontrol et
                $request->validate([
                    'product_in_transition_image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                ], [
                    'product_in_transition_image.required' => 'Ürün geçiş resmi gereklidir.',
                    'product_in_transition_image.image' => 'Dosya bir resim olmalıdır.',
                    'product_in_transition_image.mimes' => 'Dosya formatı jpeg, png, jpg, gif veya svg olmalıdır.',
                    'product_in_transition_image.max' => 'Dosya boyutu maksimum 2048 kilobayt olmalıdır.',
                ]);
            
                // Resim dosyasını yükle ve bilgileri al
                $image = $request->file('product_in_transition_image');
                $imageName = 'product_in_transition_' . $order->id . '.' . $image->getClientOriginalExtension();
                $path = $image->storeAs('public/product_in_transition', $imageName);
            
                // URL oluştur
                $productImageUrl = Storage::url($path);
            
                // MIME tipini al
                $mime_type = $image->getClientMimeType();
            
                // OrderImage modeline order_id'yi ekleyerek kaydet
                $orderImage = new OrderImage([
                    'type' => 'SC', // Product in Transition tipi
                    'product_image' => $productImageUrl, // Resim dosyasının URL'si
                    'mime_type' => $mime_type, // MIME tipini kaydet
                    'order_id' => $order->id,
                ]);
            
                $order->orderImages()->save($orderImage);
            
                // Sipariş durumunu 'PIT' (Product in Transition) olarak güncelle
                $order->update([
                    'status' => 'PD',
                    'admin_read' => false,
                    'customer_read' => false,
                    'production_date' => now(), // Üretim tarihini ayarla
                ]);
            
                return response()->json(['message' => 'Kargo resim eklendi.'], 200);
            }
        
            return response()->json(['error' => 'Sipariş durumu ' . $order->status . ' olduğu için ürün geçiş aşamasına işaretlenemiyor.'], 400);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    /**
     * Belirli bir sipariş için fatura resmi yükler.
     * @param Request $request İstek nesnesi
     * @param Order $order Sipariş modeli
     * @return JsonResponse
     */
    public function addBill(Request $request, Order $order) : JsonResponse
    {
        $rules = [
            'bill_image' => 'required|file|mimes:jpeg,png,jpg,gif,svg,pdf|max:8000',
        ];
    
        $messages = [
            'bill_image.required' => 'Fatura resmi gereklidir.',
            'bill_image.file' => 'Dosya bir resim olmalıdır.',
            'bill_image.mimes' => 'Dosya formatı jpeg, png, jpg, gif veya svg olmalıdır.',
            'bill_image.max' => 'Dosya boyutu maksimum 8000 kilobayt olmalıdır.',
        ];
    
        // Gelen resim dosyasını kontrol et
        $validator = Validator::make($request->all(), $rules, $messages);
    
        if ($validator->fails()) {
            // Hataları döndür
            return response()->json(['error' => $validator->errors()->first()], 400);
        }
    
        // Resim dosyasını yükle ve bilgileri al
        $image = $request->file('bill_image');
        $imageName = 'bill_' . $order->id . '.' . $image->getClientOriginalExtension();
        $path = $image->storeAs('public/bills', $imageName);
    
        // URL oluştur
        $productImageUrl = Storage::url($path);
    
        // MIME tipini al
        $mime_type = $image->getClientMimeType();
    
        // OrderImage modeline order_id'yi ekleyerek kaydet
        $orderImage = new OrderImage([
            'type' => 'I', // Invoice tipi
            'product_image' => $productImageUrl, // Resim dosyasının URL'si
            'mime_type' => $mime_type, // MIME tipini kaydet
            'order_id' => $order->id,
        ]);
    
        $order->orderImages()->save($orderImage);
    
        return response()->json(['message' => 'Fatura resmi başarıyla yüklendi.'], 200);
    }
}