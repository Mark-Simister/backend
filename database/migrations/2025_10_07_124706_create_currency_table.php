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
        Schema::create('currency', function (Blueprint $table) {
            $table->id();
            $table->string('currency_code', 3);  // Currency code, e.g., 'USD'
            $table->string('currency_name', 50); // Currency name, e.g., 'US Dollar'
            $table->string('currency_symbol', 50); // Currency symbol, e.g., '$'
            $table->timestamps(); // Created at, updated at
            $table->primary('id'); // Set primary key
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('currency');
    }
};
