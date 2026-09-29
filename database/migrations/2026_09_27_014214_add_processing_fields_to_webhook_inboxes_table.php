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
        Schema::table('webhook_inboxes', function (Blueprint $table) {
            $table->string('provider')->default('mercadopago');
            $table->json('payload');
            $table->string('status')->default('pending')->index();
            $table->text('error_log')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webhook_inboxes', function (Blueprint $table) {
            $table->dropColumn(['provider', 'payload', 'status', 'error_log']);
        });
    }
};
