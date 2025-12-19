<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
            // 🔹 OJO: path debe ser relativo al disco "public", ej: "products/archivo.jpg"
            if ($image->path && Storage::disk('public')->exists($image->path)) {
                Storage::disk('public')->delete($image->path);
            }

            $image->delete();
        });

        return redirect()
            ->route('products.edit', $product)
            ->with('success', 'Imagen eliminada correctamente.');
    }

    /**
     * Marcar una imagen como portada del producto.
     */
    public function setCover(Product $product, ProductImage $image)
    {
        if ($image->product_id !== $product->id) {
            abort(403, 'No autorizado');
        }

        DB::transaction(function () use ($product, $image) {
            // Poner todas en is_cover = false
            ProductImage::where('product_id', $product->id)->update(['is_cover' => false]);

            // Marcar esta como portada
            $image->is_cover = true;
            $image->save();
        });

        return back()->with('success', 'Portada actualizada correctamente.');
    }

    /**
     * Reordenar imágenes (sort_order).
     *
     * Espera en el request:
     *  orders[image_id] = sort_order
     */
    public function reorder(Request $request, Product $product)
    {
        $orders = $request->input('orders', []);

        DB::transaction(function () use ($orders, $product) {
            foreach ($orders as $imageId => $order) {
                $img = ProductImage::where('product_id', $product->id)
                    ->where('id', $imageId)
                    ->first();

                if ($img) {
                    $img->sort_order = (int) $order;
                    $img->save();
                }
            }
        });

        return back()->with('success', 'Orden de las imágenes actualizado.');
    }
}
