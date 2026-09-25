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
        'masterdata.view' => 'View master data',
        'masterdata.manage' => 'Create, edit and deactivate master data',
        'masterdata.import' => 'Import master data from Excel',
        'masterdata.export' => 'Export master data to Excel',
        'vendors.view_bank_details' => 'See and edit vendor bank details',
        'users.import' => 'Import users from Excel',
    ],

    /*
     * Seed roles for every new entity => permissions granted.
     * Roles without admin permissions still matter: workflow steps (Phase 4)
     * are assigned to them.
     */
    'default_roles' => [
        'Entity Admin' => [
            'admin.access', 'users.view', 'users.manage', 'users.import', 'roles.manage',
            'settings.manage', 'audit.view', 'audit.export',
            'masterdata.view', 'masterdata.manage', 'masterdata.import', 'masterdata.export',
        ],
        'Requester' => [],
        'Approver' => [],
        'Budget Approver' => [],
        'Validator' => [],
        'Validation Approver' => [],
        'Coordinator' => [],
        'Purchasing' => [],
        'Additional Authoriser' => [],
        'Payment Team' => ['admin.access', 'masterdata.view', 'vendors.view_bank_details'],
        'Payment Team Manager' => ['admin.access', 'masterdata.view', 'masterdata.export', 'vendors.view_bank_details'],
        'Viewer / Auditor' => ['admin.access', 'users.view', 'audit.view', 'masterdata.view', 'masterdata.export'],
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
