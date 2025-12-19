<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use App\Models\Client;
use App\Models\User;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;

use Throwable;

class ErpCatalogOrderController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [

            // Cliente
            'cliente'                 => 'required|array',
            'cliente.nombre'          => 'required|string|max:255',
            'cliente.ruc'             => 'nullable|string|max:20',
            'cliente.documento'       => 'nullable|string|max:30',
            'cliente.email'           => 'nullable|email|max:255',
            'cliente.telefono'        => 'required|string|max:30',
            'cliente.direccion'       => 'nullable|string|max:255',
            'cliente.ciudad'          => 'nullable|string|max:255',

            // Pedido
            'pedido'                  => 'required|array',
            'pedido.modo_pago'        => 'required|in:contado,credito',
            'pedido.nota'             => 'nullable|string|max:500',

            // Items
            'items'                   => 'required|array|min:1',
            'items.*.sku'             => 'required|string|max:255',
            'items.*.nombre'          => 'nullable|string|max:255',
            'items.*.cantidad'        => 'required|integer|min:1',

            // precios
            'items.*.precio_contado'  => 'required|numeric|min:0',

            // planes (opcionales)
            'items.*.precio_cuota_3'  => 'nullable|numeric|min:0',
            'items.*.cuotas'          => 'nullable|integer|min:1',
            'items.*.precio_cuota'    => 'nullable|numeric|min:0',

            'items.*.iva_type'        => 'required|in:10,5,exento',

            // Crédito (opcional: si no viene, lo calculamos)
            'credito'                 => 'nullable|array',
            'credito.cuotas'          => 'nullable|integer|min:1',
            'credito.monto_cuota'     => 'nullable|numeric|min:0',
            'credito.entrega_inicial' => 'nullable|numeric|min:0',
            'credito.total_credito'   => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success'  => false,
                'message'  => 'Datos inválidos.',
                'errors'   => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $doc = $this->normDoc($data['cliente']['documento'] ?? null);
        $ruc = $this->normDoc($data['cliente']['ruc'] ?? null);

        if (empty($doc) && empty($ruc)) {
            return response()->json([
                'success' => false,
                'stage'   => 'cliente',
                'message' => 'Falta identificación del cliente: enviá cliente.documento (CPF/Cédula) o cliente.ruc.',
            ], 422);
        }

        try {
            return DB::transaction(function () use ($data, $doc, $ruc) {

                $c = $data['cliente'];

                $userId = User::query()->min('id');
                if (!$userId) {
                    throw new \RuntimeException('No existe ningún usuario en el sistema para asociar al cliente.');
                }

                /* =====================================================
                 * 1) Cliente
                 * ===================================================== */
                $cliente = null;

                if (!empty($ruc)) $cliente = Client::where('ruc', $ruc)->first();
                if (!$cliente && !empty($doc)) $cliente = Client::where('ruc', $doc)->first();

                $email = $c['email'] ?? null;
                if (empty($email)) {
                    $email = 'catalogo+' . now()->timestamp . '@katuete.local';
                }

                $direccionFinal = trim(($c['direccion'] ?? '') . ' ' . ($c['ciudad'] ?? ''));

                if (!$cliente) {
                    $cliente = Client::create([
                        'name'     => $c['nombre'],
                        'ruc'      => !empty($ruc) ? $ruc : $doc,
                        'email'    => $email,
                        'phone'    => $c['telefono'] ?? null,
                        'address'  => $direccionFinal ?: null,
                        'active'   => 1,
                        'user_id'  => $userId,
                        'notes'    => 'Cliente creado automáticamente desde Catálogo Inteligente.',
                    ]);
                } else {
                    $cliente->name    = $c['nombre'] ?? $cliente->name;
                    $cliente->email   = $email ?? $cliente->email;
                    $cliente->phone   = $c['telefono'] ?? $cliente->phone;
                    $cliente->address = $direccionFinal ?: $cliente->address;
                    $cliente->save();
                }

                /* =====================================================
                 * 2) Defaults NOT NULL
                 * ===================================================== */
                $defaultBrandId    = $this->getOrCreateDefaultId('brands',     ['name', 'nombre'], 'Sin marca');
                $defaultCategoryId = $this->getOrCreateDefaultId('categories', ['name', 'nombre'], 'Sin categoría');
                $defaultSupplierId = $this->getOrCreateDefaultId('suppliers',  ['name', 'nombre'], 'Sin proveedor');

                /* =====================================================
                 * 3) Productos + totales
                 * ===================================================== */
                $modoPago = $data['pedido']['modo_pago']; // contado|credito

                // ✅ total contado (base IVA)
                $totalContado = 0.0;

                // ✅ total crédito (financiado) calculado desde items si hace falta
                $totalCreditoCalc = 0.0;

                $gravada10   = 0.0;
                $iva10       = 0.0;
                $gravada5    = 0.0;
                $iva5        = 0.0;
                $exento      = 0.0;

                $lineas = [];

                foreach ($data['items'] as $item) {
                    $sku      = (string) $item['sku'];
                    $cantidad = (int) $item['cantidad'];
                    $precio   = (float) $item['precio_contado'];
                    $ivaType  = $item['iva_type'];

                    $nombreProducto = $item['nombre'] ?? ('Producto ' . $sku);

                    // planes
                    $precioCuota3 = isset($item['precio_cuota_3']) ? (float) $item['precio_cuota_3'] : null;
                    $planCuotas   = isset($item['cuotas']) ? (int) $item['cuotas'] : null;
                    $precioCuota  = isset($item['precio_cuota']) ? (float) $item['precio_cuota'] : null;

                    // Buscar producto por code (SKU)
                    $product = Product::where('code', $sku)->first();

                    if (!$product) {
                        $payloadCreate = [];

                        $this->setIfColumn($payloadCreate, 'products', 'code', $sku);
                        $this->setIfColumn($payloadCreate, 'products', 'name', $nombreProducto);

                        // ✅ TU COLUMNA REAL
                        $this->setIfColumn($payloadCreate, 'products', 'price_cash', $precio);

                        if ($defaultBrandId !== null)    $this->setIfColumn($payloadCreate, 'products', 'brand_id', $defaultBrandId);
                        if ($defaultCategoryId !== null) $this->setIfColumn($payloadCreate, 'products', 'category_id', $defaultCategoryId);
                        if ($defaultSupplierId !== null) $this->setIfColumn($payloadCreate, 'products', 'supplier_id', $defaultSupplierId);

                        $this->setIfColumn($payloadCreate, 'products', 'active', 1);

                        $product = Product::create($payloadCreate);
                    } else {
                        $payloadUpdate = [];

                        $this->setIfColumn($payloadUpdate, 'products', 'name', $nombreProducto);
                        $this->setIfColumn($payloadUpdate, 'products', 'price_cash', $precio);

                        if ($defaultBrandId !== null && Schema::hasColumn('products', 'brand_id') && empty($product->brand_id)) {
                            $payloadUpdate['brand_id'] = $defaultBrandId;
                        }
                        if ($defaultCategoryId !== null && Schema::hasColumn('products', 'category_id') && empty($product->category_id)) {
                            $payloadUpdate['category_id'] = $defaultCategoryId;
                        }
                        if ($defaultSupplierId !== null && Schema::hasColumn('products', 'supplier_id') && empty($product->supplier_id)) {
                            $payloadUpdate['supplier_id'] = $defaultSupplierId;
                        }

                        if (!empty($payloadUpdate)) {
                            $product->fill($payloadUpdate);
                            $product->save();
                        }
                    }

                    // ✅ Guardar planes en product_installments
                    $this->syncInstallmentPlan($product->id, 3, $precioCuota3);
                    $this->syncInstallmentPlan($product->id, $planCuotas, $precioCuota);

                    // ===============================
                    // ✅ TOTALES CONTADO (base IVA)
                    // ===============================
                    $lineTotalContado = $cantidad * $precio;
                    $totalContado += $lineTotalContado;

                    if ($ivaType === '10') {
                        $lineIva10  = round($lineTotalContado / 11, 2);
                        $lineGrav10 = $lineTotalContado - $lineIva10;
                        $iva10     += $lineIva10;
                        $gravada10 += $lineGrav10;
                    } elseif ($ivaType === '5') {
                        $lineIva5  = round($lineTotalContado / 21, 2);
                        $lineGrav5 = $lineTotalContado - $lineIva5;
                        $iva5     += $lineIva5;
                        $gravada5 += $lineGrav5;
                    } else {
                        $exento += $lineTotalContado;
                    }

                    // ===============================
                    // ✅ TOTAL CRÉDITO (financiado)
                    // ===============================
                    if ($modoPago === 'credito') {
                        $cuotasCredito = (int)($data['credito']['cuotas'] ?? $planCuotas ?? 3);
                        if ($cuotasCredito <= 0) $cuotasCredito = 3;

                        $pc = $precioCuota ?? $precioCuota3 ?? null;
                        if ($pc !== null) {
                            $totalCreditoCalc += $cantidad * ((float)$pc) * $cuotasCredito;
                        }
                    }

                    $lineas[] = [
                        'product'    => $product,
                        'cantidad'   => $cantidad,
                        'precio'     => $precio, // ojo: unit_price queda contado (si querés mostrar crédito en items te lo adapto)
                        'line_total' => $lineTotalContado,
                        'iva_type'   => $ivaType,
                    ];

                    // ===============================
                    // ✅ IMÁGENES desde Catálogo
                    // ===============================
                    $coverUrl   = $item['cover_url'] ?? null;
                    $imagesUrls = $item['images_urls'] ?? [];

                    if (!is_array($imagesUrls)) $imagesUrls = [];
                    $imagesUrls = array_values(array_filter($imagesUrls, fn($u) => is_string($u) && trim($u) !== ''));

                    if ($coverUrl && !in_array($coverUrl, $imagesUrls, true)) {
                        array_unshift($imagesUrls, $coverUrl);
                    }

                    // 1) Guardar COVER
                    if ($coverUrl) {
                        $existing = DB::table('product_images')
                            ->where('product_id', $product->id)
                            ->where('path', $coverUrl)
                            ->first();

                        DB::table('product_images')
                            ->where('product_id', $product->id)
                            ->where('is_cover', true)
                            ->update(['is_cover' => false, 'updated_at' => now()]);

                        if ($existing) {
                            DB::table('product_images')->where('id', $existing->id)->update([
                                'is_cover'   => true,
                                'sort_order' => 0,
                                'updated_at' => now(),
                            ]);
                        } else {
                            DB::table('product_images')->insert([
                                'product_id' => $product->id,
                                'path'       => $coverUrl,
                                'alt'        => $product->name,
                                'is_cover'   => true,
                                'sort_order' => 0,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }

                    // 2) Guardar GALERÍA
                    $sort = 1;
                    foreach ($imagesUrls as $url) {
                        if (!$url) continue;
                        if ($coverUrl && $url === $coverUrl) continue;

                        $exists = DB::table('product_images')
                            ->where('product_id', $product->id)
                            ->where('path', $url)
                            ->exists();

                        if (!$exists) {
                            DB::table('product_images')->insert([
                                'product_id' => $product->id,
                                'path'       => $url,
                                'alt'        => $product->name,
                                'is_cover'   => false,
                                'sort_order' => $sort,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        $sort++;
                    }
                }

                $totalIva = $iva10 + $iva5;

                /* =====================================================
                 * 4) Venta (TOTAL correcto)
                 * ===================================================== */

                // ✅ total final: contado o financiado
                $totalVenta = $totalContado;

                if ($modoPago === 'credito') {
                    $credito = $data['credito'] ?? [];

                    $cuotas = (int)($credito['cuotas'] ?? 3);
                    if ($cuotas <= 0) $cuotas = 3;

                    $montoCuota = (float)($credito['monto_cuota'] ?? 0);

                    // si no vino, intentamos calcular desde items
                    if ($montoCuota <= 0) {
                        $montoCuota = 0.0;
                        foreach ($data['items'] as $it) {
                            $qty = (int)($it['cantidad'] ?? 1);
                            $pc  = $it['precio_cuota'] ?? $it['precio_cuota_3'] ?? null;
                            if ($pc !== null) $montoCuota += $qty * (float)$pc;
                        }
                    }

                    $totalCreditoPayload = (float)($credito['total_credito'] ?? 0);

                    // prioridad: total_credito del payload
                    if ($totalCreditoPayload > 0) {
                        $totalVenta = $totalCreditoPayload;
                    } elseif ($totalCreditoCalc > 0) {
                        $totalVenta = $totalCreditoCalc;
                    } elseif ($montoCuota > 0 && $cuotas > 0) {
                        $totalVenta = $montoCuota * $cuotas;
                    } else {
                        $totalVenta = $totalContado; // fallback final
                    }
                }

                $sale = Sale::create([
                    'client_id'   => $cliente->id,
                    'modo_pago'   => $modoPago,

                    // ✅ ACÁ está la clave del bug: guardamos total financiado si es crédito
                    'total'       => $totalVenta,

                    'estado'      => 'pendiente_aprobacion',
                    'status'      => 'pendiente_aprobacion',
                    'fecha'       => now()->toDateString(),
                    'nota'        => $data['pedido']['nota'] ?? 'Venta generada automáticamente desde Catálogo Inteligente.',

                    // IVA calculado sobre contado (recomendado)
                    'gravada_10'  => $gravada10,
                    'iva_10'      => $iva10,
                    'gravada_5'   => $gravada5,
                    'iva_5'       => $iva5,
                    'exento'      => $exento,
                    'total_iva'   => $totalIva,
                ]);

                /* =====================================================
                 *  DATOS DE CRÉDITO (para el trigger)
                 * ===================================================== */
                if ($modoPago === 'credito') {
                    $credito = $data['credito'] ?? [];

                    $cuotas = (int)($credito['cuotas'] ?? 3);
                    if ($cuotas <= 0) $cuotas = 3;

                    $montoCuota = (float)($credito['monto_cuota'] ?? 0);
                    if ($montoCuota <= 0) {
                        // calcular desde items
                        $montoCuota = 0.0;
                        foreach ($data['items'] as $it) {
                            $qty = (int)($it['cantidad'] ?? 1);
                            $pc  = $it['precio_cuota'] ?? $it['precio_cuota_3'] ?? null;
                            if ($pc !== null) $montoCuota += $qty * (float)$pc;
                        }
                    }

                    $entregaInicial = (float)($credito['entrega_inicial'] ?? 0);

                    $creditTotal = (float)($credito['total_credito'] ?? 0);
                    if ($creditTotal <= 0) $creditTotal = (float)$totalVenta; // el mismo que guardamos en sale.total

                    $payload = [];

                    if (Schema::hasColumn('sales', 'credit_installments')) {
                        $payload['credit_installments'] = $cuotas;
                    }
                    if (Schema::hasColumn('sales', 'credit_installment_amount')) {
                        $payload['credit_installment_amount'] = $montoCuota;
                    }
                    if (Schema::hasColumn('sales', 'credit_total')) {
                        $payload['credit_total'] = $creditTotal;
                    }
                    if (Schema::hasColumn('sales', 'credit_down_payment')) {
                        $payload['credit_down_payment'] = $entregaInicial;
                    }
                    if (Schema::hasColumn('sales', 'credit_first_due_date')) {
                        $payload['credit_first_due_date'] = now()->addDays(30)->toDateString();
                    }
                    if (Schema::hasColumn('sales', 'credit_every_days')) {
                        $payload['credit_every_days'] = 30;
                    }

                    if (!empty($payload)) {
                        $sale->fill($payload);
                        $sale->save();
                    }
                }

                /* =====================================================
                 * 5) Items de la venta
                 * ===================================================== */
                foreach ($lineas as $line) {
                    $p = $line['product'];

                    SaleItem::create([
                        'sale_id'      => $sale->id,
                        'product_id'   => $p->id,
                        'product_code' => $p->code,
                        'product_name' => $p->name,

                        // unit_price queda contado (si querés mostrar crédito en ítems, te lo adapto según tu sale_items)
                        'unit_price'   => $line['precio'],

                        'qty'          => $line['cantidad'],
                        'iva_type'     => $line['iva_type'],
                        'line_total'   => $line['line_total'],
                    ]);
                }

                return response()->json([
                    'success'   => true,
                    'message'   => 'Pedido del catálogo registrado como venta pendiente de aprobación.',
                    'client_id' => $cliente->id,
                    'sale_id'   => $sale->id,
                    'estado'    => $sale->estado,
                    'modo_pago' => $sale->modo_pago,
                    'totales'   => [
                        'total_guardado_en_sale' => $sale->total,
                        'total_contado_base_iva' => $totalContado,
                        'total_credito_calc'     => $totalCreditoCalc,
                    ],
                    'resumen_iva' => [
                        'gravada_10' => $gravada10,
                        'iva_10'     => $iva10,
                        'gravada_5'  => $gravada5,
                        'iva_5'      => $iva5,
                        'exento'     => $exento,
                        'total_iva'  => $totalIva,
                    ],
                ]);
            });

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error interno al procesar el pedido del catálogo.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /* =====================================================
     * Helpers
     * ===================================================== */

    private function syncInstallmentPlan(int $productId, ?int $installments, $installmentPrice): void
    {
        if (!$installments || $installments < 1) return;
        if ($installmentPrice === null) return;

        $installmentPrice = (float) $installmentPrice;
        if ($installmentPrice <= 0) return;

        if (!Schema::hasTable('product_installments')) return;

        DB::table('product_installments')->updateOrInsert(
            [
                'product_id'    => $productId,
                'installments'  => $installments,
            ],
            [
                'installment_price' => $installmentPrice,
                'updated_at'        => now(),
                'created_at'        => now(),
            ]
        );
    }

    private function setIfColumn(array &$arr, string $table, string $column, $value): void
    {
        if (Schema::hasColumn($table, $column)) {
            $arr[$column] = $value;
        }
    }

    private function getOrCreateDefaultId(string $table, array $nameColumns, string $defaultName): ?int
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'id')) return null;

        $nameCol = null;
        foreach ($nameColumns as $col) {
            if (Schema::hasColumn($table, $col)) { $nameCol = $col; break; }
        }
        if ($nameCol === null) return null;

        $existing = DB::table($table)->where($nameCol, $defaultName)->first();
        if ($existing && isset($existing->id)) return (int) $existing->id;

        $insert = [
            $nameCol     => $defaultName,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn($table, 'active')) $insert['active'] = 1;
        if (Schema::hasColumn($table, 'activo')) $insert['activo'] = 1;

        try {
            $id = DB::table($table)->insertGetId($insert);
            return $id ? (int) $id : null;
        } catch (Throwable $e) {
            $first = DB::table($table)->select('id')->orderBy('id')->first();
            return ($first && isset($first->id)) ? (int) $first->id : null;
        }
    }

    private function normDoc(?string $v): ?string
    {
        if ($v === null) return null;
        $v = trim($v);
        if ($v === '') return null;

        $v = preg_replace('/\s+/', '', $v);
        return $v ?: null;
    }
}
