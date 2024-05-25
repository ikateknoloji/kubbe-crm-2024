<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CancelledOrder extends Model
{
    use HasFactory;
    protected $table = 'cancelled_orders';

    protected $fillable = ['order_id', 'reason', 'title'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}