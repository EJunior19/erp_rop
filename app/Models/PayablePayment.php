<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayablePayment extends Model
{
    protected $fillable = [
        'payable_id',
        'payment_date',
        'amount',
        'method',
        'reference',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount'       => 'float',
    ];

    public function payable()
    {
        return $this->belongsTo(Payable::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
