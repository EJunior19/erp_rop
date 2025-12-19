<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        /* ===============================
         * 1) Columnas que pueden faltar
         * =============================== */
        Schema::table('suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('suppliers', 'code')) {
                $table->string('code', 20)->nullable()->after('id');
            }
            if (!Schema::hasColumn('suppliers', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        /* ===============================
         * 2) Índices únicos “robustos”
         * =============================== */
        DB::unprepared(<<<'SQL'
        -- Nombre único, insensible a mayúsculas/minúsculas
        CREATE UNIQUE INDEX IF NOT EXISTS uq_suppliers_name_ci
        ON public.suppliers (lower(name));

        -- RUC único SOLO si no es NULL (permite múltiples NULL)
        CREATE UNIQUE INDEX IF NOT EXISTS uq_suppliers_ruc_notnull
        ON public.suppliers (ruc)
        WHERE ruc IS NOT NULL;

        -- Code único (si decidís no permitir duplicados de code)
        CREATE UNIQUE INDEX IF NOT EXISTS uq_suppliers_code
        ON public.suppliers (code)
        WHERE code IS NOT NULL;
        SQL);

        /* ========================================
         * 3) Limpieza: tirar triggers/funciones viejas
         * ======================================== */
        DB::unprepared(<<<'SQL'
        DROP TRIGGER IF EXISTS trg_suppliers_biu_validate ON public.suppliers;
        DROP FUNCTION IF EXISTS fn_suppliers_biu_validate();

        DROP TRIGGER IF EXISTS trg_suppliers_set_code ON public.suppliers;
        DROP FUNCTION IF EXISTS fn_suppliers_set_code();

        DROP TRIGGER IF EXISTS trg_suppliers_bd_guard ON public.suppliers;
        DROP FUNCTION IF EXISTS fn_suppliers_bd_guard();
        SQL);

        /* ========================================
         * 4) BEFORE INSERT/UPDATE: validaciones
         *    - name: único (CI), ignorando soft-deletes
         *    - ruc: único si no es NULL, ignorando soft-deletes
         * ======================================== */
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_suppliers_biu_validate()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        BEGIN
            -- NAME obligatorio
            IF NEW.name IS NULL OR btrim(NEW.name) = '' THEN
                RAISE EXCEPTION 'El nombre del proveedor es obligatorio';
            END IF;

            -- Unicidad NAME (case-insensitive), ignorando soft-deletes
            IF TG_OP = 'INSERT' THEN
                IF EXISTS (
                    SELECT 1 FROM public.suppliers s
                    WHERE lower(s.name) = lower(NEW.name)
                      AND s.deleted_at IS NULL
                ) THEN
                    RAISE EXCEPTION 'Ya existe un proveedor con ese nombre';
                END IF;
            ELSE
                IF NEW.name IS DISTINCT FROM OLD.name AND EXISTS (
                    SELECT 1 FROM public.suppliers s
                    WHERE lower(s.name) = lower(NEW.name)
                      AND s.id <> OLD.id
                      AND s.deleted_at IS NULL
                ) THEN
                    RAISE EXCEPTION 'Ya existe un proveedor con ese nombre';
                END IF;
            END IF;

            -- Unicidad RUC (si no es NULL), ignorando soft-deletes
            IF NEW.ruc IS NOT NULL AND btrim(NEW.ruc) <> '' THEN
                IF TG_OP = 'INSERT' THEN
                    IF EXISTS (
                        SELECT 1 FROM public.suppliers s
                        WHERE s.ruc = NEW.ruc
                          AND s.deleted_at IS NULL
                    ) THEN
                        RAISE EXCEPTION 'El RUC ya está registrado en proveedores';
                    END IF;
                ELSE
                    IF NEW.ruc IS DISTINCT FROM OLD.ruc AND EXISTS (
                        SELECT 1 FROM public.suppliers s
                        WHERE s.ruc = NEW.ruc
                          AND s.id <> OLD.id
                          AND s.deleted_at IS NULL
                    ) THEN
                        RAISE EXCEPTION 'El RUC ya está registrado en proveedores';
                    END IF;
                END IF;
            END IF;

            RETURN NEW;
        END;
        $$;

        CREATE TRIGGER trg_suppliers_biu_validate
        BEFORE INSERT OR UPDATE ON public.suppliers
        FOR EACH ROW
        EXECUTE FUNCTION fn_suppliers_biu_validate();
        SQL);

        /* ========================================
         * 5) AFTER INSERT: generar CODE si vino vacío
         *    formatea SUP-00001 usando el ID real
         * ======================================== */
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_suppliers_set_code()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        BEGIN
            IF NEW.code IS NULL OR btrim(NEW.code) = '' THEN
                UPDATE public.suppliers
                   SET code = 'SUP-' || lpad(NEW.id::text, 5, '0')
                 WHERE id = NEW.id;
            END IF;
            RETURN NULL; -- AFTER trigger
        END;
        $$;

        CREATE TRIGGER trg_suppliers_set_code
        AFTER INSERT ON public.suppliers
        FOR EACH ROW
        EXECUTE FUNCTION fn_suppliers_set_code();
        SQL);

        /* =======================================================
         * 6) BEFORE DELETE (opcional pero recomendado):
         *    impedir borrado físico si tiene compras
         *    (tu app debería usar SoftDeletes; esto es cinturón y tirantes)
         * ======================================================= */
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_suppliers_bd_guard()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        BEGIN
            IF EXISTS (
                SELECT 1 FROM public.purchases p
                WHERE p.supplier_id = OLD.id
            ) THEN
                RAISE EXCEPTION 'No se puede eliminar un proveedor con compras registradas. Use borrado lógico (soft delete).';
            END IF;
            RETURN OLD;
        END;
        $$;

        CREATE TRIGGER trg_suppliers_bd_guard
        BEFORE DELETE ON public.suppliers
        FOR EACH ROW
        EXECUTE FUNCTION fn_suppliers_bd_guard();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DROP TRIGGER IF EXISTS trg_suppliers_biu_validate ON public.suppliers;
        DROP FUNCTION IF EXISTS fn_suppliers_biu_validate();

        DROP TRIGGER IF EXISTS trg_suppliers_set_code ON public.suppliers;
        DROP FUNCTION IF EXISTS fn_suppliers_set_code();

        DROP TRIGGER IF EXISTS trg_suppliers_bd_guard ON public.suppliers;
        DROP FUNCTION IF EXISTS fn_suppliers_bd_guard();

        -- Índices (dejarlos no molesta; descomenta si querés bajarlos)
        -- DROP INDEX IF EXISTS uq_suppliers_name_ci;
        -- DROP INDEX IF EXISTS uq_suppliers_ruc_notnull;
        -- DROP INDEX IF EXISTS uq_suppliers_code;
        SQL);
        // Nota: no quitamos code/deleted_at en down() para no perder datos
    }
};
