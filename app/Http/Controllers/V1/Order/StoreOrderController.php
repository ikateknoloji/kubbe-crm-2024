<?php

namespace App\Http\Controllers\V1\Order;

use App\Events\AdminNotificationEvent;
use App\Events\CustomerNotificationEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderImage;
use App\Models\OrderItem;
use App\Models\CustomerInfo;
use App\Models\InvoiceInfo;
use App\Models\OrderBasket;
use App\Models\OrderLogo;
use App\Rules\TurkishPhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\File; // File sınıfını içe aktarın

class StoreOrderController extends Controller
{
    /**
     * Sipariş oluşturma
     * @param \Illuminate\Http\Request $request
     * @return mixed|\Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @POST 
     * {
     *   "customer": {
     *     "name": "Name",
     *     "surname": "Surname",
     *     "phone": "Phone",
     *     "email": "Email"
     *   },
     *   "order": {
     *     "order_name": "Sipariş Adı",
     *     "offer_price": 100,
     *     "note": "Not"
     *   },
     *   "baskets": [
     *     {
     *       "items": [
     *         {
     *           "product_type_id": 1,
     *           "product_category_id": 1,
     *           "quantity": 1,
     *           "color": "Black",
     *           "unit_price": 100,
     *           "type": "T"
     *         }
     *       ],
     *       "logos": [
     *         {
     *           "logo_url": "image1.jpg"
     *         },
     *         {
     *           "logo_url": "image2.jpg"
     *         }
     *       ]
     *     },
     *     {
     *       "items": [
     *         {
     *           "product_type_id": 2,
     *           "product_category_id": 1,
     *           "quantity": 1,
     *           "color": "Black",
     *           "unit_price": 100,
     *           "type": "T"
     *         }
     *       ],
     *       "logos": [
     *         {
     *           "logo_url": "image3.jpg"
     *         }
     *       ]
     *     }
     *   ]
     * }
     */
    public function createOrder(Request $request)
    {
        // Gelen verileri validate edin
        $validatedData = $request->validate([
            'customer.name' => 'required|string|max:255',
            'customer.surname' => 'required|string|max:255',
            'customer.phone' => 'required|string|max:15',
            'customer.email' => 'required|string|email|max:255',
            'order.order_name' => 'required|string|max:255',
            'order.offer_price' => 'required|numeric',
            'order.note' => 'nullable|string',
            'baskets' => 'required|array',
            'baskets.*.items' => 'required|array',
            'baskets.*.items.*.product_type_id' => 'nullable|integer',
            'baskets.*.items.*.product_category_id' => 'required|integer',
            'baskets.*.items.*.quantity' => 'required|integer',
            'baskets.*.items.*.color' => 'required|string|max:50',
            'baskets.*.items.*.unit_price' => 'required|numeric',
            'baskets.*.items.*.type' => 'nullable|string',
            'baskets.*.logos' => 'required|array',
            'baskets.*.logos.*.logo_url' => 'required|string|max:255',
        ]);

        // Giriş yapmış kullanıcının kimliğini al
        $customerId = Auth::id();

        // Order verilerini al ve customer_id'yi ekleyerek Order oluştur
        $orderData = $validatedData['order'];
        $orderData['customer_id'] = $customerId;  // customer_id ekleniyor
        $orderData['order_code'] = 'ORD-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4));
        $orderData['status'] = 'OC';
        $order = Order::create($orderData);

        // CustomerInfo verilerini al ve order_id'yi ekleyerek CustomerInfo oluştur
        $customerInfoData = $validatedData['customer'];
        $customerInfoData['order_id'] = $order->id;  // order_id ekleniyor
        $customerInfo = CustomerInfo::create($customerInfoData);

        // Her bir sepet için OrderBasket oluştur ve ilişkili verileri kaydet
        foreach ($validatedData['baskets'] as $basketData) {
            // Yeni OrderBasket oluştur ve order_id'yi ekleyerek kaydet
            $orderBasket = new OrderBasket(['order_id' => $order->id]);
            $orderBasket->save();

            // Her bir ürün için OrderItem oluştur ve order_basket_id'yi ekleyerek kaydet
            foreach ($basketData['items'] as $itemData) {
                $itemData['order_basket_id'] = $orderBasket->id; // order_basket_id ekleniyor
                OrderItem::create($itemData);
            }

            // Her bir logo için OrderLogo oluştur ve logo_path'ı kaydet
            foreach ($basketData['logos'] as $logoData) {
                $logoRecord = new OrderLogo([
                    'order_basket_id' => $orderBasket->id, // order_basket_id ekleniyor
                    'logo_path' => $logoData['logo_url']
                ]);
                $logoRecord->save();
            }
        }

        // Başarılı yanıt döndür
        return response()->json([
            'message' => 'Data created successfully',
            'order' => $order,
            'customer_info' => $customerInfo
        ], 201);
    }
    /**
     * Sipariş detaylarını oluştur ve güncelle.
     * @param \Illuminate\Http\Request $request
     * @param int $order_id
     * @return \Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @POST 
     * {
     *   "invoice_type": "C",
     *   "shipping_type": "G",
     *   "order_address": "Adres",
     *   "company_name": "Şirket Adı",
     *   "address": "Şirket Adresi",
     *   "tax_office": "Vergi Dairesi",
     *   "tax_number": "Vergi Numarası",
     *   "email": "Email"
     * }
     * Eğer invoice_type "I" ise:
     * @POST 
     * {
     *   "invoice_type": "I",
     *   "shipping_type": "A",
     *   "order_address": "Adres"
     * }
     */
    public function createOrderDetails(Request $request, $order_id)
    {
        try {
            // İlgili siparişi bul
            $order = Order::findOrFail($order_id);

            // invoice_type ve shipping_type değerine göre doğrulama kurallarını belirle
            $rules = [
                'invoice_type' => 'nullable|in:I,C',
                'shipping_type' => 'required|in:A,G,T',
                'order_address' => 'required|string',
            ];

            if ($request->input('invoice_type') == 'C') {
                $rules = array_merge($rules, [
                    'company_name' => 'required|string',
                    'address' => 'required|string',
                    'tax_office' => 'required|string',
                    'tax_number' => 'required|string',
                ]);
            }

            // Gelen verileri doğrula
            $request->validate($rules);

            // Transaksiyon başlat
            DB::beginTransaction();

            // Siparişi güncelle
            $order->update([
                'invoice_type' => $request->input('invoice_type'),
                'shipping_type' => $request->input('shipping_type'),
            ]);

            // Sipariş adresini oluştur
            OrderAddress::create([
                'order_id' => $order_id,
                'address' => $request->input('order_address'),
            ]);

            // invoice_type 'C' ise fatura bilgilerini oluştur
            if ($request->input('invoice_type') == 'C') {
                InvoiceInfo::create([
                    'order_id' => $order_id,
                    'company_name' => $request->input('company_name'),
                    'address' => $request->input('address'),
                    'tax_office' => $request->input('tax_office'),
                    'tax_number' => $request->input('tax_number'),
                    'email' => $request->input('email'),
                ]);
            }

            // Transaksiyonu tamamla
            DB::commit();

            // Başarılı yanıt
            return response()->json(['message' => 'Sipariş detayları başarıyla oluşturuldu'], 201);
        } catch (ValidationException $e) {
            // Hata durumunda transaksiyonu geri al
            DB::rollback();
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            // Hata durumunda transaksiyonu geri al
            DB::rollback();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    /**
     * Form içeriklerinin doğrulama işlemleri.
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @POST 
     * {
     *   "customer": {
     *     "name": "Ad",
     *     "surname": "Soyad",
     *     "phone": "Telefon Numarası",
     *     "email": "Email"
     *   },
     *   "order": {
     *     "order_name": "Sipariş Adı",
     *     "note": "Not"
     *   }
     * }
     */
    public function validateForms(Request $request)
    {
        try {

            // Türkçe hata mesajlarıyla birlikte validate edin
            $messages = [
                'customer.name.required' => 'Müşteri adı gereklidir.',
                'customer.name.string' => 'Müşteri adı geçerli bir metin olmalıdır.',
                'customer.name.max' => 'Müşteri adı en fazla 255 karakter olabilir.',
                'customer.surname.required' => 'Müşteri soyadı gereklidir.',
                'customer.surname.string' => 'Müşteri soyadı geçerli bir metin olmalıdır.',
                'customer.surname.max' => 'Müşteri soyadı en fazla 255 karakter olabilir.',
                'customer.phone.required' => 'Müşteri telefon numarası gereklidir.',
                'customer.phone.string' => 'Müşteri telefon numarası geçerli bir metin olmalıdır.',
                'customer.phone.max' => 'Müşteri telefon numarası en fazla 15 karakter olabilir.',
                'customer.phone.turkish_phone_number' => 'Telefon numarası geçerli bir formatta olmalıdır.', // Kural için hata mesajı
                'customer.email.required' => 'Müşteri e-posta adresi gereklidir.',
                'customer.email.string' => 'Müşteri e-posta adresi geçerli bir metin olmalıdır.',
                'customer.email.email' => 'Müşteri e-posta adresi geçerli bir e-posta olmalıdır.',
                'customer.email.max' => 'Müşteri e-posta adresi en fazla 255 karakter olabilir.',
                'order.order_name.required' => 'Sipariş adı gereklidir.',
                'order.order_name.string' => 'Sipariş adı geçerli bir metin olmalıdır.',
                'order.order_name.max' => 'Sipariş adı en fazla 255 karakter olabilir.',
                'order.note.string' => 'Not geçerli bir metin olmalıdır.',
            ];

            // Doğrulama kurallarını belirle
            $rules = [
                'customer.name' => 'required|string|max:255',
                'customer.surname' => 'required|string|max:255',
                'customer.phone' => ['required', 'string', 'max:15', new TurkishPhoneNumber],
                'customer.email' => 'nullable|string|email|max:255',
                'order.order_name' => 'required|string|max:255',
                'order.note' => 'nullable|string',
            ];

            // Doğrulama işlemi
            $request->validate($rules,  $messages);

            // Doğrulama başarılı
            return response()->json(['message' => 'Doğrulama başarılı'], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Hata yanıtını döndür
            return response()->json(['errors' => $e->errors()], 422);
        }
    }
    /**
     * Sepet ürünleri ve logoların doğrulama işlemleri.
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @POST 
     * {
     *   "items": [
     *     {
     *       "product_type_id": 1,
     *       "product_category_id": 1,
     *       "quantity": 1,
     *       "color": "Black",
     *       "unit_price": 100.0,
     *       "type": "T"
     *     },
     *     {
     *       "product_type_id": 2,
     *       "product_category_id": 2,
     *       "quantity": 2,
     *       "color": "White",
     *       "unit_price": 200.0,
     *       "type": "S"
     *     }
     *   ],
     *   "logos": [
     *     {
     *       "logo_url": "image1.jpg"
     *     },
     *     {
     *       "logo_url": "image2.jpg"
     *     }
     *   ]
     * }
     */
    public function validateOrderItem(Request $request)
    {
        // Türkçe hata mesajlarıyla birlikte validate edin
        $messages = [
            'items.required' => 'Ürün bilgileri gereklidir.',
            'items.array' => 'Ürün bilgileri bir dizi olmalıdır.',
            'items.*.product_type_id.integer' => 'Ürün tipi kimliği geçerli bir tamsayı olmalıdır.',
            'items.*.product_category_id.required' => 'Ürün kategorisi kimliği gereklidir.',
            'items.*.product_category_id.integer' => 'Ürün kategorisi kimliği geçerli bir tamsayı olmalıdır.',
            'items.*.quantity.required' => 'Ürün miktarı gereklidir.',
            'items.*.quantity.integer' => 'Ürün miktarı geçerli bir tamsayı olmalıdır.',
            'items.*.color.required' => 'Ürün rengi gereklidir.',
            'items.*.color.string' => 'Ürün rengi geçerli bir metin olmalıdır.',
            'items.*.color.max' => 'Ürün rengi en fazla 50 karakter olabilir.',
            'items.*.unit_price.required' => 'Ürün birim fiyatı gereklidir.',
            'items.*.unit_price.numeric' => 'Ürün birim fiyatı geçerli bir sayı olmalıdır.',
            'items.*.type.string' => 'Ürün tipi geçerli bir metin olmalıdır.',
            'logos.required' => 'Logolar gereklidir.',
            'logos.array' => 'Logolar bir dizi olmalıdır.',
            'logos.*.logo_url.required' => 'Logo URL\'si gereklidir.',
            'logos.*.logo_url.string' => 'Logo URL\'si geçerli bir metin olmalıdır.',
            'logos.*.logo_url.max' => 'Logo URL\'si en fazla 255 karakter olabilir.',
        ];

        try {
            // Verileri validate edin
            $validatedData = $request->validate([
                'items' => 'required|array',
                'items.*.product_type_id' => 'nullable|integer',
                'items.*.product_category_id' => 'required|integer',
                'items.*.quantity' => 'required|integer',
                'items.*.color' => 'required|string|max:50',
                'items.*.unit_price' => 'required|numeric',
                'items.*.type' => 'nullable|string',
                'logos' => 'required|array',
                'logos.*.logo_url' => 'required|string|max:255',
            ], $messages);

            // Başarılı yanıt döndür (opsiyonel, validasyon başarılı olursa)
            return response()->json(['message' => 'Sipariş Sepeti Başarıyla Oluşturuldu.', 'data' => $validatedData], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Hata yanıtını döndür
            return response()->json(['errors' => $e->errors()], 422);
        }
    }
    /**
     * Tek bir ürünün doğrulama işlemleri.
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @POST 
     * {
     *   "product_type_id": 1,
     *   "product_category_id": 1,
     *   "quantity": 1,
     *   "color": "Black",
     *   "unit_price": 100.0,
     *   "type": "T"
     * }
     */
    public function validateItem(Request $request)
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
            'unit_price.min' => 'Ürün birim fiyatı en az 500 TL olmalıdır.',
            'type.string' => 'Ürün tipi geçerli bir metin olmalıdır.',
        ];

        try {
            // Verileri validate edin
            $validatedData = $request->validate([
                'product_type_id' => 'nullable|integer',
                'product_category_id' => 'required|integer',
                'quantity' => 'required|integer|min:1',
                'color' => 'required|string|max:50',
                'unit_price' => 'required|numeric|min:25',
                'type' => 'nullable|string',
            ], $messages);

            // Başarılı yanıt döndür (opsiyonel, validasyon başarılı olursa)
            return response()->json(['message' => 'Başarıyla Sipariş Kalemi Eklendi', 'data' => $validatedData], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Hata yanıtını döndür
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function upload(Request $request)
    {
        // Validate file type and size
        $request->validate([
            'logos' => 'required|file|mimes:jpeg,png,jpg,gif,pdf,ai|max:20480', // max 20MB
            'order_name' => 'required|string|max:255', // Validate order_name
        ]);

        if ($request->hasFile('logos')) {
            // Get the uploaded file
            $file = $request->file('logos');

            $orderName = $request->input('order_name');
            $orderName = $this->sanitizeFileName($orderName);

            // Generate a unique file name including sanitized order_name
            $filename = Str::uuid() . '_' . $orderName . '.' . $file->getClientOriginalExtension();

            // Store the file
            $path = $file->storeAs('public/logos', $filename);

            // Return the file path as the logo_url
            return response()->json([
                'logo_url' => Storage::url($path),
            ], 200);
        }

        return response()->json(['message' => 'No file uploaded'], 400);
    }

    public function revert(Request $request)
    {
        // Validate logo_url
        $request->validate([
            'logo_url' => 'required|string',
        ]);

        // Gelen logo_url bilgisinden 'storage/' kısmını çıkarın
        $filePath = str_replace('storage/', 'public/', $request->input('logo_url'));

        // Dosyayı sil
        if (Storage::exists($filePath)) {
            Storage::delete($filePath);
            return response()->json(['message' => 'File deleted'], 200);
        }

        return response()->json(['message' => 'File not found'], 404);
    }

    /**
     * Dosyayı indirme işlemi.
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function download(Request $request)
    {
        // URL'den dosya yolunu ayıklama
        $url = $request->input('url');
        // Gelen logo_url bilgisinden 'storage/' kısmını çıkarın
        $filePath = str_replace('/storage', 'public', $url);

        // Dosyanın var olup olmadığını kontrol et
        if (!Storage::exists($filePath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        // Dosyanın MIME tipini belirle
        $mimeType = Storage::mimeType($filePath);

        // Dosyayı indirmek için yanıtı döndür
        return Storage::download($filePath, basename($url), [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . basename($url) . '"'
        ]);
    }

    /**
     * Sanitize file name by converting Turkish characters to English equivalents and replacing spaces with hyphens
     */
    private function sanitizeFileName($fileName)
    {
        $turkish = ['ş', 'Ş', 'ı', 'İ', 'ç', 'Ç', 'ü', 'Ü', 'ö', 'Ö', 'ğ', 'Ğ'];
        $english = ['s', 'S', 'i', 'I', 'c', 'C', 'u', 'U', 'o', 'O', 'g', 'G'];
        $fileName = str_replace($turkish, $english, $fileName);
        $fileName = preg_replace('/\s+/', '-', $fileName); // Replace spaces with hyphens
        $fileName = preg_replace('/[^A-Za-z0-9\-_]/', '', $fileName); // Remove any remaining special characters
        return $fileName;
    }
}