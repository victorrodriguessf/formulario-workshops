<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedInteger('capacity')->nullable()->after('is_paused');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->string('payment_method')->default('pix')->after('status');
            $table->boolean('payment_received')->default(false)->after('payment_method');
        });

        DB::table('registrations')
            ->where('status', 'confirmed')
            ->update(['payment_received' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'payment_received']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('capacity');
        });
    }
};
