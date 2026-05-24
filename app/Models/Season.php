<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Season extends Model
{
    protected $casts = [
        'starting_date' => 'date',
    ];

    public function endDate(): Carbon
    {
        $next = static::whereDate('starting_date', '>', $this->starting_date)
            ->orderBy('starting_date')
            ->value('starting_date');

        return $next ? Carbon::parse($next)->subDay() : Carbon::today();
    }
}
