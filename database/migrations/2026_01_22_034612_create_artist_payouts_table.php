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
        Schema::create('artist_payouts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('artist_id')->index();
            $table->unsignedInteger('payout_method_id')->nullable();
            $table->integer('amount')->comment('Amount in cents');
            $table->string('currency')->default('EUR');
            $table->string('status')->default('pending')->comment('pending, approved, processing, completed, rejected, failed');
            $table->text('artist_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable()->comment('Admin user ID');
            $table->string('transaction_id')->nullable()->comment('External payment transaction ID');
            $table->timestamps();

            $table->foreign('artist_id')->references('id')->on('artists')->onDelete('cascade');
            $table->foreign('payout_method_id')->references('id')->on('artist_payout_methods')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artist_payouts');
    }
};
