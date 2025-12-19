<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->integer('qty');
            $table->decimal('cost', 12, 2)->default(0);
            $table->timestamps();
        });

        // Constraint para que qty siempre sea > 0
        DB::statement("
            ALTER TABLE purchase_items
            ADD CONSTRAINT purchase_items_qty_check
            CHECK (qty > 0)
        ");
    }

    public function down(): void
    {
        try {
            DB::statement("ALTER TABLE purchase_items DROP CONSTRAINT IF EXISTS purchase_items_qty_check");
        } catch (\Throwable $e) {
            // En MySQL ignora si no existe
        }

        Schema::dropIfExists('purchase_items');
    }
};
