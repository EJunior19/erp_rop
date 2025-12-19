<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('purchase_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('ordered_qty')->default(0);   // de la OC
            $table->integer('received_qty')->default(0);  // recibido físico
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->enum('status', ['completo','parcial','faltante'])->default('parcial');
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('purchase_receipt_items');
    }
};

