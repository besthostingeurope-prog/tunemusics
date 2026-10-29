<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupLandingPageSettings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'settings:cleanup-landing-page';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove stale dot-notation settings that prevent landing page from saving correctly.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting landing page settings cleanup...');

        $count = DB::table('settings')
            ->where('name', 'like', 'landingPage.%')
            ->count();

        if ($count === 0) {
            $this->info('No stale settings found. Your database is already clean.');
            return 0;
        }

        if ($this->confirm("Found $count stale records. Do you want to delete them? (Your main landing page design WILL NOT be deleted)")) {
            DB::table('settings')
                ->where('name', 'like', 'landingPage.%')
                ->delete();

            $this->info('Cleanup successful! Your landing page settings should now persist correctly.');
        } else {
            $this->info('Cleanup canceled.');
        }

        return 0;
    }
}
