<?php

namespace App\Http\Controllers;

use App\Models\Credit;
use App\Models\Sale;
use App\Models\Client;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    /**
     * Listado de créditos (pendientes, pagados, vencidos).
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 15);
        $order   = $request->input('order', 'due_asc'); // por defecto: vence primero
        $today   = now()->startOfDay();

        $credits = Credit::with([
                'client',
                'sale',
                'payments', // 👈 necesario para recibo correcto en la vista
            ])
            // 🔎 Buscar por cliente, #crédito o #venta
            ->when($q = trim($request->input('q','')), function ($q1) use ($q) {
                $q1->where(function ($w) use ($q) {

                    // 🔍 Buscar por nombre de cliente (texto)
                    $w->whereHas('client', fn($c) =>
                        $c->where('name', 'ilike', "%{$q}%")
                    );

                    // 🔢 Buscar por ID de crédito o venta SOLO si es numérico
                    if (is_numeric($q)) {
                        $w->orWhere('id', (int) $q)
                        ->orWhereHas('sale', fn($s) =>
                            $s->where('id', (int) $q)
                        );
                    }
                });
            })

            // 🎯 Estado
            ->when($status = $request->input('status'), fn($qq) => $qq->where('status', $status))
            // 🗓️ Rango de vencimiento
            ->when($d = $request->input('due_from'), fn($qq) => $qq->whereDate('due_date','>=',$d))
            ->when($d = $request->input('due_to'),   fn($qq) => $qq->whereDate('due_date','<=',$d))
            // 📌 Solo próximos 7 días (y pendientes)
            ->when($request->boolean('this_week'), function ($qq) use ($today) {
                $qq->where('status','pendiente')
                   ->whereBetween('due_date', [$today, $today->copy()->addDays(7)]);
            })

            /*
            |--------------------------------------------------------------------------
            | ↕️ ORDENAMIENTO
            |--------------------------------------------------------------------------
            */

            // Vencimiento ascendente
            ->when($order === 'due_asc', function ($qq) {
                    $qq->orderByRaw("
                        CASE
                            WHEN status = 'pendiente' THEN 1
                            WHEN status = 'vencido' THEN 2
                            WHEN status = 'pagado' THEN 3
                            ELSE 4
                        END
                    ")->orderBy('due_date');
                })


            // Vencimiento descendente
            ->when($order === 'due_desc',
                fn($qq) => $qq->orderByDesc('due_date')
            )

            // Saldo descendente
            ->when($order === 'bal_desc',
                fn($qq) => $qq->orderByDesc('balance')
            )

            // ⭐ Prioridad real: vencido → pendiente → pagado
            ->when($order === 'status_bal', function ($qq) {
                $qq->orderByRaw("
                    CASE status
                        WHEN 'vencido' THEN 1
                        WHEN 'pendiente' THEN 2
                        WHEN 'pagado' THEN 3
                        ELSE 4
                    END
                ")->orderBy('due_date');
            })

            ->paginate($perPage)
            ->appends($request->query());

        return view('credits.index', compact('credits'));
    }

    /**
     * Mostrar un crédito con detalle de pagos.
     */
    public function show(Credit $credit)
    {
        $credit->load([
            'client',
            'sale',
            'sale.invoice',
            'payments',
            'payments.user',
        ]);

        return view('credits.show', compact('credit'));
    }

    /**
     * Crear crédito desde una venta.
     */
    public function store(Request $request)
    {
        $request->validate([
            'sale_id'   => 'required|exists:sales,id',
            'client_id' => 'required|exists:clients,id',
            'amount'    => 'required|numeric|min:0',
            'due_date'  => 'required|date'
        ]);

        Credit::create([
            'sale_id'   => $request->sale_id,
            'client_id' => $request->client_id,
            'amount'    => $request->amount,
            'balance'   => $request->amount,
            'due_date'  => $request->due_date,
            'status'    => 'pendiente'
        ]);

        return redirect()
            ->route('credits.index')
            ->with('ok', 'Crédito registrado correctamente.');
    }

    /**
     * Actualizar estado del crédito manualmente (opcional).
     */
    public function update(Request $request, Credit $credit)
    {
        $request->validate([
            'status' => 'required|in:pendiente,pagado,vencido'
        ]);

        $credit->update(['status' => $request->status]);

        return redirect()
            ->back()
            ->with('ok', 'Estado actualizado.');
    }

    /**
     * Eliminar crédito (no recomendado si ya tiene pagos).
     */
    public function destroy(Credit $credit)
    {
        if ($credit->payments()->count() > 0) {
            return redirect()
                ->back()
                ->with('error', 'No se puede eliminar un crédito con pagos.');
        }

        $credit->delete();

        return redirect()
            ->route('credits.index')
            ->with('ok', 'Crédito eliminado.');
    }
}
