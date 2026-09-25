<?php

/*
|--------------------------------------------------------------------------
| PACE application catalogue
|--------------------------------------------------------------------------
|
| Permissions are code-level capabilities (what a screen or action checks).
| Roles are data: the list below is only the starting set created for each
| new entity. Admins rename, add and change roles in the admin panel, and
| workflow steps are assigned to roles, never to names in code.
|
| No secrets and no entity-specific values belong in this file.
|
*/

return [

    'name' => 'PACE',
    'tagline' => 'Approvals at PACE',

    // Password for DemoUsersSeeder accounts. Local development only; the seeder refuses to run in production.
    'demo_password' => env('DEMO_PASSWORD', 'Pace-Demo-2026!'),

    'permissions' => [
        'admin.access' => 'Open the admin panel',
        'users.view' => 'View users',
        'users.manage' => 'Create, invite, edit, lock and deactivate users',
        'roles.manage' => 'Manage roles and their permissions',
        'settings.manage' => 'Manage entity settings',
        'audit.view' => 'View the audit log and login history',
        'audit.export' => 'Export the audit log',
    ],

    /*
     * Seed roles for every new entity => permissions granted.
     * Roles without admin permissions still matter: workflow steps (Phase 4)
     * are assigned to them.
     */
    'default_roles' => [
        'Entity Admin' => [
            'admin.access', 'users.view', 'users.manage', 'roles.manage',
            'settings.manage', 'audit.view', 'audit.export',
        ],
        'Requester' => [],
        'Approver' => [],
        'Budget Approver' => [],
        'Validator' => [],
        'Validation Approver' => [],
        'Coordinator' => [],
        'Purchasing' => [],
        'Additional Authoriser' => [],
        'Payment Team' => [],
        'Payment Team Manager' => [],
        'Viewer / Auditor' => ['admin.access', 'users.view', 'audit.view'],
    ],

    /*
     * Process modules. Only Capex is live; the rest show as "Coming soon".
     */
    'processes' => [
        'capex' => ['label' => 'Capex Requests', 'enabled' => true],
        'opex' => ['label' => 'OpEx & Other Expenses', 'enabled' => false],
        'petty_cash' => ['label' => 'Petty Cash & IOU', 'enabled' => false],
        'courier' => ['label' => 'Courier Requests', 'enabled' => false],
    ],

];
