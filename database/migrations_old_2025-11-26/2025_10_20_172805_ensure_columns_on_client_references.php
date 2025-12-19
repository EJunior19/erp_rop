<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Añadir columnas que falten
        Schema::table('client_references', function (Blueprint $table) {
            if (!Schema::hasColumn('client_references', 'relationship')) {
                $table->string('relationship', 100)->nullable();
            }
            if (!Schema::hasColumn('client_references', 'email')) {
                $table->string('email')->nullable();
            }
            if (!Schema::hasColumn('client_references', 'address')) {
                $table->string('address')->nullable();
            }
            if (!Schema::hasColumn('client_references', 'note')) {
                $table->string('note')->nullable();
            }
            if (!Schema::hasColumn('client_references', 'client_id')) {
                // Si no existiera por alguna razón:
                $table->foreignId('client_id')->constrained()->onDelete('cascade');
            }
        });

        // Asegurar la FK en PostgreSQL si falta
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM   pg_constraint c
                    JOIN   pg_class t ON c.conrelid = t.oid
                    WHERE  t.relname = 'client_references'
                    AND    c.conname = 'client_references_client_id_foreign'
                ) THEN
                    ALTER TABLE client_references
                    ADD CONSTRAINT client_references_client_id_foreign
                    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE;
                END IF;
            END$$;
        ");
    }

    public function down(): void
    {
        // No eliminamos columnas por seguridad
    }
};
