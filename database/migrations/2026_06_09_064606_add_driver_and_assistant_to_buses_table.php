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
        Schema::table('buses', function (Blueprint $table) {


        $table->foreignId('driver_id')->nullable()->after('route_id')->constrained('users')->onDelete('set null');
        // للمعاون
        $table->string('assistant_name')->nullable()->after('driver_id');
        $table->string('assistant_phone')->nullable()->after('assistant_name');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buses', function (Blueprint $table) {
            //
        });
    }
};
