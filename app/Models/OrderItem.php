<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_id',
        'product_type_id',
        'product_category_id',
        'quantity',
        'color',
        'unit_price',
        'type',
        'order_basket_id'
    ];

    public function basket()
    {
        return $this->belongsTo(OrderBasket::class, 'order_basket_id');
    }


    public function productType() : BelongsTo
    {
        return $this->belongsTo(ProductType::class, 'product_type_id');
    }

    public function productCategory() : BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }


}