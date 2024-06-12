<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;
    protected $table = 'orders';

    protected $fillable = [
        'customer_id',
        'order_name',
        'order_code',
        'status',
        'manufacturer_id',
        'invoice_type',
        'is_rejected',
        'note',
        'shipping_type', 
        'offer_price',
        //  Zaman Takip
        'production_start_date',
        'production_date',
        'delivery_date',
        'estimated_production_date',
        // Okuma Takip
        'admin_read',
        'customer_read',
        'manufacturer_read',
        'production_stage'
    ];

    // Siparişe ek olarak bu bilgileri 
    protected $appends = ['original_status', 'status_color'];

    // 'status' sütunu için dönüştürme fonksiyonu
    public function getStatusAttribute($value)
    {         
        $statusMap = [
            'OC' => 'Sipariş Onayı',
            'DP' => 'Tasarım Aşaması',
            'DA' => 'Tasarım Eklendi',
            'P'  => 'Ödeme Aşaması',
            'PA' => 'Ödeme Alındı',
            'MS' => 'Üretici Seçimi',
            'PP' => 'Üretimde',
            'PR' => 'Ürün Hazır',
            'PIT' => 'Ürün Transfer Aşaması',
            'PD' => 'Ürün Teslim Edildi',
        ];

        return $statusMap[$value] ?? $value;
    }

    // status_color fonksiyonu durum kodları için renk değerlerini dönüştürme fonksiyonu.
    public  function getStatusColorAttribute($status)
    {
        $status = $this->original_status; 
        $statusColorMap = [
            'OC' => '#FF0000', // Kırmızı
            'DP' => '#debf10', // Daha koyu Sarı
            'DA' => '#008000', // Yeşil
            'P'  => '#0000FF', // Mavi
            'PA' => '#800080', // Mor
            'MS' => '#FFA500', // Turuncu
            'PP' => '#800000', // Maroon
            'PR' => '#008B8B', // Daha koyu Aqua
            'PIT' => '#000080', // Navy
            'PD' => '#006666', // Daha koyu Teal
        ];    

        return $statusColorMap[$status] ?? '#000000'; // Eğer durum kodu haritada yoksa, varsayılan olarak siyah döndürülür.
    }

    // 'status' sütunu için dönüştürme fonksiyonu orjinal durum kodlarını getirmek için burda getOriginalStatusAttribute() fonksiyonu kullanılır.
    public function getStatusLabelAttribute()
    {
        $statusMap = [
            'OC' => 'Sipariş Onayı',
            'DP' => 'Tasarım Aşaması',
            'DA' => 'Tasarım Eklendi',
            'P'  => 'Ödeme Aşaması',
            'PA' => 'Ödeme Alındı',
            'MS' => 'Üretici Seçimi',
            'PP' => 'Üretimde',
            'PR' => 'Ürün Hazır',
            'PIT' => 'Ürün Transfer Aşaması',
            'PD' => 'Ürün Teslim Edildi',
        ];
    
        return $statusMap[$this->attributes['status']] ?? $this->attributes['status'];
    }  

    // 'status' sütunu için dönüştürme fonksiyonu orjinal durum kodlarını getirmek için burda getOriginalStatusAttribute() fonksiyonu kullanılır.
    public function getOriginalStatusAttribute()
    {
        return $this->attributes['status'];
    }

    // 'invoice_type' sütunu için dönüştürme fonksiyonu burda fatura tipi tanımlar.
    public function getInvoiceTypeAttribute($value)
    {
        $invoiceTypeMap = [
            'I' => 'Bireysel',
            'C' => 'Kurumsal',
        ];

        return $invoiceTypeMap[$value] ?? $value;
    }

    // 'shipping_type' sütunu için dönüştürme fonksiyonu burda teslim tipi tanımlar.
    public function getShippingTypeAttribute($value)
    {
        $types = [
            'A' => 'Alıcı Ödemeli',
            'G' => 'Gönderici Ödemeli',
            'T' => 'Ofis Teslim',
        ];

        return $types[$value] ?? $value;
    }

    // 'shipping_type' sütunu için dönüştürme fonksiyonu burda teslim tipi tanımlar.
    public function setShippingTypeAttribute($value)
    {
        $types = [
            'Alıcı Ödemeli' => 'A',
            'Gönderici Ödemeli' => 'G',
            'Ofis Teslim' => 'T',
        ];
    
        $this->attributes['shipping_type'] = $types[$value] ?? $value;
    }

    /**
     * Bu siparişin müşterisini tanımlar.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Bu siparişin üreticisini tanımlar.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manufacturer_id');
    }

    /**
    *  Siparişin içeriği hakkında bilgileri tanımlar.
    *  Sipariş ürünlerinin Resim , Adet , Renk , kategori gibi bilgiler barındırılır.
    */

    public function orderItems() : HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    } 
    
    /**
     * Bu siparişin resimleri getirir.
     */
    public function orderImages(): HasMany
    {
        return $this->hasMany(OrderImage::class, 'order_id');
    }

    /**
      * Siparişin Müşterisinin içeriklerini ilişkileri burada tanımlanır.
      * customerInfo , OrderAddress , invoiceInfo
      * @return \Illuminate\Database\Eloquent\Relations\HasOne
      */

    public function customerInfo() : HasOne
    {
        return $this->hasOne(CustomerInfo::class);
    }

    /**
     * Siparişin Adresinin içeriklerini ilişkileri burada tanımlanır.
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function orderAddress() : HasOne
    {
        return $this->hasOne(OrderAddress::class);
    }

    /**
     * Siparişin Fatura için içeriklerini ilişkileri burada tanımlanır.
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function invoiceInfo() : HasOne
    {
        return $this->hasOne(InvoiceInfo::class, 'order_id');
    }

    /**
     * Siparişin iptal edilenlerinin içeriklerini ilişkileri burada tanımlanır.
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function orderCancelRequest()
    {
        return $this->hasOne(OrderCancelRequest::class);
    }

    /**
     * Siparişin iptal edilenlerinin içeriklerini ilişkileri burada tanımlanır.
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function cancelledOrder()
    {
        return $this->hasOne(CancelledOrder::class);
    }

    /**
     * Siparişin red edilenlerinin içeriklerini ilişkileri burada tanımlanır.
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function rejectedOrder()
    {
        return $this->hasOne(RejectedOrder::class);
    }

    public function logoImage(): HasOne
    {   
        return $this->hasOne(OrderImage::class)->where('type', 'L');
    }

    public function designImages()
    {
        return $this->hasMany(DesignImage::class, 'order_id');
    }
    
    public function productionImages(): HasMany
    {
        return $this->hasMany(ProductionImage::class, 'order_id');
    }
}