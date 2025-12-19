<?php

// database/migrations/xxxx_xx_xx_xxxxxx_add_soft_deletes_to_categories.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('categories', function (Blueprint $table) {
            if (!Schema::hasColumn('categories', 'deleted_at')) {
                $table->softDeletes(); // agrega deleted_at
            }
        });
    }
    public function down(): void {
        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
