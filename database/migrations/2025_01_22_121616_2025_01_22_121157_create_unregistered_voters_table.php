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
        Schema::create('unregistered_voters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('date_of_birth');
            $table->string('gender');
            $table->string('phone_number');
            $table->string('new_email')->nullable();
            $table->text('new_address');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('voter_id');
            $table->unsignedBigInteger('survey_id');
            $table->string('surveyer_constituency')->nullable();
            $table->integer('contacted')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unregistered_voters');
    }
};
