<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Models\InventoryMovement;

class ProductController extends Controller
{
    /* =======================
     * Helpers
     * ======================= */

    /**
     * Limpia un valor monetario o numérico que puede venir con puntos, comas o texto.
     * "150.000" -> 150000 (int), ""|null -> null
     */
    private function cleanInt($v): ?int
    {
        if ($v === null || $v === '') return null;
        return (int) preg_replace('/\D+/', '', (string) $v);
    }

    /**
     * Normaliza el código/SKU:
     * - trim
     * - uppercase
     * - si queda vacío => null (para que el trigger lo genere)
     */
    private function normalizeCode($code): ?string
    {
        if ($code === null) return null;
        $c = strtoupper(trim((string) $code));
        return $c === '' ? null : $c;
    }

    /* =======================
     * VISTAS CRUD
     * ======================= */

    public function index(Request $request)
    {
        $q = trim($request->get('q'));

        $products = Product::with(['brand','category','supplier'])
            ->when($q, function ($query) use ($q) {
                $query->where(function ($q2) use ($q) {
                    $q2->where('code', 'ILIKE', "%{$q}%")
                       ->orWhere('name', 'ILIKE', "%{$q}%")
                       ->orWhereHas('brand', function ($b) use ($q) {
                           $b->where('name', 'ILIKE', "%{$q}%");
                       })
                       ->orWhereHas('category', function ($c) use ($q) {
                           $c->where('name', 'ILIKE', "%{$q}%");
                       })
                       ->orWhereHas('supplier', function ($s) use ($q) {
                           $s->where('name', 'ILIKE', "%{$q}%");
                       });
                });
            })
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('products.index', compact('products'));
    }

    public function create()
    {
        // Preview del próximo code (solo sugerencia visual)
        $nextId = null;
        $code   = null;

        try {
            $row = DB::selectOne("
                SELECT last_value + increment_by AS next_id
                FROM pg_sequences
                WHERE schemaname='public' AND sequencename='products_id_seq'
            ");
            $nextId = $row?->next_id;
            $code   = $nextId ? sprintf('PRD-%05d', $nextId) : null;
        } catch (\Throwable $e) {
            // omit
        }

        $brands     = Brand::orderBy('name')->get();
        $categories = Category::orderBy('name')->get();
        $suppliers  = Supplier::orderBy('name')->get();

        return view('products.create', compact('nextId','code','brands','categories','suppliers'));
    }

    public function store(Request $request)
{
    // 1) Normalizar entradas (enteros en Gs)
    $priceCash = $this->cleanInt($request->input('price_cash'));

    $rawInstallmentPrices = (array) $request->input('installment_prices', $request->input('installment_price', []));
    $installmentPrices    = array_map(fn($v) => $this->cleanInt($v), $rawInstallmentPrices);
    $installments         = (array) $request->input('installments', []);

    $request->merge([
        'price_cash'         => $priceCash,
        'installment_prices' => $installmentPrices,
        'installments'       => $installments,
    ]);

    // 2) Validación (ACÁ NO se hace lógica, solo reglas)
    $validated = $request->validate([
        'name'        => ['required','string','max:255'],

        // ✅ code opcional + único
        'code'        => ['nullable','string','max:255','unique:products,code'],

        'brand_id'    => ['required','exists:brands,id'],
        'category_id' => ['required','exists:categories,id'],
        'supplier_id' => ['required','exists:suppliers,id'],

        'price_cash'  => ['nullable','integer','min:0'],
        'active'      => ['nullable','boolean'],
        'notes'       => ['nullable','string'],

        'installments'         => ['array'],
        'installments.*'       => ['nullable','integer','min:1'],
        'installment_prices'   => ['array'],
        'installment_prices.*' => ['nullable','integer','min:0'],

        'images'   => ['sometimes','array'],
        'images.*' => ['nullable','image','max:4096'],
    ]);

    // ✅ 3) Normalizar code DESPUÉS de validar (manual opcional)
    $code = $validated['code'] ?? null;
    $code = ($code !== null && trim($code) !== '') ? strtoupper(trim($code)) : null;

    $product = null;

    // 4) Persistencia
    DB::transaction(function () use ($request, $validated, $installments, $installmentPrices, $code, &$product) {

        $product = Product::create([
            'code'        => $code, // ✅ si es null => tu booted() genera PRD-00001
            'name'        => $validated['name'],
            'brand_id'    => $validated['brand_id'],
            'category_id' => $validated['category_id'],
            'supplier_id' => $validated['supplier_id'],
            'price_cash'  => $validated['price_cash'] ?? null,
            'stock'       => 0,
            'active'      => (bool)($validated['active'] ?? true),
            'notes'       => $validated['notes'] ?? null,
        ]);

        // Cuotas
        $rows = [];
        foreach ($installments as $i => $n) {
            $n = (int) $n;
            $p = $installmentPrices[$i] ?? null;
            if ($n && $p !== null && $p > 0) {
                $rows[] = [
                    'installments'      => $n,
                    'installment_price' => (int) $p,
                ];
            }
        }
        if (!empty($rows)) {
            $product->installments()->createMany($rows);
        }

        // Imágenes (primera = portada)
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $idx => $img) {
                if (!$img) continue;

                $path = $img->store('products', 'public');

                $product->images()->create([
                    'path'       => $path,
                    'alt'        => $product->name,
                    'is_cover'   => $idx === 0,
                    'sort_order' => $idx,
                ]);
            }
        }
    });

