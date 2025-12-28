<?php

namespace App\Http\Controllers;

use App\Models\Payable;
use App\Models\PayablePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayablePaymentController extends Controller
{
    public function store(Request $request, Payable $payable)
    {
        // ==========================================
        // 1️⃣ Normalizar monto (ej: "1.500.000" -> 1500000.00)
        // ==========================================
        $rawAmount  = (string) $request->input('amount');
        $normalized = preg_replace('/[^\d.,]/', '', $rawAmount);
        $normalized = str_replace(['.', ','], ['', '.'], $normalized);
        $amount     = (float) $normalized;

        $request->merge(['amount' => $amount]);

        // ==========================================
        // 2️⃣ Validación
        // ==========================================
        $request->validate([
            'amount'       => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'method'       => ['required', 'string', 'max:100'],
            'reference'    => ['nullable', 'string', 'max:100'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ]);

        // ==========================================
        // 3️⃣ Regla de negocio: no pagar de más
        // ==========================================
        if ($amount > (float) $payable->pending_amount) {
            return back()
                ->withErrors([
                    'amount' => 'El pago no puede superar el saldo pendiente.',
                ])
                ->withInput();
        }

        // ==========================================
        // 4️⃣ Transacción
        // ==========================================
        DB::transaction(function () use ($payable, $request, $amount) {

            // ➕ Registrar pago
            PayablePayment::create([
                'payable_id'   => $payable->id,
                'payment_date' => $request->payment_date,
                'amount'       => $amount,
                'method'       => $request->method,
                'reference'    => $request->reference,
                'notes'        => $request->notes,
                'created_by'   => auth()->id(),
            ]);

            // 🔄 Recalcular montos
            $sumPayments = $payable->payments()->sum('amount');
            $totalPaid   = ($payable->advance_amount ?? 0) + $sumPayments;
            $pending     = max(0, (float) $payable->total_amount - $totalPaid);

            // 📌 Estado
            $status = 'pendiente';
            if ($pending <= 0) {
                $status = 'pagado';
            } elseif ($pending < $payable->total_amount) {
                $status = 'parcial';
            }

            // 💾 Actualizar cuenta por pagar
            $payable->update([
                'pending_amount' => $pending,
                'status'         => $status,
            ]);
        });

        // ==========================================
        // 5️⃣ Redirect correcto (PRG)
        // ==========================================
        return redirect()
            ->route('payables.show', $payable->id)
            ->with('success', 'Pago registrado correctamente');
    }
}
