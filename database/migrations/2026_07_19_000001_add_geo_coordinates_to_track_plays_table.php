<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('track_plays', function (Blueprint $table) {
            if (!Schema::hasColumn('track_plays', 'lat')) {
                $table->decimal('lat', 9, 6)->nullable()->after('location');
            }
            if (!Schema::hasColumn('track_plays', 'lon')) {
                $table->decimal('lon', 9, 6)->nullable()->after('lat');
            }
            if (!Schema::hasColumn('track_plays', 'city')) {
                $table->string('city', 100)->nullable()->after('lon');
            }
        });
    }

    public function down(): void
    {
        Schema::table('track_plays', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lon', 'city']);
        });
    }
};
