<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderImage extends Model
{
    use HasFactory;
    protected $table = 'order_images';
    protected $fillable = [
        'type',
        'order_id',
        'product_image',
        'mime_type',
    ];

    public function order() : BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}