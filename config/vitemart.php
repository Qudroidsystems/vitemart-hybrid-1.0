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
        // Feed rows younger than this are held back one pull, so a row still being written is never skipped.
        'feed_settle_seconds' => (int) env('VITEMART_FEED_SETTLE_SECONDS', 2),
        // Settings head office decides for every branch (others stay per branch).
        'central_settings' => [
            'max_discount_percent', 'block_sale_below_cost', 'allow_negative_stock', 'adjustment_requires_approval',
            'transfer_needs_confirmation', 'card_needs_terminal_ref', 'require_open_shift',
            'scale_barcode_enabled', 'scale_barcode_mode', 'scale_barcode_decimals', 'barcode_prefix',
            'loyalty_points_per_amount', 'loyalty_redeem_rate', 'vat_rate', 'prices_include_tax',
        ],
    ],

    // Roles that must use two-factor sign-in.
    // null = on for HQ/branch installs, off for a single store; true/false to force.
    'mfa_enforce' => env('VITEMART_MFA_ENFORCE') === null ? null : (bool) env('VITEMART_MFA_ENFORCE'),
    'mfa_required_roles' => ['Super Admin', 'HQ Admin', 'Finance Officer', 'Inventory Controller'],
];
