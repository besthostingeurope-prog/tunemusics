<?php

namespace App\Console\Commands;

use App\Models\Artist;
use App\Models\ArtistEarning;
use Carbon\Carbon;
use Common\Billing\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CalculateDailyArtistEarnings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'earnings:calculate-daily {--date= : Date to calculate earnings for (Y-m-d format, defaults to yesterday)} {--period= : Period to calculate (Y-m format, for monthly accrual)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate artist earnings. Daily tracking (pending) or monthly accrual (accrued) on 5th';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $period = $this->option('period');
        $isMonthlyAccrual = $period || Carbon::now()->day === 5;

        if ($isMonthlyAccrual) {
            return $this->handleMonthlyAccrual($period);
        }

        return $this->handleDailyTracking();
    }

    protected function handleDailyTracking()
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();

        $this->info("Calculating daily earnings (analytics) for: {$date->toDateString()}");
        $this->newLine();

        // Debug: Count verified artists
        $totalVerified = Artist::where('verified', true)->count();
        $this->info("Total verified artists: {$totalVerified}");

        // Get all verified artists where the user has active Pro/VIP subscriptions
        // We pay users who have artist profiles, so we check user's subscription
        $artistIds = Artist::where('artists.verified', true)
            ->join('user_artist', function ($join) {
                $join->on('artists.id', '=', 'user_artist.artist_id')
                    ->where('user_artist.role', '=', 'artist');
            })
            ->join('users', 'user_artist.user_id', '=', 'users.id')
            ->join('subscriptions', function ($join) {
                $join->on('users.id', '=', 'subscriptions.user_id')
                    ->where(function ($q) {
                        $q->whereNull('subscriptions.ends_at')
                            ->orWhere('subscriptions.ends_at', '>', now());
                    });
            })
            ->join('products', 'subscriptions.product_id', '=', 'products.id')
            ->where(function ($query) {
                $query->whereRaw('LOWER(products.name) LIKE ?', ['%pro%'])
                    ->orWhereRaw('LOWER(products.name) LIKE ?', ['%vip%'])
                    ->orWhereRaw('LOWER(products.name) LIKE ?', ['%artist%']);
            })
            ->where('products.free', false)
            ->select('artists.id', 'artists.name', 'products.name as product_name', 'users.id as user_id', 'users.email as user_email')
            ->distinct()
            ->get();

        $this->info("Verified artists where user has active Pro/VIP subscription: {$artistIds->count()}");

        if ($artistIds->count() > 0) {
            $this->info("Sample artists (first 5):");
            foreach ($artistIds->take(5) as $item) {
                $this->line("  - Artist ID: {$item->id}, Name: {$item->name}, User: {$item->user_email} (ID: {$item->user_id}), Product: {$item->product_name}");
            }
        }

        $artistIds = $artistIds->pluck('id');

        // Get artists with at least 1 follower (count from likes table)
        $artists = Artist::whereIn('artists.id', $artistIds)
            ->get()
            ->map(function ($artist) {
                $followersCount = DB::table('likes')
                    ->where('likeable_id', $artist->id)
                    ->where('likeable_type', Artist::MODEL_TYPE)
                    ->count();
                $artist->followers_count = $followersCount;
                return $artist;
            })
            ->filter(function ($artist) {
                return $artist->followers_count > 0;
            });

        $this->info("Verified artists with Pro/VIP subscription AND at least 1 follower: {$artists->count()}");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($artists->count());
        $progressBar->start();

        $created = 0;
        $skipped = 0;

        foreach ($artists as $artist) {
            // Check if earnings already exist for this date
            $exists = ArtistEarning::where('artist_id', $artist->id)
                ->where('earning_date', $date)
                ->exists();

            if ($exists) {
                $skipped++;
                $progressBar->advance();
                continue;
            }

            // Calculate earnings
            $earnings = ArtistEarning::calculateEarnings($artist->followers_count);

            // Daily tracking is always pending (analytics only, not payable)
            // Create earning record
            ArtistEarning::create([
                'artist_id' => $artist->id,
                'earning_date' => $date,
                'followers_count' => $artist->followers_count,
                'gross_amount' => $earnings['gross_amount'],
                'platform_fee' => $earnings['platform_fee'],
                'net_amount' => $earnings['net_amount'],
                'status' => 'pending',
            ]);

            $created++;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("✓ Created {$created} earning records");
        $this->info("✓ Skipped {$skipped} existing records");
        $this->info("✓ Earnings are marked as pending (analytics only, not payable)");

        return Command::SUCCESS;
    }

    protected function handleMonthlyAccrual(?string $period = null)
    {
        $today = Carbon::now();

        if ($period) {
            // Validate period format
            if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
                $this->error("Invalid period format: {$period}. Expected format: YYYY-MM");
                return Command::FAILURE;
            }
            $previousMonth = Carbon::parse($period . '-01');
        } else {
            $previousMonth = $today->copy()->subMonth();
        }

        $periodString = $previousMonth->format('Y-m');
        $periodEndDate = $previousMonth->copy()->endOfMonth();

        $this->info("Calculating monthly accrual for period: {$periodString}");
        $this->info("Period end date: {$periodEndDate->toDateString()}");
        $this->newLine();

        $artistIds = Artist::where('artists.verified', true)
            ->join('user_artist', function ($join) {
                $join->on('artists.id', '=', 'user_artist.artist_id')
                    ->where('user_artist.role', '=', 'artist');
            })
            ->join('users', 'user_artist.user_id', '=', 'users.id')
            ->join('subscriptions', function ($join) {
                $join->on('users.id', '=', 'subscriptions.user_id')
                    ->where(function ($q) {
                        $q->whereNull('subscriptions.ends_at')
                            ->orWhere('subscriptions.ends_at', '>', now());
                    });
            })
            ->join('products', 'subscriptions.product_id', '=', 'products.id')
            ->where(function ($query) {
                $query->whereRaw('LOWER(products.name) LIKE ?', ['%pro%'])
                    ->orWhereRaw('LOWER(products.name) LIKE ?', ['%vip%'])
                    ->orWhereRaw('LOWER(products.name) LIKE ?', ['%artist%']);
            })
            ->where('products.free', false)
            ->select('artists.id')
            ->distinct()
            ->pluck('id');

        $artists = Artist::whereIn('artists.id', $artistIds)
            ->get()
            ->map(function ($artist) use ($periodEndDate) {
                $followersCount = DB::table('likes')
                    ->where('likeable_id', $artist->id)
                    ->where('likeable_type', Artist::MODEL_TYPE)
                    ->where('created_at', '<=', $periodEndDate->endOfDay())
                    ->count();
                $artist->followers_count = $followersCount;
                return $artist;
            })
            ->filter(function ($artist) {
                // Include all artists, even with 0 followers (they get 0 amount accrued)
                return true;
            });

        // Get artists who have pending/approved/processing payout requests for this period
        // These should NOT be updated to prevent changing amounts after payout was requested
        $artistsWithPendingPayouts = \App\Models\ArtistPayout::whereIn('artist_id', $artistIds)
            ->whereIn('status', ['pending', 'approved', 'processing'])
            ->where(function ($query) use ($periodString) {
                $query->where('period', $periodString)
                    ->orWhere('period', 'like', "{$periodString},%")
                    ->orWhere('period', 'like', "%,{$periodString},%")
                    ->orWhere('period', 'like', "%,{$periodString}");
            })
            ->pluck('artist_id')
            ->toArray();

        $this->info("Processing {$artists->count()} artists for period accrual");
        if (count($artistsWithPendingPayouts) > 0) {
            $this->info("Skipping " . count($artistsWithPendingPayouts) . " artists with pending payout requests");
        }
        $this->newLine();

        $progressBar = $this->output->createProgressBar($artists->count());
        $progressBar->start();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($artists as $artist) {
            // Skip artists with pending payout requests for this period
            if (in_array($artist->id, $artistsWithPendingPayouts)) {
                $skipped++;
                $progressBar->advance();
                continue;
            }

            $earnings = ArtistEarning::calculateEarnings($artist->followers_count);

            $existing = ArtistEarning::where('artist_id', $artist->id)
                ->where('period', $periodString)
                ->where('status', 'accrued')
                ->first();

            if ($existing) {
                $existing->update([
                    'earning_date' => $periodEndDate,
                    'followers_count' => $artist->followers_count,
                    'gross_amount' => $earnings['gross_amount'],
                    'platform_fee' => $earnings['platform_fee'],
                    'net_amount' => $earnings['net_amount'],
                ]);
                $updated++;
            } else {
                ArtistEarning::create([
                    'artist_id' => $artist->id,
                    'period' => $periodString,
                    'earning_date' => $periodEndDate,
                    'followers_count' => $artist->followers_count,
                    'gross_amount' => $earnings['gross_amount'],
                    'platform_fee' => $earnings['platform_fee'],
                    'net_amount' => $earnings['net_amount'],
                    'status' => 'accrued',
                ]);
                $created++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("✓ Created {$created} accrued earning records");
        $this->info("✓ Updated {$updated} existing accrued records");
        $this->info("✓ Skipped {$skipped} artists with pending payout requests");
        $this->info("✓ Earnings are marked as accrued (ready for payout)");

        return Command::SUCCESS;
    }
}
