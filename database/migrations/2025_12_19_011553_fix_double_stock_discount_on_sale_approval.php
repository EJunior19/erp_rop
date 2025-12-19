<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Reemplazar la función fn_sales_au_estado_recalc()
        //    para que NO actualice products.stock.
        //    Solo valida stock y registra movimientos (inventory_movements).
        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION public.fn_sales_au_estado_recalc()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
DECLARE
    _faltante RECORD;
BEGIN
    IF OLD.status IS DISTINCT FROM NEW.status THEN

        -------------------------------------------------------------------
        -- 1) APROBAR: validar stock + registrar movimientos (SALIDA)
        -------------------------------------------------------------------
        IF NEW.status = 'aprobado' THEN

            -- Validar stock (lock real sobre products)
            SELECT
                s.product_id,
                p.stock,
                s.qty_total
            INTO _faltante
            FROM (
                SELECT
                    si.product_id,
                    SUM(si.qty)::integer AS qty_total
                FROM sale_items si
                WHERE si.sale_id = NEW.id
                  AND si.product_id IS NOT NULL
                GROUP BY si.product_id
            ) s
            JOIN products p ON p.id = s.product_id
            WHERE COALESCE(p.stock, 0) < COALESCE(s.qty_total, 0)
            FOR UPDATE
            LIMIT 1;

            IF FOUND THEN
                RAISE EXCEPTION
                    'Stock insuficiente para producto %, stock %, requerido % (venta %)',
                    _faltante.product_id,
                    _faltante.stock,
                    _faltante.qty_total,
                    NEW.id
                USING ERRCODE = '23514';
            END IF;

            -- Registrar movimientos de SALIDA (una fila por producto)
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
        -- 2) REVERTIR aprobado → registrar ENTRADA
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
        // Down: no reponemos la versión vieja porque era la que bugueaba (doble descuento).
        // Si querés revertir, acá podrías pegar tu función anterior.
    }
};
