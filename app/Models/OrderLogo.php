<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderLogo extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_basket_id', 'logo_path'
    ];

    public function basket()
    {
        return $this->belongsTo(OrderBasket::class, 'order_basket_id');
    }
}