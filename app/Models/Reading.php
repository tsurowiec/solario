<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reading extends Model
{
    protected $fillable = [
        'date',
        'pv_generated',
        'peak_consumed',
        'off_peak_consumed',
        'peak_fed_in',
        'off_peak_fed_in',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}
