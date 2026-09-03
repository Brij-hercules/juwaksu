<?php
// crm/inquiries.php
$pageTitle = "Inquiries & Leads Pipeline";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/lead_status_helper.php';

// Verify view permission
require_permission('inquiries', 'view');

$successMsg = '';
$errorMsg = '';

// 1. Process Assignment Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inquiryId = intval($_POST['inquiry_id']);
    
    if (isset($_POST['update_assignment'])) {
        $assignedTo = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
        if (!has_permission('inquiries', 'edit')) {
            $errorMsg = "You do not have permission to assign leads.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE inquiries SET assigned_to = ? WHERE id = ?");
                $stmt->execute([$assignedTo, $inquiryId]);
                $successMsg = "Lead assignment updated successfully.";
            } catch (\PDOException $e) {
                $errorMsg = "Error updating lead assignment: " . $e->getMessage();
            }
        }
    }
}

// 2. Filters
$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';
$filterSource = isset($_GET['source']) ? trim($_GET['source']) : '';

// Build Query
$queryStr = "
    SELECT i.*, p.title as property_title, u.username as agent_name 
    FROM inquiries i 
    LEFT JOIN properties p ON i.property_id = p.id 
    LEFT JOIN users u ON i.assigned_to = u.id 
    WHERE 1=1
";
$params = [];

$isSalesEmployee = ($currentUser['role_name'] === 'Sales Employee');
if ($isSalesEmployee) {
    $queryStr .= " AND i.assigned_to = " . intval($currentUser['id']);
}

if (!empty($filterStatus)) {
    $queryStr .= " AND i.status = ?";
    $params[] = $filterStatus;
}
if (!empty($filterSource)) {
    $queryStr .= " AND i.source = ?";
    $params[] = $filterSource;
}

$queryStr .= " ORDER BY i.id DESC";

