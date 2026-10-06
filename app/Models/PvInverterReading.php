<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PvInverterReading extends Model
{
    protected $fillable = [
        'date',
        'value',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}
