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
        // Normalizar monto: acepta "1.500.000" -> 1500000.00
        $rawAmount = (string) $request->input('amount');
        $normalized = preg_replace('/[^\d.,]/', '', $rawAmount);
        $normalized = str_replace(['.', ','], ['', '.'], $normalized);
        $amount = (float) $normalized;

        $request->merge(['amount' => $amount]);

        $request->validate([
            'amount'       => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'method'       => ['nullable', 'string', 'max:100'],
            'reference'    => ['nullable', 'string', 'max:100'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ]);

        // Regla: no pagar más de lo pendiente
        if ($amount > (float) $payable->pending_amount) {
            return back()
                ->withErrors(['amount' => 'El pago no puede superar el saldo pendiente.'])
                ->withInput();
        }

        DB::transaction(function () use ($payable, $request, $amount) {

            PayablePayment::create([
                'payable_id'   => $payable->id,
                'payment_date' => $request->payment_date,
                'amount'       => $amount,
                'method'       => $request->method,
                'reference'    => $request->reference,
                'notes'        => $request->notes,
                'created_by'   => auth()->id(),
            ]);

            // Recalcular saldos y estado
            $sumPayments = $payable->payments()->sum('amount');
            $totalPaid   = ($payable->advance_amount ?? 0) + $sumPayments;
            $pending     = max(0, (float) $payable->total_amount - $totalPaid);

            $status = 'pendiente';
            if ($pending <= 0) {
                $status = 'pagado';
            } elseif ($pending < $payable->total_amount) {
                $status = 'parcial';
            }

            $payable->update([
                'pending_amount' => $pending,
                'status'         => $status,
            ]);
        });

        return back()->with('success', 'Pago al proveedor registrado correctamente.');
    }
}
