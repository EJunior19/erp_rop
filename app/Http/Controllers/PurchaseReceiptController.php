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
    public function create(Request $request)
    {
        $orderId = $request->integer('order'); // ?order=ID
        $q       = trim($request->get('q', '')); // ?q=texto de búsqueda

        // 1) Selected order (opcional)
        $selectedOrder = null;
        if ($orderId) {
            $selectedOrder = PurchaseOrder::query()
                ->with([
                    'supplier:id,name',
                    'items' => fn ($qi) => $qi->with(['product:id,name,code'])
                                              ->orderBy('id'),
                ])
                // Según tu flujo, recepcionar tiene sentido en estos estados
                ->whereIn('status', ['borrador','enviado','recibido'])
                ->findOrFail($orderId);
        }

        // 2) Listado de OCs disponibles para seleccionar (combo)
        $ordersQuery = PurchaseOrder::query()
            ->with(['supplier:id,name'])
            ->whereIn('status', ['borrador','enviado','recibido'])
            ->latest('id');

        if ($q !== '') {
            $ordersQuery->where(function ($w) use ($q) {
                $w->where('order_number','ilike',"%{$q}%")
                  ->orWhereHas('supplier', fn($s) => $s->where('name','ilike',"%{$q}%"));
            });
        }

        $orders = $ordersQuery->limit(50)->get(['id','order_number','supplier_id','order_date','status','total']);

        // 3) Si no hay order seleccionada y solo hay una disponible, autoseleccionar
        if (!$selectedOrder && $orders->count() === 1) {
            $selectedOrder = PurchaseOrder::with([
                    'supplier:id,name',
                    'items' => fn ($qi) => $qi->with(['product:id,name,code'])->orderBy('id'),
                ])
                ->find($orders->first()->id);
        }

        // 4) Productos (para agregar líneas sueltas manuales)
        $products = Product::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id','name','code','price_cash']);

        return view('purchase_receipts.create', [
            'orders'        => $orders,
            'selectedOrder' => $selectedOrder,
            'products'      => $products,
            'q'             => $q,
        ]);
    }

    /**
     * Guarda la recepción y sus ítems.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'receipt_number'    => 'required|string',
            'received_date'     => 'required|date',
            'notes'             => 'nullable|string', // nueva
            'items'             => 'required|array|min:1',
            'items.*.product_id'   => 'required|exists:products,id',
            'items.*.ordered_qty'  => 'required|integer|min:0',
            'items.*.received_qty' => 'required|integer|min:0',
            'items.*.unit_cost'    => 'nullable|numeric|min:0',
            'items.*.reason'       => 'nullable|in:faltante_proveedor,daño_transporte,backorder,error_pick,otro',
            'items.*.comment'      => 'nullable|string|max:500',
        ]);

        $receipt = null;

        DB::transaction(function () use ($data, &$receipt) {
            $receipt = PurchaseReceipt::create([
                'purchase_order_id' => $data['purchase_order_id'],
                'receipt_number'    => $data['receipt_number'],
                'received_date'     => $data['received_date'],
                'received_by'       => auth()->id(),
                'status'            => 'pendiente_aprobacion', // va a aprobación
                'notes'             => $data['notes'] ?? null, // <-- GUARDAR
            ]);

            foreach ($data['items'] as $row) {
                PurchaseReceiptItem::create([
                    'purchase_receipt_id' => $receipt->id,
                    'product_id'          => $row['product_id'],
                    'ordered_qty'         => $row['ordered_qty'],
                    'received_qty'        => $row['received_qty'],
                    'unit_cost'           => $row['unit_cost'] ?? 0,
                    // NUEVO: guardar motivo y comentario
                    'reason'              => $row['reason']   ?? null,
                    'comment'             => $row['comment']  ?? null,
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
            $purchase_receipt->load(['items.product', 'order']);

            // Afectar stock SOLO desde el flujo nuevo
            foreach ($purchase_receipt->items as $item) {
                $item->product->increment('stock', (int) $item->received_qty);
            }

            $purchase_receipt->update([
                'status'      => 'aprobado',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            // Si querés, más adelante: cerrar OC cuando todo esté recibido.
            // $purchase_receipt->order->update(['status' => 'recibido']);
        });

        return back()->with('success','Recepción aprobada y stock actualizado');
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
