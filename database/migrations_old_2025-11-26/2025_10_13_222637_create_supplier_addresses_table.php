<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('supplier_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('street');               // Calle / dirección
            $table->string('city');
            $table->string('state')->nullable();
            $table->string('country')->default('Paraguay');
            $table->string('postal_code')->nullable();
            $table->enum('type', ['fiscal','entrega','sucursal'])->default('fiscal');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['supplier_id','type']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('supplier_addresses');
    }
};
