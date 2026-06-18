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
        Schema::create('trip_locations', function (Blueprint $table) {
            $table->id();

            // ربط الإحداثيات برقم الرحلة حصراً
            $table->foreignId('trip_id')->constrained()->onDelete('cascade');
            
            // حقول الـ GPS بدقة عالية (double) عشان تحديد المكان بالسنتيمتر
            $table->double('latitude');
            $table->double('longitude');
            
            // سرعة الباص (اختياري بس فخم ومفيد لخرائط التتبع)
            $table->float('speed')->nullable()->default(0);


            $table->timestamps();
        });
    }

    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_locations');
    }
};
