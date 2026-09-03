<?php
// crm/index.php
$pageTitle = "CRM Overview";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/lead_status_helper.php';

$isSalesEmployee = ($currentUser['role_name'] === 'Sales Employee');
$userId = $currentUser['id'];

$dueReminders = [];
$recentInquiries = [];

// ── Pagination config ──────────────────────────────────
$perPage     = 50;
$currentPage = max(1, intval($_GET['page'] ?? 1));
$filterStatus = trim($_GET['status'] ?? '');
$filterSearch = trim($_GET['search'] ?? '');

try {
    if ($isSalesEmployee) {
        // ── SALES EMPLOYEE STATS ───────────────────────
        $statTotal = $pdo->prepare("SELECT COUNT(*) FROM inquiries WHERE assigned_to = ?");
        $statTotal->execute([$userId]);
        $statTotalLeads = $statTotal->fetchColumn();

        $statConfirm = $pdo->prepare("SELECT COUNT(*) FROM inquiries WHERE assigned_to = ? AND status = 'booking_done'");
        $statConfirm->execute([$userId]);
        $statConfirmLeads = $statConfirm->fetchColumn();

        $statLoss = $pdo->prepare("SELECT COUNT(*) FROM inquiries WHERE assigned_to = ? AND status IN ('not_interested', 'sale_lost')");
        $statLoss->execute([$userId]);
        $statLossLeads = $statLoss->fetchColumn();

        $statWait = $pdo->prepare("SELECT COUNT(*) FROM inquiries WHERE assigned_to = ? AND status NOT IN ('not_interested', 'booking_done', 'sale_lost')");
        $statWait->execute([$userId]);
        $statWaitLeads = $statWait->fetchColumn();

        // Status-wise counts
        $rawStatusCounts = $pdo->prepare("SELECT COALESCE(NULLIF(status,''),'fresh_lead') as st, COUNT(*) as cnt FROM inquiries WHERE assigned_to = ? GROUP BY st");
        $rawStatusCounts->execute([$userId]);
        $statusCounts = [];
        foreach ($rawStatusCounts->fetchAll() as $r) {
            $normalized = normalize_status($r['st']);
            $statusCounts[$normalized] = ($statusCounts[$normalized] ?? 0) + (int)$r['cnt'];
        }

        // Due Reminders
        $stmtReminders = $pdo->prepare("
            SELECT id, name, scheduled_datetime, status 
            FROM inquiries 
            WHERE assigned_to = ? 
              AND scheduled_datetime IS NOT NULL 
              AND scheduled_datetime <= NOW() 
              AND status NOT IN ('not_interested', 'booking_done', 'sale_lost')
            ORDER BY scheduled_datetime ASC
        ");
        $stmtReminders->execute([$userId]);
        $dueReminders = $stmtReminders->fetchAll();

        // ── Leads Query with Filters & Pagination ────────
        $where  = "WHERE i.assigned_to = ?";
        $params = [$userId];

        if (!empty($filterStatus)) {
            // Handle old status aliases on filter too
            $filterStatusNorm = normalize_status($filterStatus);
            // Match both old and new status values
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

        if (!empty($filterSearch)) {
            $where .= " AND (i.name LIKE ? OR i.phone LIKE ? OR i.email LIKE ?)";
            $s = "%$filterSearch%";
            $params = array_merge($params, [$s, $s, $s]);
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
            ORDER BY i.id DESC 
            LIMIT $perPage OFFSET $offset
        ");
        $stmtLeads->execute($params);
        $recentInquiries = $stmtLeads->fetchAll();

    } else {
        // ── ADMIN / GLOBAL STATS ───────────────────────
        $statProperties = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
        $statCategories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
        $statInquiries  = $pdo->query("SELECT COUNT(*) FROM inquiries")->fetchColumn();
        $statMetaLeads  = $pdo->query("SELECT COUNT(*) FROM inquiries WHERE source = 'meta_ads'")->fetchColumn();
        $statMetaClicks = $pdo->query("SELECT COUNT(*) FROM meta_ads_clicks")->fetchColumn();
        
        $stmtRecent = $pdo->query("
            SELECT i.*, p.title as property_title 
            FROM inquiries i 
            LEFT JOIN properties p ON i.property_id = p.id 
            ORDER BY i.id DESC LIMIT 5
        ");
        $recentInquiries = $stmtRecent->fetchAll();
        $totalLeads = 5; $totalPages = 1; $currentPage = 1;
    }
} catch (\Exception $e) {
    $totalLeads = 0; $totalPages = 1; $currentPage = 1;
}

// Helper for pagination URL
function pgUrl($page, $status, $search) {
    $q = array_filter(['page' => $page, 'status' => $status, 'search' => $search]);
    return 'index.php?' . http_build_query($q);
}
?>

<!-- Greetings Card banner -->
<div class="bg-gradient-to-r from-brand-600 via-indigo-900 to-indigo-950 p-8 rounded-3xl shadow-lg border border-indigo-850/50 mb-8 text-white relative overflow-hidden">
    <div class="absolute -right-20 -top-20 w-60 h-60 bg-amber-500/10 rounded-full blur-2xl"></div>
    <div class="relative z-10 max-w-xl">
        <h1 class="text-3xl font-black mb-2">Welcome Back, <?php echo htmlspecialchars($currentUser['username']); ?>!</h1>
        <p class="text-indigo-200/80 font-light text-sm leading-relaxed">
            <?php if ($isSalesEmployee): ?>
                Here is your assigned leads overview. Manage your pipeline, update statuses, and follow up with clients.
            <?php else: ?>
                Here is your real estate pipeline overview. Manage property details, respond to inquiries, and track Meta Ads performance.
            <?php endif; ?>
        </p>
    </div>
</div>

<?php if ($isSalesEmployee && !empty($dueReminders)): ?>
    <div class="alert bg-rose-50 border border-rose-200 text-rose-700 rounded-2xl p-4 mb-6 flex items-center justify-between shadow-sm" role="alert">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <strong class="font-bold text-sm">Action Required!</strong>
                <p class="text-xs mt-0.5">You have <?= count($dueReminders) ?> scheduled follow-up(s) that are due or overdue.</p>
            </div>
        </div>
        <button type="button" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-bold transition shadow-sm" data-bs-toggle="modal" data-bs-target="#remindersModal">
            View Due Tasks
        </button>
    </div>
<?php endif; ?>

<!-- Stats Counter Grid -->
<div class="row g-4 mb-8">
    <?php if ($isSalesEmployee): ?>

        <!-- Total Leads -->
        <div class="col-xl-3 col-sm-6">
            <a href="index.php" class="block bg-white p-6 rounded-2xl border border-slate-200/60 shadow-sm flex items-center gap-4 hover:shadow-md transition duration-200 <?= empty($filterStatus) ? 'ring-2 ring-brand-500' : '' ?>">
                <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block mb-0.5">Total Leads</span>
                    <span class="text-2xl font-black text-slate-800"><?= number_format($statTotalLeads ?? 0) ?></span>
                </div>
            </a>
        </div>

        <!-- Booking Done (Won) -->
        <div class="col-xl-3 col-sm-6">
            <a href="<?= pgUrl(1, 'booking_done', $filterSearch) ?>" class="block bg-white p-6 rounded-2xl border border-slate-200/60 shadow-sm flex items-center gap-4 hover:shadow-md transition duration-200 <?= $filterStatus === 'booking_done' ? 'ring-2 ring-emerald-500' : '' ?>">
                <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block mb-0.5">Booking Done</span>
                    <span class="text-2xl font-black text-slate-800"><?= number_format($statConfirmLeads ?? 0) ?></span>
                </div>
            </a>
        </div>

        <!-- Lost -->
        <div class="col-xl-3 col-sm-6">
            <a href="<?= pgUrl(1, 'sale_lost', $filterSearch) ?>" class="block bg-white p-6 rounded-2xl border border-slate-200/60 shadow-sm flex items-center gap-4 hover:shadow-md transition duration-200 <?= $filterStatus === 'sale_lost' ? 'ring-2 ring-rose-500' : '' ?>">
                <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block mb-0.5">Lost Leads</span>
                    <span class="text-2xl font-black text-slate-800"><?= number_format($statLossLeads ?? 0) ?></span>
                </div>
            </a>
        </div>

        <!-- Waiting / In Progress -->
        <div class="col-xl-3 col-sm-6">
            <div class="bg-white p-6 rounded-2xl border border-slate-200/60 shadow-sm flex items-center gap-4 hover:shadow-md transition duration-200">
                <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block mb-0.5">In Progress</span>
                    <span class="text-2xl font-black text-slate-800"><?= number_format($statWaitLeads ?? 0) ?></span>
                </div>
            </div>
        </div>

    <?php else: ?>

        <!-- Admin Stats (unchanged) -->
        <div class="col-xl-3 col-sm-6">
            <div class="bg-white p-6 rounded-2xl border border-slate-200/60 shadow-sm flex items-center gap-4 hover:shadow-md transition duration-200">
                <div class="w-12 h-12 bg-indigo-50 text-brand-500 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block mb-0.5">Total Listings</span>
                    <span class="text-2xl font-black text-slate-800"><?= number_format($statProperties ?? 0) ?></span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="bg-white p-6 rounded-2xl border border-slate-200/60 shadow-sm flex items-center gap-4 hover:shadow-md transition duration-200">
                <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block mb-0.5">Total Leads</span>
                    <span class="text-2xl font-black text-slate-800"><?= number_format($statInquiries ?? 0) ?></span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="bg-white p-6 rounded-2xl border border-slate-200/60 shadow-sm flex items-center gap-4 hover:shadow-md transition duration-200">
                <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block mb-0.5">Meta Campaign Leads</span>
                    <span class="text-2xl font-black text-slate-800"><?= number_format($statMetaLeads ?? 0) ?></span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="bg-white p-6 rounded-2xl border border-slate-200/60 shadow-sm flex items-center gap-4 hover:shadow-md transition duration-200">
                <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"></path></svg>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block mb-0.5">Meta Ads Clicks</span>
                    <span class="text-2xl font-black text-slate-800"><?= number_format($statMetaClicks ?? 0) ?></span>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<?php if ($isSalesEmployee): ?>
<!-- Status-wise Quick Boxes -->
<div class="mb-6">
    <div class="flex flex-wrap gap-2 mb-2">
        <?php foreach (LEAD_STATUSES as $stKey => $stConf): 
            $cnt = $statusCounts[$stKey] ?? 0;
            $isActive = ($filterStatus === $stKey);
        ?>
            <a href="<?= pgUrl(1, $isActive ? '' : $stKey, $filterSearch) ?>"
               class="flex items-center gap-2 px-3 py-2 rounded-xl border text-xs font-bold transition
                      <?= $isActive ? 'bg-slate-800 text-white border-slate-800 shadow' : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300 hover:shadow-sm' ?>">
                <span class="<?= $isActive ? '' : '' ?>"><?= htmlspecialchars($stConf['label']) ?></span>
                <span class="px-1.5 py-0.5 rounded-md text-[10px] font-black <?= $isActive ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700' ?>"><?= $cnt ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Leads Table -->
<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h3 class="text-base font-extrabold text-slate-800">
            <?= $isSalesEmployee ? 'My Assigned Leads' : 'Recent Leads & Inquiries' ?>
            <?php if ($isSalesEmployee && !empty($filterStatus)): ?>
                <span class="ml-2 text-sm font-medium text-slate-400">— <?= htmlspecialchars(LEAD_STATUSES[$filterStatus]['label'] ?? $filterStatus) ?></span>
            <?php endif; ?>
        </h3>
        <?php if ($isSalesEmployee): ?>
        <form action="index.php" method="GET" class="flex items-center gap-2">
            <?php if (!empty($filterStatus)): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
            <?php endif; ?>
            <input type="text" name="search" value="<?= htmlspecialchars($filterSearch) ?>" placeholder="Search name, phone, email…"
                   class="px-3 py-2 bg-slate-50 border border-slate-200 text-xs rounded-lg focus:outline-none focus:border-brand-400 w-52">
            <button type="submit" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-lg text-xs font-bold transition">Search</button>
            <?php if (!empty($filterSearch)): ?>
                <a href="<?= pgUrl(1, $filterStatus, '') ?>" class="px-3 py-2 bg-rose-50 text-rose-600 rounded-lg text-xs font-bold transition">Clear</a>
            <?php endif; ?>
        </form>
        <?php endif; ?>
    </div>

    <?php if (empty($recentInquiries)): ?>
        <p class="text-slate-400 text-xs text-center py-10">No leads found<?= !empty($filterSearch) ? ' matching your search' : '' ?>.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle text-sm">
                <thead class="text-[10px] text-slate-400 font-bold uppercase tracking-wider border-b border-slate-100">
                    <tr>
                        <th class="pb-3">Client</th>
                        <th class="pb-3">Inquiry</th>
                        <th class="pb-3">Source</th>
                        <th class="pb-3">Pipeline Status</th>
                        <th class="pb-3">Scheduled</th>
                        <th class="pb-3 text-end">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($recentInquiries as $inq): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3">
                                <div class="font-bold text-slate-800 leading-snug"><?= htmlspecialchars($inq['name']) ?></div>
                                <div class="text-[11px] text-slate-500 font-medium"><?= htmlspecialchars($inq['phone']) ?></div>
                                <div class="text-[10px] text-slate-400"><?= date('d M Y', strtotime($inq['created_at'])) ?></div>
                            </td>
                            <td class="py-3">
                                <span class="text-slate-600 block line-clamp-1 max-w-xs text-xs"><?= htmlspecialchars($inq['message'] ?? '—') ?></span>
                                <?php if ($inq['property_title']): ?>
                                    <span class="text-[10px] text-brand-500 font-medium">Re: <?= htmlspecialchars($inq['property_title']) ?></span>
                                <?php elseif (!empty($inq['campaign_name'])): ?>
                                    <span class="text-[10px] text-slate-400"><?= htmlspecialchars($inq['campaign_name']) ?></span>
                                <?php else: ?>
                                    <span class="text-[10px] text-slate-400">General Consultation</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3">
                                <?php if ($inq['source'] === 'meta_ads'): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-600 border border-blue-100">
                                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                                        Meta
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-purple-50 text-purple-600 border border-purple-100">
                                        Website
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3">
                                <?= get_status_badge($inq['status']) ?>
                            </td>
                            <td class="py-3">
                                <?php if (!empty($inq['scheduled_datetime'])): 
                                    $isPast = strtotime($inq['scheduled_datetime']) <= time();
                                ?>
                                    <span class="text-[10px] font-bold <?= $isPast ? 'text-rose-600' : 'text-slate-500' ?>">
                                        <?= date('d M, h:i A', strtotime($inq['scheduled_datetime'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-[10px] text-slate-300">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 text-end">
                                <a href="lead-details.php?id=<?= $inq['id'] ?>" class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-100 hover:bg-brand-500 hover:text-white text-slate-600 rounded text-xs font-bold transition">
                                    View →
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($isSalesEmployee && $totalPages > 1): ?>
        <!-- Pagination -->
        <div class="mt-4 pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
            <div class="text-[10px] text-slate-400 font-medium">
                Showing <strong><?= count($recentInquiries) ?></strong> of <strong><?= $totalLeads ?></strong> leads — Page <strong><?= $currentPage ?></strong> of <strong><?= $totalPages ?></strong>
            </div>
            <nav class="flex items-center gap-1 flex-wrap">
                <?php if ($currentPage > 1): ?>
                    <a href="<?= pgUrl($currentPage - 1, $filterStatus, $filterSearch) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs text-slate-600 hover:bg-slate-50 font-medium transition">← Prev</a>
                <?php else: ?>
                    <span class="px-3 py-1.5 rounded-lg border border-slate-100 bg-slate-50 text-xs text-slate-300 font-medium">← Prev</span>
                <?php endif; ?>

                <?php
                $pStart = max(1, $currentPage - 2);
                $pEnd   = min($totalPages, $currentPage + 2);
                if ($pStart > 1) echo '<a href="'.pgUrl(1, $filterStatus, $filterSearch).'" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs text-slate-600 hover:bg-slate-50 font-medium transition">1</a>';
                if ($pStart > 2) echo '<span class="px-1 text-slate-300 text-xs">…</span>';
                for ($p = $pStart; $p <= $pEnd; $p++):
                ?>
                    <a href="<?= pgUrl($p, $filterStatus, $filterSearch) ?>" class="px-3 py-1.5 rounded-lg border text-xs font-medium transition <?= $p === $currentPage ? 'bg-slate-800 text-white border-slate-800' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php
                if ($pEnd < $totalPages - 1) echo '<span class="px-1 text-slate-300 text-xs">…</span>';
                if ($pEnd < $totalPages) echo '<a href="'.pgUrl($totalPages, $filterStatus, $filterSearch).'" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs text-slate-600 hover:bg-slate-50 font-medium transition">'.$totalPages.'</a>';
                ?>

                <?php if ($currentPage < $totalPages): ?>
                    <a href="<?= pgUrl($currentPage + 1, $filterStatus, $filterSearch) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs text-slate-600 hover:bg-slate-50 font-medium transition">Next →</a>
                <?php else: ?>
                    <span class="px-3 py-1.5 rounded-lg border border-slate-100 bg-slate-50 text-xs text-slate-300 font-medium">Next →</span>
                <?php endif; ?>
            </nav>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($isSalesEmployee && !empty($dueReminders)): ?>
<!-- Reminders Modal -->
<div class="modal fade" id="remindersModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-2xl border-0 shadow-xl overflow-hidden">
            <div class="modal-header bg-rose-600 text-white border-0 py-4 px-6">
                <h5 class="modal-title font-black text-lg flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    Due Scheduled Tasks
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 bg-slate-50">
                <div class="divide-y divide-slate-100">
                    <?php foreach ($dueReminders as $rem): ?>
                        <div class="p-4 flex items-center justify-between hover:bg-white transition">
                            <div>
                                <h6 class="font-extrabold text-sm text-slate-800 mb-1"><?= htmlspecialchars($rem['name']) ?></h6>
                                <div class="flex items-center gap-3 text-xs">
                                    <span class="text-rose-600 font-bold">Due: <?= date('d M Y, h:i A', strtotime($rem['scheduled_datetime'])) ?></span>
                                    <?= get_status_badge($rem['status']) ?>
                                </div>
                            </div>
                            <a href="lead-details.php?id=<?= $rem['id'] ?>" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold rounded-lg transition whitespace-nowrap">
                                Open Lead
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        if (!sessionStorage.getItem('remindersShown')) {
            var myModal = new bootstrap.Modal(document.getElementById('remindersModal'));
            myModal.show();
            sessionStorage.setItem('remindersShown', 'true');
        }
    });
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
