<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /** Listado (excluye soft-deleted por defecto) */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $categories = Category::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'ilike', "%{$q}%")
                        ->orWhere('code', 'ilike', "%{$q}%");
                });
            })
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return view('categories.index', compact('categories'));
    }

    /** Form de creación */
    public function create()
    {
        // No consumimos la secuencia ni generamos code acá: lo hace el trigger/BD
        return view('categories.create');
    }

    /** Guardar nueva categoría */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'   => ['required', 'string', 'max:255', 'unique:categories,name'],
            'active' => ['required', 'boolean'],
        ]);

        $category = Category::create($validated);

        // Traer valores generados por la BD (ej: code por trigger)
        $category->refresh();

        return redirect()
            ->route('categories.index')
            ->with(
                'success',
                'Categoría ' . $category->name . ' creada (código ' . ($category->code ?? '—') . ').'
            );
    }

    /** Ver detalle */
    public function show(Category $category)
    {
        $category->loadCount('products'); // ->products_count

        $latestProducts = $category->products()
            ->latest('id')
            ->take(5)
            ->get(['id', 'code', 'name', 'active']);

        return view('categories.show', compact('category', 'latestProducts'));
    }

    /** Form de edición */
    public function edit(Category $category)
    {
        return view('categories.edit', compact('category'));
    }

    /** Actualizar */
    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name'   => ['required', 'string', 'max:255', "unique:categories,name,{$category->id}"],
            'active' => ['required', 'boolean'],
        ]);

        $category->update($validated);

        return redirect()
            ->route('categories.show', $category)
            ->with('success', 'Categoría actualizada.');
    }

    /** Soft delete (archivar) */
    public function destroy(Category $category)
    {
        $category->delete(); // requiere SoftDeletes en el modelo + columna deleted_at
        return redirect()
            ->route('categories.index')
            ->with('success', 'Categoría archivada.');
    }

    /** Activar / Desactivar */
    public function toggle(Category $category)
    {
        $category->active = ! $category->active;
        $category->save();

        return back()->with('success', $category->active ? 'Categoría activada.' : 'Categoría desactivada.');
    }

    /** Papelera: solo las eliminadas (soft-deleted) */
    public function deleted()
    {
        $categories = Category::onlyTrashed()->latest('id')->paginate(12);
        return view('categories.deleted', compact('categories'));
    }

    /** Restaurar desde papelera */
    public function restore($id)
    {
        $category = Category::onlyTrashed()->findOrFail($id);
        $category->restore();

        return redirect()
            ->route('categories.show', $category)
            ->with('success', 'Categoría restaurada.');
    }

    /** Borrado definitivo (opcional) */
    public function forceDelete($id)
    {
        $category = Category::onlyTrashed()->findOrFail($id);
        $category->forceDelete();

        return redirect()
            ->route('categories.deleted')
            ->with('success', 'Categoría eliminada definitivamente.');
    }
}
