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
     * Soporta ?order=ID y ?q=
     */
    public function create(Request $request)
{
    $q = trim($request->get('q', ''));

    // ✅ soporta ambos: ?order= y ?purchase_order_id=
    $orderId = $request->get('order') ?? $request->get('purchase_order_id');

    $order = null;
    if ($orderId) {
        $order = PurchaseOrder::query()
            ->with([
                'supplier:id,name',
                'items.product:id,name,stock',
            ])
            ->findOrFail($orderId);
    }

    $orders = PurchaseOrder::query()
        ->with('supplier:id,name')
        ->when($q !== '', function ($query) use ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('order_number', 'ilike', "%{$q}%")
                  ->orWhereHas('supplier', fn ($s) => $s->where('name', 'ilike', "%{$q}%"));
            });
        })
        ->whereIn('status', ['borrador','enviado','parcial'])
        ->latest('id')
        ->limit(30)
        ->get(['id','supplier_id','order_number','order_date','status','total']);

    // ✅ ESTE ERA EL QUE FALTABA
    $products = Product::query()
        ->orderBy('name')
        ->get(['id','name']);

    $items = collect();
    if ($order) {
        $items = $order->items->map(function ($it) {
            return [
                'product_id'   => $it->product_id,
                'product_name' => optional($it->product)->name,
                'ordered_qty'  => (int) ($it->quantity ?? 0),
                'received_qty' => (int) ($it->quantity ?? 0),
                'unit_cost'    => null,
                'reason'       => null,
                'comment'      => null,
            ];
        });
    }

    return view('purchase_receipts.create', compact('orders', 'order', 'items', 'q', 'products'));
}


    /**
     * Guardar recepción
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'receipt_number'    => 'required|string',
            'received_date'     => 'required|date',
            'notes'             => 'nullable|string',

            'items'                  => 'required|array|min:1',
            'items.*.product_id'     => 'required|exists:products,id',
            'items.*.ordered_qty'    => 'required|integer|min:0',
            'items.*.received_qty'   => 'required|integer|min:0',
            'items.*.unit_cost'      => 'nullable|numeric|min:0',
            'items.*.unit_price'     => 'nullable|numeric|min:0',
            'items.*.reason'         => 'nullable|in:faltante_proveedor,daño_transporte,backorder,error_pick,otro',
            'items.*.comment'        => 'nullable|string|max:500',
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
                $cost = $row['unit_cost'] ?? $row['unit_price'] ?? 0;

                PurchaseReceiptItem::create([
                    'purchase_receipt_id' => $receipt->id,
                    'product_id'          => $row['product_id'],
                    'ordered_qty'         => (int) $row['ordered_qty'],
                    'received_qty'        => (int) $row['received_qty'],
                    'unit_cost'           => (float) $cost,
                    'reason'              => $row['reason']  ?? null,
                    'comment'             => $row['comment'] ?? null,
                ]);
            }
        });

        return redirect()
            ->route('purchase_receipts.show', $receipt)
            ->with('success', 'Recepción registrada correctamente');
    }

    /**
     * Detalle
     */
    public function show(PurchaseReceipt $purchase_receipt)
    {
        $purchase_receipt->load([
            'order.supplier',
            'items.product',
            'approvedBy:id,name',
            'receivedBy:id,name',
        ]);

        return view('purchase_receipts.show', compact('purchase_receipt'));
    }

    /**
     * Ticket de impresión
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
     * Aprobar recepción (impacta stock)
     */
    public function approve(PurchaseReceipt $purchase_receipt)
    {
        if ($purchase_receipt->status !== 'pendiente_aprobacion') {
            return back()->with('error', 'La recepción no está pendiente de aprobación');
        }

        DB::transaction(function () use ($purchase_receipt) {

            $purchase_receipt->load([
                'items.product',
                'order.items',
                'order.receipts.items',
            ]);

            // Stock
            foreach ($purchase_receipt->items as $item) {
                if ($item->product) {
                    $item->product->increment('stock', (int) $item->received_qty);
                }
            }

            // Aprobar
            $purchase_receipt->update([
                'status'      => 'aprobado',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            // Estado OC
            $order = $purchase_receipt->order;

            $orderedByProduct = $order->items
                ->groupBy('product_id')
                ->map(fn ($g) => (int) $g->sum('quantity'));

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

            $fullyReceived = true;
            $hasAnyReceived = false;

            foreach ($orderedByProduct as $pid => $orderedQty) {
                $receivedQty = (int) ($receivedByProduct[$pid] ?? 0);

                if ($receivedQty > 0) $hasAnyReceived = true;
                if ($receivedQty < $orderedQty) $fullyReceived = false;
            }

            if ($fullyReceived) {
                $order->update(['status' => 'recibido']);
            } elseif ($hasAnyReceived) {
                $order->update(['status' => 'parcial']);
            }
        });

        return back()->with('success', 'Recepción aprobada correctamente');
    }

    /**
     * Rechazar recepción
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

        return back()->with('success', 'Recepción rechazada');
    }
}
