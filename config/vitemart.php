<?php

return [
    // Application release; compared between HQ and branches during upgrades.
    'version' => '2026.11.0',

    // Enrollment codes are valid for this long.
    'enrollment_code_ttl_hours' => 24,

    // Sync
    'sync' => [
        'batch_size' => 500,
        'max_attempts' => 20,
        'pull_limit' => 500,
    ],

    // Roles that must use two-factor sign-in.
    // null = on for HQ/branch installs, off for a single store; true/false to force.
    'mfa_enforce' => env('VITEMART_MFA_ENFORCE') === null ? null : (bool) env('VITEMART_MFA_ENFORCE'),
    'mfa_required_roles' => ['Super Admin', 'HQ Admin', 'Finance Officer', 'Inventory Controller'],
];
