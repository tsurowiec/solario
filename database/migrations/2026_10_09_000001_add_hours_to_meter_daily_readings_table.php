<?php

use Carbon\Carbon;
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
        Schema::table('meter_daily_readings', function (Blueprint $table) {
            $table->unsignedTinyInteger('hours')->default(0)->after('date');
        });

        // Rows imported so far were complete days only: store each day's full length (23–25 h with DST).
        foreach (DB::table('meter_daily_readings')->pluck('date', 'id') as $id => $date) {
            $start = Carbon::parse($date, 'Europe/Warsaw')->startOfDay();

            DB::table('meter_daily_readings')->where('id', $id)->update([
                'hours' => (int) $start->diffInHours($start->copy()->addDay()->startOfDay()),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meter_daily_readings', function (Blueprint $table) {
            $table->dropColumn('hours');
        });
    }
};
