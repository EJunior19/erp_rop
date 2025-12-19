<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('supplier_phones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('phone_number', 30);
            $table->enum('type', ['principal','secundario','fax','whatsapp'])->default('principal');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            // Un número no se repite para el mismo proveedor
            $table->unique(['supplier_id','phone_number'], 'uniq_supplier_phone');
            $table->index(['supplier_id','type']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('supplier_phones');
    }
};
