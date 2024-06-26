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
        Schema::create('order_logos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_basket_id');
            $table->string('logo_path');
            $table->timestamps();
            $table->foreign('order_basket_id')->references('id')->on('order_baskets')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_logos');
    }
};