<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION public.fn_apply_inventory_stock()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    v_old_qty integer;
    v_new_qty integer;
    v_delta   integer;
BEGIN
    -- Normalizar cantidades
    v_new_qty := COALESCE(NEW.quantity, 0);

    IF TG_OP = 'INSERT' THEN

        IF NEW.type = 'entrada' THEN
            UPDATE public.products
               SET stock = stock + v_new_qty
             WHERE id = NEW.product_id;

        ELSIF NEW.type = 'salida' THEN
            UPDATE public.products
               SET stock = stock - v_new_qty
             WHERE id = NEW.product_id;
        END IF;

        RETURN NEW;

    ELSIF TG_OP = 'UPDATE' THEN
        v_old_qty := COALESCE(OLD.quantity, 0);

        -- Si cambió el producto o el tipo, revertimos el anterior y aplicamos el nuevo
        IF NEW.product_id <> OLD.product_id OR NEW.type <> OLD.type THEN

            -- Revertir OLD
            IF OLD.type = 'entrada' THEN
                UPDATE public.products SET stock = stock - v_old_qty WHERE id = OLD.product_id;
            ELSIF OLD.type = 'salida' THEN
                UPDATE public.products SET stock = stock + v_old_qty WHERE id = OLD.product_id;
            END IF;

            -- Aplicar NEW
            IF NEW.type = 'entrada' THEN
                UPDATE public.products SET stock = stock + v_new_qty WHERE id = NEW.product_id;
            ELSIF NEW.type = 'salida' THEN
                UPDATE public.products SET stock = stock - v_new_qty WHERE id = NEW.product_id;
            END IF;

            RETURN NEW;
        END IF;

        -- Misma fila (mismo product_id y type): ajustar por diferencia
        v_delta := v_new_qty - v_old_qty;

        IF v_delta <> 0 THEN
            IF NEW.type = 'entrada' THEN
                UPDATE public.products SET stock = stock + v_delta WHERE id = NEW.product_id;
            ELSIF NEW.type = 'salida' THEN
                UPDATE public.products SET stock = stock - v_delta WHERE id = NEW.product_id;
            END IF;
        END IF;

        RETURN NEW;

    ELSIF TG_OP = 'DELETE' THEN
        v_old_qty := COALESCE(OLD.quantity, 0);

        -- Revertir movimiento eliminado
        IF OLD.type = 'entrada' THEN
            UPDATE public.products SET stock = stock - v_old_qty WHERE id = OLD.product_id;
        ELSIF OLD.type = 'salida' THEN
            UPDATE public.products SET stock = stock + v_old_qty WHERE id = OLD.product_id;
        END IF;

        RETURN OLD;
    END IF;

    RETURN NEW;
END;
$$;
SQL);
    }

    public function down(): void
    {
        // (Opcional) volver a la versión vieja que usaba NEW.qty
        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION public.fn_apply_inventory_stock()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    v_delta integer;
BEGIN
    -- Versión anterior (compatibilidad): NEW.qty
    v_delta := COALESCE(NEW.qty, 0);

    IF TG_OP = 'INSERT' THEN
        IF NEW.type = 'entrada' THEN
            UPDATE public.products SET stock = stock + v_delta WHERE id = NEW.product_id;
        ELSIF NEW.type = 'salida' THEN
            UPDATE public.products SET stock = stock - v_delta WHERE id = NEW.product_id;
        END IF;
    END IF;

    RETURN NEW;
END;
$$;
SQL);
    }
};
