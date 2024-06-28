<?php

namespace App\Http\Controllers\V1\Order;

use App\Events\AdminNotificationEvent;
use App\Events\CourierNotificationEvent;
use App\Events\CustomerNotificationEvent;
use App\Events\DesignerNotificationEvent;
use App\Events\ManufacturerNotificationEvent;
use App\Http\Controllers\Controller;
use App\Models\DesignImage;
use App\Models\InvoiceInfo;
use App\Models\ManufacturerNotification;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderImage;
use App\Models\ProductionImage;
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
    private function calculateNextBusinessDay(\DateTime $date, $addDays = 1)
    {
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
    public function transitionToDesignPhase(Order $order)
    {
        // Sipariş durumunu kontrol et, sadece 'OC' durumundakileri güncelle
        if ($order->status === 'Sipariş Onayı') {
            // Sipariş durumunu 'DP' (Tasarım Aşaması) olarak güncelle
            $order->update(['status' => 'DP', 'customer_read' => false]);

            $message = [
                'title' => 'Sipariş Onaylandı',
                'body' => 'Siparişiniz tasarım aşamasına geçildi.',
                'order' => $order
            ];

            broadcast(new CustomerNotificationEvent($order->customer_id, $message));

            broadcast(new DesignerNotificationEvent($message));

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

            // Sipariş durumunu kontrol et, sadece 'Tasarım Aşaması' durumundakileri güncelle
            if ($order->status === 'Tasarım Aşaması') { // 'Tasarım Aşaması' ifadesi yerine 'DP' durumu kullanıldı

                // Tek bir design_image dosyasını yükle ve kaydet
                $image = $request->file('design_image');
                $imageName  = 'design_' . $order->id . '_single.' . $image->getClientOriginalExtension();
                $filepath = $image->storeAs('public/designs', $imageName);

                // URL oluştur
                $designPath = Storage::url($filepath);

                $mime_type = $image->getClientMimeType();

                // OrderImage modeline order_id'yi ekleyerek kaydet
                $orderImage = new OrderImage([
                    'type' => 'D', // Tasarım tipi
                    'product_image' => $designPath,
                    'order_id' => $order->id,
                    'mime_type' => $mime_type,
                ]);

                $orderImage->save();

                // Sipariş durumunu 'DA' (Tasarım Onay) olarak güncelle
                $order->update(['status' => 'DA', 'customer_read' => false]);

                $message = [
                    'title' => 'Sipariş Tasarımı Eklendi',
                    'body' => 'Siparişiniz tasarımı oluşturuldu. Lütfen bir resim seçerek ödemeyi yapın.',
                    'order' => $order
                ];

                broadcast(new CustomerNotificationEvent($order->customer_id, $message));

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
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Order $order
     * @return mixed|\Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @POST 
     * {
     *   "payment_proof": "image.jpg",
     *   "invoice_type": "I",
     *   "shipping_type": "A",
     *   "order_address": "Example address",
     *   "company_name": "Example Company",
     *   "addressControll": "true",
     *   "address": "Example address",
     *   "tax_office": "Example Tax Office",
     *   "tax_number": "1234567890",
     *   "email": "example@example.com"
     *  }
     */
    public function approvePaymentAndProceed(Request $request, Order $order)
    {
        try {
            // Önce validasyon işlemini gerçekleştir
            $validationResponse = $this->validatePaymentRequest($request);
            if ($validationResponse->status() !== 200) {
                return $validationResponse;
            }

            // Sipariş durumunu kontrol et, sadece 'Tasarım Eklendi' durumundakileri güncelle
            if ($order->status === 'Tasarım Eklendi') {

                // Fatura tipine göre ilgili fatura bilgileri ekleniyor
                if ($request->invoice_type == 'C') {
                    $this->addCorporateInvoiceInfo($order, $request);
                }

                // Resim dosyasını yükle ve bilgileri al
                $file = $request->file('payment_proof');
                $imageName = 'payment_' . $order->id . '.' . $file->getClientOriginalExtension();
                $filepath = $file->storeAs('public/payments', $imageName);

                // Üretim resimlerini yükleme fonksiyonunu çağır
                if (!empty($request->file('production_images'))) {
                    $this->storeProductionImages($request, $order->id);
                }

                // URL oluştur
                $productImageUrl = Storage::url($filepath);

                // MIME tipini al
                $mime_type = $file->getClientMimeType();

                // OrderImage modeline order_id'yi ekleyerek kaydet
                $orderImage = new OrderImage([
                    'type' => 'P',
                    'mime_type' => $mime_type,
                    'product_image' => $productImageUrl,
                    'order_id' => $order->id,
                ]);

                // OrderImage modelini veritabanına kaydet
                $orderImage->save();

                // Eğer shipping_type 'T' ise, adres bilgisi kaydedilmeyecek
                if ($request->shipping_type !== 'T') {
                    // Sipariş adresini kaydet
                    $orderAddress = new OrderAddress(['address' => $request->input('order_address')]);
                    $order->orderAddress()->save($orderAddress);
                }

                // Sipariş durumunu 'P' (Ödeme Onayı) olarak güncelle
                $order->update([
                    'status' => 'P',
                    'admin_read' => false,
                    'invoice_type' => $request->input('invoice_type'),
                    'shipping_type' => $request->input('shipping_type'),
                ]);

                // Yöneticiye bildirim gönder
                broadcast(new AdminNotificationEvent([
                    'title' => 'Ödeme Gerçekleştirildi',
                    'body' => 'Lütfen Ödeme Kontrolü Yapın ve Ödeme onayı oluşturun.',
                    'order' => $order,
                ]));

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
            $order->update(['status' => 'PA', 'customer_read' => false, 'production_status' => 'in_progress']);

            $message = [
                'title' => 'Ödeme Onaylandı.',
                'body' => 'Ödemeniz onaylandı artık ürünüz üretim aşamasına geçebilir.',
                'order' => $order
            ];

            broadcast(new CustomerNotificationEvent($order->customer_id, $message));
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

                $message = [
                    'title' => 'Yeni bir Siparişiniz var.',
                    'body' => 'Siparişinizi belirtilen tarihler arasında hazır hale getirin.',
                    'order' => $order
                ];

                broadcast(new ManufacturerNotificationEvent($request->input('manufacturer_id'), $message));


                return response()->json(['message' => 'Üretici seçimi yapıldı.'], 200);
            }

            return response()->json(['error' => 'Sipariş durumu ' . $order->status . ' olduğu için üretici seçimi yapılamıyor.'], 400);
        } catch (\Illuminate\Validation\ValidationException $e) {
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
            broadcast(new AdminNotificationEvent([
                'title' => 'Sipariş üretime başlandı.',
                'body' => 'Sipariş üretime hazır olduğunda gerekli bilgiler size üretilecek.',
                'order' => $order,
            ]));

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
            if ($order->status === 'Üretimde') { // 'PP' ile eşleştiğini kontrol et

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

                broadcast(new CourierNotificationEvent([
                    'title' => 'Sipariş Hazır',
                    'body' => 'Ürün hazır teslim alabilirsiniz.',
                    'order' => $order,
                ]));

                // ? Bildirim mesajını döndürür
                broadcast(new AdminNotificationEvent([
                    'title' => 'Sipariş Hazır',
                    'body' => 'Ürün hazır kargo bölümü ürünü teslim alabilir.',
                    'order' => $order,
                ]));

                broadcast((new CustomerNotificationEvent($order->customer_id, [
                    'title' => 'Sipariş Hazır',
                    'body' => 'Siparişin resmini görüntüleyebilirsiniz.',
                    'order' => $order,
                ])));

                return response()->json(['message' => 'Ürün hazırlandı ve kaydedildi.'], 200);
            }

            return response()->json(['error' => 'Sipariş durumu ' . $order->status . ' olduğu için ürün hazırlandı olarak işaretlenemiyor.'], 400);
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
            if ($order->status === 'Ürün Hazır') { // 'PR' ile eşleştiğini kontrol et

                // Gelen resim dosyasını kontrol et
                $request->validate([
                    'product_in_transition_image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:50000',
                ], [
                    'product_in_transition_image.required' => 'Ürün geçiş resmi gereklidir.',
                    'product_in_transition_image.image' => 'Dosya bir resim olmalıdır.',
                    'product_in_transition_image.mimes' => 'Dosya formatı jpeg, png, jpg, gif veya svg olmalıdır.',
                    'product_in_transition_image.max' => 'Dosya boyutu maksimum 50000 kilobayt olmalıdır.',
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



                // ? Bildirim mesajını döndürür
                broadcast(new AdminNotificationEvent([
                    'title' => 'Sipariş Kargoya verildi.',
                    'body' => 'Ürün kargoya verildi. Kargo kodunu indirmek için sipariş sayfasına ziyaret edin.',
                    'order' => $order,
                ]));

                broadcast((new CustomerNotificationEvent($order->customer_id, [
                    'title' => 'Sipariş Kargoya verildi.',
                    'body' => 'Ürün kargoya verildi. Kargo kodunu indirmek için siparişinizin sayfasını ziyaret edebilirsiniz.',
                    'order' => $order,
                ])));

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
    public function addBill(Request $request, Order $order): JsonResponse
    {
        $rules = [
            'bill_image' => 'required|file|mimes:jpeg,png,jpg,gif,svg,pdf|max:10000',
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

        broadcast((new CustomerNotificationEvent($order->customer_id, [
            'title' => 'Fatura bilgileriniz Eklendi.',
            'body' => 'Fatura bilgileriniz eklendi. Fatura dosyasını indirmek için siparişinizin sayfasını ziyaret edebilirsiniz.',
            'order' => $order,
        ])));

        return response()->json(['message' => 'Fatura resmi başarıyla yüklendi.'], 200);
    }

    /**
     * Resim dosyasını yükle ve production_stage değerini güncelle.
     * @param \App\Models\Order $order
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı:
     * @POST 
     * {
     *   "image": "file.jpg"
     * }
     */
    public function uploadProductionImage(Request $request, Order $order)
    {
        // Validasyon
        $request->validate([
            'image' => 'required|mimes:jpeg,png,jpg,gif,svg,pdf|max:40000',
        ]);

        DB::beginTransaction();

        try {
            // Dosyayı yükle
            $imagePath = $request->file('image')->store('production_images', 'public');

            // Veritabanına kaydet
            $order->productionImages()->create([
                'file_path' => $imagePath,
            ]);

            // production_stage değerini 'U' olarak güncelle
            $order->update(['production_stage' => 'U', 'production_status' => 'in_progress']);

            DB::commit();

            return response()->json(['message' => 'Resim yüklendi ve üretim dosyası Eklendi.', 'file_path' => $imagePath], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Bir hata oluştu: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update the production status of orders to 'completed'.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updateProductionStatus(Request $request)
    {
        // Request içindeki verileri doğrula
        $validatedData = $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'required|exists:orders,id',
        ]);

        // Doğrulamadan geçen order_id'leri al
        $orderIds = $validatedData['order_ids'];

        // Order modelini kullanarak production_status'u 'completed' olarak güncelleme
        Order::whereIn('id', $orderIds)->update(['production_status' => 'completed']);

        // Başarılı yanıt döndür
        return response()->json(['message' => 'Production status updated successfully'], 200);
    }

    public function markOrderAsCompleted($orderId): JsonResponse
    {
        // Siparişi ID ile bul
        $order = Order::find($orderId);

        // Sipariş bulunamazsa hata döndür
        if ($order === null) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        // Siparişin üretim durumunu "completed" olarak güncelle
        $order->production_status = 'completed';
        $order->save();

        return response()->json(['message' => 'Order marked as completed', 'order' => $order], 200);
    }

    /**
     * Kurumsal Fatura Bilgilerini Ekle.
     * @param \App\Models\Order $order
     * @param \Illuminate\Http\Request $request
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    protected function addCorporateInvoiceInfo(Order $order, Request $request)
    {

        // addressControll değerine göre hangi adresin kaydedileceğini belirle
        $address = $request->input('addressControll') == 'true' ? $request->input('address') : $request->input('order_address');

        // Fatura bilgilerini ekleyerek kaydet
        $invoiceInfo = InvoiceInfo::create([
            'order_id' => $order->id,
            'company_name' => $request->input('company_name'),
            'address' => $address,
            'tax_office' => $request->input('tax_office'),
            'tax_number' => $request->input('tax_number'),
        ]);

        // Başarılı ekleme yanıtı
        return response()->json(['invoice_info' => $invoiceInfo], 201);
    }

    /**
     * Validate the request for approving payment and proceeding.
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
protected function validatePaymentRequest(Request $request)
{
    try {
        // Genel doğrulama kuralları
        $rules = [
            'payment_proof' => 'required|mimes:jpeg,png,jpg,gif,svg,pdf|max:10000',
            'invoice_type' => 'required|in:I,C',
            'shipping_type' => 'required|in:A,G,T',
            // Eğer shipping_type 'A' veya 'G' ise, order_address alanı zorunlu olacak
            'order_address' => 'required_unless:shipping_type,T',
            'production_images.*' => 'required|mimes:jpeg,png,jpg,gif,svg|max:10000', // production_images için validation
        ];

        // Kurumsal fatura bilgileri doğrulama kuralları
        if ($request->invoice_type === 'C') {
            $rules['company_name'] = 'required|string';
            $rules['address'] = $request->addressControll === 'true' ? 'required|string' : 'nullable|string';
            $rules['tax_office'] = 'required|string';
            $rules['tax_number'] = 'required|string';
        }
        $messages = [
        'payment_proof.required' => 'Ödeme kanıtı dosyası gereklidir.',
        'payment_proof.mimes' => 'Dosya formatı jpeg, png, jpg, gif, svg veya pdf olmalıdır.',
    'payment_proof.max' => 'Dosya boyutu maksimum 10000 kilobayt olmalıdır.',
    'invoice_type.required' => 'Fatura Tipi gereklidir.',
    'invoice_type.in' => 'Fatura türü Bireysel veya Kurumsal olmalıdır.',
    'shipping_type.required' => 'Kargo Gönderim Şekli gereklidir.',
    'shipping_type.in' => 'Kargo türü A, G veya T olmalıdır.',
    'order_address.required_if' => 'Kargo türü Alıcı  veya Gönderici ödemeli olduğunda sipariş adresi gereklidir.',
    'order_address.string' => 'Sipariş adresi bir metin olmalıdır.',
    'company_name.required' => 'Şirket adı gereklidir.',
    'company_name.string' => 'Şirket adı bir metin olmalıdır.',
    'address.required' => 'Adres gereklidir.',
    'address.string' => 'Adres bir metin olmalıdır.',
    'tax_office.required' => 'Vergi dairesi gereklidir.',
    'tax_office.string' => 'Vergi dairesi bir metin olmalıdır.',
    'tax_number.required' => 'Vergi numarası gereklidir.',
    'tax_number.string' => 'Vergi numarası bir metin olmalıdır.',
    'production_images.*.required' => 'Üretim resmi dosyası gereklidir.',
    'production_images.*.mimes' => 'Üretim resmi dosya formatı jpeg, png, jpg, gif veya svg olmalıdır.',
    'production_images.*.max' => 'Üretim resmi dosya boyutu maksimum 10000 kilobayt olmalıdır.',
        ];


        // Validasyon işlemi
        $request->validate($rules, $messages);

        return response()->json(['message' => 'Validation successful.'], 200);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json(['errors' => $e->errors()], 422);
    }
}


    // Diğer yardımcı fonksiyonlar burada yer alabilir...


    protected function storeProductionImages(Request $request, $orderId)
    {
        $images = $request->file('production_images'); // production_images array olarak alınır

        foreach ($images as $file) {
            $imageName = 'production_' . $orderId . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $filepath = $file->storeAs('public/production_images', $imageName);

            // URL oluştur
            $filePath = Storage::url($filepath);

            // ProductionImage modeline order_id'yi ekleyerek kaydet
            $productionImage = new ProductionImage([
                'order_id' => $orderId,
                'file_path' => $filePath,
            ]);

            // ProductionImage modelini veritabanına kaydet
            $productionImage->save();
        }
    }
}