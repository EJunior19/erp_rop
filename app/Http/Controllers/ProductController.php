<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
        if ($v === null || $v === '') {
            return null;
        }
        return (int) preg_replace('/\D+/', '', (string) $v);
    }

    /* =======================
     * VISTAS CRUD
     * ======================= */

    public function index()
    {
        $products = Product::with(['brand','category','supplier','installments','coverImage'])
            ->latest()
            ->paginate(15);

        return view('products.index', compact('products'));
    }

    public function create()
    {
        // Preview del próximo code sin consumir la secuencia (solo PG)
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
            // Para MySQL/SQLite, omitimos preview silenciosamente
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

        // Acepta ambos nombres por compatibilidad
        $rawInstallmentPrices = (array) $request->input('installment_prices', $request->input('installment_price', []));
        $installmentPrices    = array_map(fn($v) => $this->cleanInt($v), $rawInstallmentPrices);
        $installments         = (array) $request->input('installments', []);

        // Merge para validar sobre enteros
        $request->merge([
            'price_cash'         => $priceCash,
            'installment_prices' => $installmentPrices,
            'installments'       => $installments,
        ]);

        // 2) Validación
        $validated = $request->validate([
            'name'                 => ['required','string','max:255'],
            'brand_id'             => ['required','exists:brands,id'],
            'category_id'          => ['required','exists:categories,id'],
            'supplier_id'          => ['required','exists:suppliers,id'],
            'price_cash'           => ['nullable','integer','min:0'],
            'active'               => ['required','boolean'],
            'notes'                => ['nullable','string'],

            'installments'         => ['array'],
            'installments.*'       => ['nullable','integer','min:1'],
            'installment_prices'   => ['array'],
            'installment_prices.*' => ['nullable','integer','min:0'],

            // 📷 imágenes (múltiples)
            'images'               => ['sometimes','array'],
            'images.*'             => ['nullable','image','max:4096'], // 4MB c/u
        ]);

        // 3) Persistencia (producto + cuotas + imágenes)
        DB::transaction(function () use ($request, $validated, $installments, $installmentPrices) {

            // Crear producto
            $product = Product::create([
                'name'        => $validated['name'],
                'brand_id'    => $validated['brand_id'],
                'category_id' => $validated['category_id'],
                'supplier_id' => $validated['supplier_id'],
                'price_cash'  => $validated['price_cash'] ?? null, // entero en Gs
                'active'      => (bool)($validated['active'] ?? true),
                'notes'       => $validated['notes'] ?? null,
            ]);

            // Pares cuota -> precio (ignorando vacíos)
            $rows = [];
            foreach ($installments as $i => $n) {
                $n = (int) $n;
                $p = $installmentPrices[$i] ?? null;
                if ($n && $p) {
                    $rows[] = [
                        'installments'      => $n,
                        'installment_price' => (int) $p,
                    ];
                }
            }
            if (!empty($rows)) {
                $product->installments()->createMany($rows);
            }

            // 📷 Imágenes múltiples (primera = portada)
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $idx => $img) {
                    if (!$img) continue;
                    $path = $img->store('products', 'public'); // storage/app/public/products
                    $product->images()->create([
                        'path'       => $path,
                        'alt'        => $product->name,
                        'is_cover'   => $idx === 0,   // primera como portada
                        'sort_order' => $idx,
                    ]);
                }
            }
        });

        return redirect()
            ->route('products.index')
            ->with('success', "Producto {$validated['name']} creado correctamente con cuotas e imágenes.");
    }

    public function show(Product $product)
    {
        // Cargamos todo lo necesario para la ficha
        $product->load([
            'brand','category','supplier','installments',
            'images' => fn($q) => $q->orderBy('sort_order')->orderBy('id'),
            'coverImage'
        ]);

        // Si mostrás movimientos en la vista, podés cargarlo así:
        // $movements = $product->inventoryMovements()->latest()->limit(20)->get();
        // return view('products.show', compact('product','movements'));

        return view('products.show', compact('product'));
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
        // 1) Normalizar entradas (enteros en Gs)
        $priceCash = $this->cleanInt($request->input('price_cash'));

        // Acepta ambos nombres
        $rawInstallmentPrices = (array) $request->input('installment_prices', $request->input('installment_price', []));
        $installmentPrices    = array_map(fn($v) => $this->cleanInt($v), $rawInstallmentPrices);
        $installments         = (array) $request->input('installments', []);

        // Merge para validar sobre enteros
        $request->merge([
            'price_cash'         => $priceCash,
            'installment_prices' => $installmentPrices,
            'installments'       => $installments,
        ]);

        // 2) Validación
        $validated = $request->validate([
            'name'                 => ['required','string','max:255'],
            'brand_id'             => ['required','exists:brands,id'],
            'category_id'          => ['required','exists:categories,id'],
            'supplier_id'          => ['required','exists:suppliers,id'],
            'price_cash'           => ['nullable','integer','min:0'],
            'active'               => ['required','boolean'],
            'notes'                => ['nullable','string'],

            'installments'         => ['array'],
            'installments.*'       => ['nullable','integer','min:1'],
            'installment_prices'   => ['array'],
            'installment_prices.*' => ['nullable','integer','min:0'],

            // 📷 nuevos archivos opcionales
            'images'               => ['sometimes','array'],
            'images.*'             => ['nullable','image','max:4096'],

            // portada + orden enviados desde el form
            'cover_id'             => ['nullable','integer'],
            'orders'               => ['sometimes','array'],
        ]);

        // 3) Persistencia
        DB::transaction(function () use ($request, $product, $validated, $installments, $installmentPrices) {

            // Actualizar cabecera
            $product->update([
                'name'        => $validated['name'],
                'brand_id'    => $validated['brand_id'],
                'category_id' => $validated['category_id'],
                'supplier_id' => $validated['supplier_id'],
                'price_cash'  => $validated['price_cash'] ?? null,
                'active'      => (bool)($validated['active'] ?? true),
                'notes'       => $validated['notes'] ?? null,
            ]);

            // Reemplazar cuotas
            $product->installments()->delete();
            $rows = [];
            foreach ($installments as $i => $n) {
                $n = (int) $n;
                $p = $installmentPrices[$i] ?? null;
                if ($n && $p) {
                    $rows[] = [
                        'installments'      => $n,
                        'installment_price' => (int) $p,
                    ];
                }
            }
            if (!empty($rows)) {
                $product->installments()->createMany($rows);
            }

            // 📷 Subir imágenes nuevas (se agregan al final)
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

            // 🏷️ Actualizar portada si vino cover_id
            if ($request->filled('cover_id')) {
                $coverId = (int) $request->input('cover_id');
                // Marcar todas como no-portada
                $product->images()->update(['is_cover' => false]);
                // Marcar la elegida (si pertenece al producto)
                $img = $product->images()->whereKey($coverId)->first();
                if ($img) {
                    $img->update(['is_cover' => true]);
                }
            }

            // 🔢 Actualizar sort_order si vino orders[image_id] => orden
            $orders = (array) $request->input('orders', []);
            if (!empty($orders)) {
                foreach ($orders as $imgId => $order) {
                    $img = $product->images()->whereKey($imgId)->first();
                    if ($img) {
                        $img->update(['sort_order' => (int) $order]);
                    }
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
            // eliminar físicamente archivos de imágenes
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

    /** Autocomplete / búsqueda libre (nombre, code, id). */
    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $driver = DB::connection()->getDriverName(); // mysql, pgsql, sqlite...
        $like   = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';
        $needle = "%{$q}%";

        $rows = Product::query()
            ->where('active', true)
            ->where(function($w) use ($like, $needle, $q) {
                $w->where('name', $like, $needle)
                  ->orWhere('code', $like, $needle);

                if (ctype_digit($q)) {
                    $w->orWhere('id', (int)$q);
                }
            })
            ->orderBy('name')
            ->limit(10)
            ->get([
                'id','code','name','stock','price_cash',
                'brand_id','category_id','supplier_id'
            ]);

        return response()->json($rows);
    }

    /** Buscar por ID numérico. */
    public function findById(int $id)
    {
        $prod = Product::where('active', true)->find($id);

        if (!$prod) {
            return response()->json(['error' => 'Producto no encontrado'], 404);
        }

        return response()->json([
            'id'           => $prod->id,
            'code'         => $prod->code,
            'name'         => $prod->name,
            'price_cash'   => $prod->price_cash,
            'stock'        => $prod->stock,
            'installments' => $prod->installments()->get(['installments','installment_price']),
        ]);
    }

    /** Buscar por code (o ID numérico). Devuelve price_cash + cuotas. */
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

        if (!$prod) {
            return response()->json(['error' => 'Producto no encontrado'], 404);
        }

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
