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
        Schema::create('voter_import_lists', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->text('file_path');
            $table->string('status')->default('pending');
            $table->integer('processed_rows')->nullable();
            $table->integer('total_rows')->nullable();
            $table->integer('progress')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps(); 
        });   
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voter_import_lists');
    }
};
