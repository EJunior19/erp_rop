<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Invoice;
use App\Services\Facturacion\FackatueteClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaleApprovalController extends Controller
{
public function approve(Request $request, Sale $sale, FackatueteClient $fackatuete)
{
    $data = $request->validate([
        'issued_at'      => ['nullable','date'],
        'series'         => ['nullable','string','max:15'],
        'invoice_number' => ['nullable','string','max:30'],
    ]);

    DB::transaction(function () use (&$sale, $data) {

        // 1) Bloquear la venta
        $locked = Sale::query()
            ->whereKey($sale->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        // 2) Aprobar si corresponde
        if ($locked->status !== 'aprobado') {

            if ($locked->status !== 'pendiente_aprobacion') {
                throw new \RuntimeException('No se puede aprobar: la venta no está pendiente de aprobación.');
            }

            $updated = Sale::query()
                ->whereKey($locked->id)
                ->where('status', 'pendiente_aprobacion')
                ->update([
                    'status'      => 'aprobado',
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                    'updated_at'  => now(),
                ]);

            if ($updated === 0) {
                throw new \RuntimeException('No se pudo aprobar: cambió de estado.');
            }

            $sale = Sale::query()->lockForUpdate()->findOrFail($locked->id);

        } else {
            $sale = $locked;
        }

        // 3) Crear factura si no existe
        if (!$sale->invoice) {

            $series = $data['series'] ?? '001-001';

            // Totales desde la venta
            $subtotal = (int)($sale->gravada_10 ?? 0)
                      + (int)($sale->gravada_5 ?? 0)
                      + (int)($sale->exento ?? 0);

            $tax   = (int)($sale->total_iva ?? 0);
            $total = (int)($sale->total ?? 0);

            // Número ingresado manualmente
            $manualNumber = !empty($data['invoice_number'])
                ? trim($data['invoice_number'])
                : '';

            if ($manualNumber !== '') {
                // 👉 Manual: se respeta lo ingresado
                $invoiceNumber = $manualNumber;

            } else {

                // 👉 Automático: último número de ESA serie (cast a integer)
                $last = Invoice::query()
                    ->where('series', $series)
                    ->whereNotNull('number')
                    ->selectRaw('MAX(CAST(number AS INTEGER)) as max_number')
                    ->value('max_number');

                $invoiceNumber = $last ? ((int)$last + 1) : 1;
            }

            // Crear factura
            $sale->invoice()->create([
                'series'                 => $series,
                'number'                 => $invoiceNumber,
                'issued_at'              => $data['issued_at'] ?? now()->toDateString(),
                'status'                 => 'issued',
                'subtotal'               => $subtotal,
                'tax'                    => $tax,
                'total'                  => $total,
                'branch_code'            => null,
                'cash_register'          => null,
                'tax_stamp'              => null,
                'tax_stamp_valid_until'  => null,
            ]);
        }
    });

    // 4) Enviar al sistema de facturación electrónica (fuera de la transacción)
    $msgExtra = '';

    try {
        $resp = $fackatuete->emitirDesdeSale($sale);

        $cdc = $resp['data']['cdc'] ?? null;

        $msgExtra = $cdc
            ? " CDC generado: {$cdc}."
            : ' Documento electrónico generado en FacKatuete.';

    } catch (\Throwable $e) {

        Log::error('Error al enviar venta a FacKatuete', [
            'sale_id' => $sale->id,
            'error'   => $e->getMessage(),
        ]);

        $msgExtra = ' (Advertencia: hubo un error al enviar a facturación electrónica, revisar logs.)';
    }

    return redirect()
        ->route('sales.show', $sale->id)
        ->with('success', 'Venta aprobada, factura generada y envío a facturación electrónica iniciado.' . $msgExtra);
}


    public function reject(Sale $sale)
    {
        DB::transaction(function () use (&$sale) {
            $locked = Sale::query()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === 'aprobado') {
                throw new \RuntimeException('No se puede rechazar: la venta ya fue aprobada.');
            }

            $updated = Sale::query()
                ->whereKey($locked->id)
                ->where('status', '!=', 'aprobado')
                ->update([
                    'status'      => 'rechazado',
                    'approved_by' => null,
                    'approved_at' => null,
                    'updated_at'  => now(),
                ]);

            if ($updated === 0) {
                throw new \RuntimeException('No se pudo rechazar.');
            }

            $sale = Sale::findOrFail($locked->id);
        });

        return redirect()
            ->route('sales.show', $sale->id)
            ->with('success', 'Venta rechazada.');
    }
}
