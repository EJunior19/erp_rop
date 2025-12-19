<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ⛳ Si ya existe, no hagas nada
        if (Schema::hasTable('client_references')) {
            return;
        }

        Schema::create('client_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('relationship')->nullable();
            $table->string('phone', 50);
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_references');
    }
};
