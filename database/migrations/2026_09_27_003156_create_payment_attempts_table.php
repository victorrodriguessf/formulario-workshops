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
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('registrations');
            $table->string('idempotency_key')->unique();
            $table->string('environment');
            $table->string('order_id')->nullable();
            $table->string('transaction_id')->nullable();
            $table->integer('amount_cents')->unsigned();
            $table->string('currency')->default('BRL');
            $table->string('local_status')->default('creating');
            $table->string('provider_status')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('provider_created_at')->nullable();
            $table->timestamps();

            $table->unique(['environment', 'order_id']);
            $table->unique(['environment', 'transaction_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
