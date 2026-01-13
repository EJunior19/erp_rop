<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) FIX: no pisar status pagado ni balance 0
        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION public.fn_credit_defaults_guard()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
BEGIN
  -- Status: solo corregir si viene inválido o nulo
  IF NEW.status IS NULL OR NEW.status NOT IN ('pendiente','pagado','vencido') THEN
    NEW.status := 'pendiente';
  END IF;

  -- Balance:
  -- INSERT: si viene nulo, inicializar con amount
  -- UPDATE: si viene nulo, conservar OLD.balance
  IF TG_OP = 'INSERT' THEN
    IF NEW.balance IS NULL THEN
      NEW.balance := NEW.amount;
    END IF;
  ELSE
    IF NEW.balance IS NULL THEN
      NEW.balance := OLD.balance;
    END IF;
  END IF;

  IF NEW.notify_every_days IS NULL OR NEW.notify_every_days <= 0 THEN
    NEW.notify_every_days := 7;
  END IF;

  RETURN NEW;
END
$function$;
SQL);

        // 2) Trigger en payments: cada cambio recalcula el crédito
        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION public.fn_payments_after_change()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
  IF TG_OP = 'DELETE' THEN
    PERFORM fn_credit_recalc(OLD.credit_id);
    RETURN OLD;
  ELSE
    PERFORM fn_credit_recalc(NEW.credit_id);
    RETURN NEW;
  END IF;
END;
$$;
SQL);

        DB::statement('DROP TRIGGER IF EXISTS trg_payments_recalc_credit ON payments;');

        DB::statement(<<<'SQL'
CREATE TRIGGER trg_payments_recalc_credit
AFTER INSERT OR UPDATE OR DELETE ON payments
FOR EACH ROW
EXECUTE FUNCTION public.fn_payments_after_change();
SQL);

        // 3) Backfill: arregla créditos ya pagados (una vez)
        DB::statement('SELECT public.fn_credit_recalc(id) FROM credits;');
    }

    public function down(): void
    {
        // En producción normalmente no se baja, pero dejamos rollback básico
        DB::statement('DROP TRIGGER IF EXISTS trg_payments_recalc_credit ON payments;');
        DB::statement('DROP FUNCTION IF EXISTS public.fn_payments_after_change();');
        // No restauramos la versión vieja del guard (porque era incorrecta).
    }
};
