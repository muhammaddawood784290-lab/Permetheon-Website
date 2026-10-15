<?php

use App\Services\SchemaChecks;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 001 — business_inquiries.
 *
 * Canonical columns: name, company, email, contact_number (E.164-capable),
 * project_type, budget, timeline, message, status, priority.
 *
 * CHECK constraints are added as named table-level constraints via
 * SchemaChecks — enforced by PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_inquiries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->string('company', 150)->nullable();
            $table->string('email', 254);
            $table->string('contact_number', 16)->default('');
            $table->string('project_type');
            $table->string('budget')->nullable();
            $table->string('timeline')->nullable();
            $table->text('message');
            $table->string('status')->default('NEW');
            $table->string('priority')->default('MEDIUM');
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            $table->index('created_at');
            $table->index('status');
        });

        SchemaChecks::add('business_inquiries', [
            'chk_inquiries_name_length' => 'CHAR_LENGTH(name) BETWEEN 2 AND 100',
            'chk_inquiries_company_length' => 'company IS NULL OR CHAR_LENGTH(company) <= 150',
            'chk_inquiries_email_length' => 'CHAR_LENGTH(email) <= 254',
            'chk_inquiries_contact_e164' => "CHAR_LENGTH(contact_number) <= 16 AND (contact_number = '' OR contact_number ~ '^[+][1-9][0-9]*$')",
            'chk_inquiries_project_type' => "project_type IN ('Website Development','Web Application','Business System','Booking / Reservation System','UI/UX & Product Design','Custom Digital Product','Other')",
            'chk_inquiries_message_length' => 'CHAR_LENGTH(message) BETWEEN 20 AND 5000',
            'chk_inquiries_status' => "status IN ('NEW','REVIEWING','CONTACTED','QUALIFIED','PROPOSAL','WON','LOST')",
            'chk_inquiries_priority' => "priority IN ('LOW','MEDIUM','HIGH','URGENT')",
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('business_inquiries');
    }
};
