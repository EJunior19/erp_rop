<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
/* =========================================================
   A) STOCK: aplicar stock cuando se inserta inventory_movements
   ========================================================= */

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_trigger
    WHERE tgname = 'trg_inv_apply_stock'
  ) THEN
    CREATE TRIGGER trg_inv_apply_stock
    AFTER INSERT OR UPDATE ON public.inventory_movements
    FOR EACH ROW
    EXECUTE FUNCTION public.fn_apply_inventory_stock();
  END IF;
END $$;


/* =========================================================
   B) VENTAS: evitar duplicados de movimientos por venta
      (opcional pero recomendado para no repetir salidas/entradas)
   ========================================================= */

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint
    WHERE conname = 'inv_mov_uniq_sale_ref'
  ) THEN
    ALTER TABLE public.inventory_movements
      ADD CONSTRAINT inv_mov_uniq_sale_ref UNIQUE (ref_type, ref_id, product_id, type);
  END IF;
END $$;


/* =========================================================
   C) CREDITS: guardián para que NO nazcan "pagado"
      - status -> pendiente
      - balance -> amount
   ========================================================= */

CREATE OR REPLACE FUNCTION public.fn_credit_defaults_guard()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
  -- status seguro
  IF NEW.status IS NULL OR NEW.status NOT IN ('pendiente','pagado','vencido') THEN
    NEW.status := 'pendiente';
  END IF;

  -- si viene mal seteado, forzamos "pendiente" y balance correcto
  IF NEW.status = 'pagado' THEN
    NEW.status := 'pendiente';
  END IF;

  -- balance seguro
  IF NEW.balance IS NULL OR NEW.balance <= 0 THEN
    NEW.balance := NEW.amount;
  END IF;

  -- notify cada 7 por defecto
  IF NEW.notify_every_days IS NULL OR NEW.notify_every_days <= 0 THEN
    NEW.notify_every_days := 7;
  END IF;

  RETURN NEW;
END $$;

DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname='trg_credits_defaults_guard') THEN
    CREATE TRIGGER trg_credits_defaults_guard
    BEFORE INSERT OR UPDATE ON public.credits
    FOR EACH ROW
    EXECUTE FUNCTION public.fn_credit_defaults_guard();
  END IF;
END $$;


/* =========================================================
   D) SALES: campos para guardar plan de crédito (no rompe nada)
   ========================================================= */

ALTER TABLE public.sales
  ADD COLUMN IF NOT EXISTS credit_installments smallint,
  ADD COLUMN IF NOT EXISTS credit_first_due_date date,
  ADD COLUMN IF NOT EXISTS credit_every_days smallint,
  ADD COLUMN IF NOT EXISTS credit_down_payment numeric(14,2);

-- defaults suaves (no cambia registros viejos)
UPDATE public.sales
SET
  credit_every_days = COALESCE(credit_every_days, 30),
  credit_down_payment = COALESCE(credit_down_payment, 0)
WHERE credit_every_days IS NULL OR credit_down_payment IS NULL;


/* =========================================================
   E) GENERAR CUOTAS al aprobar venta crédito
      - usa sales.credit_installments / first_due / every_days / down_payment
      - si ya existen credits para la venta, NO hace nada
      - crea N filas en credits con status=pendiente y balance=amount
   ========================================================= */

CREATE OR REPLACE FUNCTION public.fn_sales_generate_credits_on_approve()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
  v_n smallint;
  v_first date;
  v_every smallint;
  v_down numeric(14,2);
  v_total numeric(14,2);
  v_fin numeric(14,2);
  v_per numeric(14,2);
  v_last numeric(14,2);
  i int;
  v_due date;
BEGIN
  -- solo cuando pasa a aprobado
  IF (OLD.status IS DISTINCT FROM NEW.status)
     AND NEW.status = 'aprobado'
     AND NEW.modo_pago = 'credito' THEN

    -- si ya hay cuotas para esa venta, no duplicamos
    IF EXISTS (SELECT 1 FROM public.credits c WHERE c.sale_id = NEW.id) THEN
      RETURN NEW;
    END IF;

    v_n     := COALESCE(NEW.credit_installments, 3);
    v_first := COALESCE(NEW.credit_first_due_date, (CURRENT_DATE + INTERVAL '30 days')::date);
    v_every := COALESCE(NEW.credit_every_days, 30);
    v_down  := COALESCE(NEW.credit_down_payment, 0);
    v_total := COALESCE(NEW.total, 0);

    v_fin := GREATEST(0, v_total - v_down);

    -- reparto prolijo (última cuota ajusta diferencia)
    IF v_n < 1 THEN v_n := 1; END IF;
    v_per  := trunc((v_fin / v_n) * 100) / 100;
    v_last := v_fin - (v_per * (v_n - 1));

    FOR i IN 1..v_n LOOP
      v_due := (v_first + ((i-1) * v_every));
      INSERT INTO public.credits (
        sale_id, client_id, amount, balance, due_date, status,
        notify_every_days, created_at, updated_at
      )
      VALUES (
        NEW.id,
        NEW.client_id,
        CASE WHEN i = v_n THEN v_last ELSE v_per END,
        CASE WHEN i = v_n THEN v_last ELSE v_per END,
        v_due,
        'pendiente',
        7,
        NOW(),
        NOW()
      );
    END LOOP;

  END IF;

  RETURN NEW;
END $$;

DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname='trg_sales_generate_credits_on_approve') THEN
    CREATE TRIGGER trg_sales_generate_credits_on_approve
    AFTER UPDATE OF status ON public.sales
    FOR EACH ROW
    WHEN ((OLD.status IS DISTINCT FROM NEW.status) AND (NEW.status = 'aprobado'))
    EXECUTE FUNCTION public.fn_sales_generate_credits_on_approve();
  END IF;
END $$;


/* =========================================================
   F) ACTUALIZAR tu fn_sales_au_estado_recalc para NO duplicar movimientos
      (solo agrego ON CONFLICT para tu constraint nueva)
   ========================================================= */

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
                NULL,
                'sale',
                NEW.id,
                NOW(),
                NOW()
            FROM public.sale_items si
            WHERE si.sale_id = NEW.id
              AND si.product_id IS NOT NULL
            ON CONFLICT ON CONSTRAINT inv_mov_uniq_sale_ref DO NOTHING;

        -------------------------------------------------------------------
        -- 2) Revertir venta aprobada -> devolver stock + movimiento entrada
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

            -- 2.2) Movimiento entrada
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
              AND si.product_id IS NOT NULL
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
        // Si querés, después armamos el rollback fino.
    }
};
