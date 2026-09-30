<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Services\SchemaChecks;

/** 004 — inquiry_notes: internal-only notes with FK + author join (parity row 13). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_notes', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('inquiry_id', 36);
            $table->string('admin_id', 36);
            $table->text('body');
            $table->dateTime('created_at', 3);

            $table->foreign('inquiry_id')->references('id')->on('business_inquiries')->cascadeOnDelete();
            $table->foreign('admin_id')->references('id')->on('admin_users');
            $table->index(['inquiry_id', 'created_at']);
        });

        SchemaChecks::add('inquiry_notes', [
            'chk_notes_body_length' => 'CHAR_LENGTH(`body`) BETWEEN 1 AND 2000',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_notes');
    }
};
