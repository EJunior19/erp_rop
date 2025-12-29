<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payable extends Model
{
    protected $fillable = [
        'purchase_invoice_id',
        'supplier_id',
        'total_amount',
        'advance_amount',
        'pending_amount',
        'due_date',
        'payment_term',
        'status',
        'created_by',
    ];

    protected $casts = [
        'total_amount'   => 'float',
        'advance_amount' => 'float',
        'pending_amount' => 'float',
        'due_date'       => 'date',
    ];

    // 🔗 Relaciones

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function invoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function payments()
    {
        return $this->hasMany(\App\Models\PayablePayment::class, 'payable_id');
    }

    // 🧷 Accesos "rápidos" usando relaciones ya existentes

    // Recepción a través de la factura (solo lectura)
    public function getReceiptAttribute()
    {
        return $this->invoice?->receipt;
    }

    // OC a través de la recepción (solo lectura)
    public function getOrderAttribute()
    {
        return $this->invoice?->receipt?->order;
    }

    // Monto ya pagado = adelanto + pagos registrados
    public function getPaidAmountAttribute()
    {
        $paymentsSum = $this->payments()->sum('amount');

        return (float) ($this->advance_amount ?? 0) + (float) $paymentsSum;
    }

    public function getIsOverdueAttribute()
    {
        return $this->pending_amount > 0
            && $this->due_date
            && $this->due_date->isPast();
    }
}
