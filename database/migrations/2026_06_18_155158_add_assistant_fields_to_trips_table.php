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
        Schema::table('trips', function (Blueprint $table) {
            
        // هدول في حال غيرهن الموظف للرحلة فقط ويبقى المعاون الاساسي موجود بالباص
        
        $table->string('trip_assistant_name')->nullable()->after('bus_id');
        $table->string('trip_assistant_phone')->nullable()->after('trip_assistant_name');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            //
        });
    }
};
