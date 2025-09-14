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
       Schema::create('candidates', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('category_id');
        $table->string('first_name');
        $table->string('last_name');
        $table->string('phone');
        $table->integer('position_number')->nullable();
        $table->string('photo')->nullable();
        $table->text('bio')->nullable();
        $table->text('social_links')->nullable(); // format JSON
        $table->string('video_url')->nullable();
        $table->unsignedBigInteger('created_by');
        $table->timestamps();

        $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
