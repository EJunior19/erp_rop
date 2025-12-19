<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('credit_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->string('requirement'); // ej: cedula, ingresos, garante
            $table->boolean('fulfilled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('credit_requirements');
    }
};
