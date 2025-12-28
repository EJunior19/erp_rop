<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $fillable = [
        'supplier_id',
        'purchased_at',
        'notes',
        'invoice_number',
        'timbrado_expiration',
        'timbrado',
        'estado', // almacenado en DB
    ];

    protected $casts = [
        'purchased_at'        => 'datetime',
        'timbrado_expiration' => 'date',
        'estado'              => 'string',
    ];

    protected $appends = [
        'total_amount',
        'display_status', // para UI (badge)
        'status',         // alias de lectura/escritura sobre 'estado'
    ];

    /* =========================
     * Relaciones
     * ========================= */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function invoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }


    /* =========================
     * Totales
     * ========================= */

    // Compatibilidad si alguien usa ->total
    public function getTotalAttribute()
    {
        return $this->items->sum(fn ($i) => ($i->qty ?? 0) * ($i->cost ?? 0));
    }

    public function getTotalAmountAttribute(): int
    {
        if ($this->relationLoaded('items')) {
            return (int) $this->items->sum(fn ($i) => (int)($i->qty ?? 0) * (int)($i->cost ?? 0));
        }

        return (int) $this->items()
            ->selectRaw('COALESCE(SUM(qty * cost), 0) as agg')
            ->value('agg');
    }

    public function scopeWithTotal($query)
    {
        return $query->select('purchases.*')->selectSub(
            fn ($q) => $q->from('purchase_items')
                ->selectRaw('COALESCE(SUM(qty * cost), 0)')
                ->whereColumn('purchase_items.purchase_id', 'purchases.id'),
            'total_amount'
        );
    }

    /* =========================
     * Estado: alias status <-> estado
     * ========================= */

    // Mapea inglés -> español y limpia espacios/minúsculas
    protected function normalizeEstado(?string $value): string
    {
        $v = strtolower(trim((string) $value));
        $map = [
            'approved'  => 'aprobado',
            'pending'   => 'pendiente',
            'rejected'  => 'rechazado',
        ];
        if (isset($map[$v])) $v = $map[$v];

        if (!in_array($v, ['aprobado', 'pendiente', 'rechazado'], true)) {
            $v = 'pendiente';
        }
        return $v;
    }

    // Accessor "status" (para que la vista pueda usar $p->status)
    public function getStatusAttribute(): string
    {
        return $this->normalizeEstado($this->attributes['estado'] ?? null);
    }

    // Mutator "status" (permite $purchase->status = 'approved' o 'aprobado')
    public function setStatusAttribute($value): void
    {
        $this->attributes['estado'] = $this->normalizeEstado($value);
    }

    // Accessor display_status (ideal para badge)
    public function getDisplayStatusAttribute(): string
    {
        return $this->status; // ya normalizado
    }

    // Normaliza siempre antes de guardar aunque asignen 'estado' directo
    protected static function booted(): void
    {
        static::saving(function (self $m) {
            if (array_key_exists('estado', $m->attributes)) {
                $m->attributes['estado'] = $m->normalizeEstado($m->attributes['estado']);
            }
        });
    }
}
