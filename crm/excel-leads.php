<?php
// crm/excel-leads.php
$pageTitle = "Imported Google Sheet Leads";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/db.php';

require_permission('inquiries', 'view');

$successMsg = '';
$errorMsg   = '';
$isAdmin    = in_array($currentUser['role_name'], ['Admin', 'Super Admin']);

// ── POST: Bulk Delete (Admin only) ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    if (!$isAdmin) {
        $errorMsg = "Only admins can delete leads.";
    } else {
        $ids = array_filter(array_map('intval', $_POST['selected_ids'] ?? []));
        if (!empty($ids)) {
            try {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $pdo->prepare("DELETE FROM inquiries WHERE id IN ($placeholders)")->execute($ids);
                $successMsg = count($ids) . " lead(s) deleted successfully.";
            } catch (\PDOException $e) {
                $errorMsg = "Delete error: " . $e->getMessage();
            }
        } else {
            $errorMsg = "No leads selected for deletion.";
        }
    }
}

// ── POST: Status / Assignment update ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['bulk_delete'])) {
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
$filterStatus   = trim($_GET['status'] ?? '');
$filterStart    = trim($_GET['start_date'] ?? '');
$filterEnd      = trim($_GET['end_date'] ?? '');
$filterSearch   = trim($_GET['search'] ?? '');

// ── Pagination ─────────────────────────────────────────────────────────────
$perPage     = 100;
$currentPage = max(1, intval($_GET['page'] ?? 1));

// ── Build WHERE ─────────────────────────────────────────────────────────────
$where  = "WHERE i.source = 'meta_ads'";
$params = [];

