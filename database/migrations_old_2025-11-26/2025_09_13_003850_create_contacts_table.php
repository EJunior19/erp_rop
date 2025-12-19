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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('restrict');
            $table->string('name', 100); 
            $table->string('email', 100)->nullable()->unique();
            $table->string('phone', 20)->nullable();
            $table->string('position', 100)->nullable();
            $table->text('notes')->nullable();
            $table->tinyInteger('active')->default(1); // 1=active, 0=inactive
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
