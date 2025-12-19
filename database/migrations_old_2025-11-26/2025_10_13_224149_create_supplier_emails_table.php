<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('supplier_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('email', 150);
            $table->enum('type', ['general','ventas','compras','facturacion'])->default('general');
            $table->boolean('is_default')->default(false); // “principal”
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // evitar duplicar el mismo email para el mismo proveedor
            $table->unique(['supplier_id','email'], 'uniq_supplier_email');
            $table->index(['supplier_id','type']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('supplier_emails');
    }
};
