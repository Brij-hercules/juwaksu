<?php
// crm/excel-leads.php
$pageTitle = "Imported Google Sheet Leads";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/db.php';

require_permission('inquiries', 'view');

$successMsg = '';
$errorMsg   = '';

// ── POST: Status / Assignment update ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inquiryId = intval($_POST['inquiry_id'] ?? 0);

    if (isset($_POST['update_status'])) {
        if (!has_permission('inquiries', 'edit')) {
            $errorMsg = "You do not have permission to modify leads.";
        } else {
            try {
                $pdo->prepare("UPDATE inquiries SET status = ? WHERE id = ?")
                    ->execute([trim($_POST['status']), $inquiryId]);
                $successMsg = "Lead status updated.";
            } catch (\PDOException $e) {
                $errorMsg = "Error: " . $e->getMessage();
            }
        }
    }

    if (isset($_POST['update_assignment'])) {
        if (!has_permission('inquiries', 'edit')) {
            $errorMsg = "You do not have permission to assign leads.";
        } else {
            try {
                $assignedTo = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
                $pdo->prepare("UPDATE inquiries SET assigned_to = ? WHERE id = ?")
                    ->execute([$assignedTo, $inquiryId]);
                $successMsg = "Lead assignment updated.";
            } catch (\PDOException $e) {
                $errorMsg = "Error: " . $e->getMessage();
            }
        }
    }
}

// ── Filters ────────────────────────────────────────────────────────────────
$filterCampaign = trim($_GET['campaign_name'] ?? '');
$filterStatus   = trim($_GET['status']        ?? '');
$filterStart    = trim($_GET['start_date']    ?? '');
$filterEnd      = trim($_GET['end_date']      ?? '');
$filterSearch   = trim($_GET['search']        ?? '');

// ── Build Query (ALL meta_ads leads: both API-synced & Excel-imported) ─────
$where  = "WHERE i.source = 'meta_ads'";
$params = [];

