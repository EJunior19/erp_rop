<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {

            // 1) Normalizar datos existentes
            //    - si estado está null/vacío, lo rellenamos desde status
            //    - si status trae "pendiente_aprobacion" lo pasamos a "pendiente_aprobación"
            DB::statement("
                UPDATE sales
                SET estado = CASE
                    WHEN status = 'pendiente_aprobacion' THEN 'pendiente_aprobación'
                    WHEN status = 'pendiente' THEN 'pendiente_aprobación'
                    WHEN status = 'aprobado' THEN 'aprobado'
                    WHEN status = 'rechazado' THEN 'rechazado'
                    WHEN status = 'editable' THEN 'editable'
                    WHEN status = 'cancelado' THEN 'cancelado'
                    ELSE COALESCE(NULLIF(estado,''), 'pendiente_aprobación')
                END
                WHERE (estado IS NULL OR estado = '')
            ");

            // 2) Asegurar default en estado (por si inserts no lo envían)
            DB::statement("
                ALTER TABLE sales
                ALTER COLUMN estado SET DEFAULT 'pendiente_aprobación'
            ");

            // 3) Si querés evitar inconsistencias: forzar que estado nunca sea null
            //    (solo si estás seguro que ya no hay nulos)
            // DB::statement("ALTER TABLE sales ALTER COLUMN estado SET NOT NULL");

            // 4) Trigger para sincronizar status <-> estado (compatibilidad)
            //    - si tu código sigue usando status, lo mantenemos alineado
            DB::statement("
                CREATE OR REPLACE FUNCTION fn_sales_sync_estado_status()
                RETURNS trigger AS $$
                BEGIN
                    -- Si viene status pero no estado, derivar estado
                    IF (NEW.estado IS NULL OR NEW.estado = '') AND NEW.status IS NOT NULL THEN
                        NEW.estado := CASE
                            WHEN NEW.status = 'pendiente_aprobacion' THEN 'pendiente_aprobación'
                            WHEN NEW.status = 'pendiente' THEN 'pendiente_aprobación'
                            ELSE NEW.status
                        END;
                    END IF;

                    -- Si viene estado pero no status, derivar status (sin acento)
                    IF (NEW.status IS NULL OR NEW.status = '') AND NEW.estado IS NOT NULL THEN
                        NEW.status := CASE
                            WHEN NEW.estado = 'pendiente_aprobación' THEN 'pendiente_aprobacion'
                            ELSE NEW.estado
                        END;
                    END IF;

                    -- Si vienen ambos pero no coinciden, hacemos que estado gane (por el CHECK)
                    IF NEW.estado IS NOT NULL THEN
                        NEW.status := CASE
                            WHEN NEW.estado = 'pendiente_aprobación' THEN 'pendiente_aprobacion'
                            ELSE NEW.estado
                        END;
                    END IF;

                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;
            ");

            // Drop trigger si ya existe (por si corrés la migración en DB sucia)
            DB::statement("DROP TRIGGER IF EXISTS trg_sales_sync_estado_status ON sales;");

            DB::statement("
                CREATE TRIGGER trg_sales_sync_estado_status
                BEFORE INSERT OR UPDATE ON sales
                FOR EACH ROW
                EXECUTE FUNCTION fn_sales_sync_estado_status();
            ");
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            DB::statement("DROP TRIGGER IF EXISTS trg_sales_sync_estado_status ON sales;");
            DB::statement("DROP FUNCTION IF EXISTS fn_sales_sync_estado_status();");

            // revert default (opcional)
            // DB::statement(\"ALTER TABLE sales ALTER COLUMN estado DROP DEFAULT\");
        });
    }
};
