<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\ProductImage;
/**
 * Modelo Payment
 * ---------------------
 * Representa los pagos asociados a créditos.
 * Cada pago pertenece a un crédito (credit_id) y a un usuario (user_id).
 * 
 * Efectos automáticos:
 * - Al crear: descuenta del saldo del crédito.
 * - Al editar o eliminar: recalcula el saldo total según los pagos existentes.
 */
class Payment extends Model
{
    /**
     * Campos que pueden asignarse masivamente.
     */
    protected $fillable = [
        'credit_id',     // FK hacia credits.id
        'amount',        // Monto del pago
        'payment_date',  // Fecha del pago
        'method',        // Método de pago (efectivo, transferencia, etc.)
        'reference',     // Nº de comprobante, recibo o referencia
        'note',          // Observaciones del pago
        'user_id',       // Usuario que registró el pago
    ];

    /**
     * Tipos de datos (casts automáticos)
     */
    protected $casts = [
        'amount'       => 'decimal:2',
        'payment_date' => 'date',
    ];

    /* =========================
     * Relaciones
     * ========================= */

    /**
     * Un pago pertenece a un crédito.
     */
    public function credit()
    {
        return $this->belongsTo(Credit::class, 'credit_id');
    }

    /**
     * Un pago pertenece a un usuario del sistema.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /* =========================
     * Eventos del modelo (hooks)
     * =========================
     * Cada vez que se crea, actualiza o elimina un pago, 
     * se actualiza el saldo y estado del crédito relacionado.
     */
    protected static function booted(): void
    {
        // 🔹 Al crear un pago → descuenta directamente del saldo.
        static::created(function (Payment $payment) {
            DB::transaction(function () use ($payment) {
                $credit = Credit::whereKey($payment->credit_id)->lockForUpdate()->first();
                if (!$credit) return;

                // Restar el monto al saldo actual
                $credit->balance = round((float)$credit->balance - (float)$payment->amount, 2);

                // Si el saldo queda en cero → marcar como pagado
                if ($credit->balance <= 0.00) {
                    $credit->balance = 0.00;
                    $credit->status  = 'pagado';
                    if (isset($credit->next_notify_at)) {
                        $credit->next_notify_at = null; // ya no necesita recordatorios
                    }
                }

                $credit->save();
            });
        });

        // 🔹 Al actualizar o eliminar → recalcular saldo completo.
        static::updated(fn(Payment $p) => self::recomputeCredit($p->credit_id));
        static::deleted(fn(Payment $p) => self::recomputeCredit($p->credit_id));
    }

    /**
     * Recalcula el saldo de un crédito sumando todos los pagos asociados.
     * Se usa cuando un pago se edita o elimina.
     */
    protected static function recomputeCredit(int $creditId): void
    {
        DB::transaction(function () use ($creditId) {
            $credit = Credit::whereKey($creditId)->lockForUpdate()->first();
            if (!$credit) return;

            // Sumar todos los pagos registrados
            $totalPaid = (float) $credit->payments()->sum('amount');

            // Recalcular saldo (monto total - total pagado)
            $credit->balance = round((float)$credit->amount - $totalPaid, 2);

            // Determinar estado actual
            if ($credit->balance <= 0.00) {
                $credit->balance = 0.00;
                $credit->status  = 'pagado';
                $credit->next_notify_at = null;
            } else {
                // Mantiene estado actual o lo vuelve pendiente si estaba marcado manualmente
                $credit->status = in_array($credit->status, ['vencido', 'pendiente'])
                    ? $credit->status
                    : 'pendiente';
            }

            $credit->save();
        });
    }

    /* =========================
     * Relaciones adicionales
     * ========================= */
    public function images() {
    return $this->hasMany(\App\Models\ProductImage::class)
        ->orderBy('sort_order')->orderBy('id');
    }
    public function coverImage() {
        return $this->hasOne(\App\Models\ProductImage::class)->where('is_cover', true);
    }
}
