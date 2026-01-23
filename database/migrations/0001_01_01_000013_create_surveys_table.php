<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSurveysTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            
            // Personal Information
            $table->unsignedBigInteger('voter_id');
            $table->unsignedBigInteger('user_id');
            $table->string('sex')->nullable();
            $table->string('marital_status')->nullable();
            
            // Employment Information
            $table->string('employed')->default(false);
            $table->string('children')->default(false);
            $table->string('employment_type')->nullable();
            $table->string('employment_sector')->nullable();
            
            // Demographics
            $table->string('religion')->nullable();
            $table->string('located')->nullable(); // Current location/address
            $table->string('island')->nullable(); // For Off Island
            $table->string('country')->nullable(); // For Outside Country
            $table->string('country_location')->nullable(); // For Outside Country location
            
            // Contact Information
            $table->string('home_phone_code')->nullable();
            $table->string('home_phone', 15)->nullable();
            $table->string('work_phone_code')->nullable();
            $table->string('work_phone', 15)->nullable();
            $table->string('cell_phone_code')->nullable();
            $table->string('cell_phone', 15)->nullable();
            $table->string('email')->nullable();
            
            // Comments
            $table->text('special_comments')->nullable();
            $table->text('other_comments')->nullable();
            
            // Voting Information
            $table->string('voting_for')->nullable();
            $table->string('last_voted')->nullable();
            $table->string('voted_for_party')->nullable();
            $table->string('voted_where')->nullable();
            $table->string('voted_in_house')->nullable();
            $table->text('note')->nullable();
            // Images
            $table->string('voter_image')->nullable();
            $table->string('house_image')->nullable();
            $table->integer('voters_in_house')->nullable();
             
            // Meta Information
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
              
            // Indexes for better query performance
            $table->index('voter_id');
            $table->index('user_id'); 
            $table->index('email');
            $table->index(['created_by', 'updated_by']); 
        });
    }
 
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('surveys');
    }
}