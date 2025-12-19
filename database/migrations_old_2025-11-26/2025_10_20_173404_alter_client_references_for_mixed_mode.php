<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_references', function (Blueprint $table) {
            if (!Schema::hasColumn('client_references','referenced_client_id')) {
                $table->foreignId('referenced_client_id')->nullable()->constrained('clients')->nullOnDelete();
            }
        });

        // CHECK: o referenced_client_id, o name+phone
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint
                    WHERE conname = 'client_references_xor_check'
                ) THEN
                    ALTER TABLE client_references
                    ADD CONSTRAINT client_references_xor_check
                    CHECK (
                        (referenced_client_id IS NOT NULL AND name IS NULL AND phone IS NULL)
                        OR
                        (referenced_client_id IS NULL AND name IS NOT NULL AND phone IS NOT NULL)
                    );
                END IF;
            END$$;
        ");

        // Evitar duplicados cliente→referencia cuando es cliente existente
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint
                    WHERE conname = 'uniq_client_refclient'
                ) THEN
                    ALTER TABLE client_references
                    ADD CONSTRAINT uniq_client_refclient
                    UNIQUE (client_id, referenced_client_id);
                END IF;
            END$$;
        ");
    }

    public function down(): void
    {
        // No eliminamos nada para no perder integridad ni datos
    }
};
