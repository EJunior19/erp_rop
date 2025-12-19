<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION public.fn_sales_au_estado_recalc()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
DECLARE
    _faltante RECORD;
BEGIN
    -- Solo si realmente cambia el estado/status
    IF OLD.status IS DISTINCT FROM NEW.status THEN

        -------------------------------------------------------------------
        -- 1) Cuando pasa a "aprobado": validar stock y descontar
        -------------------------------------------------------------------
        IF NEW.status = 'aprobado' THEN

            -- 1.1) Validar que haya stock suficiente para TODOS los productos
            SELECT
                si.product_id,
                p.stock,
                SUM(si.qty)::integer AS qty_total
            INTO _faltante
            FROM sale_items si
            JOIN products p ON p.id = si.product_id
            WHERE si.sale_id = NEW.id
              AND si.product_id IS NOT NULL
            GROUP BY si.product_id, p.stock
            HAVING p.stock < SUM(si.qty)
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

            -- 1.2) Descontar stock de todos los productos de la venta
            UPDATE products p
            SET stock = p.stock - s.qty_total
            FROM (
                SELECT
                    si.product_id,
                    SUM(si.qty)::integer AS qty_total
                FROM sale_items si
                WHERE si.sale_id = NEW.id
                  AND si.product_id IS NOT NULL
                GROUP BY si.product_id
            ) AS s
            WHERE p.id = s.product_id;

            -- 1.3) Registrar movimientos de inventario (SALIDA)
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
                si.qty,
                'Venta aprobada: Sale #' || NEW.id,
                NULL,              -- luego podés meter el usuario aprobador
                'sale',
                NEW.id,
                NOW(),
                NOW()
            FROM public.sale_items si
            WHERE si.sale_id = NEW.id
              AND si.product_id IS NOT NULL;

        -------------------------------------------------------------------
        -- 2) Cuando se revierte una venta que estaba aprobada
        --    (cancelado / rechazado / editable / pendiente_aprobacion)
        -------------------------------------------------------------------
        ELSIF OLD.status = 'aprobado'
          AND NEW.status IN ('cancelado', 'rechazado', 'editable', 'pendiente_aprobacion') THEN

            -- 2.1) Devolver stock
            UPDATE products p
            SET stock = p.stock + s.qty_total
            FROM (
                SELECT
                    si.product_id,
                    SUM(si.qty)::integer AS qty_total
                FROM sale_items si
                WHERE si.sale_id = NEW.id
                  AND si.product_id IS NOT NULL
                GROUP BY si.product_id
            ) AS s
            WHERE p.id = s.product_id;

            -- 2.2) Registrar movimientos de inventario (ENTRADA)
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
                si.qty,
                'Venta revertida: Sale #' || NEW.id,
                NULL,
                'sale',
                NEW.id,
                NOW(),
                NOW()
            FROM public.sale_items si
            WHERE si.sale_id = NEW.id
              AND si.product_id IS NOT NULL;

        END IF;

    END IF;

    RETURN NEW;
END;
$function$;
SQL);
    }

    public function down(): void
    {
        // Opcional: versión anterior simple, sin tocar stock
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION public.fn_sales_au_estado_recalc()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
BEGIN
    -- Solo si realmente cambia el estado
    IF OLD.status IS DISTINCT FROM NEW.status THEN

        -- ✅ Cuando pasa a "aprobado" (salida de stock)
        IF NEW.status = 'aprobado' THEN

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
                si.qty,
                'Venta aprobada: Sale #' || NEW.id,
                NULL,
                'sale',
                NEW.id,
                NOW(),
                NOW()
            FROM public.sale_items si
            WHERE si.sale_id = NEW.id;

        -- 🔁 Cuando se revierte una venta que ya estaba aprobada
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
                si.qty,
                'Venta revertida: Sale #' || NEW.id,
                NULL,
                'sale',
                NEW.id,
                NOW(),
                NOW()
            FROM public.sale_items si
            WHERE si.sale_id = NEW.id;

        END IF;

    END IF;

    RETURN NEW;
END;
$function$;
SQL);
    }
};
