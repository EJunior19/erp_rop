<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false; // ✅ IMPORTANTE

    public function up(): void
    {
        DB::unprepared(<<<'SQL'
-- ⚠️ NO pongas BEGIN/COMMIT acá adentro

CREATE OR REPLACE FUNCTION public.fn_credit_defaults_guard()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
  IF NEW.status IS NULL OR NEW.status NOT IN ('pendiente','pagado','vencido') THEN
    NEW.status := 'pendiente';
  END IF;

  IF NEW.status = 'pagado' THEN
    NEW.status := 'pendiente';
  END IF;

  IF NEW.balance IS NULL OR NEW.balance <= 0 THEN
    NEW.balance := NEW.amount;
  END IF;

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
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS trg_credits_defaults_guard ON public.credits;
DROP FUNCTION IF EXISTS public.fn_credit_defaults_guard();
SQL);
    }
};
