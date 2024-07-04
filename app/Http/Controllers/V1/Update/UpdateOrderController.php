<?php

namespace App\Http\Controllers\V1\Update;

use App\Events\AdminNotificationEvent;
use App\Events\CourierNotificationEvent;
use App\Events\CustomerNotificationEvent;
use App\Events\DesignerNotificationEvent;
use App\Events\ManufacturerNotificationEvent;
use App\Http\Controllers\Controller;
use App\Models\DesignImage;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\OrderImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;

class UpdateOrderController extends Controller
{

    /**
     * Mevcut tasarımı günceller ve yeni resmi kaydeder.
     *
     * @param Request $request İstek nesnesi
     * @param Order $order Sipariş modeli
     * @return JsonResponse
     */
    public function updateDesign(Request $request, Order $order)
    {
        try {
            // Doğrulama kuralları
            $validator = Validator::make($request->all(), [
                'design_image' => 'required|file|mimes:jpeg,png,jpg,gif,svg,pdf|max:10048',
                'design_images' => 'sometimes|array',
                'design_images.*' => 'required|file|mimes:jpeg,png,jpg,gif,svg,pdf|max:10048',
            ], [
                'design_image.required' => 'Lütfen bir tasarım resmi yükleyin.',
                'design_image.file' => 'Dosya bir resim olmalıdır.',
                'design_image.mimes' => 'Dosya formatı jpeg, png, jpg, gif veya svg olmalıdır.',
                'design_images.required' => 'Lütfen en az bir tasarım resmi yükleyin.',
                'design_images.*.required' => 'Lütfen bir tasarım resmi yükleyin.',
                'design_images.*.file' => 'Dosya bir resim dosyası olmalıdır.',
                'design_images.*.mimes' => 'Dosya formatı jpeg, png, jpg, gif veya svg olmalıdır.',
            ]);

            // Doğrulama hatası varsa, ilk hatayı döndür
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()->first()], 422);
            }

            // Mevcut tasarım resmini bul
            $currentDesign = $order->orderImages()->where('type', 'D')->first();

            // Yeni resim dosyasını yükle
            $image = $request->file('design_image');
            $imageName = 'design_' . $order->id . '.' . $image->getClientOriginalExtension();
            $path = $image->storeAs('public/designs', $imageName);

            // URL oluştur
            $designPath = Storage::url($path);

            if ($currentDesign) {
                // Eski tasarım resmini sil ve mevcut kaydı güncelle
                if (!empty($currentDesign->design_path)) {
                    Storage::delete($currentDesign->design_path);
                }
                $currentDesign->update([
                    'design_path' => $designPath,
                ]);
            } else {
                // Eğer mevcut resim yoksa, yeni bir OrderImage nesnesi oluştur ve kaydet
                OrderImage::create([
                    'order_id' => $order->id,
                    'type' => 'D',
                    'design_path' => $designPath,
                ]);
            }

            // design_images arrayındaki her bir dosyayı yükle ve kaydet (isteğe bağlı)
            if ($request->hasFile('design_images')) {
                collect($request->file('design_images'))->each(function ($image) use ($order) {
                    $imageName  = 'design_' . $order->id . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                    $filepath = $image->storeAs('public/designs_files', $imageName);

                    // URL oluştur
                    $designPath = Storage::url($filepath);

                    // DesignImage modeline order_id'yi ekleyerek kaydet
                    DesignImage::create([
                        'design_path' => $designPath,
                        'order_id' => $order->id,
                    ]);
                });
            }

            $message = [
                'title' => 'Tasarım resmi güncellendi.',
                'body' => 'Tasarım resmi güncellendi lütfen tasarım resmini müşterinizle paylaşın.',
                'order' => $order
            ];

            // Bildirimi yayınla
            broadcast(new CustomerNotificationEvent($order->customer_id, $message));

            return response()->json(['message' => 'Tasarım başarıyla güncellendi.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Bir hata oluştu: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Sipariş notunu günceller.
     *
     * @param Request $request İstek nesnesi
     * @param int $orderId Sipariş ID'si
     * @return JsonResponse
     */
    public function updateOrderNote(Request $request, $orderId)
    {
        // Doğrulama kuralları
        $validator = Validator::make($request->all(), [
            'note' => 'required|string|max:1000',
        ], [
            'note.required' => 'Lütfen bir not girin.',
            'note.string' => 'Not metinsel bir değer olmalıdır.',
            'note.max' => 'Not çok uzun, maksimum 1000 karakter olmalıdır.',
        ]);

        // Doğrulama hatası varsa, ilk hatayı döndür
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }
        
        // Siparişi bul
        $order = Order::find($orderId);
        if (!$order) {
            return response()->json(['error' => 'Sipariş bulunamadı'], 404);
        }

        // Notu güncelle
        $order->note = $request->input('note');
        $order->save();
        return response()->json(['message' => 'Sipariş notu başarıyla güncellendi.', 'order' => $order], 200);
        
    }


    /**
     * Belirli bir siparişe bağlı müşteri bilgisinin detaylarını günceller.
     *
     * @param Request $request
     * @param int $orderId Siparişin ID'si
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateCustomerInfo(Request $request, $orderId)
    {
        // Gelen isteği doğrula
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'surname' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
        ], [
            'name.required' => 'İsim alanı gereklidir.',
            'surname.required' => 'Soyisim alanı gereklidir.',
            'phone.required' => 'Telefon alanı gereklidir.',
            'email.email' => 'Geçerli bir e-posta adresi giriniz.',
        ]);

        // Doğrulama başarısızsa hata mesajlarını döndür
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        // Siparişi bul ve ilişkili müşteri bilgisini al
        $order = Order::findOrFail($orderId);
        $customerInfo = $order->customerInfo;

        // Müşteri bilgisini güncelle
        $customerInfo->update($validator->validated());

        $message = [
            'title' => 'Müşteri bilgileri güncellendi.',
            'body' => 'Müşteri bilgileri inceleyin artık yeni müşteri bilgilerine sahipsiniz.',
            'order' => $order
        ];

        // Bildirimi yayınla
        broadcast(new AdminNotificationEvent($message));

        // Başarılı yanıt döndür
        return response()->json([
            'message' => 'Müşteri bilgisi başarıyla güncellendi.',
            'customerInfo' => $customerInfo
        ], 200);
    }

    /**
     * Belirli bir siparişin adresini günceller veya yeni bir adres ekler.
     * Eğer 'shipping_type' 'T' ise mevcut adres silinir.
     * Diğer 'shipping_type' değerleri için adres bilgisi zorunludur ve güncellenir/eklenir.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @PUT /order-addresses/1
     * {
     *   "shipping_type": "A",
     *   "address": "Yeni Adres"
     * }
     */
    public function updateOrderAddress(Request $request, $orderId)
    {
        // shipping_type değerini kontrol et
        $shippingType = $request->input('shipping_type');
        
        // Doğrulama kurallarını ayarla
        $rules = [
            'shipping_type' => 'required|in:A,G,T',
        ];
    
        // Eğer shipping_type 'T' değilse, address alanı gerekli olacak
        if ($shippingType !== 'T') {
            $rules['address'] = 'required|string|max:65535';
        }
    
        // Gelen isteği doğrula
        $validator = Validator::make($request->all(), $rules, [
            'address.required' => 'Adres alanı gereklidir.',
            'address.string' => 'Adres alanı metin tipinde olmalıdır.',
            'address.max' => 'Adres çok uzun. Maksimum 65535 karakter olmalıdır.',
            'shipping_type.required' => 'Kargo tipi alanı gereklidir.',
            'shipping_type.in' => 'Geçersiz kargo tipi.',
        ]);
    
        // Doğrulama başarısızsa hata döndür
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }
    
        // Eğer shipping_type 'T' ise, mevcut adresi sil
        if ($shippingType === 'T') {
            OrderAddress::where('order_id', $orderId)->delete();
        } else {
            // Sipariş adresini bul veya yoksa yeni bir kayıt oluştur
            $orderAddress = OrderAddress::updateOrCreate(
                ['order_id' => $orderId],
                ['address' => $request->input('address')]
            );
        }
    
        // Sipariş bilgisini al
        $order = Order::where('id', $orderId)->firstOrFail();
    
        // Sipariş durumunu ve kargo tipini güncelle
        $order->update([
            'shipping_type' => $shippingType,
            // Diğer güncellemeler...
        ]);

        // Başarılı yanıt döndür
        return response()->json([
            'message' => 'Sipariş adresi ve kargo tipi başarıyla güncellendi.',
            'order' => $order
        ], 200);
    }

    /**
     * Belirli bir siparişe bağlı fatura bilgisinin detaylarını günceller veya siler.
     *
     * @param Request $request
     * @param Order $order Sipariş modeli
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateInvoiceInfo(Request $request, Order $order)
    {
        // Fatura tipini kontrol et
        if ($request->input('invoice_type') === 'I') {
            
            // Siparişin fatura tipini güncelle
            $order->update([
                'invoice_type' => $request->input('invoice_type'),
            ]);
        
            // Bireysel fatura bilgilerini sil
            $order->invoiceInfo()->delete();
        
            return response()->json([
                'message' => 'Bireysel fatura bilgileri başarıyla silindi.',
            ], 200);
        } else {
            // Gelen isteği doğrula
            $validator = Validator::make($request->all(), [
                'company_name' => 'required|string|max:255',
                'tax_office' => 'required|max:255',
                'tax_number' => 'required|max:255',
                'invoice_type' => 'required|max:255',
                'order_address' => 'required|string|max:65535',
            ], 
            [
                'company_name.required' => 'Şirket adı alanı gereklidir.',
                'tax_office.required' => 'Vergi dairesi alanı gereklidir.',
                'tax_number.required' => 'Vergi numarası alanı gereklidir.',
                'invoice_type.required' => 'Fatura tipi alanı gereklidir.',
                'addressControll.required' => 'Adres kontrolü alanı gereklidir.',
                'addressControll.boolean' => 'Adres kontrolü alanı doğru veya yanlış olmalıdır.',
                'order_address.required_if' => 'Adres kontrolü doğru olduğunda sipariş adresi alanı gereklidir.',
                'order_address.string' => 'Sipariş adresi metinsel bir değer olmalıdır.',
                'order_address.max' => 'Sipariş adresi çok uzun, maksimum 65535 karakter olmalıdır.',
            ]);
        
            // Doğrulama başarısızsa hata mesajlarını döndür
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()->first()], 422);
            }
        
            // addressControll değerine göre hangi adresin kaydedileceğini belirle
            $address = $request->input('order_address');
        
            // Fatura bilgilerini güncelle veya ekle
            $order->invoiceInfo()->updateOrCreate(
                [
                    'order_id' => $order->id
                ],
                [
                    'company_name' => $request->input('company_name'),
                    'address' => $address,
                    'tax_office' => $request->input('tax_office'),
                    'tax_number' => $request->input('tax_number'),
                ]
            );
        
            // Siparişin fatura tipini güncelle
            $order->update([
                'invoice_type' => $request->input('invoice_type'),
            ]);
        
            // Kurumsal fatura bilgileri güncellendi
            return response()->json([
                'message' => 'Kurumsal fatura bilgileri başarıyla güncellendi.',
            ], 200);
        }
    }

    /**
     * Belirli bir sipariş kaleminin detaylarını günceller.
     *
     * @param Request $request
     * @param int $orderItemId
     * @return \Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @PUT /order-items/1
     * {
     *   "product_type_id": 1,
     *   "product_category_id": 1,
     *   "quantity": 1,
     *   "color": "Black",
     *   "unit_price": "100",
     *   "type": "T"
     * }
     */
    public function updateOrderItemAndTotalOfferPrice(Request $request, $orderItemId)
    {
        $validator = Validator::make($request->all(), [
            'product_type_id' => 'nullable|exists:product_types,id',
            'product_category_id' => 'required|exists:product_categories,id',
            'quantity' => 'required|integer|min:1',
            'color' => 'required|string|max:255',
            'unit_price' => 'required|numeric|min:0',
            'type' => 'nullable|string|max:255'
        ], [
            'product_type_id.exists' => 'Seçilen ürün tipi geçerli değil.',
            'product_category_id.required' => 'Ürün kategorisi alanı gereklidir.',
            'product_category_id.exists' => 'Seçilen ürün kategorisi geçerli değil.',
            'quantity.required' => 'Miktar alanı gereklidir.',
            'quantity.integer' => 'Miktar bir tam sayı olmalıdır.',
            'quantity.min' => 'Miktar en az 1 olmalıdır.',
            'color.required' => 'Renk alanı gereklidir.',
            'color.string' => 'Renk bir metin olmalıdır.',
            'color.max' => 'Renk çok uzun. Maksimum 255 karakter olmalıdır.',
            'unit_price.required' => 'Birim fiyatı alanı gereklidir.',
            'unit_price.numeric' => 'Birim fiyatı bir sayı olmalıdır.',
            'unit_price.min' => 'Birim fiyatı sıfırdan büyük olmalıdır.',
            'type.string' => 'Tip bir metin olmalıdır.',
            'type.max' => 'Tip çok uzun. Maksimum 255 karakter olmalıdır.'
        ]);

        // Doğrulama başarısızsa hata mesajlarını döndür
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        // Sipariş kalemini bul veya hata döndür
        $orderItem = OrderItem::findOrFail($orderItemId);

        // Sipariş kalemini güncelle
        $orderItem->update($validator->validated());

        // İlişkili siparişin toplam teklif fiyatını hesapla
        $totalOfferPrice = OrderItem::where('order_id', $orderItem->order_id)
            ->select(DB::raw('SUM(unit_price * quantity) as total'))
            ->pluck('total')
            ->first();

        // İlişkili siparişi bul
        $order = Order::findOrFail($orderItem->order_id);

        // Siparişin teklif fiyatını güncelle
        $order->update(['offer_price' => $totalOfferPrice]);


        $message = [
            'title' => 'Teklif fiyatları güncellendi.',
            'body' => 'Siparişi inceleyebilir ve teklif tutarını inceleyebilirsiniz.',
            'order' => $order
        ];

        // Bildirimi yayınla
        broadcast(new AdminNotificationEvent($message));

        // Başarılı yanıt döndür
        return response()->json([
            'mesaj' => 'Sipariş kalemi ve toplam teklif fiyatı başarıyla güncellendi.',
            'orderItem' => $orderItem,
            'totalOfferPrice' => $totalOfferPrice
        ], 200);
    }

    /**
     * Üretici Seçimi İşlemini Gerçekleştir.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Order $order
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateManufacturer(Request $request, Order $order)
    {
        // Doğrulama kuralları
        $validator = Validator::make($request->all(), [
            'manufacturer_id' => 'required|exists:users,id',
        ], [
            'manufacturer_id.required' => 'Üretici ID alanı gereklidir.',
            'manufacturer_id.exists' => 'Seçilen üretici mevcut değil.',
        ]);


        // Doğrulama hatası varsa, ilk hatayı döndür
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        // Üreticiyi güncelle
        $order->update([
            'manufacturer_id' => $request->input('manufacturer_id'),
        ]);

        $message = [
            'title' => 'Üretici güncellendi.',
            'body' => 'Yeni bir siparişiniz var.',
            'order' => $order
        ];

        broadcast(new ManufacturerNotificationEvent($order->manufacturer_id, $message));

        return response()->json(['message' => 'Üretici başarıyla güncellendi.'], 200);
    }

    /**
     * Tasarım Onayını ve Ödemeyi Gerçekleştir.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Order $order
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePayment(Request $request, Order $order)
    {
        // Doğrulama kuralları
        $validator = Validator::make($request->all(), [
            'payment_proof' => 'required|mimes:jpeg,png,jpg,gif,svg,pdf|max:2048',
        ], [
            'payment_proof.required' => 'Ödeme kanıtı dosyası gereklidir.',
            'payment_proof.mimes' => 'Dosya formatı jpeg, png, jpg, gif, svg veya pdf olmalıdır.',
            'payment_proof.max' => 'Dosya boyutu maksimum 2048 kilobayt olmalıdır.',
        ]);

        // Doğrulama hatası varsa, ilk hatayı döndür
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        // Mevcut 'Payment' resmini bul veya yoksa yeni bir tane oluştur
        $orderImage = $order->orderImages()->where('type', 'P')->first();

        // Yeni resim dosyasını yükle ve bilgileri al
        $file = $request->file('payment_proof');
        $imageName = 'payment_' . $order->id . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('public/payments', $imageName);

        // URL oluştur
        $productImageUrl = Storage::url($path);

        if ($orderImage) {
            // Eski resmi sil
            Storage::delete($orderImage->product_image);

            // Mevcut kaydı güncelle
            $orderImage->update([
                'product_image' => $productImageUrl,
                'mime_type' => $file->getClientMimeType(),
            ]);
        } else {
            // Eğer mevcut resim yoksa, yeni bir OrderImage nesnesi oluştur ve kaydet
            OrderImage::create([
                'order_id' => $order->id,
                'type' => 'P',
                'product_image' => $productImageUrl,
                'mime_type' => $file->getClientMimeType(),
            ]);
        }

        $message = [
            'title' => 'Ödeme bilgileri güncellendi.',
            'body' => 'Ödeme bilgileri inceleyin ödemeyi kontrol edin.',
            'order' => $order
        ];

        // Bildirimi yayınla
        broadcast(new AdminNotificationEvent($message));

        return response()->json(['message' => 'Ödeme dosyası yüklendi ve güncellendi.'], 200);
    }

    /**
     * Ürün hazır olduğunda resmi günceller.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Order $order
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProductReadyImage(Request $request, Order $order)
    {
        // Doğrulama kuralları
        $validator = Validator::make($request->all(), [
            'product_ready_image' => 'required|file|mimes:pdf,jpeg,png,jpg,gif,svg|max:2048',
        ], [
            'product_ready_image.required' => 'Ürün hazır resmi gereklidir.',
            'product_ready_image.file' => 'Dosya bir resim olmalıdır.',
            'product_ready_image.mimes' => 'Dosya formatı pdf, jpeg, png, jpg, gif veya svg olmalıdır.',
            'product_ready_image.max' => 'Dosya boyutu maksimum 2048 kilobayt olmalıdır.',
        ]);

        // Doğrulama hatası varsa, ilk hatayı döndür
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        // Mevcut 'Product Ready' resmini bul
        $orderImage = $order->orderImages()->where('type', 'PR')->first();

        // Yeni resim dosyasını yükle ve bilgileri al
        $image = $request->file('product_ready_image');
        $imageName = 'product_ready_' . $order->id . '.' . $image->getClientOriginalExtension();
        $path = $image->storeAs('public/product_ready', $imageName);

        // URL oluştur
        $productImageUrl = Storage::url($path);

        if ($orderImage) {
            // Eski resmi sil
            Storage::delete($orderImage->product_image);

            // Mevcut kaydı güncelle
            $orderImage->update([
                'product_image' => $productImageUrl,
                'mime_type' => $image->getClientMimeType(),
            ]);
        } else {
            // Eğer mevcut resim yoksa, yeni bir OrderImage nesnesi oluştur ve kaydet
            OrderImage::create([
                'order_id' => $order->id,
                'type' => 'PR',
                'product_image' => $productImageUrl,
                'mime_type' => $image->getClientMimeType(),
            ]);
        }

        $message = [
            'title' => 'Ürün resmi güncellendi.',
            'body' => 'Ürün resmi güncellendi lütfen ürün resmini müşterinizle paylaşın.',
            'order' => $order
        ];

        // Bildirimi yayınla
        broadcast(new CustomerNotificationEvent($order->customer_id, $message));

        return response()->json(['message' => 'Ürün hazır resmi başarıyla güncellendi.'], 200);
    }

    /**
     * Ürünün kargo aşamasında olduğunu belirtir ve resim ekler.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Order $order
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateMarkProductInTransition(Request $request, Order $order)
    {
        // Doğrulama kuralları
        $validator = Validator::make($request->all(), [
            'product_in_transition_image' => 'required|file|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ], [
            'product_in_transition_image.required' => 'Ürün geçiş resmi gereklidir.',
            'product_in_transition_image.file' => 'Dosya bir resim olmalıdır.',
            'product_in_transition_image.mimes' => 'Dosya formatı jpeg, png, jpg, gif veya svg olmalıdır.',
            'product_in_transition_image.max' => 'Dosya boyutu maksimum 2048 kilobayt olmalıdır.',
        ]);

        // Doğrulama hatası varsa, ilk hatayı döndür
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        // Mevcut 'Product in Transition' resmini bul veya yoksa yeni bir tane oluştur
        $orderImage = $order->orderImages()->where('type', 'SC')->first();

        // Yeni resim dosyasını yükle ve bilgileri al
        $image = $request->file('product_in_transition_image');
        $imageName = 'product_in_transition_' . $order->id . '.' . $image->getClientOriginalExtension();
        $path = $image->storeAs('public/product_in_transition', $imageName);

        // URL oluştur
        $productImageUrl = Storage::url($path);

        if ($orderImage) {
            // Eski resmi sil
            Storage::delete($orderImage->product_image);

            // Mevcut kaydı güncelle
            $orderImage->update([
                'product_image' => $productImageUrl,
                'mime_type' => $image->getClientMimeType(),
            ]);
        } else {
            // Eğer mevcut resim yoksa, yeni bir OrderImage nesnesi oluştur ve kaydet
            OrderImage::create([
                'order_id' => $order->id,
                'type' => 'SC',
                'product_image' => $productImageUrl,
                'mime_type' => $image->getClientMimeType(),
            ]);
        }

        $message = [
            'title' => 'Kargo bilgileri güncellendi.',
            'body' => 'Kargo kodu güncellendi lütfen kargo kodunu indirin.',
            'order' => $order
        ];

        // Bildirimi yayınla
        broadcast(new CustomerNotificationEvent($order->customer_id, $message));

        return response()->json(['message' => 'Kargo Resmi başarıyla güncellendi.'], 200);
    }

    /**
     * Ödeme Tutarını Güncelle.
     * ? admin
     * @param \App\Models\Order $order
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePaymentAmount(Request $request, Order $order)
    {
        // Gelen isteği doğrula
        $validatedData = $request->validate([
            'paid_amount' => 'required|numeric'
        ]); 

        try {
            // Ödeme tutarını güncelle
            $order->update([
                'paid_amount' => $validatedData['paid_amount']
            ]); 

            return response()->json(['message' => 'Ödeme tutarı güncellendi.', 'order' => $order], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Ödeme tutarı güncelleme hatası: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Close Account.
     * ? admin
     * @param \App\Models\Order $order
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function closeAccount(Request $request, Order $order)
    {
        try {
            // Set the paid amount equal to the offer price
            $order->update([
                'paid_amount' => $order->offer_price
            ]);
        
            return response()->json(['message' => 'Hesap başarıyla kapatıldı.', 'order' => $order], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error closing account: ' . $e->getMessage()], 500);
        }
    }
}