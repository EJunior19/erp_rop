<?php

namespace App\Services\Facturacion;

use App\Models\Sale;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FackatueteClient
{
    /**
     * Envía una venta del ERP a FacKatuete para generar/firmar el DE.
     *
     * @throws \RuntimeException si la API responde error duro (4xx/5xx)
     */
    public function emitirDesdeSale(Sale $sale): array
    {
        // 🔧 Configuración base (URL, token, empresa)
        $baseUrl = rtrim(Config::get('services.fackatuete.base_url', 'http://127.0.0.1:8002'), '/');
        $token   = Config::get('services.fackatuete.token');

        if (!$token) {
            throw new \RuntimeException('FACKATUETE_TOKEN no está configurado en el .env');
        }

        $empresaRuc = Config::get('services.fackatuete.empresa_ruc', '80000001');
        $empresaDv  = Config::get('services.fackatuete.empresa_dv',  '0');
        $ambiente   = Config::get('services.fackatuete.ambiente',   'test');

        // 🔗 Relaciones: client + items (nos aseguramos de cargarlas)
        $sale->loadMissing(['client', 'items.product', 'invoice']);

        $client = $sale->client;
        $items  = $sale->items;

        // 🗓 Fecha de la venta (usamos columna real "fecha")
        if (method_exists($sale, 'getAttribute') && $sale->getAttribute('fecha')) {
            $fecha = $sale->fecha instanceof \Carbon\CarbonInterface
                ? $sale->fecha->format('Y-m-d') . ' ' . now()->format('H:i:s')
                : $sale->fecha . ' ' . now()->format('H:i:s');
        } else {
            $fecha = now()->format('Y-m-d H:i:s');
        }

        // 💳 Condición de venta (usamos columna real "modo_pago")
        $condicionVenta = $sale->modo_pago === 'credito' ? 'credito' : 'contado';

        // 👤 Documento del cliente (RUC + DV si viene con guión)
        $documentoCliente = $client->ruc ?? '00000001';
        $dvCliente        = null;

        if (is_string($documentoCliente) && str_contains($documentoCliente, '-')) {
            [$doc, $dv]     = explode('-', $documentoCliente, 2);
            $documentoCliente = trim($doc);
            $dvCliente        = trim($dv);
        }

        // 🤝 Payload ERP → FacKatuete
        $payload = [
            'empresa' => [
                'ruc'      => $empresaRuc,
                'dv'       => $empresaDv,
                'ambiente' => $ambiente,
            ],

            'venta' => [
                'id_erp'           => $sale->id,
                'fecha'            => $fecha,
                'tipo_documento'   => 'FAC', // por ahora fijo
                'condicion_venta'  => $condicionVenta, // contado | credito
                'moneda'           => 'PYG',
                'total_general'    => (float) ($sale->total ?? 0),

                // Totales de IVA según tu tabla "sales"
                'total_gravada_10' => (float) ($sale->gravada_10 ?? 0),
                'total_gravada_5'  => (float) ($sale->gravada_5 ?? 0),
                'total_exenta'     => (float) ($sale->exento ?? 0),
                'total_iva'        => (float) ($sale->total_iva ?? 0),
            ],

            'cliente' => [
                'documento' => $documentoCliente,
                'dv'        => $dvCliente,
                'nombre'    => $client->name ?? $client->razon_social ?? 'CLIENTE ERP',
                'direccion' => $client->address ?? $client->direccion ?? null,
                'telefono'  => $client->phone ?? $client->telefono ?? null,
            ],

            'items' => [],
        ];

        // 🧾 Ítems de la venta
        foreach ($items as $item) {
            $qty   = (float) ($item->qty ?? $item->quantity ?? 0);
            $price = (float) ($item->unit_price ?? $item->price ?? 0);
            $sub   = (float) ($item->line_total ?? $item->subtotal ?? ($qty * $price));

            // iva_type: '10' | '5' | 'exento'
            $ivaType = $item->iva_type ?? '10';
            $ivaPorc = match ((string) $ivaType) {
                '10'    => 10,
                '5'     => 5,
                'exento', '0' => 0,
                default => 10, // por defecto asumimos 10%
            };

            $codigo = $item->product_code
                ?? ($item->product->code ?? null);

            $descripcion = $item->product_name
                ?? ($item->product->name ?? 'Item ERP');

            $payload['items'][] = [
                'codigo'       => $codigo,
                'descripcion'  => $descripcion,
                'cantidad'     => $qty,
                'precio_unit'  => $price,
                'subtotal'     => $sub,
                'iva'          => $ivaPorc, // 👈 0 / 5 / 10
            ];
        }

        Log::info('Enviando venta a FacKatuete', [
            'sale_id' => $sale->id,
            'payload' => $payload,
        ]);

        // 🌐 Llamada HTTP a FacKatuete
        $response = Http::withToken($token)
            ->acceptJson()
            ->post($baseUrl . '/api/v1/documentos/desde-erp', $payload);

        if (!$response->ok()) {
            Log::error('Error HTTP al llamar FacKatuete', [
                'sale_id' => $sale->id,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);

            throw new \RuntimeException(
                'Error al enviar venta a FacKatuete. HTTP ' . $response->status()
            );
        }

        $json = $response->json();
        $data = $json['data'] ?? null;

        Log::info('Respuesta FacKatuete', [
            'sale_id'  => $sale->id,
            'response' => $json,
        ]);

        // 🔄 Sincronizar datos del DE en la venta / factura
        if ($data) {
            // Venta
            $sale->de_id         = $data['id']           ?? null;
            $sale->de_cdc        = $data['cdc']          ?? null;
            $sale->de_status     = $data['estado_sifen'] ?? 'pendiente';
            $sale->de_last_error = null;
            $sale->save();

            // Factura ligada (si existe)
            if ($sale->invoice) {
                $invoice = $sale->invoice;

                if (isset($data['establecimiento'], $data['punto_expedicion'])) {
                    $invoice->series = sprintf(
                        '%03d-%03d',
                        (int) $data['establecimiento'],
                        (int) $data['punto_expedicion']
                    );
                }

                if (isset($data['numero'])) {
                    $invoice->number = (int) $data['numero'];
                }

                $invoice->save();
            }
        }

        return $json ?? [];
    }
}
