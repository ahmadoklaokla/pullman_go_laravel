<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('booking_seats', function (Blueprint $table) {
            
        // بيعطيني اسم صاحب الحجز اذا حطيت جمب اسم الراكب بالفورم صح 
        $table->boolean('is_primary')->default(false)->after('seat_number');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_seats', function (Blueprint $table) {
            //
        });
    }
};
