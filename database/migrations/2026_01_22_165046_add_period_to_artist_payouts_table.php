<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('artist_payouts', function (Blueprint $table) {
            $table->string('period', 255)->nullable()->after('artist_id')->index()->comment('YYYY-MM format (or comma-separated for multiple periods), links to earnings period');
        });

        DB::statement("UPDATE artist_payouts SET period = DATE_FORMAT(requested_at, '%Y-%m') WHERE requested_at IS NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('artist_payouts', function (Blueprint $table) {
            $table->dropColumn('period');
        });
    }
};
