<?php

namespace App\Console\Commands;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Common\Websockets\API\ReverbAPI;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ChatHealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:health-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the health of the chat system (database, Reverb connection, broadcasting)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Chat System Health Check ===');
        $this->newLine();

        $allPassed = true;

        // 1. Check database connectivity
        $this->info('1. Checking database connectivity...');
        try {
            DB::connection()->getPdo();
            $this->info('   ✓ Database connection: OK');
        } catch (\Exception $e) {
            $this->error('   ✗ Database connection: FAILED');
            $this->error('     Error: ' . $e->getMessage());
            $allPassed = false;
        }
        $this->newLine();

        // 2. Check chat tables exist
        $this->info('2. Checking chat tables...');
        try {
            $conversationCount = ChatConversation::count();
            $messageCount = ChatMessage::count();
            $this->info("   ✓ chat_conversations table: OK ($conversationCount conversations)");
            $this->info("   ✓ chat_messages table: OK ($messageCount messages)");
        } catch (\Exception $e) {
            $this->error('   ✗ Chat tables: FAILED');
            $this->error('     Error: ' . $e->getMessage());
            $allPassed = false;
        }
        $this->newLine();

        // 3. Check database indexes
        $this->info('3. Checking database indexes...');
        try {
            $conversationIndexes = DB::select("SHOW INDEX FROM chat_conversations WHERE Key_name LIKE 'idx_%'");
            $messageIndexes = DB::select("SHOW INDEX FROM chat_messages WHERE Key_name LIKE 'idx_%'");

            if (count($conversationIndexes) >= 3) {
                $this->info('   ✓ chat_conversations indexes: OK (' . count($conversationIndexes) . ' performance indexes)');
            } else {
                $this->warn('   ⚠ chat_conversations indexes: Missing some indexes. Run migrations.');
            }

            if (count($messageIndexes) >= 2) {
                $this->info('   ✓ chat_messages indexes: OK (' . count($messageIndexes) . ' performance indexes)');
            } else {
                $this->warn('   ⚠ chat_messages indexes: Missing some indexes. Run migrations.');
            }
        } catch (\Exception $e) {
            $this->warn('   ⚠ Could not check indexes: ' . $e->getMessage());
        }
        $this->newLine();

        // 4. Check broadcasting configuration
        $this->info('4. Checking broadcasting configuration...');
        $broadcastDriver = config('broadcasting.default');
        $this->info("   Broadcasting driver: $broadcastDriver");

        if ($broadcastDriver === 'reverb') {
            $reverbConfig = config('broadcasting.connections.reverb');
            $this->info('   Reverb configuration:');
            $this->info('     - Host: ' . ($reverbConfig['options']['host'] ?? 'N/A'));
            $this->info('     - Port: ' . ($reverbConfig['options']['port'] ?? 'N/A'));
            $this->info('     - Scheme: ' . ($reverbConfig['options']['scheme'] ?? 'N/A'));
            $this->info('     - App ID: ' . ($reverbConfig['app_id'] ?? 'N/A'));
            $this->info('     - App Key: ' . ($reverbConfig['key'] ?? 'N/A'));

            // 5. Check Reverb server connection
            $this->newLine();
            $this->info('5. Checking Reverb server connection...');
            try {
                $reverbApi = app(ReverbAPI::class);
                $channels = $reverbApi->getAllChannels();
                $this->info('   ✓ Reverb server: CONNECTED');
                $this->info("     Active channels: {$channels->count()}");
            } catch (\Exception $e) {
                $this->error('   ✗ Reverb server: DISCONNECTED');
                $this->error('     Error: ' . $e->getMessage());
                $this->warn('     Make sure Reverb is running: php artisan reverb:start');
                $allPassed = false;
            }
        } elseif ($broadcastDriver === 'pusher') {
            $pusherConfig = config('broadcasting.connections.pusher');
            $this->info('   Pusher configuration:');
            $this->info('     - App ID: ' . ($pusherConfig['app_id'] ?? 'N/A'));
            $this->info('     - Key: ' . ($pusherConfig['key'] ?? 'N/A'));
            $this->info('     - Cluster: ' . ($pusherConfig['options']['cluster'] ?? 'N/A'));
        } elseif ($broadcastDriver === 'null' || $broadcastDriver === 'log') {
            $this->warn('   ⚠ Broadcasting is disabled or set to log mode');
            $this->warn('     Real-time features will not work');
        }
        $this->newLine();

        // 6. Check queue configuration
        $this->info('6. Checking queue configuration...');
        $queueDriver = config('queue.default');
        $this->info("   Queue driver: $queueDriver");
        if ($queueDriver === 'sync') {
            $this->warn('   ⚠ Using sync queue - broadcasts are synchronous');
            $this->warn('     Consider using redis or database queue for better performance');
        } else {
            $this->info('   ✓ Using async queue driver');
        }
        $this->newLine();

        // Summary
        $this->info('=== Summary ===');
        if ($allPassed) {
            $this->info('✓ All critical checks passed!');
            return Command::SUCCESS;
        } else {
            $this->error('✗ Some checks failed. Please review the errors above.');
            return Command::FAILURE;
        }
    }
}
