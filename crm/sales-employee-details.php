<?php
// crm/sales-employee-details.php
$pageTitle = "Employee Performance Details";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/lead_status_helper.php';

// Verify permission
if (!in_array($currentUser['role_name'], ['Admin', 'Super Admin', 'Meta Manager'])) {
    die("<div class='p-8 text-center text-rose-500 font-bold'>Unauthorized Access.</div>");
}

$employeeId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';

// Fetch Employee Details
$stmtAgent = $pdo->prepare("SELECT id, username, email FROM users WHERE id = ? AND status = 'active'");
$stmtAgent->execute([$employeeId]);
$agent = $stmtAgent->fetch();

if (!$agent) {
    die("<div class='p-8 text-center text-rose-500 font-bold'>Employee not found or inactive.</div>");
}

// Fetch their Leads
$query = "
    SELECT i.*, p.title as property_title 
    FROM inquiries i 
    LEFT JOIN properties p ON i.property_id = p.id 
    WHERE i.assigned_to = ?
";
$params = [$employeeId];

if (!empty($filterStatus)) {
    $query .= " AND i.status = ?";
    $params[] = $filterStatus;
}
$query .= " ORDER BY i.created_at DESC";

$stmtLeads = $pdo->prepare($query);
$stmtLeads->execute($params);
$leads = $stmtLeads->fetchAll();

// Group statuses into Tabs for easy filtering (High level)
// You can adjust these buckets based on what's most useful for the Admin
$statusTabs = [
    '' => 'All Leads',
    'fresh_lead' => 'Fresh Leads',
    'follow_up' => 'Follow Up Due',
    'interested' => 'Interested',
    'visit_planned' => 'Visits Planned',
    'booking_done' => 'Won / Closed',
    'sale_lost' => 'Lost'
];
?>

<div class="flex items-center gap-4 mb-6">
    <a href="sales-performance.php" class="p-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
    </a>
    <div>
        <h2 class="text-xl font-black text-slate-800"><?= htmlspecialchars($agent['username']) ?>'s Assigned Leads</h2>
        <div class="text-xs text-slate-400 mt-1 flex gap-3">
            <span>Employee ID: #<?= $agent['id'] ?></span>
            <span><?= htmlspecialchars($agent['email']) ?></span>
        </div>
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6 mb-8">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div class="flex flex-wrap gap-2">
            <?php foreach ($statusTabs as $k => $label): 
                $isActive = $filterStatus === $k;
            ?>
                <a href="sales-employee-details.php?id=<?= $employeeId ?><?= $k ? '&status='.$k : '' ?>" 
                   class="px-3 py-1.5 rounded-lg text-xs font-bold transition <?= $isActive ? 'bg-slate-800 text-white shadow-sm' : 'bg-slate-50 text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <div class="flex gap-2">
            <?php
            $exportQuery = http_build_query([
                'module' => 'inquiries', // Export uses inquiries but we can filter by assigned_to in next update if needed, actually export.php doesn't support assigned_to yet, we can add it or just export all
            ]);
            // Currently export.php doesn't have an assigned_to filter, so it might export all if they click here.
            // A quick fix is to pass assigned_to=X to export.php and update export.php to respect it.
            ?>
        </div>
    </div>

    <!-- Leads Table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle text-sm">
            <thead class="text-[10px] text-slate-400 font-bold uppercase tracking-wider border-b border-slate-150">
                <tr>
                    <th class="pb-3">Client Details</th>
                    <th class="pb-3">Source & Campaign</th>
                    <th class="pb-3">Scheduled For</th>
                    <th class="pb-3">Status</th>
                    <th class="pb-3 text-end">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($leads)): ?>
                    <tr>
                        <td colspan="5" class="py-6 text-center text-slate-400">No leads found for this filter.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($leads as $lead): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3">
                                <div class="font-bold text-slate-800 leading-snug"><?= htmlspecialchars($lead['name']) ?></div>
                                <div class="text-[11px] text-slate-500 font-medium"><?= htmlspecialchars($lead['phone']) ?></div>
                                <div class="text-[10px] text-slate-400"><?= date('d M, Y', strtotime($lead['created_at'])) ?></div>
                            </td>
                            <td class="py-3">
                                <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded border <?= $lead['source'] === 'meta_ads' ? 'bg-blue-50 text-blue-600 border-blue-100' : 'bg-purple-50 text-purple-600 border-purple-100' ?>">
                                    <?= $lead['source'] === 'meta_ads' ? 'Meta Ad' : 'Website' ?>
                                </span>
                                <?php if ($lead['campaign_name']): ?>
                                    <div class="text-[10px] mt-1 text-slate-500 font-medium"><?= htmlspecialchars($lead['campaign_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="py-3">
                                <?php if ($lead['scheduled_datetime']): ?>
                                    <?php 
                                    $isPast = strtotime($lead['scheduled_datetime']) <= time();
                                    $color = $isPast ? 'text-rose-600 font-bold' : 'text-slate-600';
                                    ?>
                                    <span class="text-xs <?= $color ?>"><?= date('d M Y, h:i A', strtotime($lead['scheduled_datetime'])) ?></span>
                                <?php else: ?>
                                    <span class="text-[10px] text-slate-300">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3">
                                <?= get_status_badge($lead['status']) ?>
                            </td>
                            <td class="py-3 text-end">
                                <a href="lead-details.php?id=<?= $lead['id'] ?>" target="_blank" class="px-2.5 py-1 bg-brand-50 hover:bg-brand-500 hover:text-white text-brand-600 rounded text-[10px] font-bold transition">View →</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
