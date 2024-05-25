<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourierNotification extends Model
{
    use HasFactory;

    protected $table = 'courier_notifications';
    protected $fillable = [
        'message',
        'is_read',
    ];
}