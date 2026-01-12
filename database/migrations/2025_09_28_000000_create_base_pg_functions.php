<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION public.fn_apply_inventory_stock()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
DECLARE
  v_delta integer := 0;
BEGIN
  -- INSERT: aplicar efecto nuevo
  IF TG_OP = 'INSERT' THEN
    IF NEW.type = 'entrada' THEN
      v_delta := NEW.qty;
    ELSIF NEW.type = 'salida' THEN
      v_delta := -NEW.qty;
    END IF;

    IF v_delta <> 0 THEN
      UPDATE public.products
      SET stock = COALESCE(stock, 0) + v_delta
      WHERE id = NEW.product_id;
    END IF;

    RETURN NEW;
  END IF;

  -- UPDATE: revertir efecto viejo y aplicar el nuevo
  IF TG_OP = 'UPDATE' THEN
    IF OLD.type = 'entrada' THEN
      v_delta := v_delta - OLD.qty;
    ELSIF OLD.type = 'salida' THEN
      v_delta := v_delta + OLD.qty;
    END IF;

    IF NEW.type = 'entrada' THEN
      v_delta := v_delta + NEW.qty;
    ELSIF NEW.type = 'salida' THEN
      v_delta := v_delta - NEW.qty;
    END IF;

    IF v_delta <> 0 THEN
      UPDATE public.products
      SET stock = COALESCE(stock, 0) + v_delta
      WHERE id = NEW.product_id;
    END IF;

    RETURN NEW;
  END IF;

  RETURN NEW;
END;
$function$;
SQL);
}


    /**
     * Reverse the migrations.
     */
    
public function down(): void
{
    DB::unprepared('DROP FUNCTION IF EXISTS public.fn_apply_inventory_stock();');
}

};
