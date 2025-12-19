<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1) Eliminar triggers de sale_items que modifican stock directamente
        |--------------------------------------------------------------------------
        | El stock ya NO se toca desde sale_items.
        | Todo pasa por inventory_movements + fn_apply_inventory_stock
        */
        DB::statement("DROP TRIGGER IF EXISTS trg_sale_items_ai ON public.sale_items;");
        DB::statement("DROP TRIGGER IF EXISTS trg_sale_items_au ON public.sale_items;");
        DB::statement("DROP TRIGGER IF EXISTS trg_sale_items_ad ON public.sale_items;");

        /*
        |--------------------------------------------------------------------------
        | 2) Reemplazar fn_sales_au_estado_recalc (SIN tocar products.stock)
        |--------------------------------------------------------------------------
        */
        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION public.fn_sales_au_estado_recalc()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
DECLARE
    r RECORD;
    v_stock integer;
BEGIN
    -- Solo si cambia el status
    IF OLD.status IS DISTINCT FROM NEW.status THEN

        -------------------------------------------------------------------
        -- 1) APROBAR: validar stock + registrar SALIDA
        -------------------------------------------------------------------
        IF NEW.status = 'aprobado' THEN

            -- Validar stock producto por producto (lock correcto)
            FOR r IN
                SELECT si.product_id, SUM(si.qty)::integer AS qty_total
                FROM public.sale_items si
                WHERE si.sale_id = NEW.id
                  AND si.product_id IS NOT NULL
                GROUP BY si.product_id
            LOOP
                SELECT p.stock
                  INTO v_stock
                  FROM public.products p
                 WHERE p.id = r.product_id
                 FOR UPDATE;

                IF COALESCE(v_stock, 0) < COALESCE(r.qty_total, 0) THEN
                    RAISE EXCEPTION
                        'Stock insuficiente para producto %, stock %, requerido % (venta %)',
                        r.product_id, v_stock, r.qty_total, NEW.id
                    USING ERRCODE = '23514';
                END IF;
            END LOOP;

            -- Registrar movimientos de SALIDA (agrupado por producto)
            INSERT INTO public.inventory_movements (
                product_id,
                type,
                quantity,
                reason,
                user_id,
                ref_type,
                ref_id,
                created_at,
                updated_at
            )
            SELECT
                si.product_id,
                'salida',
                SUM(si.qty)::integer,
                'Venta aprobada: Sale #' || NEW.id,
                NEW.approved_by,
                'sale',
                NEW.id,
                NOW(),
                NOW()
            FROM public.sale_items si
            WHERE si.sale_id = NEW.id
              AND si.product_id IS NOT NULL
            GROUP BY si.product_id
            ON CONFLICT ON CONSTRAINT inv_mov_uniq_sale_ref DO NOTHING;

        -------------------------------------------------------------------
        -- 2) REVERTIR aprobado -> registrar ENTRADA
        -------------------------------------------------------------------
        ELSIF OLD.status = 'aprobado'
          AND NEW.status IN ('cancelado', 'rechazado', 'editable', 'pendiente_aprobacion') THEN

            INSERT INTO public.inventory_movements (
                product_id,
                type,
                quantity,
                reason,
                user_id,
                ref_type,
                ref_id,
                created_at,
                updated_at
            )
            SELECT
                si.product_id,
                'entrada',
                SUM(si.qty)::integer,
                'Venta revertida: Sale #' || NEW.id,
                NEW.approved_by,
                'sale',
                NEW.id,
                NOW(),
                NOW()
            FROM public.sale_items si
            WHERE si.sale_id = NEW.id
              AND si.product_id IS NOT NULL
            GROUP BY si.product_id
            ON CONFLICT ON CONSTRAINT inv_mov_uniq_sale_ref DO NOTHING;

        END IF;
    END IF;

    RETURN NEW;
END;
$function$;
SQL);
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Down
        |--------------------------------------------------------------------------
        | No reactivamos triggers viejos porque eran la causa del bug.
        | Si necesitás rollback completo, se hace manual.
        */
    }
};
