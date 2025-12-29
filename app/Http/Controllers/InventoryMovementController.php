<?php
namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Http\Request;

class InventoryMovementController extends Controller
{
    public function index()
    {
        $movements = InventoryMovement::with('product','user')->latest()->paginate(15);
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
            'type'       => 'required|in:entrada,salida',
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
            'ref_type'   => 'adjust', // o 'manual' si preferís
            'ref_id'     => null,
            'user_id'    => auth()->id(),
        ];

        $movement = InventoryMovement::create($movementData);

        // Actualizar stock
        if ($data['type'] === 'entrada') {
            $product->increment('stock', $movementData['qty']);
        } else {
            $product->decrement('stock', $movementData['qty']);
        }

        return redirect()->route('inventory.index')
            ->with('ok', $data['type'] === 'entrada'
                ? '✅ Entrada registrada y stock actualizado.'
                : '✅ Salida registrada y stock actualizado.');
    }

    public function destroy(InventoryMovement $inventoryMovement)
    {
        // 🚨 Recomendado: no eliminar directamente, marcar como anulado
        $inventoryMovement->delete();
        return back()->with('ok','🗑️ Movimiento eliminado.');
    }
}
