<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'

/* ============================================================
 *  A) EVITAR que se creen "payments" automáticos (NO pagado)
 *     - La tabla payments debe representar PAGOS REALES.
 *     - Si se insertan pagos al crear cuotas, quedan "Pagadas".
 * ============================================================ */

CREATE OR REPLACE FUNCTION public.fn_credit_generate_initial_payment()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
BEGIN
  -- ✅ Antes insertaba en payments y marcaba como pagado.
  -- ✅ Ahora NO hacemos nada. Los pagos se registran cuando el cliente paga.
  RETURN NEW;
END;
$function$;

/* ------------------------------------------------------------
 *  A.1) Borrar cualquier trigger en credits que llame esa función
 *       (no asumimos el nombre del trigger; lo detectamos y lo dropeamos)
 * ------------------------------------------------------------ */
DO $$
DECLARE r RECORD;
BEGIN
  FOR r IN
    SELECT t.tgname AS trigger_name
    FROM pg_trigger t
    JOIN pg_class c ON c.oid = t.tgrelid
    JOIN pg_proc  p ON p.oid = t.tgfoid
    JOIN pg_namespace n ON n.oid = c.relnamespace
    WHERE n.nspname = 'public'
      AND c.relname = 'credits'
      AND NOT t.tgisinternal
      AND p.proname = 'fn_credit_generate_initial_payment'
  LOOP
    EXECUTE format('DROP TRIGGER IF EXISTS %I ON public.credits;', r.trigger_name);
  END LOOP;
END $$;


/* ============================================================
 *  B) GENERAR cuotas al aprobar venta usando datos de CRÉDITO
 *     - Usa NEW.credit_installments, NEW.credit_installment_amount,
 *       NEW.credit_total, NEW.credit_down_payment, etc.
 *     - Si no existen columnas, cae a defaults sin explotar.
 * ============================================================ */

CREATE OR REPLACE FUNCTION public.fn_sales_generate_credits_on_approve()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
DECLARE
  v_n        smallint;
  v_first    date;
  v_every    smallint;
  v_down     numeric(14,2);
  v_total    numeric(14,2);
  v_fin      numeric(14,2);
  v_per      numeric(14,2);
  v_last     numeric(14,2);
  i          int;
  v_due      date;

  -- nuevos (si existen)
  v_monto_cuota numeric(14,2);
  v_total_credito numeric(14,2);
BEGIN
  -- solo cuando pasa a aprobado y es crédito
  IF (OLD.status IS DISTINCT FROM NEW.status)
     AND NEW.status = 'aprobado'
     AND NEW.modo_pago = 'credito' THEN

    -- si ya hay cuotas para esa venta, no duplicamos
    IF EXISTS (SELECT 1 FROM public.credits c WHERE c.sale_id = NEW.id) THEN
      RETURN NEW;
    END IF;

    /* ===============================
     * Tomar parámetros desde la venta
     * =============================== */

    -- cuotas
    v_n := COALESCE(NEW.credit_installments, 3);
    IF v_n < 1 THEN v_n := 1; END IF;

    -- fechas
    v_first := COALESCE(NEW.credit_first_due_date, (CURRENT_DATE + INTERVAL '30 days')::date);
    v_every := COALESCE(NEW.credit_every_days, 30);

    -- entrega inicial
    v_down  := COALESCE(NEW.credit_down_payment, 0);

    -- ✅ total contado (fallback)
    v_total := COALESCE(NEW.total, 0);

    -- ✅ si existen estos campos en sales (según tu UI/API), los usamos:
    -- credit_installment_amount = monto por cuota
    -- credit_total = total_credito
    v_monto_cuota   := COALESCE(NEW.credit_installment_amount, NULL);
    v_total_credito := COALESCE(NEW.credit_total, NULL);

    /*
     * Regla:
     * - Si viene total_credito > 0 => ese es el monto del crédito.
     * - Si no, usamos total (contado) como fallback.
     */
    IF v_total_credito IS NOT NULL AND v_total_credito > 0 THEN
      v_total := v_total_credito;
    END IF;

    v_fin := GREATEST(0, v_total - v_down);

    /*
     * Regla:
     * - Si viene monto_cuota > 0 => usamos ese valor.
     *   Ajustamos la última para que cierre exacto con v_fin.
     * - Si no viene => calculamos con v_fin / v_n.
     */
    IF v_monto_cuota IS NOT NULL AND v_monto_cuota > 0 THEN
      v_per  := trunc(v_monto_cuota * 100) / 100;
      v_last := v_fin - (v_per * (v_n - 1));
      IF v_last < 0 THEN
        -- si por algún motivo el monto_cuota excede, recalculamos proporcional
        v_per  := trunc((v_fin / v_n) * 100) / 100;
        v_last := v_fin - (v_per * (v_n - 1));
      END IF;
    ELSE
      v_per  := trunc((v_fin / v_n) * 100) / 100;
      v_last := v_fin - (v_per * (v_n - 1));
    END IF;

    /* ===============================
     * Insertar cuotas en credits
     * status SIEMPRE pendiente
     * =============================== */
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
END
$function$;


/* ------------------------------------------------------------
 *  B.1) Asegurar trigger en sales para generar cuotas al aprobar
 *       (podés tener múltiples triggers AFTER UPDATE OF status)
 * ------------------------------------------------------------ */
DROP TRIGGER IF EXISTS trg_sales_au_generate_credits ON public.sales;

CREATE TRIGGER trg_sales_au_generate_credits
AFTER UPDATE OF status ON public.sales
FOR EACH ROW
EXECUTE FUNCTION public.fn_sales_generate_credits_on_approve();

SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
-- rollback básico (no restaura el comportamiento viejo de pagos automáticos)
DROP TRIGGER IF EXISTS trg_sales_au_generate_credits ON public.sales;

-- Podés dejar las funciones nuevas, o si querés volver atrás,
-- acá pegarías las versiones anteriores.
SQL);
    }
};
