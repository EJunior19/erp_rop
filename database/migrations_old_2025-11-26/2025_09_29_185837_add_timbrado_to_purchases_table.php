<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('timbrado', 20)->nullable()->after('invoice_number');
            $table->date('timbrado_expiration')->nullable()->after('timbrado');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['timbrado', 'timbrado_expiration']);
        });
    }
};

