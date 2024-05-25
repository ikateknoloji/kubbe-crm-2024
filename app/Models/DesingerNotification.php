<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DesingerNotification extends Model
{
    use HasFactory;

    protected $table = 'desinger_notifications';
    protected $fillable = [
        'message',
        'is_read',
    ];
}