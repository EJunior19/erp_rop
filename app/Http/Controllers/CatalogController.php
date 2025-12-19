<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;

class CatalogController extends Controller
{
    /**
     * Catálogo tipo ecommerce (responsive).
     */
    public function index(Request $request)
    {
        $search     = trim((string) $request->input('q', ''));
        $categoryId = $request->input('category_id');
        $brandId    = $request->input('brand_id');
        $perPage    = (int) $request->input('per_page', 24);
        if ($perPage <= 0) $perPage = 24;

        $products = Product::query()
            ->with(['coverImage', 'images']) // importante para cover_url e images_urls
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'ilike', "%{$search}%")
                        ->orWhere('code', 'ilike', "%{$search}%");
                });
            })
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($brandId, fn ($q) => $q->where('brand_id', $brandId))
            ->where('active', true)
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $categories = Category::orderBy('name')->get();
        $brands     = Brand::orderBy('name')->get();

        return view('catalog.index', [
            'products'    => $products,
            'categories'  => $categories,
            'brands'      => $brands,
            'q'           => $search,
            'categoryId'  => $categoryId,
            'brandId'     => $brandId,
            'perPage'     => $perPage,
        ]);
    }
}
