<?php

use App\Services\SchemaChecks;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** ADD City + Country to business_inquiries (2026-10-14).
 *
 * PURELY ADDITIVE: two new NULLABLE varchar(100) columns + two named length
 * CHECK constraints. No existing column is altered, dropped or rewritten and
 * no existing row is touched — legacy inquiries (submitted before the fields
 * existed) keep NULL here and the admin UI shows an honest "Not provided".
 *
 * New submissions carry required city/country (StoreInquiryRequest); NULL
 * exists only for pre-amendment rows, exactly like the contact-number
 * amendment before it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_inquiries')) {
            return;
        }

        Schema::table('business_inquiries', function (Blueprint $table) {
            if (! Schema::hasColumn('business_inquiries', 'city')) {
                $table->string('city', 100)->nullable()->after('contact_number');
            }
            if (! Schema::hasColumn('business_inquiries', 'country')) {
                $table->string('country', 100)->nullable()->after('city');
            }
        });

        SchemaChecks::add('business_inquiries', [
            'chk_inquiries_city_length' => 'city IS NULL OR CHAR_LENGTH(city) BETWEEN 2 AND 100',
            'chk_inquiries_country_length' => 'country IS NULL OR CHAR_LENGTH(country) BETWEEN 2 AND 100',
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('business_inquiries')) {
            return;
        }

        DB::statement('ALTER TABLE business_inquiries DROP CONSTRAINT IF EXISTS chk_inquiries_city_length');
        DB::statement('ALTER TABLE business_inquiries DROP CONSTRAINT IF EXISTS chk_inquiries_country_length');

        Schema::table('business_inquiries', function (Blueprint $table) {
            if (Schema::hasColumn('business_inquiries', 'city')) {
                $table->dropColumn('city');
            }
            if (Schema::hasColumn('business_inquiries', 'country')) {
                $table->dropColumn('country');
            }
        });
    }
};
