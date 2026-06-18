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
        Schema::table('cities', function (Blueprint $table) {
            
        // ثابتات لانو المسافة بين اي مدينتين ثابتة 
        $table->decimal('lat', 10, 8)->nullable()->after('name');   //خط الطول
        $table->decimal('lng', 11, 8)->nullable()->after('lat');    // خط العرض


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            //
        });
    }
};
