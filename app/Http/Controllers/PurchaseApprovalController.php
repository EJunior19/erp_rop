<?php

namespace App\Http\Controllers;

use App\Models\PurchaseReceipt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Models\InventoryMovement;


class PurchaseApprovalController extends Controller
{
    /**
     * Aprobar recepción:
     * - Solo si no está "rechazado".
     * - Idempotente: si ya está "aprobado", no hace nada.
     * - Stock/OC lo maneja la BD vía triggers.
     */
    public function approve(PurchaseReceipt $receipt)
    {
        try {
            DB::transaction(function () use (&$receipt) {

                $locked = PurchaseReceipt::query()
                    ->whereKey($receipt->getKey())
                    ->lockForUpdate()
                    ->with([
                        'items:id,purchase_receipt_id,product_id,received_qty',
                        'order:id,status',
                        'order.items:id,purchase_order_id,product_id,quantity',
                        'order.receipts:id,purchase_order_id,status',
                        'order.receipts.items:id,purchase_receipt_id,product_id,received_qty',
                    ])
                    ->firstOrFail();

                if ($locked->status === 'aprobado') {
                    $receipt = $locked;
                    return;
                }

                if ($locked->status === 'rechazado') {
                    throw new \RuntimeException('No se puede aprobar: la recepción fue rechazada.');
                }

                // 1) Aprobar (sello)
                $locked->update([
                    'status'      => 'aprobado',
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                ]);

                // 2) ✅ Inventario: crear ENTRADAS (idempotente)
                // Si ya existen movimientos para esta recepción, no duplicar.
                $exists = InventoryMovement::query()
                    ->where('ref_type', InventoryMovement::REF_PURCHASE)
                    ->where('ref_id', $locked->id) // usamos la recepción como referencia
                    ->exists();

                if (!$exists) {
                    foreach ($locked->items as $it) {
                        $qty = (int) $it->received_qty;
                        if ($qty <= 0) continue;

                        InventoryMovement::create([
                            'product_id' => (int) $it->product_id,
                            'type'       => InventoryMovement::TYPE_IN,
                            'qty'        => $qty,
                            'ref_type'   => InventoryMovement::REF_PURCHASE,
                            'ref_id'     => (int) $locked->id,
                            'reason'     => 'Compra recibida', // o lo que uses como estándar
                            'note'       => 'Recepción #'.$locked->id,
                            'user_id'    => Auth::id(),
                        ]);
                    }
                }

                // 3) ✅ OC: marcar como "recibido" cuando corresponda (sin depender del trigger)
                $order = $locked->order;

                // Pedidos por producto
                $orderedByProduct = $order->items
                    ->groupBy('product_id')
                    ->map(fn ($g) => (int) $g->sum('quantity'));

                // Recibidos aprobados por producto (sumando TODAS recepciones aprobadas)
                $receivedByProduct = collect();

                foreach ($order->receipts->where('status', 'aprobado') as $r) {
                    foreach ($r->items as $rit) {
                        $pid = (int) $rit->product_id;
                        $receivedByProduct[$pid] = (int) ($receivedByProduct[$pid] ?? 0) + (int) $rit->received_qty;
                    }
                }

                $fullyReceived = true;
                foreach ($orderedByProduct as $productId => $orderedQty) {
                    if (((int)($receivedByProduct[$productId] ?? 0)) < (int)$orderedQty) {
                        $fullyReceived = false;
                        break;
                    }
                }

                if ($fullyReceived && $order && $order->status !== 'recibido') {
                    $order->update(['status' => 'recibido']);
                }

                $receipt = $locked;
            });

        } catch (QueryException $e) {
            if ($e->getCode() === 'P0001') {
                return back()->with('error', $e->getMessage());
            }
            throw $e;

        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('purchase_receipts.show', $receipt->id)
            ->with('success', 'Recepción aprobada.');
    }


    /**
     * Rechazar recepción:
     * - No permite rechazar si ya está "aprobado".
     * - Limpia sello de aprobación si existía.
     */
    public function reject(PurchaseReceipt $receipt)
    {
        try {
            DB::transaction(function () use (&$receipt) {
                $locked = PurchaseReceipt::query()
                    ->whereKey($receipt->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->status === 'aprobado') {
                    throw new \RuntimeException('No se puede rechazar: la recepción ya fue aprobada.');
                }

                if ($locked->status !== 'rechazado') {
                    $locked->update([
                        'status'      => 'rechazado',
                        'approved_by' => null,
                        'approved_at' => null,
                    ]);
                }

                $receipt = $locked;
            });
        } catch (QueryException $e) {
            if ($e->getCode() === 'P0001') {
                return back()->with('error', $e->getMessage());
            }
            throw $e;
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('purchase_receipts.show', $receipt->id)
            ->with('success', 'Recepción rechazada.');
    }
}
