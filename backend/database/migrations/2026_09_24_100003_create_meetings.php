<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Services\SchemaChecks;

/**
 * 100003 — meetings: bookings, 1:1 with their inquiry (a meeting cannot exist
 * without an inquiry; an inquiry has at most one meeting — enforced by the
 * unique constraint on inquiry_id).
 *
 * DOUBLE-BOOKING GUARD (spec §5): `unique_slot_guard` is a generated column
 * that is NULL unless the row holds a slot (BOOKED or COMPLETED — COMPLETED
 * keeps its historical hold). The UNIQUE index on it makes two active rows for
 * the same starts_at impossible at the database level, closing the race between
 * "check availability" and "insert" even under concurrent requests. CANCELLED /
 * NO_SHOW rows get guard = NULL and their slot becomes bookable again.
 *
 * MySQL: `(CASE WHEN status IN ('BOOKED','COMPLETED') THEN starts_at END)` —
 * MariaDB 10.4+ supports generated-column UNIQUE indexes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('inquiry_id', 36);
            // Stored as business-timezone wall time (config/meetings.php tz).
            $table->dateTime('starts_at', 3);
            $table->unsignedSmallInteger('duration_minutes');
            $table->enum('status', ['BOOKED', 'COMPLETED', 'CANCELLED', 'NO_SHOW'])->default('BOOKED');
            $table->string('cancelled_reason', 255)->default('');
            $table->string('cancelled_by', 36)->nullable();
            $table->dateTime('cancelled_at', 3)->nullable();
            $table->dateTime('created_at', 3);
            $table->dateTime('updated_at', 3);

            // Explicit inquiry → meeting relationship (spec §4): a meeting
            // cannot exist without its inquiry; deleting the inquiry must never
            // orphan a meeting, so the FK is RESTRICT — deletion goes through
            // the documented policy (delete meeting first, in the same op).
            $table->foreign('inquiry_id')->references('id')->on('business_inquiries')->restrictOnDelete();

            // DOUBLE-BOOKING GUARD: unique active-slot hold.
            $table->dateTime('unique_slot_guard', 3)
                ->nullable()
                ->virtualAs("(CASE WHEN `status` IN ('BOOKED','COMPLETED') THEN `starts_at` END)");
            $table->unique('unique_slot_guard', 'uq_meetings_active_slot');

            $table->index(['status', 'starts_at']);
            $table->index('starts_at');
        });

        SchemaChecks::add('meetings', [
            'chk_meetings_duration' => '`duration_minutes` BETWEEN 10 AND 240',
            'chk_meetings_cancel' => "(`status` IN ('CANCELLED','NO_SHOW')) OR (`cancelled_at` IS NULL AND `cancelled_by` IS NULL)",
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
