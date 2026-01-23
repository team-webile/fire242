<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema; 

return new class extends Migration
{
    public function up()
    {
        Schema::create('voters', function (Blueprint $table) {
            $table->id();
            $table->integer('const')->nullable();
            $table->integer('polling')->nullable(); 
            $table->integer('voter')->nullable();
            $table->string('surname')->nullable();
            $table->string('first_name')->nullable();
            $table->string('second_name')->nullable();
            $table->date('dob')->nullable();
            $table->string('pobcn')->nullable();
            $table->string('pobis')->nullable();
            $table->string('pobse')->nullable();
            $table->string('house_number')->nullable();
            $table->string('aptno')->nullable();
            $table->string('blkno')->nullable();
            $table->text('address')->nullable();
            $table->boolean('newly_registered')->default(0);
            $table->timestamps();


            $table->index('voter');
            $table->index('surname'); 
            $table->index('second_name'); 
            $table->index('first_name');
            $table->index('dob');
           

        });
    } 

    public function down()
    {
        Schema::dropIfExists('voters');
    }
}; 