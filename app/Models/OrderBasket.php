<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderBasket extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function logos()
    {
        return $this->hasMany(OrderLogo::class);
    }
}