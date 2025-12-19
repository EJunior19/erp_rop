<?php

// database/migrations/xxxx_xx_xx_xxxxxx_create_product_images_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('product_images', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->onDelete('cascade');
            $t->string('path');                 // ruta en disk 'public' (storage/app/public/...)
            $t->string('alt')->nullable();      // texto alternativo
            $t->boolean('is_cover')->default(false);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();

            $t->index(['product_id','sort_order']);
        });

        // ✅ Portada única por producto (PostgreSQL: índice parcial)
        DB::statement("
          CREATE UNIQUE INDEX product_images_one_cover_per_product
          ON product_images(product_id)
          WHERE is_cover = true
        ");
    }

    public function down(): void {
        DB::statement("DROP INDEX IF EXISTS product_images_one_cover_per_product");
        Schema::dropIfExists('product_images');
    }
};
