<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'client_id','modo_pago','fecha','nota','estado','status',
        'total','gravada_10','iva_10','gravada_5','iva_5','exento','total_iva',

        // ✅ crédito (para triggers)
        'credit_installments',
        'credit_first_due_date',
        'credit_every_days',
        'credit_down_payment',
        'credit_total',
        'credit_installment_amount',
    ];

    protected $casts = [
        'fecha'      => 'date',

        // ✅ numeric(14,2) => decimal:2
        'total'      => 'decimal:2',
        'gravada_10' => 'decimal:2',
        'iva_10'     => 'decimal:2',
        'gravada_5'  => 'decimal:2',
        'iva_5'      => 'decimal:2',
        'exento'     => 'decimal:2',
        'total_iva'  => 'decimal:2',

        'credit_down_payment'       => 'decimal:2',
        'credit_total'              => 'decimal:2',
        'credit_installment_amount' => 'decimal:2',
        'credit_first_due_date'     => 'date',
    ];


    /* =========================
     * Relaciones
     * ========================= */

    // Cliente (con soft deletes, según lo tenías)
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id')->withTrashed();
    }

    // Ítems de la venta
    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    // Cuotas / Cuentas por cobrar (credits)
    public function credits()
    {
        // Si tu tabla de cuotas es "accounts_receivable" con modelo Credit ya configurado,
        // esta relación funciona tal cual.
        return $this->hasMany(Credit::class, 'sale_id');
    }

    // Pagos de la venta (a través de las cuotas)
    public function payments()
    {
        return $this->hasManyThrough(
            Payment::class, // Modelo final
            Credit::class,  // Modelo intermedio
            'sale_id',      // FK en Credit que apunta a Sale
            'credit_id',    // FK en Payment que apunta a Credit
            'id',           // Local key en Sale
            'id'            // Local key en Credit
        );
    }

    /* =========================
     * Helpers / Atributos
     * ========================= */

    public function isCredit(): bool
    {
        return $this->modo_pago === 'credito';
    }

    // Saldo total pendiente sumando todas las cuotas
    public function getCreditBalanceAttribute(): int
    {
        // Si ya cargaste relaciones con ->with('credits'), evitamos query extra:
        if ($this->relationLoaded('credits')) {
            return (int) $this->credits->sum(fn ($c) => $c->computed_balance);
        }
        // Si no está cargado, consultamos directo a la DB:
        return (int) Credit::where('sale_id', $this->id)
            ->selectRaw('COALESCE(SUM(amount - COALESCE(paid_amount,0)),0) as bal')
            ->value('bal');
    }

    // Monto total financiado (suma de amount de todas las cuotas)
    public function getCreditTotalAmountAttribute(): int
    {
        if ($this->relationLoaded('credits')) {
            return (int) $this->credits->sum('amount');
        }
        return (int) Credit::where('sale_id', $this->id)->sum('amount');
    }

    /* =========================
     * Scopes útiles
     * ========================= */
    public function scopeCredit($q)
    {
        return $q->where('modo_pago', 'credito');
    }

    public function scopeCash($q)
    {
        return $q->where('modo_pago', 'contado');
    }

    // Relación con factura (si aplica)
    public function invoice() {
    return $this->hasOne(\App\Models\Invoice::class);
}

}
