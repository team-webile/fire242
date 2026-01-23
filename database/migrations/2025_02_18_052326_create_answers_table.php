<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAnswersTable extends Migration 
{
    public function up() 
    {
        Schema::create('answers', function (Blueprint $table) {
            $table->id(); // Creates an auto-incrementing ID
            $table->foreignId('question_id')->constrained()->onDelete('cascade'); // Foreign key referencing questions table
            $table->integer('position')->default(0); // Column for ordering answers
            $table->text('answer'); // Column for the answer text
            $table->timestamps(); // Creates created_at and updated_at columns
        });
    }

    public function down()
    {
        Schema::dropIfExists('answers');
    }
}