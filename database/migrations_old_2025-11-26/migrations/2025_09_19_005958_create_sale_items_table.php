<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('sale_items', function (Blueprint $t) {
            $t->id();

            $t->foreignId('sale_id')
              ->constrained('sales')
              ->cascadeOnDelete()
              ->index();

            $t->string('product_code', 100)->index();
            $t->string('product_name')->nullable();

            $t->decimal('unit_price', 14, 2);
            $t->integer('qty');

            // tipo de IVA: '10', '5', 'exento'
            $t->string('iva_type', 10);

            $t->decimal('line_total', 14, 2);

            $t->timestamps();
        });

        // CHECK constraint para iva_type
        DB::statement("
            ALTER TABLE sale_items
            ADD CONSTRAINT sale_items_iva_type_check
            CHECK (iva_type IN ('10','5','exento'))
        ");
    }

    public function down(): void {
        // eliminar constraint antes de dropear la tabla
        try {
            DB::statement("ALTER TABLE sale_items DROP CONSTRAINT IF EXISTS sale_items_iva_type_check");
        } catch (\Throwable $e) {
            // en MySQL no rompe aunque no exista
        }

        Schema::dropIfExists('sale_items');
    }
};
