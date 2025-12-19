<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('order_number')->unique();
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('status', ['borrador','enviado','recibido','cerrado'])->default('borrador');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('purchase_orders');
    }
};