if ($filterCampaign) { $where .= " AND i.campaign_name = ?";             $params[] = $filterCampaign; }
if ($filterStatus)   { $where .= " AND i.status = ?";                    $params[] = $filterStatus;   }
if ($filterStart)    { $where .= " AND DATE(i.created_at) >= ?";          $params[] = $filterStart;    }
if ($filterEnd)      { $where .= " AND DATE(i.created_at) <= ?";          $params[] = $filterEnd;      }
if ($filterSearch)   {
    $where .= " AND (i.name LIKE ? OR i.phone LIKE ? OR i.email LIKE ?)";
    $like = "%$filterSearch%";
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$query = "SELECT i.*, u.username as agent_name 
          FROM inquiries i 
          LEFT JOIN users u ON i.assigned_to = u.id 
          $where 
          ORDER BY i.id DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $leads = $stmt->fetchAll();

    $campaignsList = $pdo->query("
        SELECT DISTINCT campaign_name FROM inquiries
        WHERE source='meta_ads' AND campaign_name IS NOT NULL
    ")->fetchAll(PDO::FETCH_COLUMN);

    $agents = $pdo->query("
        SELECT u.id, u.username, r.role_name 
        FROM users u JOIN roles r ON u.role_id = r.id
        WHERE u.status='active' AND r.role_name IN ('Admin','Sales Employee','Meta Manager')
        ORDER BY u.username
    ")->fetchAll();

    // Stats (all meta leads)
    $stTotal  = $pdo->query("SELECT COUNT(*) FROM inquiries WHERE source='meta_ads'")->fetchColumn();
    $stNew    = $pdo->query("SELECT COUNT(*) FROM inquiries WHERE source='meta_ads' AND status='new'")->fetchColumn();
    $stActive = $pdo->query("SELECT COUNT(*) FROM inquiries WHERE source='meta_ads' AND status IN ('contacting','qualified')")->fetchColumn();
    $stClosed = $pdo->query("SELECT COUNT(*) FROM inquiries WHERE source='meta_ads' AND status='closed'")->fetchColumn();

} catch (\Exception $e) {
    $leads = $campaignsList = $agents = [];
    $stTotal = $stNew = $stActive = $stClosed = 0;
    $errorMsg = $e->getMessage();
}
?>

<?php if ($successMsg): ?>
<div class="alert alert-success alert-dismissible fade show border-0 rounded-xl bg-emerald-50 text-emerald-700 shadow-sm text-xs p-4 mb-6" role="alert">
    <strong>Success!</strong> <?= $successMsg ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($errorMsg): ?>
<div class="alert alert-danger alert-dismissible fade show border-0 rounded-xl bg-rose-50 text-rose-700 shadow-sm text-xs p-4 mb-6" role="alert">
    <strong>Error!</strong> <?= $errorMsg ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
    <?php foreach ([
        ['Total Leads',    $stTotal,  'text-slate-800',   'bg-slate-50  border-slate-200'],
        ['New / Unworked', $stNew,    'text-blue-700',    'bg-blue-50   border-blue-100'],
        ['In Follow-Up',   $stActive, 'text-amber-700',   'bg-amber-50  border-amber-100'],
        ['Converted',      $stClosed, 'text-emerald-700', 'bg-emerald-50 border-emerald-100'],
    ] as [$label,$val,$tc,$bg]): ?>
    <div class="<?= $bg ?> border rounded-2xl p-5">
        <div class="text-[10px] text-slate-400 font-extrabold uppercase tracking-wider mb-1"><?= $label ?></div>
        <div class="font-black text-2xl <?= $tc ?>"><?= $val ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Header row -->
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h3 class="text-base font-extrabold text-slate-800 mb-0.5">Imported Google Sheet Leads</h3>
        <p class="text-slate-400 text-xs font-light">Showing all leads imported directly via the API endpoint or Excel file upload.</p>
    </div>
    <a href="excel-import.php"
        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
        </svg>
        Import New File
    </a>
</div>

<!-- Main Card -->
<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6">

    <!-- Filters -->
    <form action="excel-leads.php" method="GET" class="row g-2 bg-slate-50 p-4 rounded-xl mb-6 border border-slate-200/50">
        <!-- Search -->
        <div class="col-md-3">
            <input type="text" name="search" value="<?= htmlspecialchars($filterSearch) ?>"
                placeholder="Search name, phone, email…"
                class="w-full px-3 py-2 rounded-lg bg-white border border-slate-200 text-xs focus:outline-none">
        </div>

        <!-- Campaign -->
        <div class="col-md-2">
            <select name="campaign_name" class="w-full px-2 py-2 rounded bg-white border border-slate-200 text-xs">
                <option value="">All Campaigns</option>
                <?php foreach ($campaignsList as $cn): ?>
                    <option value="<?= htmlspecialchars($cn) ?>" <?= $filterCampaign===$cn?'selected':'' ?>>
                        <?= htmlspecialchars($cn) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Status -->
        <div class="col-md-2">
            <select name="status" class="w-full px-2 py-2 rounded bg-white border border-slate-200 text-xs">
                <option value="">All Statuses</option>
                <?php foreach (['new'=>'New','contacting'=>'Contacting','qualified'=>'Qualified','lost'=>'Lost','closed'=>'Closed'] as $v=>$l): ?>
                    <option value="<?= $v ?>" <?= $filterStatus===$v?'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Date range -->
        <div class="col-md-2">
            <input type="date" name="start_date" value="<?= $filterStart ?>"
                class="w-full px-2 py-1.5 rounded bg-white border border-slate-200 text-xs">
        </div>
        <div class="col-md-2">
            <input type="date" name="end_date" value="<?= $filterEnd ?>"
                class="w-full px-2 py-1.5 rounded bg-white border border-slate-200 text-xs">
        </div>

        <div class="col-md-1 flex gap-1">
            <button type="submit" class="flex-1 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded text-xs font-bold transition">Go</button>
            <a href="excel-leads.php" class="flex-1 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded text-xs font-bold transition text-center">✕</a>
        </div>
    </form>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle text-sm">
            <thead class="text-[10px] text-slate-400 font-extrabold uppercase tracking-wider border-b border-slate-150">
                <tr>
                    <th class="pb-3">Client Details</th>
                    <th class="pb-3">Ad Campaign / Form</th>
                    <th class="pb-3">Lead Answers / Profile</th>
                    <th class="pb-3">Representative</th>
                    <th class="pb-3">Pipeline Status</th>
                    <th class="pb-3 text-end">Update</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($leads)): ?>
                    <tr>
                        <td colspan="6" class="py-12 text-center">
                            <div class="text-slate-300 text-4xl mb-2">📋</div>
                            <div class="text-slate-400 font-semibold text-sm">No leads found matching your filters.</div>
                            <a href="excel-import.php" class="text-blue-500 text-xs underline mt-1 inline-block">Import a file to get started →</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($leads as $lead): ?>
                    <tr>
                        <!-- Client Details -->
                        <td class="py-3">
                            <div class="font-bold text-slate-800 leading-snug"><?= htmlspecialchars($lead['name']) ?></div>
                            <div class="text-[11px] text-slate-500 font-medium"><?= htmlspecialchars($lead['phone']) ?></div>
                            <div class="text-[10px] text-slate-400"><?= htmlspecialchars($lead['email'] ?? '—') ?></div>
                            <?php if ($lead['meta_lead_id']): ?>
                            <div class="text-[9px] bg-slate-100 px-1.5 py-0.5 rounded text-slate-400 inline-block mt-1 font-mono">
                                Meta ID: <?= htmlspecialchars($lead['meta_lead_id']) ?>
                            </div>
                            <?php endif; ?>
                        </td>

                        <!-- Campaign / Form -->
                        <td class="py-3">
                            <div class="font-semibold text-slate-700 text-xs leading-snug">
                                <?= htmlspecialchars($lead['campaign_name'] ?? 'General Campaign') ?>
                            </div>
                            <?php if ($lead['form_name'] ?? null): ?>
                            <div class="text-[10px] text-slate-400">Form: <?= htmlspecialchars($lead['form_name']) ?></div>
                            <?php endif; ?>
                            <div class="flex items-center gap-1 mt-1 flex-wrap">
                                <?php if ($lead['platform'] ?? null): ?>
                                <span class="px-1.5 py-0.5 rounded bg-blue-50 text-blue-600 border border-blue-100 text-[8px] font-bold uppercase">
                                    <?= htmlspecialchars($lead['platform']) ?>
                                </span>
                                <?php endif; ?>
                                <?php if ($lead['is_organic'] ?? 0): ?>
                                <span class="px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-600 border border-emerald-100 text-[8px] font-bold uppercase">Organic</span>
                                <?php endif; ?>
                                <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-400 text-[8px] font-bold">
                                    <?= date('d-M-y H:i', strtotime($lead['created_at'])) ?>
                                </span>
                            </div>
                        </td>

                        <!-- Survey Answers -->
                        <td class="py-3 text-xs text-slate-600 space-y-0.5 max-w-[200px]">
                            <?php if ($lead['are_you_looking_for'] ?? null): ?>
                            <div><span class="text-slate-400">Looking for:</span> <strong><?= htmlspecialchars($lead['are_you_looking_for']) ?></strong></div>
                            <?php endif; ?>
                            <?php if ($lead['budget'] ?? null): ?>
                            <div><span class="text-slate-400">Budget:</span> <strong class="text-brand-500"><?= htmlspecialchars($lead['budget']) ?></strong></div>
                            <?php endif; ?>
                            <?php if ($lead['purchase_time'] ?? null): ?>
                            <div><span class="text-slate-400">Timeline:</span> <?= htmlspecialchars($lead['purchase_time']) ?></div>
                            <?php endif; ?>
                            <?php if ($lead['have_you_invested_in_property_before'] ?? null): ?>
                            <div><span class="text-slate-400">Invested before:</span> <?= htmlspecialchars($lead['have_you_invested_in_property_before']) ?></div>
                            <?php endif; ?>
                            <?php if (!($lead['are_you_looking_for'] ?? null) && !($lead['budget'] ?? null)): ?>
                            <span class="text-slate-300 text-[10px]">No survey answers</span>
                            <?php endif; ?>
                        </td>

                        <!-- Assignment -->
                        <td class="py-3">
                            <form action="excel-leads.php?<?= $_SERVER['QUERY_STRING'] ?? '' ?>" method="POST">
                                <input type="hidden" name="inquiry_id" value="<?= $lead['id'] ?>">
                                <input type="hidden" name="update_assignment" value="1">
                                <select name="assigned_to" onchange="this.form.submit()"
                                    class="px-2 py-1 bg-slate-50 border border-slate-200 text-xs rounded focus:outline-none">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($agents as $a): ?>
                                    <option value="<?= $a['id'] ?>" <?= $lead['assigned_to']==$a['id']?'selected':'' ?>>
                                        <?= htmlspecialchars($a['username']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>

                        <!-- Status Badge -->
                        <td class="py-3">
                            <?php
                            $col = match($lead['status']) {
                                'new'        => 'bg-blue-100 text-blue-700 border border-blue-200',
                                'contacting' => 'bg-amber-100 text-amber-700 border border-amber-200',
                                'qualified'  => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
                                'lost'       => 'bg-rose-100 text-rose-700 border border-rose-200',
                                'closed'     => 'bg-indigo-100 text-indigo-700 border border-indigo-200',
                                default      => 'bg-slate-100 text-slate-600',
                            };
                            ?>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider <?= $col ?>">
                                <?= htmlspecialchars($lead['status']) ?>
                            </span>
                        </td>

                        <!-- Update Status -->
                        <td class="py-3 text-end">
                            <form action="excel-leads.php?<?= $_SERVER['QUERY_STRING'] ?? '' ?>" method="POST" class="inline-flex">
                                <input type="hidden" name="inquiry_id" value="<?= $lead['id'] ?>">
                                <input type="hidden" name="update_status" value="1">
                                <select name="status" onchange="this.form.submit()"
                                    class="px-2 py-1 bg-slate-50 border border-slate-200 text-xs rounded font-medium focus:outline-none">
                                    <?php foreach (['new'=>'New','contacting'=>'Contact','qualified'=>'Qualify','lost'=>'Lost','closed'=>'Closed'] as $v=>$l): ?>
                                    <option value="<?= $v ?>" <?= $lead['status']===$v?'selected':'' ?>><?= $l ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Footer count -->
    <?php if (!empty($leads)): ?>
    <div class="mt-4 pt-4 border-t border-slate-100 text-[10px] text-slate-400 font-medium">
        Showing <strong><?= count($leads) ?></strong> lead(s)
        <?= $filterSearch||$filterStatus||$filterCampaign||$filterStart||$filterEnd ? ' (filtered)' : '' ?>
        — <a href="excel-import.php" class="text-blue-500 underline">Import more leads</a>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
