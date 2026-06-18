<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class TripLocation extends Model
{
    
protected $fillable = [

        'trip_id',

        'latitude',
        'longitude',
        
        'speed',
    ];



    // علاقة عكسية: هذا الإحداثي ينتمي لرحلة معينة
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
