<?php

use App\Console\Commands\ResetDemoAdminAccount;
use App\Console\Commands\SeedSampleData;
use Carbon\Carbon;
use Common\Channels\UpdateAllChannelsContent;
use Illuminate\Support\Facades\Schedule;

Schedule::command(UpdateAllChannelsContent::class)
    ->dailyAt('03:20')
    ->withoutOverlapping();

Schedule::command('chat:cleanup')
    ->daily()
    ->withoutOverlapping();

// Monthly accrual calculation - runs on 5th of each month at 02:00 UTC
// For testing: set EARNINGS_TEST_MODE=true in .env to run daily at 21:20 UTC
if (config('app.earnings_test_mode')) {
    // TEST MODE: Run daily at 21:20 UTC for testing
    Schedule::command('earnings:calculate-daily --period=' . Carbon::now()->subMonth()->format('Y-m'))
        ->dailyAt('21:20')
        ->timezone('UTC')
        ->withoutOverlapping();
} else {
    // PRODUCTION: Run on 5th of each month at 02:00 UTC
    Schedule::command('earnings:calculate-daily --period=' . Carbon::now()->subMonth()->format('Y-m'))
        ->monthlyOn(5, '02:00')
        ->timezone('UTC')
        ->withoutOverlapping();
}

if (config('app.demo')) {
    Schedule::command(ResetDemoAdminAccount::class)
        ->dailyAt('03:30')
        ->withoutOverlapping();

    if (str_contains(config('app.url'), 'bemusic.2')) {
        Schedule::command(SeedSampleData::class)
            ->weeklyOn(6, '1:30');
    }
}
