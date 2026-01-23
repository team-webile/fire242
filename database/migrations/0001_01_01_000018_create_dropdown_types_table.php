<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {  
        Schema::create('dropdown_types', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // sex, marital_status, employment_type, religion
            $table->string('value');
            $table->bigInteger('position')->nullable()->default(null);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('dropdown_types');
    }
}; 