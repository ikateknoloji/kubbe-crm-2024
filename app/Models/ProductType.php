<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductType extends Model
{
    use HasFactory;

    /**
     * Tablo ile ilişkilendirilmiş model.
     *
     * @var string
     */
    protected $table = 'product_types';

    /**
     * Toplu atama yapılabilir özellikler.
     *
     * @var array
     */
    protected $fillable = ['product_type', 'product_category_id'];

    /**
     * Ürün kategorisi ile olan ilişkiyi tanımlar.
     */
    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function orderItems() : HasMany
    {
        return $this->hasMany(OrderItem::class, 'product_type_id');
    }
}