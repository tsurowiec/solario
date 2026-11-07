<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FIELDS = ['early_peak_sell', 'early_peak_distr', 'early_peak_quality', 'early_peak_oze', 'early_peak_cogen'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('prices', function (Blueprint $table) {
            foreach (self::FIELDS as $field) {
                $table->decimal($field, 10, 5)->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prices', function (Blueprint $table) {
            $table->dropColumn(self::FIELDS);
        });
    }
};
