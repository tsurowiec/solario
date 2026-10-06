<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeterDailyReading extends Model
{
    public const VALUE_FIELDS = [
        't1_consumed',
        't1_fed_in',
        't1_balanced_consumed',
        't1_balanced_fed_in',
        't2_consumed',
        't2_fed_in',
        't2_balanced_consumed',
        't2_balanced_fed_in',
    ];

    protected $fillable = [
        'date',
        ...self::VALUE_FIELDS,
    ];

    protected $casts = [
        'date' => 'date',
        't1_consumed' => 'float',
        't1_fed_in' => 'float',
        't1_balanced_consumed' => 'float',
        't1_balanced_fed_in' => 'float',
        't2_consumed' => 'float',
        't2_fed_in' => 'float',
        't2_balanced_consumed' => 'float',
        't2_balanced_fed_in' => 'float',
    ];
}
