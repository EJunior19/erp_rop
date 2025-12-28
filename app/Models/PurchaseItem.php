<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $table = 'purchase_items';

    protected $fillable = [
        'purchase_id',
        'product_id',
        'qty',
        'cost',          // ✅ ESTE ES EL CAMPO REAL
    ];

    protected $casts = [
        'purchase_id' => 'integer',
        'product_id'  => 'integer',
        'qty'         => 'integer',
        'cost'        => 'float', // ✅ NO unit_cost
    ];

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
