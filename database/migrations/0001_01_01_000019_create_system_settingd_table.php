<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('active_time')->comment('Active time in minutes');
            $table->timestamps();
        });

        // Insert default values
        DB::table('system_settings')->insert([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'active_time' => 480, // 8 hours in minutes
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('system_settings');
    }
};