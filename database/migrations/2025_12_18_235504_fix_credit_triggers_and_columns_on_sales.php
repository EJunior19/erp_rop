<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * 1) Agregar columnas faltantes en sales (si no existen)
         */
        Schema::table('sales', function ($table) {
            // En Postgres, Schema Builder no tiene ifNotExists directo para columnas,
            // entonces validamos con Schema::hasColumn más abajo.
        });

        if (!Schema::hasColumn('sales', 'credit_total')) {
            DB::statement("ALTER TABLE sales ADD COLUMN credit_total numeric(14,2);");
        }

        if (!Schema::hasColumn('sales', 'credit_installment_amount')) {
            DB::statement("ALTER TABLE sales ADD COLUMN credit_installment_amount numeric(14,2);");
        }

        /**
         * 2) Asegurar que NO se generen pagos automáticos (eso marca como 'Pagado')
         *    => eliminamos el trigger de credits si existe
         */
        DB::statement("DROP TRIGGER IF EXISTS trg_credit_generate_initial_payment ON credits;");

        /**
         * 3) Corregir la función de generación de cuotas (usar credit_total si existe)
         *    - v_total debe ser credit_total (financiado) y no NEW.total (contado)
         */
        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION public.fn_sales_generate_credits_on_approve()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
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

    -- ✅ CLAVE: usar el total financiado si existe
    v_total := COALESCE(NEW.credit_total, NEW.total, 0);

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
END
$function$;
SQL);

        /**
         * 4) Evitar triggers duplicados en sales (dejamos solo 1)
         *    Tu tabla muestra:
         *    - trg_sales_au_generate_credits
         *    - trg_sales_generate_credits_on_approve
         *    Ambos apuntan a la misma función -> borramos uno.
         */
        DB::statement("DROP TRIGGER IF EXISTS trg_sales_au_generate_credits ON sales;");

        /**
         * 5) Asegurar que exista el trigger correcto (solo 1)
         */
        DB::statement(<<<'SQL'
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_trigger
    WHERE tgname = 'trg_sales_generate_credits_on_approve'
      AND tgrelid = 'public.sales'::regclass
  ) THEN
    CREATE TRIGGER trg_sales_generate_credits_on_approve
    AFTER UPDATE OF status ON public.sales
    FOR EACH ROW
    WHEN (old.status IS DISTINCT FROM new.status AND new.status = 'aprobado')
    EXECUTE FUNCTION public.fn_sales_generate_credits_on_approve();
  END IF;
END $$;
SQL);
    }

    public function down(): void
    {
        /**
         * Down: volvemos atrás solo lo “estructural”.
         * (No recreamos el trigger de pagos automáticos porque era el bug.)
         */
        if (Schema::hasColumn('sales', 'credit_total')) {
            DB::statement("ALTER TABLE sales DROP COLUMN credit_total;");
        }
        if (Schema::hasColumn('sales', 'credit_installment_amount')) {
            DB::statement("ALTER TABLE sales DROP COLUMN credit_installment_amount;");
        }
    }
};
