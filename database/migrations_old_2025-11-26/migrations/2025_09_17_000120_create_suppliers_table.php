<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();                             // PK
            $table->string('name');                   // Nombre del proveedor
            $table->string('ruc')->nullable();        // RUC (opcional)
            $table->string('phone')->nullable();      // Teléfono
            $table->string('email')->unique()->nullable();      // Email
            $table->string('address')->nullable();    // Dirección
            $table->boolean('active')->default(true); // Activo / Inactivo
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('suppliers'); }
};
