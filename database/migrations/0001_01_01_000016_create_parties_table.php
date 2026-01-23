<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {   
        if (!Schema::hasTable('parties')) {
            Schema::create('parties', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('short_name');
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
            }); 
        }
    }

    public function down()
    {
        Schema::dropIfExists('parties');
    }
};  