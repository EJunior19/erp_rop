<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION fn_credit_recalc(p_credit_id bigint)
RETURNS void
LANGUAGE plpgsql
AS $$
DECLARE
  v_amount  numeric(14,2);
  v_paid    numeric(14,2);
  v_due     date;
  v_balance numeric(14,2);
  v_status  varchar(255);
BEGIN
  SELECT amount, due_date
    INTO v_amount, v_due
  FROM credits
  WHERE id = p_credit_id
  FOR UPDATE;

  IF NOT FOUND THEN
    RETURN;
  END IF;

  SELECT COALESCE(SUM(amount), 0)
    INTO v_paid
  FROM payments
  WHERE credit_id = p_credit_id;

  v_balance := GREATEST(v_amount - v_paid, 0);

  IF v_balance = 0 THEN
    v_status := 'pagado';
  ELSE
    IF v_due < CURRENT_DATE THEN
      v_status := 'vencido';
    ELSE
      v_status := 'pendiente';
    END IF;
  END IF;

  UPDATE credits
  SET balance    = v_balance,
      status     = v_status,
      updated_at = NOW()
  WHERE id = p_credit_id;
END;
$$;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS fn_credit_recalc(bigint);');
    }
};
