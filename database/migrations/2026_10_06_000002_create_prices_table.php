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
        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->date('since')->unique();

            // PLN per kWh
            $table->decimal('peak_sell', 10, 5);
            $table->decimal('off_peak_sell', 10, 5);
            $table->decimal('peak_distr', 10, 5);
            $table->decimal('off_peak_distr', 10, 5);
            $table->decimal('peak_quality', 10, 5);
            $table->decimal('off_peak_quality', 10, 5);
            $table->decimal('peak_oze', 10, 5);
            $table->decimal('off_peak_oze', 10, 5);
            $table->decimal('peak_cogen', 10, 5);
            $table->decimal('off_peak_cogen', 10, 5);

            // PLN per month
            $table->decimal('sell_monthly', 10, 5);
            $table->decimal('power_monthly', 10, 5);
            $table->decimal('subscription_monthly', 10, 5);
            $table->decimal('network_monthly', 10, 5);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prices');
    }
};
