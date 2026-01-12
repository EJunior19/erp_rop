<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Brand;

class BrandController extends Controller
{
    /**
     * Muestra el listado paginado de marcas.
     * GET /brands
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $brands = Brand::query()
            ->withCount('products')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'ilike', "%{$q}%")
                        ->orWhere('code', 'ilike', "%{$q}%");
                });
            })
            ->latest('id')          // más consistente que latest() si no querés depender de created_at
            ->paginate(12)
            ->withQueryString();    // mantiene ?q=... en paginación

        return view('brands.index', compact('brands'));
    }

    /**
     * Muestra el formulario de creación.
     * GET /brands/create
     */
    public function create()
    {
        // Próximo ID de la secuencia
        $nextId = DB::select("SELECT nextval(pg_get_serial_sequence('brands','id')) as next_id")[0]->next_id;

        // Generar código con prefijo fijo (ejemplo BR-00001)
        $code = sprintf("BR-%05d", $nextId);

        return view('brands.create', compact('nextId', 'code'));
    }

    /**
     * Procesa el formulario y crea una marca.
     * POST /brands
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'   => ['required', 'string', 'max:255', 'unique:brands,name'],
            'active' => ['nullable', 'boolean'],
        ]);

        $data['active'] = $request->boolean('active');

        $brand = Brand::create($data);

        return redirect()
            ->route('brands.index', $brand)
            ->with('ok', '✅ Marca creada correctamente.');
    }

    /**
     * Muestra el detalle de una marca.
     * GET /brands/{brand}
     */
    public function show(Brand $brand)
    {
        // útil si querés mostrar conteo en show
        $brand->loadCount('products');

        return view('brands.show', compact('brand'));
    }

    /**
     * Muestra el formulario de edición.
     * GET /brands/{brand}/edit
     */
    public function edit(Brand $brand)
    {
        return view('brands.edit', compact('brand'));
    }

    /**
     * Actualiza una marca.
     * PUT/PATCH /brands/{brand}
     */
    public function update(Request $request, Brand $brand)
    {
        $data = $request->validate([
            'name'   => ['required', 'string', 'max:255', 'unique:brands,name,' . $brand->id],
            'active' => ['nullable', 'boolean'],
        ]);

        $data['active'] = $request->boolean('active');

        $brand->update($data);

        return redirect()
            ->route('brands.show', $brand)
            ->with('ok', '✅ Marca actualizada.');
    }

    /**
     * Elimina una marca.
     * DELETE /brands/{brand}
     */
    public function destroy(Brand $brand)
    {
        $brand->delete();

        return redirect()
            ->route('brands.index')
            ->with('ok', '🗑️ Marca eliminada.');
    }
}
