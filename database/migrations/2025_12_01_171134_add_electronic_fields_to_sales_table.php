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
        Schema::table('sales', function (Blueprint $table) {
        $table->unsignedBigInteger('de_id')->nullable()->after('id');          // ID del documento en FACKATUETE
        $table->string('de_cdc', 44)->nullable()->after('de_id');              // CDC
        $table->string('de_status', 30)->default('none')->after('de_cdc');     // none, pendiente, firmado, error, etc.
        $table->text('de_last_error')->nullable()->after('de_status');         // último error si falla la emisión
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            //
        });
    }
};
