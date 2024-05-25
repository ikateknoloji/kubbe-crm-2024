<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    use HasFactory;

    protected $table = 'product_categories';

    protected $fillable = ['category'];

    /**
     * Ürün kategorisi ile olan ilişkiyi tanımlar.
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function productTypes() : HasMany
    {
        return $this->hasMany(ProductType::class, 'product_category_id');
    }

    /**
     * Ürün kategorisi ile olan ürünün içeriklerini tanımlar.
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orderItems() : HasMany
    {
        return $this->hasMany(OrderItem::class, 'product_category_id');
    }
}