<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('subscription_listing', function (Blueprint $table) {
            $table->id(); 
            $table->string('subscription_name'); 
            $table->text('sub_description'); 
            $table->decimal('price', 8, 2); 
            $table->integer('duration'); 
            $table->string('duration_unit'); 
            $table->enum('type', ['one_time', 'recurring']); 
            $table->timestamps(); 
        });
    }

    public function down()
    {
        Schema::dropIfExists('subscription_listing');
    }
};
