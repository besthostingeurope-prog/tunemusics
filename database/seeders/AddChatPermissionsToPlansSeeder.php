<?php

namespace Database\Seeders;

use Common\Auth\Permissions\Permission;
use Common\Billing\Models\Product;
use Illuminate\Database\Seeder;

class AddChatPermissionsToPlansSeeder extends Seeder
{
    public function run()
    {
        $chatViewId = Permission::where('name', 'chat.view')->value('id');
        $chatSendId = Permission::where('name', 'chat.send')->value('id');

        if (!$chatViewId || !$chatSendId) {
            echo "ERROR: chat.view or chat.send not found. Run: php artisan db:seed --class=Common\\\\Database\\\\Seeders\\\\PermissionTableSeeder\n";
            return;
        }

        $plans = [
            ['name' => 'Basic', 'chat_send_restrictions' => [['name' => 'max_conversations', 'value' => 50]]],
            ['name' => 'Pro Unlimited', 'chat_send_restrictions' => [['name' => 'max_conversations', 'value' => null], ['name' => 'max_recipient_followers', 'value' => null]]],
            ['name' => 'Artist Pro', 'chat_send_restrictions' => [['name' => 'max_conversations', 'value' => null], ['name' => 'max_recipient_followers', 'value' => null]]],
        ];

        foreach ($plans as $planConfig) {
            $product = Product::where('name', $planConfig['name'])->first();
            if (!$product) {
                continue;
            }

            if (!$product->permissions()->where('permissions.id', $chatViewId)->exists()) {
                $product->permissions()->attach($chatViewId, ['restrictions' => json_encode([])]);
                echo "Added chat.view to {$planConfig['name']}\n";
            }

            if (!$product->permissions()->where('permissions.id', $chatSendId)->exists()) {
                $product->permissions()->attach($chatSendId, [
                    'restrictions' => json_encode($planConfig['chat_send_restrictions']),
                ]);
                echo "Added chat.send to {$planConfig['name']}\n";
            }
        }

        echo "Done. No existing permissions were changed or removed.\n";
    }
}
