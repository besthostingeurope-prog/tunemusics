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
        Schema::create('artist_earnings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('artist_id')->index();
            $table->date('earning_date')->index();
            $table->integer('followers_count')->default(0);
            $table->integer('gross_amount')->default(0)->comment('Amount in cents before platform fee');
            $table->integer('platform_fee')->default(0)->comment('10% platform fee in cents');
            $table->integer('net_amount')->default(0)->comment('Amount in cents after platform fee');
            $table->string('status')->default('pending')->comment('pending, accrued, paid');
            $table->timestamps();

            $table->unique(['artist_id', 'earning_date']);
            $table->foreign('artist_id')->references('id')->on('artists')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artist_earnings');
    }
};
