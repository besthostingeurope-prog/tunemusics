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
            // Check by positions array (preferred) or name
            $isAdminSidebar = 
                (isset($menu['positions']) && in_array('admin-sidebar', $menu['positions'])) ||
                (isset($menu['name']) && $menu['name'] === 'Admin Sidebar');
            
            if ($isAdminSidebar) {
                if (!collect($menu['items'])->contains('label', 'Chat Management')) {
                    $menus[$key]['items'][] = [
                        'label' => 'Chat Management',
                        'action' => '/admin/chat-conversations',
                        'type' => 'route',
                        'id' => 'chat-mgmt-001',
                        'permissions' => ['chat.moderate'],
                        'icon' => [
                            [
                                'tag' => 'path',
                                'attr' => [
                                    'd' =>
                                        'M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z',
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
            $isAdminSidebar = 
                (isset($menu['positions']) && in_array('admin-sidebar', $menu['positions'])) ||
                (isset($menu['name']) && $menu['name'] === 'Admin Sidebar');
            
            if ($isAdminSidebar) {
                $menus[$key]['items'] = array_filter(
                    $menu['items'],
                    fn($item) => $item['label'] !== 'Chat Management',
                );
                $menus[$key]['items'] = array_values($menus[$key]['items']);

                Setting::where('name', 'menus')->update([
                    'value' => json_encode($menus),
                ]);
            }
        }
    }
};
