<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use HasFactory;

    protected $table = 'product_images';

    protected $fillable = [
        'product_id',
        'path',
        'alt',
        'is_cover',
        'sort_order',
    ];

    protected $casts = [
        'is_cover'   => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Relación: una imagen pertenece a un producto.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Devuelve la URL pública de la imagen.
     */
    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->path);
    }

    /**
     * Scope: solo imágenes de portada.
     */
    public function scopeCover($query)
    {
        return $query->where('is_cover', true);
    }

    /**
     * Scope: ordenar por prioridad visual.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
