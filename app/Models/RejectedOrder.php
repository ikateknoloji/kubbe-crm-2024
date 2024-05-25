<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RejectedOrder extends Model
{
    use HasFactory;

    protected $table = 'rejected_orders';

    protected $fillable = ['order_id', 'reason','title'];

    public function order() : BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}