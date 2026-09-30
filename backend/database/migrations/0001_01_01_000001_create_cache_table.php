<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CACHE TABLE — required because CACHE_STORE=database.
 *
 * The rate limiters (inquiry 5/10min, login 10/10min, admin-read 120/min)
 * persist their counters here. This is a deliberate security control: a
 * per-request cache store silently resets every counter and disables all
 * rate limiting (docs/SECURITY_FINDINGS_CARRYOVER.md, finding 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
