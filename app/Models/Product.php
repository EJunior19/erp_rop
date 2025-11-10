<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ProductImage;

class Product extends Model
{
    protected $fillable = [
        'name','brand_id','category_id','supplier_id',
        'price_cash','stock','active','notes','code', // code queda por si alguna vez se setea manualmente (no es necesario)
    ];

    protected $casts = [
        'price_cash' => 'integer',
        'active'     => 'boolean',
    ];

    // Relaciones (ajustá los namespaces si difieren)
    public function brand()    { return $this->belongsTo(\App\Models\Brand::class); }
    public function category() { return $this->belongsTo(\App\Models\Category::class); }
    public function supplier() { return $this->belongsTo(\App\Models\Supplier::class); }

    /**
     * Autogenera code tipo PRD-00001 cuando no se envía code.
     * Se hace en "created" para disponer del ID autoincremental.
     */
    protected static function booted(): void
    {
        static::created(function (Product $p) {
            if (empty($p->code)) {
                $p->code = sprintf('PRD-%05d', $p->id);
                // updateQuietly evita eventos infinitos y toca solo la columna
                $p->updateQuietly(['code' => $p->code]);
            }
        });
    }
    // Relación con cuotas
    public function installments()
    {
        return $this->hasMany(ProductInstallment::class);
    }
    /* =========================
     * Relaciones adicionales
     * ========================= */
    public function images() {
    return $this->hasMany(\App\Models\ProductImage::class)
        ->orderBy('sort_order')->orderBy('id');
    }
    public function coverImage() {
        return $this->hasOne(\App\Models\ProductImage::class)->where('is_cover', true);
    }
}
