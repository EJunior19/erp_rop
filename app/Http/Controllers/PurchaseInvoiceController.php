<?php

namespace App\Http\Controllers;

use App\Models\{
    PurchaseInvoice,
    PurchaseInvoiceItem,
    PurchaseReceipt,
    PurchaseReceiptItem,
    Product,
    Payable
};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class PurchaseInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = PurchaseInvoice::query()
            ->with(['receipt.order.supplier'])
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('purchase_invoices.index', compact('invoices'));
    }

    public function create(Request $request)
    {
        $receiptId = $request->integer('receipt');
        $selected  = null;

        if ($receiptId) {
            // Trae la recepción + OC + proveedor + productos + sumatoria ya facturada
            // 🔒 Solo permitimos recepciones APROBADAS
            $selected = PurchaseReceipt::with([
                    'order.supplier',
                    'items' => function ($q) {
                        $q->with(['product:id,name,code'])
                          ->withSum('invoiceItems as invoiced_qty', 'qty')
                          ->orderBy('id');
                    },
                ])
                ->where('status', 'aprobado')
                ->findOrFail($receiptId);

            // Agrega remaining_qty (recibido - ya facturado)
            $selected->items->transform(function ($it) {
                $invoiced = (int) ($it->invoiced_qty ?? 0);
                $it->remaining_qty = max(0, (int) $it->received_qty - $invoiced);
                return $it;
            });
        }

        // últimas 50 recepciones para el selector
        // 🔒 Solo recepciones APROBADAS
        $receipts = PurchaseReceipt::with('order.supplier')
            ->where('status', 'aprobado')
            ->latest('id')
            ->limit(50)
            ->get();

        return view('purchase_invoices.create', [
            'receipts' => $receipts,
            'selected' => $selected,
        ]);
    }

    public function store(Request $request)
    {
        // Validación base + condición de pago
        $data = $request->validate([
            'purchase_receipt_id'                  => ['required','exists:purchase_receipts,id'],
            'invoice_number'                       => ['required','string','max:50'],
            'invoice_date'                         => ['required','date'],
            'notes'                                => ['nullable','string','max:2000'],

            // 👇 CONDICIÓN DE PAGO / ADELANTO
            'payment_term'                         => ['required','in:contado,credito,zafra,especial'],
            'due_date'                             => ['nullable','date'],
            'advance_amount'                       => ['nullable','numeric','min:0'],

            'items'                                 => ['required','array','min:1'],
            'items.*.purchase_receipt_item_id'      => ['required','exists:purchase_receipt_items,id'],
            'items.*.product_id'                    => ['required','exists:products,id'],
            'items.*.qty'                           => ['required','integer','min:1'],
            'items.*.unit_cost'                     => ['required','numeric','min:0'],
            'items.*.tax_rate'                      => ['nullable','numeric','min:0','max:100'],
        ]);

        // Traer la recepción y los ítems con sumatoria ya facturada
        $receipt = PurchaseReceipt::with([
                'order.supplier',
                'items' => function ($q) {
                    $q->withSum('invoiceItems as invoiced_qty', 'qty');
                }
            ])
            ->findOrFail($data['purchase_receipt_id']);

        // 🔒 Reglas: solo se puede facturar una recepción APROBADA
        if ($receipt->status !== 'aprobado') {
            return back()
                ->withInput()
                ->withErrors([
                    'purchase_receipt_id' => 'Solo se pueden facturar recepciones aprobadas.',
                ]);
        }

        // Construir mapa de "remaining" por ítem de recepción (recibido - ya facturado)
        $remainingByReceiptItem = [];
        foreach ($receipt->items as $rit) {
            $already = (int) ($rit->invoiced_qty ?? 0);
            $remainingByReceiptItem[$rit->id] = max(0, (int)$rit->received_qty - $already);
        }

        // Mapa de ítems de recepción para validar precios
        $receiptItemsById = $receipt->items->keyBy('id');

        // Validación de negocio:
        //  - no facturar más de lo disponible
        //  - el precio unitario debe coincidir con el de la recepción (unit_cost)
        $errors = [];
        foreach ($data['items'] as $idx => $row) {
            $rid      = (int) $row['purchase_receipt_item_id'];
            $qty      = (int) $row['qty'];
            $unitCost = (float) $row['unit_cost'];

            $remaining    = $remainingByReceiptItem[$rid] ?? null;
            $receiptItem  = $receiptItemsById->get($rid);

            if ($remaining === null || !$receiptItem) {
                $errors["items.$idx.purchase_receipt_item_id"] =
                    'El ítem de recepción no pertenece a la recepción indicada.';
                continue;
            }

            // ❗ Cantidad
            if ($qty > $remaining) {
                $errors["items.$idx.qty"] =
                    "La cantidad ($qty) excede la disponible para facturar ($remaining).";
            }

            // ❗ Precio unitario: debe coincidir con el de la recepción
            $receivedCost = (float) $receiptItem->unit_cost;
            // Permitimos una tolerancia mínima por temas de redondeo
            if (abs($unitCost - $receivedCost) > 0.0001) {
                $errors["items.$idx.unit_cost"] =
                    "El costo unitario de la factura ({$unitCost}) no coincide con el de la recepción ({$receivedCost}).";
            }
        }

        if (!empty($errors)) {
            return back()->withInput()->withErrors($errors);
        }

        $invoice = null;

        try {
            DB::transaction(function () use ($data, $receipt, &$invoice) {

                // 1) Crear cabecera de factura
                $invoice = PurchaseInvoice::create([
                    'purchase_receipt_id' => $receipt->id,
                    'invoice_number'      => $data['invoice_number'],
                    'invoice_date'        => $data['invoice_date'],
                    'notes'               => $data['notes'] ?? null,
                    'created_by'          => auth()->id(),
                    'status'              => 'emitida', // o 'borrador' si prefieres
                    'subtotal'            => 0,
                    'tax'                 => 0,
                    'total'               => 0,
                ]);

                $sumSubtotal = 0.0;
                $sumTax      = 0.0;
                $sumTotal    = 0.0;

                // 2) Ítems de factura
                foreach ($data['items'] as $row) {
                    $qty       = (int) $row['qty'];
                    $unitCost  = (float) $row['unit_cost'];
                    $taxRate   = (float) ($row['tax_rate'] ?? 0);

                    $lineSubtotal = round($qty * $unitCost, 2);
                    $lineTax      = round($lineSubtotal * ($taxRate / 100), 2);
                    $lineTotal    = round($lineSubtotal + $lineTax, 2);

                    PurchaseInvoiceItem::create([
                        'purchase_invoice_id'       => $invoice->id,
                        'purchase_receipt_item_id'  => (int) $row['purchase_receipt_item_id'],
                        'product_id'                => (int) $row['product_id'],
                        'qty'                       => $qty,
                        'unit_cost'                 => $unitCost,
                        'tax_rate'                  => $taxRate,
                        'subtotal'                  => $lineSubtotal,
                        'tax'                       => $lineTax,
                        'total'                     => $lineTotal,
                    ]);

                    $sumSubtotal += $lineSubtotal;
                    $sumTax      += $lineTax;
                    $sumTotal    += $lineTotal;
                }

                // 3) Actualiza totales de la factura
                $invoice->update([
                    'subtotal' => $sumSubtotal,
                    'tax'      => $sumTax,
                    'total'    => $sumTotal,
                ]);

                // 4) Crear la CUENTA POR PAGAR (Payable)
                $advance = (float) ($data['advance_amount'] ?? 0);
                if ($advance < 0) {
                    $advance = 0;
                }
                if ($advance > $sumTotal) {
                    $advance = $sumTotal;
                }

                $pending = $sumTotal - $advance;

                $status = 'pendiente';
                if ($pending <= 0) {
                    $pending = 0;
                    $status  = 'pagado';
                } elseif ($pending < $sumTotal) {
                    $status  = 'parcial';
                }

                // Si no vino due_date y es crédito, por defecto +30 días
                $dueDate = $data['due_date'] ?? null;
                if (!$dueDate && $data['payment_term'] === 'credito') {
                    $dueDate = now()->addDays(30)->toDateString();
                }

                // ⚠️ OPCIONAL: si agregaste purchase_order_id a payables,
                // asegurate que esté en $fillable del modelo Payable
                Payable::create([
                    'purchase_invoice_id' => $invoice->id,
                    'purchase_order_id'   => $receipt->purchase_order_id ?? $receipt->order->id ?? null, // si existe la columna
                    'supplier_id'         => $receipt->order->supplier_id,
                    'total_amount'        => $sumTotal,
                    'advance_amount'      => $advance,
                    'pending_amount'      => $pending,
                    'due_date'            => $dueDate,
                    'payment_term'        => $data['payment_term'],
                    'status'              => $status,
                    'created_by'          => auth()->id(),
                ]);
            });
        } catch (QueryException $e) {
            // 23505 = unique_violation (p.ej. invoice_number duplicado por receipt)
            if ((string) $e->getCode() === '23505') {
                return back()
                    ->withInput()
                    ->withErrors(['invoice_number' => 'Ya existe una factura con ese número para esta recepción.']);
            }
            throw $e;
        }

        return redirect()
            ->route('purchase_invoices.show', $invoice)
            ->with('success', 'Factura registrada y cuenta por pagar creada');
    }

    public function show(PurchaseInvoice $purchase_invoice)
    {
        $purchase_invoice->load([
            'receipt.order.supplier',
            'items.product',
            'items.receiptItem', // relación: PurchaseInvoiceItem -> purchase_receipt_item
        ]);

        return view('purchase_invoices.show', compact('purchase_invoice'));
    }
}
