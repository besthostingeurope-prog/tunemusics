<?php

namespace App\Console\Commands;

use App\Models\Artist;
use App\Models\ArtistPayoutMethod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedEarningsTestData extends Command
{
    protected $signature = 'earnings:seed-test
        {--count=5 : Number of test artists to create}
        {--followers=5 : Number of followers per artist}
        {--with-bank : Add bank details for artists}
        {--run-accrual : Run monthly accrual after seeding}';

    protected $description = 'Seed test data for earnings and payout system testing';

    public function handle()
    {
        $count = (int) $this->option('count');
        $followersCount = (int) $this->option('followers');
        $withBank = $this->option('with-bank');
        $runAccrual = $this->option('run-accrual');

        $this->info("Setting up earnings test data for {$count} artists...");
        $this->newLine();

        $createdArtists = [];

        for ($i = 1; $i <= $count; $i++) {
            $this->info("=== Creating Artist {$i}/{$count} ===");

            $email = "testartist{$i}@test.com";
            $artistFollowers = $followersCount + rand(0, 10); // Vary followers a bit

            // Step 1: Create or get test user
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => "Test Artist {$i}",
                    'username' => "testartist{$i}_" . time(),
                    'password' => bcrypt('password123'),
                    'email_verified_at' => now(),
                ]
            );
            $this->line("  User: {$user->email} (ID: {$user->id})");

            // Step 2: Create or get artist
            $artist = Artist::firstOrCreate(
                ['name' => "Test Artist {$i}"],
                [
                    'verified' => true,
                    'image_small' => null,
                ]
            );

            // Make sure artist is verified
            if (!$artist->verified) {
                $artist->update(['verified' => true]);
            }
            $this->line("  Artist: {$artist->name} (ID: {$artist->id})");

            // Step 3: Link user to artist
            $existingLink = DB::table('user_artist')
                ->where('user_id', $user->id)
                ->where('artist_id', $artist->id)
                ->first();

            if (!$existingLink) {
                DB::table('user_artist')->insert([
                    'user_id' => $user->id,
                    'artist_id' => $artist->id,
                    'role' => 'artist',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Step 4: Create Pro subscription
            $this->createProSubscription($user);

            // Step 5: Create followers
            $this->createFollowers($artist, $artistFollowers);

            // Step 6: Add bank details if requested (add to some artists randomly)
            if ($withBank || rand(0, 1) === 1) {
                $this->createBankDetails($artist, $i);
            }

            $createdArtists[] = [
                'email' => $user->email,
                'artist' => $artist->name,
                'followers' => $this->getCurrentFollowers($artist),
                'has_bank' => ArtistPayoutMethod::where('artist_id', $artist->id)->exists() ? 'Yes' : 'No',
            ];

            $this->newLine();
        }

        // Step 7: Run accrual if requested
        if ($runAccrual) {
            $this->info("Running monthly accrual...");
            $previousMonth = Carbon::now()->subMonth()->format('Y-m');
            $this->call('earnings:calculate-daily', ['--period' => $previousMonth]);
        }

        $this->newLine();
        $this->info("=== Test Data Summary ===");
        $this->table(
            ['Email', 'Artist', 'Followers', 'Bank Details'],
            $createdArtists
        );

        $this->newLine();
        $this->info("=== Test Credentials ===");
        $this->line("Password for all users: password123");

        $this->newLine();
        $this->info("=== Next Steps ===");
        $this->line("1. Login as any test user (e.g., testartist1@test.com / password123)");
        $this->line("2. Go to /backstage/earnings to see earnings page");
        $this->line("3. Run: php artisan earnings:calculate-daily --period=" . Carbon::now()->subMonth()->format('Y-m'));
        $this->line("4. Request payout from the earnings page");
        $this->line("5. Go to /admin/payouts to approve/reject the payout");

        $this->newLine();
        $this->info("=== Scheduled Job Testing ===");
        $this->line("To enable test mode (daily accrual at 21:20 UTC):");
        $this->line("1. Add EARNINGS_TEST_MODE=true to .env");
        $this->line("2. Run: php artisan config:clear");
        $this->line("3. Make sure queue worker is running: php artisan queue:work");
        $this->line("4. Make sure scheduler is running: php artisan schedule:work");

        return Command::SUCCESS;
    }

    protected function createProSubscription(User $user)
    {
        // Check if Pro product exists
        $proProduct = DB::table('products')
            ->whereRaw('LOWER(name) LIKE ?', ['%pro%'])
            ->where('free', false)
            ->first();

        if (!$proProduct) {
            // Create a Pro product
            $proProductId = DB::table('products')->insertGetId([
                'name' => 'Pro Plan',
                'description' => 'Pro subscription for artists',
                'free' => false,
                'position' => 1,
                'recommended' => true,
                'hidden' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $proProduct = DB::table('products')->find($proProductId);

            // Create a price for this product
            DB::table('prices')->insert([
                'product_id' => $proProduct->id,
                'amount' => 9.99,
                'currency' => 'EUR',
                'interval' => 'month',
                'interval_count' => 1,
                'default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Get price for this product
        $price = DB::table('prices')
            ->where('product_id', $proProduct->id)
            ->first();

        if (!$price) {
            // Create a price if it doesn't exist
            $priceId = DB::table('prices')->insertGetId([
                'product_id' => $proProduct->id,
                'amount' => 9.99,
                'currency' => 'EUR',
                'interval' => 'month',
                'interval_count' => 1,
                'default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $price = DB::table('prices')->find($priceId);
        }

        // Check if user already has subscription
        $existingSub = DB::table('subscriptions')
            ->where('user_id', $user->id)
            ->where('product_id', $proProduct->id)
            ->whereNull('ends_at')
            ->first();

        if (!$existingSub) {
            DB::table('subscriptions')->insert([
                'user_id' => $user->id,
                'product_id' => $proProduct->id,
                'price_id' => $price->id,
                'gateway_name' => 'manual',
                'gateway_id' => 'test_' . $user->id . '_' . time(),
                'quantity' => 1,
                'description' => 'Test Pro Subscription',
                'renews_at' => now()->addYear(),
                'ends_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function createFollowers(Artist $artist, int $count)
    {
        $existingFollowers = DB::table('likes')
            ->where('likeable_id', $artist->id)
            ->where('likeable_type', Artist::MODEL_TYPE)
            ->count();

        $needed = $count - $existingFollowers;

        if ($needed <= 0) {
            return;
        }

        // Create follower users
        for ($i = 0; $i < $needed; $i++) {
            $uniqueId = $artist->id . '_' . $i . '_' . time();
            $follower = User::firstOrCreate(
                ['email' => "follower_{$uniqueId}@test.com"],
                [
                    'name' => "Follower {$i}",
                    'username' => "follower_{$uniqueId}",
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ]
            );

            $exists = DB::table('likes')
                ->where('likeable_id', $artist->id)
                ->where('likeable_type', Artist::MODEL_TYPE)
                ->where('user_id', $follower->id)
                ->exists();

            if (!$exists) {
                DB::table('likes')->insert([
                    'likeable_id' => $artist->id,
                    'likeable_type' => Artist::MODEL_TYPE,
                    'user_id' => $follower->id,
                    'created_at' => now()->subDays(rand(1, 30)),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->line("  Followers: " . $this->getCurrentFollowers($artist));
    }

    protected function createBankDetails(Artist $artist, int $index)
    {
        $existing = ArtistPayoutMethod::where('artist_id', $artist->id)->first();

        if ($existing) {
            return;
        }

        // Create varied bank details
        $bankNames = ['Deutsche Bank', 'ING Bank', 'ABN AMRO', 'Rabobank', 'BNP Paribas'];
        $countries = ['Germany', 'Netherlands', 'France', 'Belgium', 'Austria'];

        ArtistPayoutMethod::create([
            'artist_id' => $artist->id,
            'type' => 'bank_transfer',
            'account_holder_name' => "Test Artist {$index}",
            'bank_name' => $bankNames[$index % count($bankNames)],
            'iban' => 'DE8937040044053201' . str_pad($index, 4, '0', STR_PAD_LEFT),
            'swift_code' => 'TEST' . strtoupper(substr(md5($index), 0, 4)),
            'country' => $countries[$index % count($countries)],
            'is_default' => true,
            'is_verified' => false,
        ]);

        $this->line("  Bank: Yes");
    }

    protected function getCurrentFollowers(Artist $artist): int
    {
        return DB::table('likes')
            ->where('likeable_id', $artist->id)
            ->where('likeable_type', Artist::MODEL_TYPE)
            ->count();
    }
}
