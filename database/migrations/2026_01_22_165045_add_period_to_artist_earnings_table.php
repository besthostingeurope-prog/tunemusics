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
        Schema::table('artist_earnings', function (Blueprint $table) {
            $table->string('period', 7)->nullable()->after('earning_date')->index()->comment('YYYY-MM format for monthly periods');
        });

        DB::statement("UPDATE artist_earnings SET period = DATE_FORMAT(earning_date, '%Y-%m') WHERE status = 'accrued'");

        Schema::table('artist_earnings', function (Blueprint $table) {
            $table->string('status')->default('pending')->comment('pending (analytics only), accrued (payable), paid, rejected')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('artist_earnings', function (Blueprint $table) {
            $table->dropColumn('period');
            $table->string('status')->default('pending')->comment('pending, accrued, paid')->change();
        });
    }
};
