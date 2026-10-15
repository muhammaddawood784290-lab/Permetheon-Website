<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 004 — inquiry_notes: internal-only notes with FK + author join (parity row 13).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_notes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('inquiry_id');
            $table->unsignedBigInteger('admin_id');
            $table->text('body');
            $table->timestamp('created_at');
            $table->foreign('inquiry_id')->references('id')->on('business_inquiries')->onDelete('cascade');
            $table->foreign('admin_id')->references('id')->on('admin_users');
            $table->index(['inquiry_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_notes');
    }
};
