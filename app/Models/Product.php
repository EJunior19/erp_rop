<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    protected $fillable = [
        'name','brand_id','category_id','supplier_id',
        'price_cash','stock','active','notes','code',
    ];

    protected $casts = [
        'price_cash' => 'integer',
        'active'     => 'boolean',
    ];

    public function brand()    { return $this->belongsTo(\App\Models\Brand::class); }
    public function category() { return $this->belongsTo(\App\Models\Category::class); }
    public function supplier() { return $this->belongsTo(\App\Models\Supplier::class); }

    protected static function booted(): void
    {
        static::created(function (Product $p) {
            if (empty($p->code)) {
                $p->code = sprintf('PRD-%05d', $p->id);
                $p->updateQuietly(['code' => $p->code]);
            }
        });
    }

    public function installments()
    {
        return $this->hasMany(ProductInstallment::class);
    }

    public function images()
    {
        return $this->hasMany(\App\Models\ProductImage::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function coverImage()
    {
        return $this->hasOne(\App\Models\ProductImage::class)
            ->where('is_cover', true)
            ->orderByDesc('id');
    }

    // 🔹 URL lista para usar en catálogo, show, etc.
    public function getCoverUrlAttribute(): ?string
{
    $img = $this->coverImage;
    if (!$img || !$img->path) return null;

    if (preg_match('#^https?://#i', $img->path)) {
        return $img->path;
    }

    $clean = ltrim(preg_replace('#^public/#', '', $img->path), '/');
    return asset('storage/' . $clean);
}

public function getImagesUrlsAttribute(): array
{
    return $this->images->map(function ($img) {
        if (!$img->path) return null;

        if (preg_match('#^https?://#i', $img->path)) {
            return $img->path;
        }

        $clean = ltrim(preg_replace('#^public/#', '', $img->path), '/');
        return asset('storage/' . $clean);
    })->filter()->values()->toArray();
}

}
