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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class StoreOrderController extends Controller
{
    /**
     * Sipariş oluşturma
     * @param \Illuminate\Http\Request $request
     * @return mixed|\Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @POST 
     * {
     *   "order_name": "Sipariş Adı",
     *   "invoice_type": "I",
     *   "offer_price": "100",
     *   "note": "Not",
     *   "order_items": [
     *     {
     *       "product_type_id": 1,
     *       "product_category_id": 1,
     *       "quantity": 1,
     *       "color": "Black",
     *       "unit_price": "100",
     *       "type": "T"
     *     },
     *     {
     *       "product_type_id": 2,
     *       "product_category_id": 1,
     *       "quantity": 1,
     *       "color": "Black",
     *       "unit_price": "100",
     *       "type": "T"
     *     }
     *   ],
     *   "order_address": "Adres",
     *   "image_url": "image.jpg",
     *   "name": "Name",
     *   "surname": "Surname",
     *   "phone": "Phone",
     *   "email": "Email"
     *  }
     */
    
    public function createOrder(Request $request)
    {
        try {
            // Gelen verileri doğrula
            $request->validate([
                // Sipariş Detayları
                'order_name' => 'required|string',
                'invoice_type' => 'required|in:I,C',
                'offer_price' => 'required|numeric|min:0',
                'note' => 'nullable|string',
                'shipping_type' => 'required|in:A,G,T', // Eklenen kısım
                // Sipariş Kalemleri Detaylı Bilgi
                'order_items.*.product_type_id' => ['nullable', 'exists:product_types,id'],
                'order_items.*.product_category_id' => ['required', 'exists:product_categories,id'],
                'order_items.*.quantity' => ['required', 'integer'],
                'order_items.*.color' => ['required', 'string'],
                'order_items.*.unit_price' => ['required', 'numeric'],
                'order_items.*.type' => ['nullable', 'string'],
                // Sipariş için logo bilgisi
                'image_url' => 'required|file|mimes:jpeg,png,jpg,gif,svg,pdf,ai,webp',
                // Müşteri Bilgileri
                'name' => 'required|string',
                'surname' => 'required|string',
                'phone' => ['required', 'string', 'regex:/^(\+90|0)?[1-9]{1}[0-9]{9}$/'],
                'email' => 'nullable|email',
                // Adres Bilgisi
                'order_address' => 'required|string',
            ]);
            
            // Transaksiyon başlat
            DB::beginTransaction();

            // Yeni sipariş oluştur
            $order = Order::create([
                'customer_id' => Auth::id(),
                'order_code' => 'ORD-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4)),
                'status' => 'OC', // Otomatik olarak "OC" durumu
                'invoice_type' => $request->input('invoice_type'),
                'offer_price' => $request->input('offer_price'),
                'order_name' => $request->input('order_name'),
                'shipping_type' => $request->input('shipping_type'),
            ]);

            $orderAddress = new OrderAddress(['address' => $request->input('order_address')]);
            $order->orderAddress()->save($orderAddress);

            // Sipariş öğelerini ekleyerek kaydet
            $orderItems = collect($request->input('order_items'))->map(function ($item) use ($order) {
                return new OrderItem([
                    'order_id' => $order->id,
                    'product_type_id' => $item['product_type_id'],
                    'product_category_id' => $item['product_category_id'],
                    'type' => $item['type'],
                    'quantity' => $item['quantity'],
                    'color' => $item['color'],
                    'unit_price' => $item['unit_price'],
                ]);
            });

            $order->orderItems()->saveMany($orderItems);

            // Fatura tipine göre ilgili fatura bilgileri ekleniyor
            if ($request->invoice_type == 'C') {
                $this->addCorporateInvoiceInfo($order, $request);
            }else {
                // Fatura tipi 'C' değilse, CustomerInfo tablosuna bilgileri ekliyoruz
                CustomerInfo::create([
                    'name' => $request->input('name'),
                    'surname' => $request->input('surname'),
                    'phone' => $request->input('phone'),
                    'email' => $request->input('email'),
                    'order_id' => $order->id, // Yeni oluşturulan siparişin ID'si
                ]);
            }

            if ($request->hasFile('image_url')) {
                $image = $request->file('image_url');
                $imageName = 'L' . $order->id . '.' . $image->getClientOriginalExtension();
                
                // Resmi storage'a kaydet
                $path = $image->storeAs('public/logo', $imageName);
            
                // Kaydedilen dosyanın URL'sini al
                $url = Storage::url($path);
            
                // OrderImage kaydını oluştur
                OrderImage::create([
                    'type' => 'L', // Logo tipi
                    'product_image' => $url, // Resim dosyasının URL'si
                    'mime_type' => $image->getClientMimeType(), // MIME tipini kaydet
                    'order_id' => $order->id,
                ]);

                broadcast(new AdminNotificationEvent([
                    'title' => 'Yeni Sipariş Oluşturuldu',
                    'body' => 'Bir sipariş oluşturuldu.',
                    'order' => $order,
                ]));
            }

            
            DB::commit();
            // Başarılı oluşturma yanıtı
            return response()->json(['order' => $order], 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollback();
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json($e, 500);
        }
    }

    /**
     * Fatura bilgilerini Ekleme
     * @param \App\Models\Order $order
     * @param \Illuminate\Http\Request $request
     * @return mixed|\Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @POST 
     * {
     *   "company_name": "Fatura Bilgileri",
     *   "address": "Adres",
     *   "tax_office": "Tax Office",
     *   "tax_number": "Tax Number",
     *   "email": "Email"
     *   "name": "Name",
     *   "surname": "Surname",
     *   "phone": "Phone"
     *  }
     */
    protected function addCorporateInvoiceInfo(Order $order, Request $request)
    {
        // addressControll değerine göre 'address' alanının doğrulama kuralını belirle
        if ($request->input('addressControll') == 'true') {
            $addressRule = 'required|string';
        } else {
            $addressRule = 'nullable|string';
        }
    
        // Fatura bilgilerini doğrula
        $request->validate([
            'company_name' => 'required|string',
            'address' => $addressRule,
            'tax_office' => 'required|string',
            'tax_number' => 'required|string',
            'email' => 'required|email',
        ]);
    
        // addressControll değerine göre hangi adresin kaydedileceğini belirle
        $address = $request->input('addressControll') == 'true' ? $request->input('address') : $request->input('order_address');
    
        // Fatura bilgilerini ekleyerek kaydet
        $invoiceInfo = InvoiceInfo::create([
            'order_id' => $order->id,
            'company_name' => $request->input('company_name'),
            'address' => $address,
            'tax_office' => $request->input('tax_office'),
            'tax_number' => $request->input('tax_number'),
            'email' => $request->input('email'),
        ]);
    
        // Müşteri bilgilerini ekleyerek kaydet
        $customerInfo = CustomerInfo::create([
            'name' => $request->input('name'),
            'surname' => $request->input('surname'),
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'order_id' => $order->id, // Yeni oluşturulan siparişin ID'si
        ]);
        $message = [
            'title' => 'Sipariş Oluşturuldu',
            'body' => 'Bir sipariş oluşturuldu.',
            'order' => $order,
        ];

        // Başarılı ekleme yanıtı
        return response()->json(['invoice_info' => $invoiceInfo], 201);
    } 
    
    /**
     * Form içeriklerinin validation işlemlerini yapıyoruz.
     * ? Tüm rotalar rotası
     */
    public function validateForms(Request $request)
    {
        // Fatura tipine göre doğrulama kurallarını belirle
        $rules = [
            'order_name' => 'required|string',
            'invoice_type' => 'required|in:I,C',
            'note' => 'nullable|string',
            'image_url' => 'required|file|mimes:jpeg,png,jpg,gif,svg,pdf,ai,webp',
            'name' => 'required|string',
            'surname' => 'required|string',
            'phone' => 'required|string|regex:/^([5]{1}[0-9]{9})$/',
            'email' => 'nullable|email',
            'order_address' => 'required|string',  // Adres bilgisi için validasyon kuralı
            'shipping_type' => 'required|in:T,A,G',
        ];
    
        // addressControll değerine göre 'address' alanının doğrulama kuralını belirle
        if ($request->input('addressControll') == 'true') {
            $rules['address'] = 'required|string';
        } else {
            $rules['address'] = 'nullable|string';
        }
    
        if ($request->input('invoice_type') == 'C') {
            $rules['company_name'] = 'required|string';
            $rules['tax_office'] = 'required|string';
            $rules['tax_number'] = 'required|string';
            $rules['email'] = 'required|email';
        }
    
        $request->validate($rules,[
            'order_name' => 'Şipariş adı gereklidir',
            'invoice_type.required' => 'Fatura tipi zorunludur.',
            'invoice_type.in' => 'Geçersiz fatura tipi.',
            'image_url.required' => 'Resim dosyası alanı gereklidir',
            'image_url.image' => 'Geçersiz resim formatı.',
            'image_url.mimes' => 'Geçersiz resim MIME türü.',
            'image_url.max' => 'Resim boyutu en fazla 2048 KB olmalıdır.',
            'phone.required' => 'Telefon numarası zorunludur.',
            'phone.string' => 'Telefon numarası bir dize olmalıdır.',
            'phone.regex' => 'Geçersiz telefon numarası.',
            'name.required' => 'Ad alanı zorunludur.',
            'name.string' => 'Ad alanı bir dize olmalıdır.',
            'surname.required' => 'Soyadı alanı zorunludur.',
            'surname.string' => 'Soyadı alanı bir dize olmalıdır.',
            'company_name.required' => 'Şirket adı alanı zorunludur.',
            'company_name.string' => 'Şirket adı bir dize olmalıdır.',
            'address.required' => 'Fatura Adresi alanı zorunludur.',
            'address.string' => 'Adres bir dize olmalıdır.',
            'tax_office.required' => 'Vergi dairesi alanı zorunludur.',
            'tax_office.string' => 'Vergi dairesi bir dize olmalıdır.',
            'tax_number.required' => 'Vergi numarası alanı zorunludur.',
            'tax_number.string' => 'Vergi numarası bir dize olmalıdır.',
            'email.required' => 'E-posta alanı zorunludur.',
            'email.email' => 'Geçersiz e-posta adresi.',
            'order_address.required' => 'Adres alanı zorunludur.',
            'order_address.string' => 'Adres bir dize olmalıdır.',
            'shipping_type.required' => 'Kargo gönderim şeklini alanı zorunludur.',
            'shipping_type.in' => 'Geçerli bir kargo gönderim şeklini seçiniz (T, A veya G).',
        ]);
    
        // Doğrulama başarılı
        return response()->json(['message' => 'Doğrulama başarılı'], 200);
    }

    public function validateOrderItem(Request $request)
    {
        try {
            // Validate incoming data
            $validatedData = $request->validate([
                'product_type_id' => 'nullable|exists:product_types,id',
                'type' => 'nullable|string',
                'product_category_id' => 'required|exists:product_categories,id',
                'quantity' => 'required|integer|min:1',
                'color' => 'required|string',
                'unit_price' => 'required|numeric|min:0',
            ], [
                'product_type_id.exists' => 'Geçersiz ürün tipi.',
                'product_category_id.required' => 'Ürün kategorisi gereklidir.',
                'product_category_id.exists' => 'Geçersiz ürün kategorisi.',
                'quantity.required' => 'Miktar gereklidir.',
                'quantity.integer' => 'Miktar bir tam sayı olmalıdır.',
                'quantity.min' => 'Miktar en az 1 olmalıdır.',
                'color.required' => 'Renk gereklidir.',
                'color.string' => 'Renk bir metin olmalıdır.',
                'unit_price.required' => 'Birim fiyat gereklidir.',
                'unit_price.numeric' => 'Birim fiyat bir sayı olmalıdır.',
                'unit_price.min' => 'Birim fiyat en az 0 olmalıdır.',
            ]);

            if (empty($request->input('product_type_id')) && empty($request->input('type'))) {
                return response()->json(['error' => 'Ürün tipi zorunludur.'], 422);
            }
    
            // Successful validation response
            return response()->json(['message' => 'Doğrulama başarılı.'], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Get the first error message
            $firstError = Arr::first($e->errors())[0];
    
            // Return error response
            return response()->json(['error' => $firstError], 422);
        }
    }
}