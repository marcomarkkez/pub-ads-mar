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
            // design.json §10/§17 — ONE `chats` resource (tickets + conversations retired).
            'client' => [
                'campaigns'     => ['create', 'read', 'update', 'delete'],
                'adsets'        => ['create', 'read', 'update', 'delete'],
                'ads'           => ['create', 'read', 'update', 'delete'],
                'bookings'      => ['create', 'read', 'update'],
                'spaces'        => ['read'],
                'proofs'        => ['read'],
                // No 'update': zero routes are gated on chats,update — the lifecycle
                // verbs (close/reopen/join) all check chats,create. A grant nothing reads
                // is a permission that looks like a policy and enforces nothing.
                'chats'         => ['create', 'read'],
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
                'chats'                 => ['create', 'read'],
            ],

            'admin' => [
                // No 'delete' (owner 2026-08-03): the admin removes a user from their roles,
                // it never erases the account. PermissionController refuses to grant it back.
                'users'                 => ['create', 'read', 'update'],
                'campaigns'             => ['read'],
                'adsets'                => ['read'],
                'ads'                   => ['read'],
                'spaces'                => ['read'],
                'space_photos'          => ['read'],
                'space_availabilities'  => ['read'],
                'bookings'              => ['read'],
                'payments'              => ['read'],
                'proofs'                => ['read'],
                // Admin is READ-ONLY/incognito on chats (never posts — R1). Only
                // chats.read → the create-gated mutations 403 for admin at the mw.
                'chats'                 => ['read'],
                'invoices'              => ['read'],
                'collaborators'         => ['read'],
                'dashboard'             => ['read'],
                'configurations'        => ['read', 'update'],
                // UC-31 · §12 — Admin is the ONLY reader of the audit log, and
                // read is the only action that exists: it is append-only.
                'audit'                 => ['read'],
                // UC-29/UC-32 · §12/§8 — the audited moderation actions, the ONLY
                // place Admin writes: takedown/restore, freeze/unfreeze and the
                // payout stop (`update`); the clawback (`refund`) is split off so
                // the money-reversal power can be revoked on its own.
                'moderation'            => ['read', 'update', 'refund'],
            ],

            'support' => [
                // UC-23 · §11 — edit-any-NON-money object (audited). `update` on
                // CONTENT objects only; there is deliberately no `payments`,
                // `invoices` or wallet permission — Support flags, Payments
                // executes (§8). Money-determining FIELDS are excluded in
                // Support\ObjectEditController.
                'users'         => ['read', 'update'],
                'spaces'        => ['read', 'update'],
                'ads'           => ['read', 'update'],
                'bookings'      => ['read', 'update'],
                'collaborators' => ['read', 'update'],
                // create+read: Support posts/joins/flags/resolves via the /chats map
                // (no chats.update — lifecycle actions are gated on chats.create).
                'chats'     => ['create', 'read'],
                'dashboard' => ['read'], // [todo B8] support stats dashboard
            ],

            'payments' => [
                'payments' => ['read', 'update', 'refund'],
                // B9 · §2 gives Payments 👁 on Proof, NOT ✏: it inspects a proof to
                // understand a hold, and never reviews its content. `update` removed
                // together with the /payments/proofs routes (2026-08-02).
                'proofs'   => ['read'],
                'bookings' => ['read'],
                'invoices' => ['read'],
                // Payments reads + posts ONLY in the internal Support↔Payments chat
                // (controller ACL blocks it from client↔provider chats).
                'chats'    => ['create', 'read'],
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
