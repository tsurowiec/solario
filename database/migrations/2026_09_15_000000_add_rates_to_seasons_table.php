<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->decimal('peak_rate', 8, 2)->default(1.40)->after('starting_date');
            $table->decimal('off_peak_rate', 8, 2)->default(0.70)->after('peak_rate');
            $table->decimal('fed_in_ratio', 4, 2)->default(0.80)->after('off_peak_rate');
        });
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn(['peak_rate', 'off_peak_rate', 'fed_in_ratio']);
        });
    }
};
