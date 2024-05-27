<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManufacturerNotification extends Model
{
    use HasFactory;

    protected $table = 'manufacturer_notifications';

    protected $fillable = [
        'message',
        'is_read',
        'user_id'
    ];

    public function user() : BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}