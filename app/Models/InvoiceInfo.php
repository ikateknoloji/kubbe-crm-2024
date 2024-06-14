<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceInfo extends Model
{
    use HasFactory;

    protected $table = 'invoice_infos';

    protected $fillable = [
        'order_id',
        'company_name',
        'address',
        'tax_office',
        'tax_number',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}