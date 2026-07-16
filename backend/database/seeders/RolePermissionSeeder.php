<?php

namespace Database\Seeders;

use App\Models\RolePermission;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        RolePermission::truncate();

        $permissions = [
            'client' => [
                'campaigns'     => ['create', 'read', 'update', 'delete'],
                'adsets'        => ['create', 'read', 'update', 'delete'],
                'ads'           => ['create', 'read', 'update', 'delete'],
                'bookings'      => ['create', 'read', 'update'],
                'spaces'        => ['read'],
                'proofs'        => ['read'],
                'conversations' => ['create', 'read'],
                'tickets'       => ['create', 'read'],
                'collaborators' => ['create', 'read', 'delete'],
                'invoices'      => ['read'],
            ],

            'provider' => [
                'spaces'                => ['create', 'read', 'update', 'delete'],
                'space_photos'          => ['create', 'delete'],
                'space_availabilities'  => ['create', 'read', 'delete'],
                'bookings'              => ['read', 'update'],
                'dashboard'             => ['read'],
                'proofs'                => ['create', 'read'],
                'conversations'         => ['create', 'read'],
                'tickets'               => ['create', 'read'],
            ],

            'admin' => [
                'users'                 => ['create', 'read', 'update', 'delete'],
                'campaigns'             => ['read'],
                'adsets'                => ['read'],
                'ads'                   => ['read'],
                'spaces'                => ['read'],
                'space_photos'          => ['read'],
                'space_availabilities'  => ['read'],
                'bookings'              => ['read'],
                'payments'              => ['read'],
                'proofs'                => ['read'],
                'tickets'               => ['read'],
                'conversations'         => ['read'],
                'invoices'              => ['read'],
                'collaborators'         => ['read'],
                'dashboard'             => ['read'],
                'configurations'        => ['read', 'update'],
            ],

            'support' => [
                'tickets'       => ['create', 'read', 'update'],
                // 'create' so Support can POST replies in the threads it joins /
                // anchors (dispute threads); the messages route gates on it.
                'conversations' => ['read', 'create'],
                'users'         => ['read'],
                'dashboard'     => ['read'], // [todo B8] support stats dashboard
            ],

            'payments' => [
                'payments' => ['read', 'update', 'refund'],
                'proofs'   => ['read', 'update'],
                'bookings' => ['read'],
                'invoices' => ['read'],
                // Payments reads + posts ONLY in the internal Support↔Payments
                // thread (controller ACL blocks it from client↔provider threads).
                'conversations' => ['read', 'create'],
                'dashboard' => ['read'], // [todo B8] payments stats dashboard
            ],
        ];

        $rows = [];
        $now = now();

        foreach ($permissions as $role => $resources) {
            foreach ($resources as $resource => $actions) {
                foreach ($actions as $action) {
                    $rows[] = [
                        'role'       => $role,
                        'resource'   => $resource,
                        'action'     => $action,
                        'allowed'    => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        RolePermission::insert($rows);
        RolePermission::clearCache();
    }
}
