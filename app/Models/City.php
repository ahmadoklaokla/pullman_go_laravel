<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
   protected $fillable = [
    'name',

    // لحساب المسافة بين اي مدينتين لانو المسافة ثابتة
    'lat', 
    'lng',

    'is_active'
    
];


// الدينة ممكن تكون تابعة لاكثر من شركة
public function companies() 
{
    return $this->belongsToMany(Company::class);
}


}
