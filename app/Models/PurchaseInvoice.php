<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseInvoice extends Model
{
    protected $fillable = [
        'purchase_receipt_id',
        'invoice_number',
        'invoice_date',
        'subtotal',
        'tax',
        'total',
        'status',
        'notes',
        'created_by',
    ];

    public function receipt()
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseInvoiceItem::class);
    }

    protected static function booted()
    {
        static::saving(function($inv){
            $inv->loadMissing('items');
            $inv->subtotal = $inv->items->sum('subtotal');
            $inv->tax      = $inv->items->sum('tax');
            $inv->total    = $inv->items->sum('total');
        });
    }
    public function purchase()
{
    return $this->hasOne(Purchase::class);
}

}
