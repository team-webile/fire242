<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('voter_notes', function (Blueprint $table) {
            $table->id();
            $table->text('note');
            $table->unsignedBigInteger('unregistered_voter_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        }); 
    }

    public function down()
    {
        Schema::dropIfExists('voter_notes');
    }
};
