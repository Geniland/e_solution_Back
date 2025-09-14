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
        Schema::create('vote_payments', function (Blueprint $table) {
            $table->id();

            $table->decimal('amount', 10, 2);
            $table->unsignedBigInteger('candidate_id');
            $table->string('payment_status')->default('pending');
            $table->string('transaction_reference')->unique();
            $table->string('network');
            $table->string('visitor_token')->nullable();
            $table->string('ip_address')->nullable();

            $table->timestamps();

            // Si tu as une table candidates :
            $table->foreign('candidate_id')->references('id')->on('candidates')->onDelete('cascade');
      
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vote_payments');
    }
};
