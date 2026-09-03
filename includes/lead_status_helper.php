<?php
// includes/lead_status_helper.php

define('LEAD_STATUSES', [
    'fresh_lead' => [
        'label' => 'Fresh Lead',
        'color' => 'bg-slate-100 text-slate-700 border border-slate-200',
        'next' => ['connected', 'not_connected'],
        'schedule' => false,
        'terminal' => false
    ],
    'not_connected' => [
        'label' => 'Not Connected',
        'color' => 'bg-orange-100 text-orange-700 border border-orange-200',
        'next' => ['busy', 'not_picked'],
        'schedule' => false,
        'terminal' => false
    ],
    'busy' => [
        'label' => 'Busy',
        'color' => 'bg-yellow-100 text-yellow-700 border border-yellow-200',
        'next' => ['follow_up'],
        'schedule' => false,
        'terminal' => false
    ],
    'not_picked' => [
        'label' => 'Not Picked',
        'color' => 'bg-yellow-100 text-yellow-700 border border-yellow-200',
        'next' => ['follow_up'],
        'schedule' => false,
        'terminal' => false
    ],
    'follow_up' => [
        'label' => 'Follow Up',
        'color' => 'bg-blue-100 text-blue-700 border border-blue-200',
        'next' => ['connected', 'not_connected'],
        'schedule' => true,
        'terminal' => false
    ],
    'connected' => [
        'label' => 'Connected',
        'color' => 'bg-cyan-100 text-cyan-700 border border-cyan-200',
        'next' => ['interested', 'not_interested'],
        'schedule' => false,
        'terminal' => false
    ],
    'not_interested' => [
        'label' => 'Not Interested',
        'color' => 'bg-rose-100 text-rose-700 border border-rose-200',
        'next' => [],
        'schedule' => false,
        'terminal' => true
    ],
    'interested' => [
        'label' => 'Interested',
        'color' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
        'next' => ['visit_planned', 'visit_done', 'revisit_done'],
        'schedule' => false,
        'terminal' => false
    ],
    'visit_planned' => [
        'label' => 'Visit Planned',
        'color' => 'bg-indigo-100 text-indigo-700 border border-indigo-200',
        'next' => ['visit_done', 'revisit_done'],
        'schedule' => true,
        'terminal' => false
    ],
    'visit_done' => [
        'label' => 'Visit Done',
        'color' => 'bg-indigo-100 text-indigo-700 border border-indigo-200',
        'next' => ['revisit_done', 'booking_done', 'sale_lost'],
        'schedule' => false,
        'terminal' => false
    ],
    'revisit_done' => [
        'label' => 'Revisit Done',
        'color' => 'bg-purple-100 text-purple-700 border border-purple-200',
        'next' => ['booking_done', 'sale_lost'],
        'schedule' => true,
        'terminal' => false
    ],
    'booking_done' => [
        'label' => 'Booking / Sale Done',
        'color' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
        'next' => [],
        'schedule' => false,
        'terminal' => true
    ],
    'sale_lost' => [
        'label' => 'Sale Lost',
        'color' => 'bg-rose-200 text-rose-800 border border-rose-300',
        'next' => [],
        'schedule' => false,
        'terminal' => true
    ]
]);

/**
 * Maps old/legacy status values to new workflow status keys.
 * This handles leads that were NOT migrated via the migration script.
 */
define('LEGACY_STATUS_MAP', [
    'new'        => 'fresh_lead',
    'contacting' => 'follow_up',
    'qualified'  => 'interested',
    'lost'       => 'sale_lost',
    'closed'     => 'booking_done',
]);

/**
 * Normalizes a status value — converts any legacy status to the new key.
 * Always use this before looking up LEAD_STATUSES.
 */
function normalize_status($statusKey) {
    if (isset(LEGACY_STATUS_MAP[$statusKey])) {
        return LEGACY_STATUS_MAP[$statusKey];
    }
    return $statusKey;
}

/**
 * Returns the HTML badge for a given status.
 */
function get_status_badge($statusKey) {
    $statusKey = normalize_status($statusKey);
    if (!isset(LEAD_STATUSES[$statusKey])) {
        // Fallback for unknown status
        return '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-slate-100 text-slate-600 border border-slate-200">' . htmlspecialchars($statusKey) . '</span>';
    }
    
    $s = LEAD_STATUSES[$statusKey];
    return '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider ' . $s['color'] . '">' . htmlspecialchars($s['label']) . '</span>';
}

/**
 * Returns the label for a given status.
 */
function get_status_label($statusKey) {
    $statusKey = normalize_status($statusKey);
    if (!isset(LEAD_STATUSES[$statusKey])) return ucfirst($statusKey);
    return LEAD_STATUSES[$statusKey]['label'];
}
