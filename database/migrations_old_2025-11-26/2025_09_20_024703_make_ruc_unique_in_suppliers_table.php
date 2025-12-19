<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('ruc')->nullable()->unique()->change();
        });
    }

    public function down() {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropUnique('suppliers_ruc_unique'); // nombre del índice por convención
            $table->string('ruc')->nullable()->change();
        });
    }
};