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
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('event_id')->constrained('events');
            $table->text('participant_name');
            $table->text('participant_whatsapp');
            $table->text('participant_email');
            $table->text('participant_cpf_encrypted');
            $table->string('cpf_lookup_hmac');
            $table->enum('status', ['awaiting_payment', 'confirmed', 'expired', 'cancelled', 'review_required'])->default('awaiting_payment');
            $table->integer('agreed_price_cents')->unsigned();
            $table->string('protocol')->unique();
            $table->string('privacy_policy_version')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'cpf_lookup_hmac']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
