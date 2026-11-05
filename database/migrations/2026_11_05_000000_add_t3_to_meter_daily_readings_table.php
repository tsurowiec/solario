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
        Schema::table('meter_daily_readings', function (Blueprint $table) {
            $table->decimal('t3_consumed', 10, 3)->default(0);
            $table->decimal('t3_fed_in', 10, 3)->default(0);
            $table->decimal('t3_balanced_consumed', 10, 3)->default(0);
            $table->decimal('t3_balanced_fed_in', 10, 3)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meter_daily_readings', function (Blueprint $table) {
            $table->dropColumn(['t3_consumed', 't3_fed_in', 't3_balanced_consumed', 't3_balanced_fed_in']);
        });
    }
};
