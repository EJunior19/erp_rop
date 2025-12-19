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
  -- FIX: NO generar pagos automáticamente.
  -- payments debe registrar pagos reales, no "cuotas" ni "entregas" automáticas.
  RETURN NEW;
END;
$$;
SQL);
    }

    public function down(): void
    {
        // No hacemos rollback automático porque no tenemos la versión anterior guardada.
        // Si querés, después te preparo el down con tu versión vieja.
    }
};
