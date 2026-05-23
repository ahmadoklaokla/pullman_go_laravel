<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingSeat extends Model
{
    protected $fillable =
     [
        'booking_id',
        'seat_number',
        // اسم المسافر وليس اسم المستخدم
        'passenger_name',
        
        // في حال الحجز اليدوي 
        // مشان يعطيني اسم صاحب الحجز جمب اسم الراكب الي بقلو اي هو هاد صاحب الحجز
        'is_primary'
    ];



    // المقعد يتبع لحجز واحد (معين)
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
