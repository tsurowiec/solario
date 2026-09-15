<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Season extends Model
{
    protected $fillable = [
        'name',
        'starting_date',
        'peak_rate',
        'off_peak_rate',
        'fed_in_ratio',
    ];

    protected $casts = [
        'starting_date' => 'date',
        'peak_rate' => 'float',
        'off_peak_rate' => 'float',
        'fed_in_ratio' => 'float',
    ];

    public function endDate(): Carbon
    {
        $next = static::whereDate('starting_date', '>', $this->starting_date)
            ->orderBy('starting_date')
            ->value('starting_date');

        return $next ? Carbon::parse($next)->subDay() : Carbon::today();
    }

    public static function activeOn(Carbon $date): ?self
    {
        return static::whereDate('starting_date', '<=', $date)
            ->orderByDesc('starting_date')
            ->first();
    }
}
