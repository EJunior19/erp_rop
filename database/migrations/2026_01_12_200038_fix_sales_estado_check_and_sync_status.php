<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {

            // 1) Asegurar que exista la columna "estado" (por si en algún entorno no está)
            DB::statement("
                DO $$
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1
                        FROM information_schema.columns
                        WHERE table_schema = 'public'
                          AND table_name   = 'sales'
                          AND column_name  = 'estado'
                    ) THEN
                        ALTER TABLE public.sales
                          ADD COLUMN estado varchar(255);
                    END IF;
                END $$;
            ");

            // 2) Normalizar datos existentes (acentos / variantes)
            DB::statement("
                UPDATE public.sales
                SET
                  estado = CASE
                    WHEN estado IS NULL THEN estado
                    WHEN estado = 'pendiente_aprobación' THEN 'pendiente_aprobacion'
                    WHEN estado = 'pendiente_aprobacion' THEN 'pendiente_aprobacion'
                    WHEN estado = 'aprobado' THEN 'aprobado'
                    WHEN estado = 'rechazado' THEN 'rechazado'
                    WHEN estado = 'editable' THEN 'editable'
                    WHEN estado = 'cancelado' THEN 'cancelado'
                    WHEN estado = 'pendiente' THEN 'pendiente_aprobacion'
                    ELSE estado
                  END;
            ");

            // Si existe status, también lo normalizamos
            DB::statement("
                DO $$
                BEGIN
                    IF EXISTS (
                        SELECT 1
                        FROM information_schema.columns
                        WHERE table_schema = 'public'
                          AND table_name   = 'sales'
                          AND column_name  = 'status'
                    ) THEN
                        UPDATE public.sales
                        SET
                          status = CASE
                            WHEN status IS NULL THEN status
                            WHEN status = 'pendiente_aprobación' THEN 'pendiente_aprobacion'
                            WHEN status = 'pendiente_aprobacion' THEN 'pendiente_aprobacion'
                            WHEN status = 'aprobado' THEN 'aprobado'
                            WHEN status = 'rechazado' THEN 'rechazado'
                            WHEN status = 'editable' THEN 'editable'
                            WHEN status = 'cancelado' THEN 'cancelado'
                            WHEN status = 'pendiente' THEN 'pendiente_aprobacion'
                            ELSE status
                          END;
                    END IF;
                END $$;
            ");

            // 3) Dropear constraint viejo (si existe)
            DB::statement("
                DO $$
                BEGIN
                    IF EXISTS (
                        SELECT 1
                        FROM pg_constraint
                        WHERE conname = 'sales_estado_chk'
                          AND conrelid = 'public.sales'::regclass
                    ) THEN
                        ALTER TABLE public.sales DROP CONSTRAINT sales_estado_chk;
                    END IF;
                END $$;
            ");

            // 4) Poner default correcto en estado
            DB::statement("
                ALTER TABLE public.sales
                  ALTER COLUMN estado SET DEFAULT 'pendiente_aprobacion';
            ");

            // 5) Crear constraint NUEVO (coincide con tu app)
            DB::statement("
                ALTER TABLE public.sales
                ADD CONSTRAINT sales_estado_chk
                CHECK (
                    estado::text = ANY (
                        ARRAY[
                            'pendiente_aprobacion',
                            'aprobado',
                            'rechazado',
                            'editable',
                            'cancelado'
                        ]::text[]
                    )
                );
            ");

            // 6) Trigger para sincronizar estado <-> status (y normalizar)
            DB::statement("
                CREATE OR REPLACE FUNCTION public.fn_sales_sync_estado_status()
                RETURNS trigger
                LANGUAGE plpgsql
                AS $$
                BEGIN
                    -- Normalizar acentos / variantes
                    IF NEW.estado = 'pendiente_aprobación' THEN
                        NEW.estado := 'pendiente_aprobacion';
                    END IF;
                    IF NEW.status = 'pendiente_aprobación' THEN
                        NEW.status := 'pendiente_aprobacion';
                    END IF;

                    IF NEW.estado = 'pendiente' THEN
                        NEW.estado := 'pendiente_aprobacion';
                    END IF;
                    IF NEW.status = 'pendiente' THEN
                        NEW.status := 'pendiente_aprobacion';
                    END IF;

                    -- Si vino status y no vino estado -> copiar
                    IF NEW.estado IS NULL AND NEW.status IS NOT NULL THEN
                        NEW.estado := NEW.status;
                    END IF;

                    -- Si vino estado y no vino status -> copiar
                    IF NEW.status IS NULL AND NEW.estado IS NOT NULL THEN
                        NEW.status := NEW.estado;
                    END IF;

                    -- Si ninguno vino -> poner default
                    IF NEW.estado IS NULL THEN
                        NEW.estado := 'pendiente_aprobacion';
                    END IF;
                    IF NEW.status IS NULL THEN
                        NEW.status := NEW.estado;
                    END IF;

                    RETURN NEW;
                END;
                $$;
            ");

            DB::statement("
                DO $$
                BEGIN
                    IF EXISTS (
                        SELECT 1 FROM pg_trigger
                        WHERE tgname = 'trg_sales_sync_estado_status'
                          AND tgrelid = 'public.sales'::regclass
                    ) THEN
                        DROP TRIGGER trg_sales_sync_estado_status ON public.sales;
                    END IF;

                    CREATE TRIGGER trg_sales_sync_estado_status
                    BEFORE INSERT OR UPDATE ON public.sales
                    FOR EACH ROW
                    EXECUTE FUNCTION public.fn_sales_sync_estado_status();
                END $$;
            ");
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            DB::statement("
                DO $$
                BEGIN
                    IF EXISTS (
                        SELECT 1 FROM pg_trigger
                        WHERE tgname = 'trg_sales_sync_estado_status'
                          AND tgrelid = 'public.sales'::regclass
                    ) THEN
                        DROP TRIGGER trg_sales_sync_estado_status ON public.sales;
                    END IF;
                END $$;
            ");

            DB::statement("DROP FUNCTION IF EXISTS public.fn_sales_sync_estado_status();");

            DB::statement("
                DO $$
                BEGIN
                    IF EXISTS (
                        SELECT 1
                        FROM pg_constraint
                        WHERE conname = 'sales_estado_chk'
                          AND conrelid = 'public.sales'::regclass
                    ) THEN
                        ALTER TABLE public.sales DROP CONSTRAINT sales_estado_chk;
                    END IF;
                END $$;
            ");

            // No intento restaurar el check viejo con acento porque eso te vuelve a romper inserts.
            DB::statement("ALTER TABLE public.sales ALTER COLUMN estado DROP DEFAULT;");
        });
    }
};
