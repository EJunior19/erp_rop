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
        public function index(\Illuminate\Http\Request $request)
    {
        $perPage = (int) $request->input('per_page', 15);
        $order   = $request->input('order', 'due_asc'); // por defecto: vence primero
        $today   = now()->startOfDay();

        $credits = \App\Models\Credit::with(['client','sale'])
            // 🔎 Buscar por cliente, #crédito o #venta
            ->when($q = trim($request->input('q','')), function ($q1) use ($q) {
                $q1->where(function ($w) use ($q) {
                    $w->whereHas('client', fn($c) => $c->where('name', 'ilike', "%{$q}%"))
                    ->orWhere('id', $q)
                    ->orWhereHas('sale', fn($s) => $s->where('id', $q));
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
            // ↕️ Orden
            ->when($order === 'due_asc',  fn($qq) => $qq->orderBy('due_date')->orderByDesc('balance'))
            ->when($order === 'due_desc', fn($qq) => $qq->orderByDesc('due_date'))
            ->when($order === 'bal_desc', fn($qq) => $qq->orderByDesc('balance'))
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
            'sale.invoice',   // 👈 importante
            'payments',
            'payments.user',  // 👈 ya que mostrás el usuario del pago
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

        return redirect()->route('credits.index')->with('ok', 'Crédito registrado correctamente.');
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

        return redirect()->back()->with('ok', 'Estado actualizado.');
    }

    /**
     * Eliminar crédito (no recomendado si ya tiene pagos).
     */
    public function destroy(Credit $credit)
    {
        if ($credit->payments()->count() > 0) {
            return redirect()->back()->with('error', 'No se puede eliminar un crédito con pagos.');
        }

        $credit->delete();
        return redirect()->route('credits.index')->with('ok', 'Crédito eliminado.');
    }
}
