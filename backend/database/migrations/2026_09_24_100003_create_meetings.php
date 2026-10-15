<?php

use App\Services\SchemaChecks;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** 100003 — meetings: bookings, 1:1 with their inquiry (FK RESTRICT).
 *
 * DOUBLE-BOOKING GUARD (spec §5): a PostgreSQL partial unique index
 * `uq_meetings_active_slot` on (`status`, `starts_at`) where status IN
 * ('BOOKED','COMPLETED') is the database-level equivalent of the MySQL
 * unique-on-generated-column. It forbids two active rows for the same
 * slot, closing the race between "check availability" and "insert" even
 * under concurrency. CANCELLED / NO_SHOW / COMPLETED rows are covered by
 * the index only while they still hold a slot; they are excluded from the
 * index where status IN ('CANCELLED','NO_SHOW'), so those slots become
 * bookable again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('inquiry_id');
            $table->timestamp('starts_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->enum('status', ['BOOKED', 'COMPLETED', 'CANCELLED', 'NO_SHOW'])->default('BOOKED');
            $table->string('cancelled_reason', 255)->default('');
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->foreign('inquiry_id')->references('id')->on('business_inquiries')->onDelete('restrict');
        });

        SchemaChecks::add('meetings', [
            'chk_meetings_duration' => 'duration_minutes BETWEEN 10 AND 240',
            'chk_meetings_cancel' => "(status IN ('CANCELLED','NO_SHOW')) OR (cancelled_at IS NULL AND cancelled_by IS NULL)",
        ]);

        // Double-booking guard: PostgreSQL partial unique index over active
        // slot holds. MySQL's ->unique()->where() is not supported here, so
        // emit the native PG index directly. It forbids two active rows for
        // the same slot (status BOOKED/COMPLETED), closing the race between
        // "check availability" and "insert" even under concurrency. CANCELLED
        // / NO_SHOW rows are excluded so their slots become bookable again.
        DB::statement(
            "CREATE UNIQUE INDEX uq_meetings_active_slot ON meetings (status, starts_at) "
            .
            "WHERE status IN ('BOOKED', 'COMPLETED')"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_meetings_active_slot');

        Schema::dropIfExists('meetings');
    }
};
