<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Código AUTOGENERADO: lo dejamos nullable para poder crearlo y
            // actualizarlo post-insert con el ID (ver modelo Product).
            $table->string('code')->nullable()->unique();

            $table->string('name');                   // Nombre del producto

            // Relaciones OBLIGATORIAS (producto debe pertenecer a las 3)
            $table->foreignId('brand_id')->constrained('brands')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();

            $table->decimal('price_cash', 12, 2)->nullable(); // Precio contado (opcional al inicio)
            $table->integer('stock')->default(0);             // Stock actual
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('products'); }
};
