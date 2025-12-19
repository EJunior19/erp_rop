<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) BACKFILL desde suppliers a tablas normalizadas (solo si existen valores y no hay duplicados)
        // Emails
        DB::statement(<<<'SQL'
INSERT INTO supplier_emails (supplier_id, email, type, is_active, is_default, created_at, updated_at)
SELECT s.id, s.email, 'general', TRUE, TRUE, NOW(), NOW()
FROM suppliers s
WHERE s.email IS NOT NULL AND s.email <> ''
  AND NOT EXISTS (
      SELECT 1 FROM supplier_emails e
      WHERE e.supplier_id = s.id AND LOWER(e.email) = LOWER(s.email)
  );
SQL);

        // Teléfonos
        DB::statement(<<<'SQL'
INSERT INTO supplier_phones (supplier_id, phone_number, type, is_active, is_primary, created_at, updated_at)
SELECT s.id, s.phone, 'principal', TRUE, TRUE, NOW(), NOW()
FROM suppliers s
WHERE s.phone IS NOT NULL AND s.phone <> ''
  AND NOT EXISTS (
      SELECT 1 FROM supplier_phones p
      WHERE p.supplier_id = s.id AND p.phone_number = s.phone
  );
SQL);

        // Direcciones (backfill) — asegurando city NOT NULL
        DB::statement(<<<'SQL'
        INSERT INTO supplier_addresses
        (supplier_id, street, city, state, country, postal_code, type, is_primary, created_at, updated_at)
        SELECT
        s.id,
        s.address,                                                -- street
        COALESCE(NULLIF(TRIM(s.address),''), 'Sin ciudad'),       -- city: fallback si no tenemos dato
        NULL::varchar,                                            -- state
        'Paraguay',                                               -- country (ajusta si querés)
        NULL::varchar,                                            -- postal_code
        'fiscal',                                                 -- type
        TRUE,                                                     -- is_primary
        NOW(), NOW()
        FROM suppliers s
        WHERE s.address IS NOT NULL AND s.address <> ''
        AND NOT EXISTS (
            SELECT 1
            FROM supplier_addresses a
            WHERE a.supplier_id = s.id
                AND a.street = s.address
        );
        SQL);


        // 2) DROPS de columnas legacy en suppliers
        Schema::table('suppliers', function ($table) {
            if (Schema::hasColumn('suppliers', 'phone'))   $table->dropColumn('phone');
            if (Schema::hasColumn('suppliers', 'email'))   $table->dropColumn('email');
            if (Schema::hasColumn('suppliers', 'address')) $table->dropColumn('address');
        });
    }

    public function down(): void
    {
        // 1) Volver a crear columnas (por compatibilidad)
        Schema::table('suppliers', function ($table) {
            if (!Schema::hasColumn('suppliers', 'phone'))   $table->string('phone', 50)->nullable();
            if (!Schema::hasColumn('suppliers', 'email'))   $table->string('email', 255)->nullable();
            if (!Schema::hasColumn('suppliers', 'address')) $table->string('address', 255)->nullable();
        });

        // 2) Restaurar valores base desde las tablas normalizadas (toma el principal/default)
        DB::statement(<<<'SQL'
UPDATE suppliers s SET
  phone   = subp.phone_number
FROM (
  SELECT DISTINCT ON (supplier_id) supplier_id, phone_number
  FROM supplier_phones
  ORDER BY supplier_id, is_primary DESC, id ASC
) subp
WHERE subp.supplier_id = s.id;
SQL);

        DB::statement(<<<'SQL'
UPDATE suppliers s SET
  email   = sube.email
FROM (
  SELECT DISTINCT ON (supplier_id) supplier_id, email
  FROM supplier_emails
  ORDER BY supplier_id, is_default DESC, id ASC
) sube
WHERE sube.supplier_id = s.id;
SQL);

        DB::statement(<<<'SQL'
UPDATE suppliers s SET
  address = suba.street
FROM (
  SELECT DISTINCT ON (supplier_id) supplier_id, street
  FROM supplier_addresses
  ORDER BY supplier_id, is_primary DESC, id ASC
) suba
WHERE suba.supplier_id = s.id;
SQL);
    }
};
