<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 🧩 Actualiza funciones de triggers con "status"
        DB::unprepared(<<<'SQL'
        -- BEFORE DELETE: restaura stock si se borra una venta aprobada
        CREATE OR REPLACE FUNCTION public.fn_sales_bd_adjust()
        RETURNS trigger
        LANGUAGE plpgsql
        AS $$
        BEGIN
          IF OLD.status = 'aprobado' THEN
            UPDATE products p
               SET stock = p.stock + i.qty
              FROM sale_items i
             WHERE i.product_id = p.id
               AND i.sale_id = OLD.id;
          END IF;
          RETURN OLD;
        END;
        $$;
        SQL);

        DB::unprepared(<<<'SQL'
        -- AFTER UPDATE: ajusta stock e inserta movimientos según el cambio de estado
        CREATE OR REPLACE FUNCTION public.fn_sales_au_estado_recalc()
        RETURNS trigger
        LANGUAGE plpgsql
        AS $$
        BEGIN
          IF OLD.status IS DISTINCT FROM NEW.status THEN

            -- ✅ Cuando pasa a "aprobado"
            IF NEW.status = 'aprobado' THEN
              UPDATE products p
                 SET stock = p.stock - si.qty
                FROM sale_items si
               WHERE si.sale_id = NEW.id
                 AND si.product_id = p.id;

              INSERT INTO inventory_movements (ref_type, ref_id, product_id, type, qty, note, created_at)
              SELECT 'sale', NEW.id, si.product_id, 'salida', si.qty, 'Venta aprobada', now()
                FROM sale_items si
               WHERE si.sale_id = NEW.id;

            -- 🔁 Cuando se cancela o revierte una venta aprobada
            ELSIF OLD.status = 'aprobado'
              AND NEW.status IN ('cancelado','rechazado','editable','pendiente_aprobacion') THEN

              UPDATE products p
                 SET stock = p.stock + si.qty
                FROM sale_items si
               WHERE si.sale_id = NEW.id
                 AND si.product_id = p.id;

              INSERT INTO inventory_movements (ref_type, ref_id, product_id, type, qty, note, created_at)
              SELECT 'sale', NEW.id, si.product_id, 'entrada', si.qty, 'Venta revertida', now()
                FROM sale_items si
               WHERE si.sale_id = NEW.id;
            END IF;

          END IF;

          RETURN NEW;
        END;
        $$;
        SQL);

        // 🔄 Reemplaza el trigger antiguo si existía
        DB::unprepared(<<<'SQL'
        DROP TRIGGER IF EXISTS trg_sales_au_estado ON public.sales;

        CREATE TRIGGER trg_sales_au_estado
        AFTER UPDATE OF status ON public.sales
        FOR EACH ROW
        EXECUTE FUNCTION public.fn_sales_au_estado_recalc();
        SQL);

        DB::unprepared(<<<'SQL'
        DROP TRIGGER IF EXISTS trg_sales_bd_adjust ON public.sales;

        CREATE TRIGGER trg_sales_bd_adjust
        BEFORE DELETE ON public.sales
        FOR EACH ROW
        EXECUTE FUNCTION public.fn_sales_bd_adjust();
        SQL);
    }

    public function down(): void
    {
        // 🔙 Opción para revertir si fuera necesario
        DB::unprepared(<<<'SQL'
        DROP TRIGGER IF EXISTS trg_sales_au_estado ON public.sales;
        DROP TRIGGER IF EXISTS trg_sales_bd_adjust ON public.sales;
        DROP FUNCTION IF EXISTS public.fn_sales_au_estado_recalc();
        DROP FUNCTION IF EXISTS public.fn_sales_bd_adjust();
        SQL);
    }
};
