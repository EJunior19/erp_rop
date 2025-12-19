<?php

namespace App\Http\Controllers;

use App\Models\Payable;
use Illuminate\Http\Request;

class PayableController extends Controller
{
    public function index(Request $request)
    {
        $status   = $request->get('status');
        $supplier = $request->get('supplier');
        $from     = $request->get('from');
        $to       = $request->get('to');

        $payables = Payable::query()
            ->with([
                'supplier',
                'invoice.receipt.order',
            ])
            // filtro por estado
            ->when($status, function ($q) use ($status) {
                $q->where('status', $status);
            })
            // filtro por proveedor (id o parte del nombre)
            ->when($supplier, function ($q) use ($supplier) {
                $q->whereHas('supplier', function ($qq) use ($supplier) {
                    $qq->where('name', 'ilike', "%{$supplier}%")
                       ->orWhere('ruc', 'ilike', "%{$supplier}%");
                });
            })
            // rango de vencimiento
            ->when($from, function ($q) use ($from) {
                $q->whereDate('due_date', '>=', $from);
            })
            ->when($to, function ($q) use ($to) {
                $q->whereDate('due_date', '<=', $to);
            })
            ->orderByRaw('CASE WHEN pending_amount > 0 THEN 0 ELSE 1 END') // primero las pendientes
            ->orderBy('due_date')
            ->orderBy('id', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('payables.index', compact('payables', 'status', 'supplier', 'from', 'to'));
    }
     public function show(Payable $payable)
    {
        // Cargamos todas las relaciones que usa la vista
        $payable->load([
            'supplier',
            'invoice.receipt.order',
            'payments.user',
        ]);

        return view('payables.show', compact('payable'));
    }
}
