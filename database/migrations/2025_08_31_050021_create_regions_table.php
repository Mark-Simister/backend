<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('region_name');
            $table->string('region_code')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('currency', 10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
