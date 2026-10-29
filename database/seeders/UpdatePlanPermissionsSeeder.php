<?php

namespace Database\Seeders;

use Common\Auth\Permissions\Permission;
use Common\Billing\Models\Product;
use Illuminate\Database\Seeder;

class UpdatePlanPermissionsSeeder extends Seeder
{
    public function run()
    {
        $permissionIds = Permission::pluck('id', 'name');

        // Check which permissions exist
        echo "Available permissions: " . implode(', ', $permissionIds->keys()->toArray()) . "\n\n";

        // Check for required permissions
        $requiredPermissions = [
            'music.create',
            'backstageRequests.create',
            'artists.follow',
            'tracks.like',
            'files.create',
            'chat.view',
            'chat.send',
        ];
        $missingPermissions = [];

        foreach ($requiredPermissions as $permission) {
            if (!isset($permissionIds[$permission])) {
                $missingPermissions[] = $permission;
            }
        }

        if (!empty($missingPermissions)) {
            echo "ERROR: Missing permissions: " . implode(', ', $missingPermissions) . "\n";
            echo "Please run: php artisan db:seed --class=Common\\\\Database\\\\Seeders\\\\PermissionTableSeeder\n";
            return;
        }

        // Update Basic plan
        $basicPlan = Product::where('name', 'Basic')->first();
        if ($basicPlan) {
            $this->updatePlanPermissions($basicPlan, [
                [
                    'id' => $permissionIds['music.create'],
                    'restrictions' => [
                        ['name' => 'minutes', 'value' => 180],
                    ],
                ],
                [
                    'id' => $permissionIds['artists.follow'],
                    'restrictions' => [
                        ['name' => 'count', 'value' => 50],
                    ],
                ],
                [
                    'id' => $permissionIds['tracks.like'],
                    'restrictions' => [
                        ['name' => 'count', 'value' => 100],
                    ],
                ],
                [
                    'id' => $permissionIds['files.create'],
                    'restrictions' => [],
                ],
                [
                    'id' => $permissionIds['chat.view'],
                    'restrictions' => [],
                ],
                [
                    'id' => $permissionIds['chat.send'],
                    'restrictions' => [
                        ['name' => 'max_conversations', 'value' => 50],
                    ],
                ],
            ]);
            echo "Updated Basic plan permissions\n";
        }

        // Update Pro Unlimited plan
        $proPlan = Product::where('name', 'Pro Unlimited')->first();
        if ($proPlan) {
            $this->updatePlanPermissions($proPlan, [
                [
                    'id' => $permissionIds['music.create'],
                    'restrictions' => [
                        ['name' => 'minutes', 'value' => null],
                    ],
                ],
                [
                    'id' => $permissionIds['backstageRequests.create'],
                    'restrictions' => [],
                ],
                [
                    'id' => $permissionIds['artists.follow'],
                    'restrictions' => [
                        ['name' => 'count', 'value' => null],
                    ],
                ],
                [
                    'id' => $permissionIds['tracks.like'],
                    'restrictions' => [
                        ['name' => 'count', 'value' => null],
                    ],
                ],
                [
                    'id' => $permissionIds['files.create'],
                    'restrictions' => [],
                ],
                [
                    'id' => $permissionIds['chat.view'],
                    'restrictions' => [],
                ],
                [
                    'id' => $permissionIds['chat.send'],
                    'restrictions' => [
                        ['name' => 'max_conversations', 'value' => null],
                        ['name' => 'max_recipient_followers', 'value' => null],
                    ],
                ],
            ]);
            echo "Updated Pro Unlimited plan permissions\n";
        }

        // Update Artist Pro plan (if it exists)
        $artistProPlan = Product::where('name', 'Artist Pro')->first();
        if ($artistProPlan) {
            $this->updatePlanPermissions($artistProPlan, [
                [
                    'id' => $permissionIds['music.create'],
                    'restrictions' => [
                        ['name' => 'minutes', 'value' => null],
                    ],
                ],
                [
                    'id' => $permissionIds['backstageRequests.create'],
                    'restrictions' => [],
                ],
                [
                    'id' => $permissionIds['artists.follow'],
                    'restrictions' => [
                        ['name' => 'count', 'value' => null],
                    ],
                ],
                [
                    'id' => $permissionIds['tracks.like'],
                    'restrictions' => [
                        ['name' => 'count', 'value' => null],
                    ],
                ],
                [
                    'id' => $permissionIds['files.create'],
                    'restrictions' => [],
                ],
                [
                    'id' => $permissionIds['chat.view'],
                    'restrictions' => [],
                ],
                [
                    'id' => $permissionIds['chat.send'],
                    'restrictions' => [
                        ['name' => 'max_conversations', 'value' => null],
                        ['name' => 'max_recipient_followers', 'value' => null],
                    ],
                ],
            ]);
            echo "Updated Artist Pro plan permissions\n";
        }
    }

    protected function updatePlanPermissions(Product $product, array $permissions)
    {
        // Detach all current permissions
        $product->permissions()->detach();

        // Attach new permissions
        foreach ($permissions as $permission) {
            $product->permissions()->attach($permission['id'], [
                'restrictions' => json_encode($permission['restrictions']),
            ]);
        }
    }
}
