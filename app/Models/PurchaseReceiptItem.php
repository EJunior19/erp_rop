<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReceiptItem extends Model
{
    protected $fillable = [
        'purchase_receipt_id',
        'product_id',
        'ordered_qty',
        'received_qty',
        'unit_cost',
        'subtotal',
        'status',
        'reason',
        'comment',
    ];

    public function receipt()
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Ítems de factura que se generaron a partir de este ítem de recepción.
     *
     * purchase_invoice_items.purchase_receipt_item_id -> purchase_receipt_items.id
     */
    public function invoiceItems()
    {
        return $this->hasMany(PurchaseInvoiceItem::class, 'purchase_receipt_item_id');
    }

    protected static function booted()
    {
        static::saving(function ($i) {
            $i->subtotal = ($i->received_qty ?? 0) * ($i->unit_cost ?? 0);

            $i->status = ($i->received_qty >= $i->ordered_qty)
                ? 'completo'
                : (($i->received_qty > 0) ? 'parcial' : 'faltante');
        });
    }
}
