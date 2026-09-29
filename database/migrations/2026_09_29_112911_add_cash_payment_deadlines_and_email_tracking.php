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
        Schema::table('events', function (Blueprint $table) {
            $table->dateTime('cash_payment_deadline')->nullable()->after('registrations_close_at');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->dateTime('cash_payment_due_at')->nullable()->after('payment_received');
            $table->dateTime('cash_reservation_email_sent_at')->nullable()->after('cash_payment_due_at');
            $table->dateTime('confirmation_email_sent_at')->nullable()->after('cash_reservation_email_sent_at');
            $table->index(['payment_method', 'status', 'cash_payment_due_at'], 'registrations_cash_expiration_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropIndex('registrations_cash_expiration_index');
            $table->dropColumn([
                'cash_payment_due_at',
                'cash_reservation_email_sent_at',
                'confirmation_email_sent_at',
            ]);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('cash_payment_deadline');
        });
    }
};
