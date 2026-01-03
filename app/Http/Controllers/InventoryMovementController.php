<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Http\Request;

class InventoryMovementController extends Controller
{
    public function index(Request $request)
    {
        $q     = trim((string) $request->query('q', ''));
        $type  = (string) $request->query('type', '');
        $from  = (string) $request->query('from', '');
        $to    = (string) $request->query('to', '');
        $export = (string) $request->query('export', '');

        $movementsQuery = InventoryMovement::query()
            ->with(['product','user'])
            ->when($type !== '', function ($query) use ($type) {
                $query->where('type', $type);
            })
            ->when($from !== '', function ($query) use ($from) {
                $query->whereDate('created_at', '>=', $from);
            })
            ->when($to !== '', function ($query) use ($to) {
                $query->whereDate('created_at', '<=', $to);
            })
            ->when($q !== '', function ($query) use ($q) {
                $needle = "%{$q}%";

                $query->where(function ($w) use ($needle, $q) {
                    // razón / texto
                    $w->where('reason', 'ilike', $needle)
                      ->orWhere('note', 'ilike', $needle);

                    // producto
                    $w->orWhereHas('product', function ($p) use ($needle) {
                        $p->where('name', 'ilike', $needle)
                          ->orWhere('code', 'ilike', $needle);
                    });

                    // usuario
                    $w->orWhereHas('user', function ($u) use ($needle) {
                        $u->where('name', 'ilike', $needle)
                          ->orWhere('email', 'ilike', $needle);
                    });

                    // si es número: por id exacto
                    if (ctype_digit((string) $q)) {
                        $w->orWhere('id', (int) $q);
                        $w->orWhere('product_id', (int) $q);
                        $w->orWhere('user_id', (int) $q);
                    }
                });
            })
            ->latest('id');

        // ✅ Export CSV (respetando filtros)
        if ($export === 'csv') {
            $rows = $movementsQuery->limit(5000)->get();

            $filename = 'movimientos_inventario_' . now()->format('Ymd_His') . '.csv';

            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');

                // BOM para Excel
                fwrite($out, "\xEF\xBB\xBF");

                fputcsv($out, [
                    'ID', 'Fecha', 'Producto', 'Código', 'Tipo', 'Cantidad', 'Razón', 'Usuario'
                ], ';');

                foreach ($rows as $m) {
                    fputcsv($out, [
                        $m->id,
                        optional($m->created_at)->format('Y-m-d H:i:s'),
                        optional($m->product)->name,
                        optional($m->product)->code,
                        $m->type,
                        (int) $m->qty,
                        $m->reason,
                        optional($m->user)->name ?? 'Sistema',
                    ], ';');
                }

                fclose($out);
            }, $filename, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        $movements = $movementsQuery
            ->paginate(15)
            ->withQueryString();

        return view('inventory.index', compact('movements'));
    }

    public function create()
    {
        $products = Product::orderBy('name')->get();
        return view('inventory.create', compact('products'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'type'       => 'required|in:entrada,salida,ajuste',
            'quantity'   => 'required|integer|min:1',
            'reason'     => 'nullable|string|max:255',
        ]);

        $product = Product::findOrFail($data['product_id']);

        // Validar stock en salidas
        if ($data['type'] === 'salida' && $product->stock < $data['quantity']) {
            return back()->withErrors(['quantity' => 'Stock insuficiente.'])->withInput();
        }

        // ✅ Mapear quantity -> qty (columna real)
        $movementData = [
            'product_id' => $data['product_id'],
            'type'       => $data['type'],
            'qty'        => (int) $data['quantity'],
            'reason'     => $data['reason'] ?? null,
            'note'       => null,
            'ref_type'   => 'adjust',
            'ref_id'     => null,
            'user_id'    => auth()->id(),
        ];

        $movement = InventoryMovement::create($movementData);

        // Actualizar stock
        if ($data['type'] === 'entrada' || $data['type'] === 'ajuste') {
            $product->increment('stock', $movementData['qty']);
        } else {
            $product->decrement('stock', $movementData['qty']);
        }

        return redirect()->route('inventory.index')
            ->with('ok', $data['type'] === 'entrada'
                ? '✅ Entrada registrada y stock actualizado.'
                : ($data['type'] === 'ajuste'
                    ? '✅ Ajuste registrado y stock actualizado.'
                    : '✅ Salida registrada y stock actualizado.'));
    }

    public function destroy(InventoryMovement $inventoryMovement)
    {
        // 🚨 Recomendado: no eliminar directamente, marcar como anulado
        $inventoryMovement->delete();
        return back()->with('ok','🗑️ Movimiento eliminado.');
    }
}