try {
    $stmtList = $pdo->prepare($queryStr);
    $stmtList->execute($params);
    $inquiries = $stmtList->fetchAll();
    
    // Fetch all active agents/sales staff for assignment dropdown
    $agents = $pdo->query("
        SELECT u.id, u.username, r.role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.status = 'active' AND r.role_name IN ('Admin', 'Sales Employee', 'Meta Manager')
        ORDER BY u.username ASC
    ")->fetchAll();
} catch (\Exception $e) {
    $inquiries = [];
    $agents = [];
    $errorMsg = "Error: " . $e->getMessage();
}
?>

<!-- Alerts -->
<?php if (!empty($successMsg)): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 rounded-xl bg-emerald-50 text-emerald-700 shadow-sm text-xs p-4 mb-6" role="alert">
        <strong>Success!</strong> <?php echo $successMsg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($errorMsg)): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 rounded-xl bg-rose-50 text-rose-700 shadow-sm text-xs p-4 mb-6" role="alert">
        <strong>Error!</strong> <?php echo $errorMsg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
        <div>
            <h3 class="text-base font-extrabold text-slate-800 mb-0.5">Leads Pipeline</h3>
            <p class="text-slate-400 text-xs font-light font-sans">Review incoming messages, assign sales agents and update follow-up statuses.</p>
        </div>
        
        <div class="flex items-center gap-2">
            <!-- Export endpoints matching active filters -->
            <?php
            $exportQuery = http_build_query([
                'module' => 'inquiries',
                'status' => $filterStatus,
                'source' => $filterSource
            ]);
            ?>
            <a href="export.php?<?php echo $exportQuery; ?>&format=csv" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold transition flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                CSV Export
            </a>
            <a href="export.php?<?php echo $exportQuery; ?>&format=pdf" target="_blank" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold transition flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                PDF Export
            </a>
        </div>
    </div>

    <!-- Pipeline Filters -->
    <form action="inquiries.php" method="GET" class="row g-3 bg-slate-50 p-4 rounded-xl mb-6 border border-slate-200/50">
        <div class="col-md-5">
            <label for="status" class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Lead Status</label>
            <select name="status" id="status" class="w-full px-3 py-2 rounded-lg bg-white border border-slate-200 text-xs">
                <option value="">All Pipeline Statuses</option>
                <?php foreach (LEAD_STATUSES as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $filterStatus === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-5">
            <label for="source" class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Marketing Source</label>
            <select name="source" id="source" class="w-full px-3 py-2 rounded-lg bg-white border border-slate-200 text-xs">
                <option value="">All Sources</option>
                <option value="website" <?php echo $filterSource === 'website' ? 'selected' : ''; ?>>Direct Website Inquiry</option>
                <option value="meta_ads" <?php echo $filterSource === 'meta_ads' ? 'selected' : ''; ?>>Meta Facebook Ads Lead</option>
            </select>
        </div>
        
        <div class="col-md-2 flex items-end">
            <button type="submit" class="w-full py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-lg text-xs font-bold transition">
                Filter Leads
            </button>
        </div>
    </form>

    <!-- Leads Table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle text-sm">
            <thead class="text-[10px] text-slate-400 font-bold uppercase tracking-wider border-b border-slate-150">
                <tr>
                    <th class="pb-3">Client Details</th>
                    <th class="pb-3">Inquiry Details</th>
                    <th class="pb-3">Assigned Representative</th>
                    <th class="pb-3">Status</th>
                    <th class="pb-3 text-end">Update Controls</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($inquiries)): ?>
                    <tr>
                        <td colspan="5" class="py-6 text-center text-slate-400">No leads registered in pipeline.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($inquiries as $inq): ?>
                        <tr>
                            <!-- Client -->
                            <td class="py-3">
                                <div class="font-bold text-slate-800 leading-snug"><?php echo htmlspecialchars($inq['name']); ?></div>
                                <div class="text-[11px] text-slate-500 font-medium"><?php echo htmlspecialchars($inq['phone']); ?></div>
                                <div class="text-[10px] text-slate-400"><?php echo htmlspecialchars($inq['email']); ?></div>
                            </td>
                            
                            <!-- Inquiry -->
                            <td class="py-3">
                                <div class="text-xs text-slate-600 max-w-xs break-words mb-1">"<?php echo htmlspecialchars($inq['message']); ?>"</div>
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded border <?php echo $inq['source'] === 'meta_ads' ? 'bg-blue-50 text-blue-600 border-blue-100' : 'bg-purple-50 text-purple-600 border-purple-100'; ?>">
                                        <?php echo $inq['source'] === 'meta_ads' ? 'Meta Ad Form' : 'Website Form'; ?>
                                    </span>
                                    <?php if ($inq['campaign_name']): ?>
                                        <span class="text-[9px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded font-medium">Campaign: <?php echo htmlspecialchars($inq['campaign_name']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($inq['property_title']): ?>
                                        <span class="text-[9px] bg-indigo-50 text-brand-500 border border-indigo-100 px-1.5 py-0.5 rounded font-bold">Property: <?php echo htmlspecialchars($inq['property_title']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            
                            <!-- Assignment -->
                            <td class="py-3">
                                <form action="inquiries.php?<?php echo $_SERVER['QUERY_STRING'] ?? ''; ?>" method="POST" class="flex items-center gap-1">
                                    <input type="hidden" name="inquiry_id" value="<?php echo $inq['id']; ?>">
                                    <input type="hidden" name="update_assignment" value="1">
                                    
                                    <select name="assigned_to" onchange="this.form.submit()" class="px-2 py-1 bg-slate-50 border border-slate-200 text-xs rounded focus:outline-none">
                                        <option value="">Unassigned</option>
                                        <?php foreach ($agents as $agent): ?>
                                            <option value="<?php echo $agent['id']; ?>" <?php echo $inq['assigned_to'] == $agent['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($agent['username']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                            
                            <!-- Status Badges -->
                            <td class="py-3">
                                <?= get_status_badge($inq['status']) ?>
                            </td>
                            
                            <!-- Update Status actions -->
                            <td class="py-3 text-end flex flex-col gap-1 items-end">
                                <a href="lead-details.php?id=<?= $inq['id'] ?>" class="px-2.5 py-1 bg-brand-50 hover:bg-brand-500 hover:text-white text-brand-600 rounded text-[10px] font-bold transition">View Details →</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
