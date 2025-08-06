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
        Schema::table('characters', function (Blueprint $table) {
            
            $table->enum('sex', ['male', 'female', 'other'])->nullable();
            $table->string('page_heading')->nullable();
            $table->string('page_sub_heading')->nullable();
            $table->text('preferences')->nullable();
            $table->string('loved_pet1')->nullable();
            $table->string('loved_pet2')->nullable();
            $table->string('loved_pet3')->nullable();
            $table->string('hated_pet1')->nullable();
            $table->string('hated_pet2')->nullable();
            $table->string('hated_pet3')->nullable();
            $table->string('character_page_url_slug')->unique()->nullable();
            $table->boolean('public_private_toggle')->default(false);
            $table->date('character_launch_date')->nullable();
            $table->integer('character_popularity_score')->nullable();
            $table->text('editor_notes_content_guidelines')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn([
                'sex',
                'page_heading',
                'page_sub_heading',
                'preferences',
                'loved_pet1',
                'loved_pet2',
                'loved_pet3',
                'hated_pet1',
                'hated_pet2',
                'hated_pet3',
                'character_page_url_slug',
                'public_private_toggle',
                'character_launch_date',
                'character_popularity_score',
                'editor_notes_content_guidelines',
            ]);
        });
    }
};
