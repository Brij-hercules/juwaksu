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

// ── Status-wise counts ───────────────────────────────────────
$rawStatusCounts = $pdo->prepare("SELECT COALESCE(NULLIF(status,''),'fresh_lead') as st, COUNT(*) as cnt FROM inquiries WHERE assigned_to = ? GROUP BY st");
$rawStatusCounts->execute([$employeeId]);
$statusCounts = [];
foreach ($rawStatusCounts->fetchAll() as $r) {
    $normalized = normalize_status($r['st']);
    $statusCounts[$normalized] = ($statusCounts[$normalized] ?? 0) + (int)$r['cnt'];
}

// ── Pagination config ──────────────────────────────────
$perPage     = 50;
$currentPage = max(1, intval($_GET['page'] ?? 1));

// ── Fetch their Leads ───────────────────────────────────────
$where  = "WHERE i.assigned_to = ?";
$params = [$employeeId];

if (!empty($filterStatus)) {
    $filterStatusNorm = normalize_status($filterStatus);
    $legacyMatches = array_keys(array_filter(LEGACY_STATUS_MAP, fn($v) => $v === $filterStatusNorm));
    if (!empty($legacyMatches)) {
        $placeholders = implode(',', array_fill(0, count($legacyMatches) + 1, '?'));
        $where .= " AND (COALESCE(NULLIF(i.status,''),'fresh_lead') = ? OR i.status IN ($placeholders))";
        $params[] = $filterStatusNorm;
        foreach ($legacyMatches as $lm) $params[] = $lm;
    } else {
        $where .= " AND COALESCE(NULLIF(i.status,''),'fresh_lead') = ?";
        $params[] = $filterStatusNorm;
    }
}

// Count total for pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM inquiries i $where");
$countStmt->execute($params);
$totalLeads = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalLeads / $perPage));
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $perPage;

// Fetch leads
$stmtLeads = $pdo->prepare("
    SELECT i.*, p.title as property_title 
    FROM inquiries i 
    LEFT JOIN properties p ON i.property_id = p.id 
    $where
    ORDER BY i.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmtLeads->execute($params);
$leads = $stmtLeads->fetchAll();

function pgUrl($id, $page, $status) {
    $q = array_filter(['id' => $id, 'page' => $page, 'status' => $status]);
    return 'sales-employee-details.php?' . http_build_query($q);
}
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

<!-- Status-wise Quick Boxes -->
<div class="mb-6">
    <div class="flex flex-wrap gap-2 mb-2">
        <a href="<?= pgUrl($employeeId, 1, '') ?>"
            class="flex items-center gap-2 px-3 py-2 rounded-xl border text-xs font-bold transition
                <?= empty($filterStatus) ? 'bg-slate-800 text-white border-slate-800 shadow' : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300 hover:shadow-sm' ?>">
            <span>All Leads</span>
            <span class="px-1.5 py-0.5 rounded-md text-[10px] font-black <?= empty($filterStatus) ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700' ?>"><?= array_sum($statusCounts) ?></span>
        </a>
        
        <?php foreach (LEAD_STATUSES as $stKey => $stConf): 
            $cnt = $statusCounts[$stKey] ?? 0;
            $isActive = ($filterStatus === $stKey);
        ?>
            <a href="<?= pgUrl($employeeId, 1, $isActive ? '' : $stKey) ?>"
               class="flex items-center gap-2 px-3 py-2 rounded-xl border text-xs font-bold transition
                      <?= $isActive ? 'bg-slate-800 text-white border-slate-800 shadow' : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300 hover:shadow-sm' ?>">
                <span><?= htmlspecialchars($stConf['label']) ?></span>
                <span class="px-1.5 py-0.5 rounded-md text-[10px] font-black <?= $isActive ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700' ?>"><?= $cnt ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6 mb-8">
    
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
    
    <?php if ($totalPages > 1): ?>
    <!-- Pagination -->
    <div class="mt-4 pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
        <div class="text-[10px] text-slate-400 font-medium">
            Showing <strong><?= count($leads) ?></strong> of <strong><?= $totalLeads ?></strong> leads — Page <strong><?= $currentPage ?></strong> of <strong><?= $totalPages ?></strong>
        </div>
        <nav class="flex items-center gap-1 flex-wrap">
            <?php if ($currentPage > 1): ?>
                <a href="<?= pgUrl($employeeId, $currentPage - 1, $filterStatus) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs text-slate-600 hover:bg-slate-50 font-medium transition">← Prev</a>
            <?php else: ?>
                <span class="px-3 py-1.5 rounded-lg border border-slate-100 bg-slate-50 text-xs text-slate-300 font-medium">← Prev</span>
            <?php endif; ?>

            <?php
            $pStart = max(1, $currentPage - 2);
            $pEnd   = min($totalPages, $currentPage + 2);
            if ($pStart > 1) echo '<a href="'.pgUrl($employeeId, 1, $filterStatus).'" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs text-slate-600 hover:bg-slate-50 font-medium transition">1</a>';
            if ($pStart > 2) echo '<span class="px-1 text-slate-300 text-xs">…</span>';
            for ($p = $pStart; $p <= $pEnd; $p++):
            ?>
                <a href="<?= pgUrl($employeeId, $p, $filterStatus) ?>" class="px-3 py-1.5 rounded-lg border text-xs font-medium transition <?= $p === $currentPage ? 'bg-slate-800 text-white border-slate-800' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <?php
            if ($pEnd < $totalPages - 1) echo '<span class="px-1 text-slate-300 text-xs">…</span>';
            if ($pEnd < $totalPages) echo '<a href="'.pgUrl($employeeId, $totalPages, $filterStatus).'" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs text-slate-600 hover:bg-slate-50 font-medium transition">'.$totalPages.'</a>';
            ?>

            <?php if ($currentPage < $totalPages): ?>
                <a href="<?= pgUrl($employeeId, $currentPage + 1, $filterStatus) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs text-slate-600 hover:bg-slate-50 font-medium transition">Next →</a>
            <?php else: ?>
                <span class="px-3 py-1.5 rounded-lg border border-slate-100 bg-slate-50 text-xs text-slate-300 font-medium">Next →</span>
            <?php endif; ?>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
