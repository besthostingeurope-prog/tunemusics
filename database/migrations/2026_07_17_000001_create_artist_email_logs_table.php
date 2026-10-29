<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('artist_email_logs')) {
            Schema::create('artist_email_logs', function (Blueprint $table) {
                $table->id();
                $table->string('subject');
                $table->text('body');
                $table->boolean('is_html')->default(false);
                $table->unsignedInteger('sent_count')->default(0);
                $table->unsignedInteger('total_count')->default(0);
                $table->json('recipients')->nullable();
                $table->json('failed')->nullable();
                $table->unsignedInteger('sent_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_email_logs');
    }
};
