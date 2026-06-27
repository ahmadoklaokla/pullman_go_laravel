<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Blueprint as TableBlueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void

    {
        Schema::table('app_notifications', function (Blueprint $table) {


            // إضافة حقل آيدي السائق ويكون nullable
            $table->foreignId('driver_id')->nullable()->constrained('users')->onDelete('cascade');
            
            // إضافة حقل الإرسال لكل السائقين وقيمته الافتراضية true (1)
            $table->boolean('send_to_all_drivers')->default(true);
            
        });
    }




    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_notifications', function (Blueprint $table) {
            //
        });
    }
};
