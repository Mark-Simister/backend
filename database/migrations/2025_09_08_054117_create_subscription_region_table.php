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
        Schema::create('subscription_region', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id');
            $table->unsignedBigInteger('region_id');
            $table->timestamps();

            $table->foreign('subscription_id')
                ->references('id')->on('subscription_listing')
                ->onDelete('cascade');

            $table->foreign('region_id')
                ->references('id')->on('regions')
                ->onDelete('cascade');

            $table->unique(['subscription_id', 'region_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_region');
    }
};
