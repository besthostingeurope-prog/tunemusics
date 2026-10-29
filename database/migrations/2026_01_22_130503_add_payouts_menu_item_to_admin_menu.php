<?php

use Common\Settings\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $menus = Setting::where('name', 'menus')->first()->value ?? [];

        if (!count($menus)) {
            return;
        }

        foreach ($menus as $key => $menu) {
            if ($menu['name'] === 'Admin Sidebar') {
                if (!collect($menu['items'])->contains('label', 'Payouts')) {
                    $menus[$key]['items'][] = [
                        'label' => 'Payouts',
                        'action' => '/admin/payouts',
                        'type' => 'route',
                        'id' => 'payouts-' . uniqid(),
                        'icon' => [
                            [
                                'tag' => 'path',
                                'attr' => [
                                    'd' =>
                                        'M21 18v1c0 1.1-.9 2-2 2H5c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z',
                                ],
                            ],
                        ],
                    ];

                    Setting::where('name', 'menus')->update([
                        'value' => json_encode($menus),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $menus = Setting::where('name', 'menus')->first()->value ?? [];

        if (!count($menus)) {
            return;
        }

        foreach ($menus as $key => $menu) {
            if ($menu['name'] === 'Admin Sidebar') {
                $menus[$key]['items'] = collect($menu['items'])
                    ->reject(fn($item) => $item['label'] === 'Payouts')
                    ->values()
                    ->toArray();

                Setting::where('name', 'menus')->update([
                    'value' => json_encode($menus),
                ]);
            }
        }
    }
};