if ($filterCampaign) {
    $where   .= " AND i.campaign_name = ?";
    $params[] = $filterCampaign;
}
if ($filterStatus) {
    $where   .= " AND i.status = ?";
    $params[] = $filterStatus;
}
if ($filterStart) {
    $where   .= " AND DATE(i.created_at) >= ?";
    $params[] = $filterStart;
}
if ($filterEnd) {
    $where   .= " AND DATE(i.created_at) <= ?";
    $params[] = $filterEnd;
}
if ($filterSearch) {
    $where   .= " AND (i.name LIKE ? OR i.phone LIKE ? OR i.email LIKE ?)";
    $like     = "%$filterSearch%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$isSalesEmployee = ($currentUser['role_name'] === 'Sales Employee');
$salesExtra      = $isSalesEmployee ? " AND i.assigned_to = " . intval($currentUser['id']) : '';

try {
    // Total count for pagination
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM inquiries i $where $salesExtra");
    $countStmt->execute($params);
    $totalLeads  = (int) $countStmt->fetchColumn();
    $totalPages  = max(1, (int) ceil($totalLeads / $perPage));
    $currentPage = min($currentPage, $totalPages);
    $offset      = ($currentPage - 1) * $perPage;

    // Paginated leads
    $query = "SELECT i.*, u.username as agent_name
              FROM inquiries i
              LEFT JOIN users u ON i.assigned_to = u.id
              $where $salesExtra
              ORDER BY i.id DESC
              LIMIT $perPage OFFSET $offset";

    $stmt  = $pdo->prepare($query);
    $stmt->execute($params);
    $leads = $stmt->fetchAll();

    $campaignsList = $pdo->query("
        SELECT DISTINCT campaign_name FROM inquiries
        WHERE source='meta_ads' AND campaign_name IS NOT NULL
    ")->fetchAll(PDO::FETCH_COLUMN);

    $agents = $pdo->query("
        SELECT u.id, u.username, r.role_name
        FROM users u JOIN roles r ON u.role_id = r.id
        WHERE u.status='active' AND r.role_name = 'Sales Employee'
        ORDER BY u.username
    ")->fetchAll();

    // Stats (all meta leads)
    $statWhere = "WHERE source='meta_ads'";
    if ($isSalesEmployee) {
        $statWhere .= " AND assigned_to = " . intval($currentUser['id']);
    }

    $stTotal  = $pdo->query("SELECT COUNT(*) FROM inquiries $statWhere")->fetchColumn();
    $stNew    = $pdo->query("SELECT COUNT(*) FROM inquiries $statWhere AND status='new'")->fetchColumn();
    $stActive = $pdo->query("SELECT COUNT(*) FROM inquiries $statWhere AND status IN ('contacting','qualified')")->fetchColumn();
    $stClosed = $pdo->query("SELECT COUNT(*) FROM inquiries $statWhere AND status='closed'")->fetchColumn();

} catch (\Exception $e) {
    $leads = $campaignsList = $agents = [];
    $stTotal = $stNew = $stActive = $stClosed = 0;
    $totalLeads = $totalPages = 0;
    $errorMsg = $e->getMessage();
}

// Pagination URL helper
$qsBase = [];
foreach (['search' => $filterSearch, 'campaign_name' => $filterCampaign, 'status' => $filterStatus, 'start_date' => $filterStart, 'end_date' => $filterEnd] as $k => $v) {
    if ($v !== '') $qsBase[$k] = $v;
}
function pagUrl(array $base, int $p): string {
    $base['page'] = $p;
    return 'excel-leads.php?' . http_build_query($base);
}
?>

<?php if ($successMsg): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 rounded-xl bg-emerald-50 text-emerald-700 shadow-sm text-xs p-4 mb-6"
        role="alert">
        <strong>Success!</strong> <?= $successMsg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($errorMsg): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 rounded-xl bg-rose-50 text-rose-700 shadow-sm text-xs p-4 mb-6"
        role="alert">
        <strong>Error!</strong> <?= $errorMsg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Stats -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
    <?php foreach ([
        ['Total Leads', $stTotal, 'text-slate-800', 'bg-slate-50  border-slate-200'],
        ['New / Unworked', $stNew, 'text-blue-700', 'bg-blue-50   border-blue-100'],
        ['In Follow-Up', $stActive, 'text-amber-700', 'bg-amber-50  border-amber-100'],
        ['Converted', $stClosed, 'text-emerald-700', 'bg-emerald-50 border-emerald-100'],
    ] as [$label, $val, $tc, $bg]): ?>
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
        <p class="text-slate-400 text-xs font-light">Showing all leads imported directly via the API endpoint or Excel
            file upload.</p>
    </div>
    <a href="excel-import.php" style="display:none;"
        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
        </svg>
        Import New File
    </a>
</div>

<!-- Main Card -->
<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6">

    <!-- Filters -->
    <form action="excel-leads.php" method="GET"
        class="row g-2 bg-slate-50 p-4 rounded-xl mb-6 border border-slate-200/50">
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
                    <option value="<?= htmlspecialchars($cn) ?>" <?= $filterCampaign === $cn ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cn) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Status -->
        <div class="col-md-2">
            <select name="status" class="w-full px-2 py-2 rounded bg-white border border-slate-200 text-xs">
                <option value="">All Statuses</option>
                <?php foreach (['new' => 'New', 'contacting' => 'Contacting', 'qualified' => 'Qualified', 'lost' => 'Lost', 'closed' => 'Closed'] as $v => $l): ?>
                    <option value="<?= $v ?>" <?= $filterStatus === $v ? 'selected' : '' ?>><?= $l ?></option>
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
            <button type="submit"
                class="flex-1 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded text-xs font-bold transition">Go</button>
            <a href="excel-leads.php"
                class="flex-1 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded text-xs font-bold transition text-center">✕</a>
        </div>
    </form>

    <!-- Bulk Delete Bar (Admin only) -->
    <?php if ($isAdmin): ?>
    <form id="bulkForm" action="excel-leads.php?<?= htmlspecialchars($_SERVER['QUERY_STRING'] ?? '') ?>" method="POST">
        <input type="hidden" name="bulk_delete" value="1">
        <div id="bulkBar" style="display:none;" class="mb-4 flex items-center gap-3 bg-rose-50 border border-rose-200 rounded-xl px-4 py-2.5">
            <span id="selectedCount" class="text-xs font-bold text-rose-700">0 selected</span>
            <button type="submit"
                onclick="return confirm('Delete selected leads? This cannot be undone.')"
                class="px-4 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-bold transition flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Delete Selected
            </button>
            <button type="button" onclick="clearSelection()"
                class="px-3 py-1.5 bg-white border border-rose-200 text-rose-500 rounded-lg text-xs font-bold hover:bg-rose-100 transition">
                Cancel
            </button>
        </div>
    <?php endif; ?>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle text-sm">
            <thead class="text-[10px] text-slate-400 font-extrabold uppercase tracking-wider border-b border-slate-150">
                <tr>
                    <?php if ($isAdmin): ?>
                    <th class="pb-3 w-8">
                        <input type="checkbox" id="selectAll" title="Select All"
                            class="w-4 h-4 rounded border-slate-300 cursor-pointer accent-rose-600">
                    </th>
                    <?php endif; ?>
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
                        <td colspan="<?= $isAdmin ? 7 : 6 ?>" class="py-12 text-center">
                            <div class="text-slate-300 text-4xl mb-2">📋</div>
                            <div class="text-slate-400 font-semibold text-sm">No leads found matching your filters.</div>
                            <a href="excel-import.php" class="text-blue-500 text-xs underline mt-1 inline-block">Import a file to get started →</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($leads as $lead): ?>
                        <tr class="lead-row">
                            <?php if ($isAdmin): ?>
                            <td class="py-3">
                                <input type="checkbox" name="selected_ids[]" value="<?= $lead['id'] ?>"
                                    class="lead-checkbox w-4 h-4 rounded border-slate-300 cursor-pointer accent-rose-600">
                            </td>
                            <?php endif; ?>

                            <!-- Client Details -->
                            <td class="py-3">
                                <div class="font-bold text-slate-800 leading-snug"><?= htmlspecialchars($lead['name']) ?></div>
                                <div class="text-[11px] text-slate-500 font-medium"><?= htmlspecialchars($lead['phone']) ?>
                                </div>
                                <div class="text-[10px] text-slate-400"><?= htmlspecialchars($lead['email'] ?? '—') ?></div>
                                <?php if ($lead['meta_lead_id']): ?>
                                    <div
                                        class="text-[9px] bg-slate-100 px-1.5 py-0.5 rounded text-slate-400 inline-block mt-1 font-mono">
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
                                    <?php
                                    $plat = strtolower(trim($lead['platform'] ?? ''));
                                    $isIG = in_array($plat, ['ig','instagram']);
                                    $isFB = in_array($plat, ['fb','facebook']);
                                    if ($plat):
                                    ?>
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded border text-[8px] font-bold uppercase
                                            <?= $isIG ? 'bg-gradient-to-r from-purple-50 to-pink-50 text-pink-600 border-pink-200' : ($isFB ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-blue-50 text-blue-600 border-blue-100') ?>">
                                            <?php if ($isIG): ?>
                                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                                                </svg>
                                                IG
                                            <?php elseif ($isFB): ?>
                                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                                </svg>
                                                FB
                                            <?php else: ?>
                                                <?= htmlspecialchars($lead['platform']) ?>
                                            <?php endif; ?>
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
                                    <div><span class="text-slate-400">Looking for:</span>
                                        <strong><?= htmlspecialchars($lead['are_you_looking_for']) ?></strong>
                                    </div>
                                <?php endif; ?>
                                <?php if ($lead['budget'] ?? null): ?>
                                    <div><span class="text-slate-400">Budget:</span> <strong
                                            class="text-brand-500"><?= htmlspecialchars($lead['budget']) ?></strong></div>
                                <?php endif; ?>
                                <?php if ($lead['purchase_time'] ?? null): ?>
                                    <div><span class="text-slate-400">Timeline:</span>
                                        <?= htmlspecialchars($lead['purchase_time']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($lead['have_you_invested_in_property_before'] ?? null): ?>
                                    <div><span class="text-slate-400">Invested before:</span>
                                        <?= htmlspecialchars($lead['have_you_invested_in_property_before']) ?>
                                    </div>
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
                                            <option value="<?= $a['id'] ?>" <?= $lead['assigned_to'] == $a['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($a['username']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>

                            <!-- Status Badge -->
                            <td class="py-3">
                                <?php
                                $col = match ($lead['status']) {
                                    'new' => 'bg-blue-100 text-blue-700 border border-blue-200',
                                    'contacting' => 'bg-amber-100 text-amber-700 border border-amber-200',
                                    'qualified' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
                                    'lost' => 'bg-rose-100 text-rose-700 border border-rose-200',
                                    'closed' => 'bg-indigo-100 text-indigo-700 border border-indigo-200',
                                    default => 'bg-slate-100 text-slate-600',
                                };
                                ?>
                                <span
                                    class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider <?= $col ?>">
                                    <?= htmlspecialchars($lead['status']) ?>
                                </span>
                            </td>

                            <!-- Update Status -->
                            <td class="py-3 text-end flex flex-col gap-1 items-end">
                                <form action="excel-leads.php?<?= $_SERVER['QUERY_STRING'] ?? '' ?>" method="POST"
                                    class="inline-flex">
                                    <input type="hidden" name="inquiry_id" value="<?= $lead['id'] ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    <select name="status" onchange="this.form.submit()"
                                        class="px-2 py-1 bg-slate-50 border border-slate-200 text-xs rounded font-medium focus:outline-none">
                                        <?php foreach (['new' => 'New', 'contacting' => 'Contact', 'qualified' => 'Qualify', 'lost' => 'Lost', 'closed' => 'Closed'] as $v => $l): ?>
                                            <option value="<?= $v ?>" <?= $lead['status'] === $v ? 'selected' : '' ?>><?= $l ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                                <a href="lead-details.php?id=<?= $lead['id'] ?>"
                                    class="px-2.5 py-1 bg-brand-50 hover:bg-brand-500 hover:text-white text-brand-600 rounded text-[10px] font-bold transition">View
                                    Details →</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($isAdmin): ?>
    </form><!-- /bulkForm -->
    <?php endif; ?>

    <!-- Footer: count + pagination -->
    <?php if (!empty($leads)): ?>
    <div class="mt-4 pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
        <div class="text-[10px] text-slate-400 font-medium">
            Showing <strong><?= count($leads) ?></strong> of <strong><?= $totalLeads ?></strong> lead(s)
            — Page <strong><?= $currentPage ?></strong> of <strong><?= $totalPages ?></strong>
            <?= $filterSearch || $filterStatus || $filterCampaign || $filterStart || $filterEnd ? ' (filtered)' : '' ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <nav class="flex items-center gap-1 flex-wrap">
            <?php if ($currentPage > 1): ?>
                <a href="<?= pagUrl($qsBase, $currentPage - 1) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs text-slate-600 hover:bg-slate-50 font-medium transition">← Prev</a>
            <?php else: ?>
                <span class="px-3 py-1.5 rounded-lg border border-slate-100 bg-slate-50 text-xs text-slate-300 font-medium">← Prev</span>
            <?php endif; ?>

            <?php
            $pStart = max(1, $currentPage - 2);
            $pEnd   = min($totalPages, $currentPage + 2);
            if ($pStart > 1): ?>
                <a href="<?= pagUrl($qsBase, 1) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs text-slate-600 hover:bg-slate-50 font-medium transition">1</a>
                <?php if ($pStart > 2): ?><span class="px-1 text-slate-300 text-xs">…</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($pg = $pStart; $pg <= $pEnd; $pg++): ?>
                <?php if ($pg === $currentPage): ?>
                    <span class="px-3 py-1.5 rounded-lg bg-slate-800 text-white text-xs font-bold"><?= $pg ?></span>
                <?php else: ?>
                    <a href="<?= pagUrl($qsBase, $pg) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs text-slate-600 hover:bg-slate-50 font-medium transition"><?= $pg ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($pEnd < $totalPages): ?>
                <?php if ($pEnd < $totalPages - 1): ?><span class="px-1 text-slate-300 text-xs">…</span><?php endif; ?>
                <a href="<?= pagUrl($qsBase, $totalPages) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs text-slate-600 hover:bg-slate-50 font-medium transition"><?= $totalPages ?></a>
            <?php endif; ?>

            <?php if ($currentPage < $totalPages): ?>
                <a href="<?= pagUrl($qsBase, $currentPage + 1) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs text-slate-600 hover:bg-slate-50 font-medium transition">Next →</a>
            <?php else: ?>
                <span class="px-3 py-1.5 rounded-lg border border-slate-100 bg-slate-50 text-xs text-slate-300 font-medium">Next →</span>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($isAdmin): ?>
<style>
.lead-row.row-selected { background-color: #fff1f2; }
</style>
<script>
(function(){
    var selectAll     = document.getElementById('selectAll');
    var checkboxes    = document.querySelectorAll('.lead-checkbox');
    var bulkBar       = document.getElementById('bulkBar');
    var selectedCount = document.getElementById('selectedCount');

    function updateBar() {
        var checked = document.querySelectorAll('.lead-checkbox:checked');
        if (checked.length > 0) {
            bulkBar.style.display = 'flex';
            selectedCount.textContent = checked.length + ' selected';
        } else {
            bulkBar.style.display = 'none';
        }
        document.querySelectorAll('.lead-row').forEach(function(row) {
            var cb = row.querySelector('.lead-checkbox');
            if (cb && cb.checked) row.classList.add('row-selected');
            else row.classList.remove('row-selected');
        });
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(function(cb){ cb.checked = selectAll.checked; });
            updateBar();
        });
    }

    checkboxes.forEach(function(cb){
        cb.addEventListener('change', function(){
            if (selectAll) selectAll.checked = Array.from(checkboxes).every(function(c){ return c.checked; });
            updateBar();
        });
    });

    window.clearSelection = function() {
        checkboxes.forEach(function(cb){ cb.checked = false; });
        if (selectAll) selectAll.checked = false;
        updateBar();
    };
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>