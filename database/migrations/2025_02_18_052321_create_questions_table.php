<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuestionsTable extends Migration 
{
    public function up() 
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id(); // Creates an auto-incrementing ID
            $table->text('question'); // Column for the question text
            $table->integer('position')->default(0); // Column for ordering questions
            $table->timestamps(); // Creates created_at and updated_at columns
        });
    }

    public function down()
    {
        Schema::dropIfExists('questions');
    }
}