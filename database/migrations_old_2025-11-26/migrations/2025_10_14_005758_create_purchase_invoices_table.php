<?php

// database/migrations/2025_10_13_000002_create_purchase_invoice_items_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('purchase_invoice_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_invoice_id')->constrained()->cascadeOnDelete();
            $t->foreignId('purchase_receipt_item_id')->constrained()->cascadeOnDelete(); // vínculo directo al item recibido
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();

            $t->integer('qty')->default(0);               // cantidad que facturo de ese recibido
            $t->decimal('unit_cost', 10, 2)->default(0);  // puede coincidir o ajustarse
            $t->decimal('tax_rate', 5, 2)->default(0);    // % IVA si aplica (0,5,10 etc)
            $t->decimal('subtotal', 12, 2)->default(0);
            $t->decimal('tax', 12, 2)->default(0);
            $t->decimal('total', 12, 2)->default(0);
            $t->timestamps();

            // No factures el mismo item con la misma factura más de una vez:
            $t->unique(['purchase_invoice_id','purchase_receipt_item_id']);
        });

        // Protección: NO permitir que la suma facturada supere lo recibido
        DB::statement("
            CREATE OR REPLACE FUNCTION fn_check_invoiced_qty() RETURNS trigger
            LANGUAGE plpgsql AS $$
            DECLARE v_received int; v_invoiced int;
            BEGIN
                SELECT received_qty INTO v_received
                FROM purchase_receipt_items WHERE id = NEW.purchase_receipt_item_id;

                SELECT COALESCE(SUM(qty),0) INTO v_invoiced
                FROM purchase_invoice_items
                WHERE purchase_receipt_item_id = NEW.purchase_receipt_item_id
                  AND id <> COALESCE(NEW.id, -1);

                IF (v_invoiced + NEW.qty) > v_received THEN
                    RAISE EXCEPTION 'La cantidad facturada (%), supera lo recibido (%) para el ítem %',
                        (v_invoiced + NEW.qty), v_received, NEW.purchase_receipt_item_id;
                END IF;

                RETURN NEW;
            END $$;
        ");

        DB::statement("
            CREATE TRIGGER trg_check_invoiced_qty
            BEFORE INSERT OR UPDATE OF qty, purchase_receipt_item_id
            ON purchase_invoice_items
            FOR EACH ROW
            EXECUTE FUNCTION fn_check_invoiced_qty();
        ");
    }

    public function down(): void {
        DB::statement("DROP TRIGGER IF EXISTS trg_check_invoiced_qty ON purchase_invoice_items");
        DB::statement("DROP FUNCTION IF EXISTS fn_check_invoiced_qty");
        Schema::dropIfExists('purchase_invoice_items');
    }
};
