<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            //Relacion con clients
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();

            //Campos de la tabla
            $table->enum('modo_pago', ['contado', 'credito'])->default('contado');
            $table->decimal('total', 12, 2);
            $table->enum('estado', ['pendiente','aprobado','rechazado'])->default('pendiente');
            $table->date('fecha')->nullable();
            $table->text('nota')->nullable();
            
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