    return redirect()
        ->route('products.show', $product)
        ->with('success', "Producto {$product->name} creado correctamente.");
}


    public function show(Product $product)
    {
        $product->load([
            'brand',
            'category',
            'supplier',
            'installments',
            'images' => fn($q) => $q->orderBy('sort_order')->orderBy('id'),
            'coverImage',
        ]);

        // ✅ Cargar últimos movimientos del producto
        $movements = InventoryMovement::with(['product','user'])
            ->where('product_id', $product->id)
            ->latest()
            ->take(20)
            ->get();

        return view('products.show', compact('product', 'movements'));
    }

    public function edit(Product $product)
    {
        $brands     = Brand::orderBy('name')->get();
        $categories = Category::orderBy('name')->get();
        $suppliers  = Supplier::orderBy('name')->get();

        $product->load([
            'installments',
            'images' => fn($q) => $q->orderBy('sort_order')->orderBy('id'),
            'coverImage'
        ]);

        return view('products.edit', compact('product','brands','categories','suppliers'));
    }

    public function update(Request $request, Product $product)
    {
        // 1) Normalizar entradas
        $priceCash = $this->cleanInt($request->input('price_cash'));

        $rawInstallmentPrices = (array) $request->input('installment_prices', $request->input('installment_price', []));
        $installmentPrices    = array_map(fn($v) => $this->cleanInt($v), $rawInstallmentPrices);
        $installments         = (array) $request->input('installments', []);

        $request->merge([
            'price_cash'         => $priceCash,
            'installment_prices' => $installmentPrices,
            'installments'       => $installments,
        ]);

        // 2) Validación
        $validated = $request->validate([
            'name' => ['required','string','max:255'],

            // ✅ CODE MANUAL + UNIQUE IGNORANDO EL MISMO PRODUCTO
           'code' => ['nullable','string','max:255', Rule::unique('products','code')->ignore($product->id)],

            'brand_id'    => ['required','exists:brands,id'],
            'category_id' => ['required','exists:categories,id'],
            'supplier_id' => ['required','exists:suppliers,id'],

            'price_cash'  => ['nullable','integer','min:0'],
            'active'      => ['nullable','boolean'],
            'notes'       => ['nullable','string'],

            'installments'         => ['array'],
            'installments.*'       => ['nullable','integer','min:1'],
            'installment_prices'   => ['array'],
            'installment_prices.*' => ['nullable','integer','min:0'],

            'images'   => ['sometimes','array'],
            'images.*' => ['nullable','image','max:4096'],

            'cover_id' => ['nullable','integer'],
            'orders'   => ['sometimes','array'],
        ]);

        // ✅ Normalizar code: uppercase + trim; vacío => null (trigger genera)
        $validated['code'] = $this->normalizeCode($validated['code'] ?? null);

        DB::transaction(function () use ($request, $product, $validated, $installments, $installmentPrices) {

            // ✅ Actualizar cabecera (incluye code)
            $product->update([
                // NULL => trigger genera automáticamente (si tu trigger está para UPDATE también).
                // Si tu trigger solo genera en INSERT, y querés permitir “vaciar” en UPDATE,
                // asegurate que el trigger lo soporte o dejá el code actual.
                'code'        => $validated['code'],
                'name'        => $validated['name'],
                'brand_id'    => $validated['brand_id'],
                'category_id' => $validated['category_id'],
                'supplier_id' => $validated['supplier_id'],
                'price_cash'  => $validated['price_cash'] ?? null,
                'active'      => (bool)($validated['active'] ?? true),
                'notes'       => $validated['notes'] ?? null,
            ]);

            // Si el trigger ajusta code en UPDATE, refrescamos
            $product->refresh();

            // Reemplazar cuotas
            $product->installments()->delete();

            $rows = [];
            foreach ($installments as $i => $n) {
                $n = (int) $n;
                $p = $installmentPrices[$i] ?? null;
                if ($n && $p !== null && $p > 0) {
                    $rows[] = [
                        'installments'      => $n,
                        'installment_price' => (int) $p,
                    ];
                }
            }
            if (!empty($rows)) {
                $product->installments()->createMany($rows);
            }

            // Subir imágenes nuevas
            if ($request->hasFile('images')) {
                $maxSort = (int) ($product->images()->max('sort_order') ?? 0);
                foreach ($request->file('images') as $idx => $img) {
                    if (!$img) continue;

                    $path = $img->store('products', 'public');

                    $product->images()->create([
                        'path'       => $path,
                        'alt'        => $product->name,
                        'is_cover'   => false,
                        'sort_order' => $maxSort + 1 + $idx,
                    ]);
                }
            }

            // Portada
            if ($request->filled('cover_id')) {
                $coverId = (int) $request->input('cover_id');
                $product->images()->update(['is_cover' => false]);

                $img = $product->images()->whereKey($coverId)->first();
                if ($img) $img->update(['is_cover' => true]);
            }

            // Orden
            $orders = (array) $request->input('orders', []);
            if (!empty($orders)) {
                foreach ($orders as $imgId => $order) {
                    $img = $product->images()->whereKey($imgId)->first();
                    if ($img) $img->update(['sort_order' => (int) $order]);
                }
            }
        });

        return redirect()
            ->route('products.show', $product)
            ->with('success','Producto actualizado con cuotas e imágenes.');
    }

    public function destroy(Product $product)
    {
        DB::transaction(function () use ($product) {
            foreach ($product->images as $img) {
                if ($img->path && Storage::disk('public')->exists($img->path)) {
                    Storage::disk('public')->delete($img->path);
                }
            }

            $product->images()->delete();
            $product->installments()->delete();
            $product->delete();
        });

        return redirect()
            ->route('products.index')
            ->with('success','Producto eliminado');
    }

    /* =======================
     * API
     * ======================= */

    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') return response()->json([]);

        $driver = DB::connection()->getDriverName();
        $like   = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';
        $needle = "%{$q}%";

        $rows = Product::query()
            ->where('active', true)
            ->where(function($w) use ($like, $needle, $q) {
                $w->where('name', $like, $needle)
                  ->orWhere('code', $like, $needle);

                if (ctype_digit($q)) $w->orWhere('id', (int)$q);
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id','code','name','stock','price_cash','brand_id','category_id','supplier_id']);

        return response()->json($rows);
    }

    public function findById(int $id)
    {
        $prod = Product::where('active', true)->find($id);

        if (!$prod) return response()->json(['error' => 'Producto no encontrado'], 404);

        return response()->json([
            'id'           => $prod->id,
            'code'         => $prod->code,
            'name'         => $prod->name,
            'price_cash'   => $prod->price_cash,
            'stock'        => $prod->stock,
            'installments' => $prod->installments()->get(['installments','installment_price']),
        ]);
    }

    public function findByCode($code)
    {
        $q = Product::query()->where('active', true);

        if (ctype_digit((string)$code)) {
            $prod = $q->where('id', (int)$code)->first();
        } else {
            $prod = $q->where('code', $code)->first();
            if (!$prod) {
                $num = preg_replace('/\D+/', '', (string)$code);
                if ($num !== '' && ctype_digit($num)) {
                    $prod = Product::where('active', true)->where('id', (int)$num)->first();
                }
            }
        }

        if (!$prod) return response()->json(['error' => 'Producto no encontrado'], 404);

        return response()->json([
            'id'           => $prod->id,
            'code'         => $prod->code,
            'name'         => $prod->name,
            'price_cash'   => $prod->price_cash,
            'stock'        => $prod->stock,
            'installments' => $prod->installments()->get(['installments','installment_price']),
        ]);
    }
}
