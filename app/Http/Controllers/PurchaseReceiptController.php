<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\{
    PurchaseReceipt,
    PurchaseReceiptItem,
    PurchaseOrder,
    Product,
    User
};

class PurchaseReceiptController extends Controller
{
    /**
     * Listado con filtro, estado y paginación.
     */
    public function index(Request $request)
    {
        $q       = trim($request->get('q', ''));
        $status  = $request->get('status');
        $perPage = (int) $request->get('per_page', 15);

        $receipts = PurchaseReceipt::query()
            ->with([
                'order:id,supplier_id,order_number,order_date,status,total',
                'order.supplier:id,name',
                'approvedBy:id,name',
                'receivedBy:id,name',
            ])
            ->withCount('items')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('receipt_number', 'ilike', "%{$q}%")
                      ->orWhereHas('order', fn ($o) => $o->where('order_number', 'ilike', "%{$q}%"))
                      ->orWhereHas('order.supplier', fn ($s) => $s->where('name', 'ilike', "%{$q}%"));
                });
            })
            ->when($status, fn ($q2) => $q2->where('status', $status))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('purchase_receipts.index', compact('receipts', 'q', 'status', 'perPage'));
    }

    /**
     * Formulario de nueva recepción.
     * Si viene ?order=ID, precarga la OC y sus ítems.
     * Soporta ?q= para buscar OCs por número o proveedor.
     */
public function store(Request $request)
{
    $data = $request->validate([
        'purchase_order_id' => 'required|exists:purchase_orders,id',
        'receipt_number'    => 'required|string',
        'received_date'     => 'required|date',
        'notes'             => 'nullable|string',
        'items'             => 'required|array|min:1',
        'items.*.product_id'   => 'required|exists:products,id',
        'items.*.ordered_qty'  => 'required|integer|min:0',
        'items.*.received_qty' => 'required|integer|min:0',

        // ✅ aceptar ambos nombres
        'items.*.unit_cost'  => 'nullable|numeric|min:0',
        'items.*.unit_price' => 'nullable|numeric|min:0',

        'items.*.reason'  => 'nullable|in:faltante_proveedor,daño_transporte,backorder,error_pick,otro',
        'items.*.comment' => 'nullable|string|max:500',
    ]);

    $receipt = null;

    DB::transaction(function () use ($data, &$receipt) {

        $receipt = PurchaseReceipt::create([
            'purchase_order_id' => $data['purchase_order_id'],
            'receipt_number'    => $data['receipt_number'],
            'received_date'     => $data['received_date'],
            'received_by'       => auth()->id(),
            'status'            => 'pendiente_aprobacion',
            'notes'             => $data['notes'] ?? null,
        ]);

        foreach ($data['items'] as $row) {

            $cost = $row['unit_cost']
                ?? $row['unit_price']
                ?? 0;

            PurchaseReceiptItem::create([
                'purchase_receipt_id' => $receipt->id,
                'product_id'          => $row['product_id'],
                'ordered_qty'         => (int) $row['ordered_qty'],
                'received_qty'        => (int) $row['received_qty'],

                // ✅ guarda costo sin romper aunque venga como unit_price
                'unit_cost'           => (float) $cost,

                'reason'              => $row['reason']  ?? null,
                'comment'             => $row['comment'] ?? null,
            ]);
        }
    });

    return redirect()
        ->route('purchase_receipts.show', $receipt)
        ->with('success', 'Recepción registrada');
}


    /**
     * Detalle de la recepción.
     */
    public function show(PurchaseReceipt $purchase_receipt)
    {
        $purchase_receipt->load([
            'order.supplier',
            'items.product',
            'approvedBy:id,name',
            'receivedBy:id,name'
        ]);

        return view('purchase_receipts.show', compact('purchase_receipt'));
    }
    /**
     * Ticket chico — Impresión rápida de la recepción.
     */
    public function ticket(PurchaseReceipt $purchase_receipt)
    {
        $purchase_receipt->load([
            'order.supplier',
            'items.product',
            'approvedBy:id,name',
            'receivedBy:id,name',
        ]);

        return view('prints.receipts.ticket', compact('purchase_receipt'));
    }

    /**
     * Aprobar recepción (afecta stock).
     * Si ya lo manejas en PurchaseApprovalController, puedes eliminar este método
     * y mantener solo las rutas hacia ese controlador.
     */
public function approve(PurchaseReceipt $purchase_receipt)
{
    if ($purchase_receipt->status !== 'pendiente_aprobacion') {
        return back()->with('error', 'La recepción no está pendiente de aprobación');
    }

    DB::transaction(function () use ($purchase_receipt) {

        // 🔹 Cargar relaciones necesarias
        $purchase_receipt->load([
            'items.product',
            'order.items',
            'order.receipts.items',
        ]);

        // 1️⃣ Afectar stock
        foreach ($purchase_receipt->items as $item) {
            if ($item->product) {
                $item->product->increment('stock', (int) $item->received_qty);
            }
        }

        // 2️⃣ Aprobar recepción
        $purchase_receipt->update([
            'status'      => 'aprobado',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        // 3️⃣ Verificar estado de la Orden de Compra
        $order = $purchase_receipt->order;

        // Cantidades pedidas por producto
        $orderedByProduct = $order->items
            ->groupBy('product_id')
            ->map(fn ($g) => (int) $g->sum('quantity'));

        // Cantidades recibidas (solo recepciones aprobadas)
        $receivedRows = DB::table('purchase_receipt_items as pri')
            ->join('purchase_receipts as pr', 'pr.id', '=', 'pri.purchase_receipt_id')
            ->where('pr.purchase_order_id', $order->id)
            ->where('pr.status', 'aprobado')
            ->groupBy('pri.product_id')
            ->selectRaw('pri.product_id, SUM(pri.received_qty) as qty')
            ->get();

        $receivedByProduct = $receivedRows
            ->pluck('qty', 'product_id')
            ->map(fn ($v) => (int) $v);

        // Determinar estado final
        $fullyReceived  = true;
        $hasAnyReceived = false;

        foreach ($orderedByProduct as $productId => $orderedQty) {
            $receivedQty = (int) ($receivedByProduct[$productId] ?? 0);

            if ($receivedQty > 0) {
                $hasAnyReceived = true;
            }

            if ($receivedQty < $orderedQty) {
                $fullyReceived = false;
            }
        }

        // 4️⃣ Actualizar estado de la OC
        if ($fullyReceived) {
            $order->update(['status' => 'recibido']);
        } elseif ($hasAnyReceived) {
            $order->update(['status' => 'parcial']);
        }
    });

    return back()->with('success', 'Recepción aprobada correctamente.');
}


        /**
         * Rechazar recepción (sin afectar stock).
         */
        public function reject(PurchaseReceipt $purchase_receipt)
        {
            if ($purchase_receipt->status !== 'pendiente_aprobacion') {
                return back()->with('error', 'La recepción no está pendiente de aprobación');
            }

            $purchase_receipt->update([
                'status'      => 'rechazado',
                'approved_by' => null,
                'approved_at' => null,
            ]);

            return back()->with('success','Recepción rechazada');
        }
    }
