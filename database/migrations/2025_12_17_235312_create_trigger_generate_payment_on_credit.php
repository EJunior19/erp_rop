<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION public.fn_credit_generate_initial_payment()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
  -- Evitar duplicados: si ya hay pagos para este crédito, no hace nada
  IF EXISTS (
    SELECT 1 FROM public.payments WHERE credit_id = NEW.id
  ) THEN
    RETURN NEW;
  END IF;

  -- Crear la cuota inicial
  INSERT INTO public.payments (
    credit_id,
    amount,
    payment_date,
    method,
    created_at,
    updated_at,
    note
  )
  VALUES (
    NEW.id,
    NEW.balance,
    NEW.due_date,
    NULL,
    NOW(),
    NOW(),
    'Cuota inicial generada automáticamente'
  );

  RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_credit_generate_initial_payment ON public.credits;

CREATE TRIGGER trg_credit_generate_initial_payment
AFTER INSERT ON public.credits
FOR EACH ROW
EXECUTE FUNCTION public.fn_credit_generate_initial_payment();
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS trg_credit_generate_initial_payment ON public.credits;
DROP FUNCTION IF EXISTS public.fn_credit_generate_initial_payment();
SQL);
    }
};
