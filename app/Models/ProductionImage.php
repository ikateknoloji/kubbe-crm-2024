<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionImage extends Model
{
    use HasFactory;
    protected $fillable = ['order_id', 'file_path'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}