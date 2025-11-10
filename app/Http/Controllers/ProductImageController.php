<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductImageController extends Controller
{
    /**
     * Eliminar una imagen asociada a un producto.
     */
    public function destroy(Product $product, ProductImage $image)
    {
        // Seguridad: confirmar que la imagen pertenece al producto
        if ($image->product_id !== $product->id) {
            abort(403, 'No autorizado');
        }

        DB::transaction(function () use ($image) {
            if ($image->path && Storage::disk('public')->exists($image->path)) {
                Storage::disk('public')->delete($image->path);
            }
            $image->delete();
        });

        return redirect()
            ->route('products.edit', $product)
            ->with('success', 'Imagen eliminada correctamente.');
    }
}
