<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Credit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * Registrar un pago (abono) a un crédito.
     *
     * Soporta dos modos:
     *  - Ruta anidada:  POST /credits/{credit}/payments  (name: credits.payments.store)
     *      Firma: store(Request $request, ?Credit $credit)
     *  - Ruta plana:    POST /payments                   (name: payments.store)
     *      Firma: store(Request $request) con hidden credit_id
     */
    public function store(Request $request, ?Credit $credit = null)
    {
        // 1) Resolver el crédito según el tipo de ruta
        if (!$credit) {
            $request->validate([
                'credit_id' => ['required', 'exists:credits,id'],
            ]);
            $credit = Credit::findOrFail($request->input('credit_id'));
        }

        // 2) Normalizar monto (acepta "1.500.000" -> 1500000)
        $digits = preg_replace('/\D+/', '', (string) $request->input('amount', ''));
        $amountClean = ($digits === '' ? null : (int) $digits);

        // 3) Validar campos (con amount ya normalizado)
        $request->merge(['amount' => $amountClean]);
        $request->validate([
            'amount'       => ['required', 'integer', 'min:1'],
            'payment_date' => ['required', 'date'],
            'method'       => ['nullable', 'string', 'max:100'],
            'reference'    => ['nullable', 'string', 'max:100'],
            'note'         => ['nullable', 'string', 'max:500'],
        ]);

        // 4) Regla de negocio: el abono no puede superar el saldo
        if ((int) $amountClean > (int) $credit->balance) {
            return back()
                ->withErrors(['amount' => 'El abono no puede superar el saldo pendiente.'])
                ->withInput();
        }

        // 5) Persistir en transacción
        DB::transaction(function () use ($credit, $amountClean, $request) {
            Payment::create([
            'credit_id'    => $credit->id,
            'user_id'      => auth()->id(),
            'amount'       => $amountClean,
            'payment_date' => $request->payment_date,
            'method'       => $request->method,
            'reference'    => $request->reference,
            'note'         => $request->note,
            ]);

            $credit->balance = max(0, (int) $credit->balance - (int) $amountClean);
            if ((int) $credit->balance === 0) {
                $credit->status = 'pagado';
            }
            $credit->save();

            if (method_exists($credit, 'refreshAggregates')) {
                $credit->refreshAggregates();
            }
        });

        return redirect()
            ->route('credits.show', $credit)
            ->with('ok', '✅ Pago registrado correctamente.');
    }

}
