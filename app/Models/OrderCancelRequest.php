<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderCancelRequest extends Model
{
    use HasFactory;
    protected $table = 'order_cancel_requests';

    protected $fillable = ['order_id', 'reason', 'title'];

    public function order() : BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}