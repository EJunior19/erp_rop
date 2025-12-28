<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    /* =========================
     * Mass assignment
     * ========================= */
    protected $fillable = [
        'client_id',
        'modo_pago',
        'fecha',
        'nota',
        'status',

        'total',
        'gravada_10',
        'iva_10',
        'gravada_5',
        'iva_5',
        'exento',
        'total_iva',

        // ✅ crédito (para triggers)
        'credit_installments',
        'credit_first_due_date',
        'credit_every_days',
        'credit_down_payment',
        'credit_total',
        'credit_installment_amount',
    ];

    /* =========================
     * Casts
     * ========================= */
    protected $casts = [
        'fecha' => 'date',

        // numeric(14,2) → decimal:2
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

    // Cliente (incluye soft-deletes)
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id')->withTrashed();
    }

    // Ítems de la venta
    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    // Cuotas / Cuentas por cobrar
    public function credits()
    {
        return $this->hasMany(Credit::class, 'sale_id');
    }

    // Pagos (a través de las cuotas)
    public function payments()
    {
        return $this->hasManyThrough(
            Payment::class, // modelo final
            Credit::class,  // modelo intermedio
            'sale_id',      // FK en credits
            'credit_id',    // FK en payments
            'id',           // PK en sales
            'id'            // PK en credits
        );
    }

    // Relación con factura (si aplica)
    public function invoice()
    {
        return $this->hasOne(\App\Models\Invoice::class);
    }

    /* =========================
     * Helpers / Atributos
     * ========================= */

    public function isCredit(): bool
    {
        return $this->modo_pago === 'credito';
    }

    /**
     * 🔁 COMPATIBILIDAD
     * Permite seguir usando $sale->estado en vistas/controladores
     * aunque la columna real sea "status"
     */
    public function getEstadoAttribute(): string
    {
        return (string) $this->status;
    }

    /**
     * Label humano para UI
     */
    public function getEstadoLabelAttribute(): string
    {
        return match ($this->status) {
            'pendiente_aprobacion' => 'Pendiente',
            'aprobado'             => 'Aprobado',
            'rechazado'            => 'Rechazado',
            'cancelado'            => 'Anulado',
            'editable'             => 'Editable',
            default                => ucfirst((string) $this->status),
        };
    }

    /**
     * Saldo total pendiente (suma de cuotas)
     */
    public function getCreditBalanceAttribute(): int
    {
        if ($this->relationLoaded('credits')) {
            return (int) $this->credits->sum(fn ($c) => $c->computed_balance);
        }

        return (int) Credit::where('sale_id', $this->id)
            ->selectRaw('COALESCE(SUM(amount - COALESCE(paid_amount,0)),0)')
            ->value('sum');
    }

    /**
     * Total financiado (suma de cuotas)
     */
    public function getCreditTotalAmountAttribute(): int
    {
        if ($this->relationLoaded('credits')) {
            return (int) $this->credits->sum('amount');
        }

        return (int) Credit::where('sale_id', $this->id)->sum('amount');
    }

    /* =========================
     * Scopes
     * ========================= */
    public function scopeCredit($q)
    {
        return $q->where('modo_pago', 'credito');
    }

    public function scopeCash($q)
    {
        return $q->where('modo_pago', 'contado');
    }
}
