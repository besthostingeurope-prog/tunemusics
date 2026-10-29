<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AddPayoutsMenuItem extends Command
{
    protected $signature = 'menu:add-payouts';
    protected $description = 'Add Payouts menu item to admin sidebar';

    public function handle()
    {
        // Find the admin-sidebar menu
        $menu = DB::table('menus')->where('name', 'admin-sidebar')->first();

        if (!$menu) {
            $this->error('Admin sidebar menu not found!');
            return 1;
        }

        // Check if payouts menu item already exists
        $existing = DB::table('menu_items')
            ->where('menu_id', $menu->id)
            ->where('action', '/admin/payouts')
            ->exists();

        if ($existing) {
            $this->info('Payouts menu item already exists!');
            return 0;
        }

        // Find the max order to add at the end
        $maxOrder = DB::table('menu_items')
            ->where('menu_id', $menu->id)
            ->max('order') ?? 0;

        // Insert the payouts menu item
        DB::table('menu_items')->insert([
            'menu_id' => $menu->id,
            'label' => 'Payouts',
            'action' => '/admin/payouts',
            'type' => 'route',
            'order' => $maxOrder + 1,
            'icon' => 'account-balance-wallet',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info('✓ Successfully added Payouts menu item to admin sidebar');
        $this->line('  Position: ' . ($maxOrder + 1));
        $this->line('  Route: /admin/payouts');

        return 0;
    }
}
