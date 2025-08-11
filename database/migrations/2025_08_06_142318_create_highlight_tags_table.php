<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('highlight_tags', function (Blueprint $table) {
            $table->id();
            $table->string('label'); 
            $table->string('emoji'); 
            $table->boolean('automated')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('highlight_tags');
    }
};
