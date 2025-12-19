<?php

namespace App\Services;

use App\Models\Sale;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FacturacionElectronicaService
{
    public function emitirDesdeVenta(Sale $sale): void
    {
        // 🔒 No emitir dos veces
        if ($sale->de_id && $sale->de_status === 'firmado') {
            return;
        }

        $baseUrl = config('services.fackatuete.base_url');
        $token   = config('services.fackatuete.token');

        $client = $sale->client;            // ajustá si tu relación se llama distinto
        $items  = $sale->items;             // ajustá si la relación se llama distinto

        $payload = [
            'empresa' => [
                'ruc'       => '80000001',  // en el futuro: sacar de config/tabla empresas
                'dv'        => '0',
                'ambiente'  => 'test',
            ],
            'venta' => [
                'id_erp'           => $sale->id,
                'fecha'            => $sale->created_at->format('Y-m-d H:i:s'),
                'tipo_documento'   => 'FAC', // por ahora solo factura
                'condicion_venta'  => $sale->payment_condition === 'credit' ? 'credito' : 'contado',
                'moneda'           => 'PYG',
                'total_general'    => (float) $sale->total,
                'total_gravada_10' => (float) ($sale->total_10 ?? $sale->total),
                'total_gravada_5'  => (float) ($sale->total_5 ?? 0),
                'total_exenta'     => (float) ($sale->total_exenta ?? 0),
            ],
            'cliente' => [
                'documento' => $client->ruc ?? $client->document ?? '0000001',
                'dv'        => $client->dv ?? null,
                'nombre'    => $client->name,
                'direccion' => $client->address ?? null,
                'telefono'  => $client->phone ?? null,
            ],
            'items' => $items->map(function ($item) {
                $product = $item->product;

                return [
                    'codigo'       => $product->code ?? null,
                    'descripcion'  => $product->name,
                    'cantidad'     => (float) $item->quantity,
                    'precio_unit'  => (float) $item->unit_price,
                    'subtotal'     => (float) $item->total,
                    'iva' => $item->iva == 10 ? 10 : ($item->iva == 5 ? 5 : 0),
                ];
            })->toArray(),
        ];

        $response = Http::withToken($token)
            ->acceptJson()
            ->post($baseUrl.'/api/v1/documentos/desde-erp', $payload);

        if (! $response->successful()) {
            $sale->de_status     = 'error';
            $sale->de_last_error = 'HTTP '.$response->status().' - '.$response->body();
            $sale->save();

            throw new RuntimeException('Error al emitir DE: '.$response->status().' - '.$response->body());
        }

        $data = $response->json()['data'] ?? null;

        if (! $data) {
            $sale->de_status     = 'error';
            $sale->de_last_error = 'Respuesta inválida de FACKATUETE';
            $sale->save();

            throw new RuntimeException('Respuesta inválida de FACKATUETE');
        }

        // ✅ Guardar datos del DE en la venta
        $sale->de_id        = $data['id']   ?? null;
        $sale->de_cdc       = $data['cdc']  ?? null;
        $sale->de_status    = $data['estado_sifen'] ?? 'pendiente';
        $sale->de_last_error = null;
        $sale->save();
    }
}
