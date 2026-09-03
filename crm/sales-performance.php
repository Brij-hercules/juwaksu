<?php
// crm/sales-performance.php
$pageTitle = "Sales Employee Performance";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/lead_status_helper.php';

// Verify permission
if (!in_array($currentUser['role_name'], ['Admin', 'Super Admin', 'Meta Manager'])) {
    die("<div class='p-8 text-center text-rose-500 font-bold'>Unauthorized Access.</div>");
}

// 1. Fetch Date Filter
$filterStart = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$filterEnd   = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// 2. Fetch Sales Employees
$stmtAgents = $pdo->prepare("
    SELECT u.id, u.username, u.email 
    FROM users u
    JOIN roles r ON u.role_id = r.id
    WHERE r.role_name = 'Sales Employee' AND u.status = 'active'
    ORDER BY u.username ASC
");
$stmtAgents->execute();
$salesAgents = $stmtAgents->fetchAll();

// 3. Fetch Counts per Agent per Status
$countsQuery = "
    SELECT assigned_to, status, COUNT(*) as lead_count 
    FROM inquiries 
    WHERE assigned_to IS NOT NULL
";
$params = [];
if (!empty($filterStart)) {
    $countsQuery .= " AND DATE(created_at) >= ?";
    $params[] = $filterStart;
}
if (!empty($filterEnd)) {
    $countsQuery .= " AND DATE(created_at) <= ?";
    $params[] = $filterEnd;
}
$countsQuery .= " GROUP BY assigned_to, status";

$stmtCounts = $pdo->prepare($countsQuery);
$stmtCounts->execute($params);
$rawCounts = $stmtCounts->fetchAll();

// Structure counts by user ID
$agentStats = [];
foreach ($salesAgents as $agent) {
    $agentStats[$agent['id']] = [
        'total' => 0,
        'statuses' => []
    ];
    foreach (LEAD_STATUSES as $k => $v) {
        $agentStats[$agent['id']]['statuses'][$k] = 0;
    }
}

foreach ($rawCounts as $row) {
    $aId = $row['assigned_to'];
    $st  = $row['status'];
    $cnt = (int)$row['lead_count'];
    
    if (isset($agentStats[$aId])) {
        $agentStats[$aId]['total'] += $cnt;
        if (isset($agentStats[$aId]['statuses'][$st])) {
            $agentStats[$aId]['statuses'][$st] = $cnt;
        }
    }
}
?>

<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6 mb-8">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h3 class="text-base font-extrabold text-slate-800 mb-0.5">Sales Performance Overview</h3>
            <p class="text-slate-400 text-xs font-light font-sans">Track assigned lead distribution and conversion progress for each sales representative.</p>
        </div>
        
        <!-- Date Filter -->
        <form action="sales-performance.php" method="GET" class="flex gap-2 items-center">
            <input type="date" name="start_date" value="<?= $filterStart ?>" class="px-3 py-2 bg-slate-50 border border-slate-200 text-xs rounded-lg focus:outline-none">
            <span class="text-slate-400 text-xs font-medium">to</span>
            <input type="date" name="end_date" value="<?= $filterEnd ?>" class="px-3 py-2 bg-slate-50 border border-slate-200 text-xs rounded-lg focus:outline-none">
            <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-lg text-xs font-bold transition">Apply</button>
            <?php if ($filterStart || $filterEnd): ?>
                <a href="sales-performance.php" class="px-3 py-2 bg-rose-50 text-rose-600 rounded-lg text-xs font-bold transition">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="row g-4 mb-8">
    <?php if (empty($salesAgents)): ?>
        <div class="col-12">
            <div class="p-12 bg-white rounded-2xl border border-slate-200/60 shadow-sm text-center text-slate-400">
                <p class="mb-0 font-medium">No active Sales Employees found in the system.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($salesAgents as $agent): 
            $stats = $agentStats[$agent['id']];
            $total = $stats['total'];
            $won = $stats['statuses']['booking_done'];
            $conversionRate = $total > 0 ? round(($won / $total) * 100, 1) : 0;
        ?>
            <div class="col-lg-4 col-md-6">
                <a href="sales-employee-details.php?id=<?= $agent['id'] ?>" class="block h-full bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6 hover:shadow-md hover:-translate-y-1 transition duration-300 relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-blue-50 rounded-full blur-3xl -mr-10 -mt-10 opacity-50 group-hover:bg-brand-100 transition"></div>
                    
                    <div class="relative z-10">
                        <div class="flex justify-between items-start mb-5">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-slate-800 text-white flex items-center justify-center font-bold text-sm">
                                    <?= strtoupper(substr($agent['username'], 0, 1)) ?>
                                </div>
                                <div>
                                    <h4 class="font-black text-slate-800 text-sm mb-0"><?= htmlspecialchars($agent['username']) ?></h4>
                                    <span class="text-[10px] text-slate-400 font-medium"><?= htmlspecialchars($agent['email']) ?></span>
                                </div>
                            </div>
                            
                            <div class="text-right">
                                <div class="text-[22px] font-black text-brand-600 leading-none"><?= $total ?></div>
                                <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1">Total Leads</div>
                            </div>
                        </div>

                        <!-- Mini Status Breakdown -->
                        <div class="grid grid-cols-2 gap-2 mb-5">
                            <?php 
                            // Show important statuses
                            $keyStatuses = ['fresh_lead', 'follow_up', 'visit_planned', 'booking_done'];
                            foreach ($keyStatuses as $k):
                                $conf = LEAD_STATUSES[$k];
                                $cnt = $stats['statuses'][$k];
                            ?>
                                <div class="p-2.5 rounded-xl border border-slate-100 bg-slate-50 flex justify-between items-center">
                                    <span class="text-[9px] font-extrabold text-slate-500 uppercase"><?= $conf['label'] ?></span>
                                    <span class="text-xs font-black <?= $cnt > 0 ? 'text-slate-800' : 'text-slate-400' ?>"><?= $cnt ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="border-t border-slate-100 pt-4 flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <span class="px-2 py-1 bg-emerald-50 text-emerald-600 rounded text-[10px] font-bold">
                                    <?= $conversionRate ?>% Won
                                </span>
                            </div>
                            <span class="text-[10px] font-bold text-brand-500 flex items-center gap-1 group-hover:text-brand-600">
                                View Details <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                            </span>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
