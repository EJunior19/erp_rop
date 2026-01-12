<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            DO $$
            BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM pg_constraint
                    WHERE conname = 'sales_estado_check'
                      AND conrelid = 'public.sales'::regclass
                ) THEN
                    ALTER TABLE public.sales DROP CONSTRAINT sales_estado_check;
                END IF;
            END $$;
        ");
    }

    public function down(): void
    {
        // (Opcional) recrear el check viejo si algún día querés volver atrás
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_constraint
                    WHERE conname = 'sales_estado_check'
                      AND conrelid = 'public.sales'::regclass
                ) THEN
                    ALTER TABLE public.sales
                    ADD CONSTRAINT sales_estado_check
                    CHECK (
                        estado::text = ANY (
                            ARRAY['pendiente','aprobado','rechazado']::text[]
                        )
                    );
                END IF;
            END $$;
        ");
    }
};
