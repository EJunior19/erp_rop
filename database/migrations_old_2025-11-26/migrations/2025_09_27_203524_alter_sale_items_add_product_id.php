<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('sale_items', function (Blueprint $table) {
            // 🔹 Agregar columna product_id después de sale_id
            if (!Schema::hasColumn('sale_items', 'product_id')) {
                $table->foreignId('product_id')
                      ->nullable() // lo dejamos nullable por si ya hay filas existentes
                      ->constrained('products')
                      ->cascadeOnDelete()
                      ->after('sale_id');
            }

            // 🔹 Eliminar product_code si ya no lo querés
            if (Schema::hasColumn('sale_items', 'product_code')) {
                $table->dropColumn('product_code');
            }
        });
    }

    public function down(): void {
        Schema::table('sale_items', function (Blueprint $table) {
            // Revertir cambios
            if (Schema::hasColumn('sale_items', 'product_id')) {
                $table->dropConstrainedForeignId('product_id');
            }

            if (!Schema::hasColumn('sale_items', 'product_code')) {
                $table->string('product_code', 100)->nullable();
            }
        });
    }
};
