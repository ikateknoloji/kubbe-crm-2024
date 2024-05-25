<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAddress extends Model
{
    use HasFactory;
     
    protected $table = 'order_addresses';
    protected $fillable = ['order_id', 'address'];

    public function order() : BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}