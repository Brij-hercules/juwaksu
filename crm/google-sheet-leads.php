<?php
// crm/google-sheet-leads.php
$pageTitle = "Google Sheet Leads Pipeline";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/db.php';

// Verify view permission (Google Sheet Leads are a subset of inquiries)
require_permission('inquiries', 'view');

$successMsg = '';
$errorMsg = '';

// 1. Process Status / Assignment Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inquiryId = intval($_POST['inquiry_id']);
    
    if (isset($_POST['update_status'])) {
        $newStatus = trim($_POST['status']);
        if (!has_permission('inquiries', 'edit')) {
            $errorMsg = "You do not have permission to modify leads.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE inquiries SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $inquiryId]);
                $successMsg = "Lead status updated successfully.";
            } catch (\PDOException $e) {
                $errorMsg = "Error updating lead status: " . $e->getMessage();
            }
        }
    }
    
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

// 2. Fetch Stored API Key for presentation to Admin
$apiKey = '';
try {
    $stmtKey = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'google_sheet_api_key'");
    $stmtKey->execute();
    $apiKey = $stmtKey->fetchColumn();
} catch (\Exception $e) {
    // Fail silently
}

// 3. Filters
$filterCampaign = isset($_GET['campaign_name']) ? trim($_GET['campaign_name']) : '';
$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';
$filterStart = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$filterEnd = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// 4. Build Leads Query (Only Google Sheet leads where meta_lead_id is present)
$queryStr = "
    SELECT i.*, u.username as agent_name 
    FROM inquiries i 
    LEFT JOIN users u ON i.assigned_to = u.id 
    WHERE i.meta_lead_id IS NOT NULL
";
$params = [];

if (!empty($filterCampaign)) {
    $queryStr .= " AND i.campaign_name = ?";
    $params[] = $filterCampaign;
}
if (!empty($filterStatus)) {
    $queryStr .= " AND i.status = ?";
    $params[] = $filterStatus;
}
if (!empty($filterStart)) {
    $queryStr .= " AND DATE(i.created_at) >= ?";
    $params[] = $filterStart;
}
if (!empty($filterEnd)) {
    $queryStr .= " AND DATE(i.created_at) <= ?";
    $params[] = $filterEnd;
}

$queryStr .= " ORDER BY i.id DESC";

try {
    $stmtList = $pdo->prepare($queryStr);
    $stmtList->execute($params);
    $leads = $stmtList->fetchAll();

    // Fetch unique campaign names for filtering dropdown
    $campaignsList = $pdo->query("
        SELECT DISTINCT campaign_name 
        FROM inquiries 
        WHERE meta_lead_id IS NOT NULL AND campaign_name IS NOT NULL
    ")->fetchAll(PDO::FETCH_COLUMN);

    // Fetch active agents for assignment dropdown
    $agents = $pdo->query("
        SELECT u.id, u.username, r.role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.status = 'active' AND r.role_name IN ('Admin', 'Sales Employee', 'Meta Manager')
        ORDER BY u.username ASC
    ")->fetchAll();

    // Stats
    $statTotal = $pdo->query("SELECT COUNT(*) FROM inquiries WHERE meta_lead_id IS NOT NULL")->fetchColumn();
    $statNew = $pdo->query("SELECT COUNT(*) FROM inquiries WHERE meta_lead_id IS NOT NULL AND status = 'new'")->fetchColumn();
    $statActive = $pdo->query("SELECT COUNT(*) FROM inquiries WHERE meta_lead_id IS NOT NULL AND status IN ('contacting', 'qualified')")->fetchColumn();
    $statClosed = $pdo->query("SELECT COUNT(*) FROM inquiries WHERE meta_lead_id IS NOT NULL AND status = 'closed'")->fetchColumn();

} catch (\Exception $e) {
    $leads = [];
    $campaignsList = [];
    $agents = [];
    $statTotal = $statNew = $statActive = $statClosed = 0;
    $errorMsg = "Error: " . $e->getMessage();
}

// 5. Read last 10 lines from log file
$logs = [];
$logFilePath = __DIR__ . '/../api/logs/google_sheet_sync.log';
if (file_exists($logFilePath)) {
    $logContent = file($logFilePath);
    if ($logContent) {
        $logs = array_slice(array_reverse($logContent), 0, 10);
    }
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

<!-- Stats Panel -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-white p-5 rounded-2xl border border-slate-200/60 shadow-sm">
        <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-wider block mb-1">Total Synced Leads</span>
        <span class="font-black text-slate-800 text-2xl"><?php echo $statTotal; ?></span>
    </div>
    <div class="bg-white p-5 rounded-2xl border border-slate-200/60 shadow-sm">
        <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-wider block mb-1">New Pipeline Leads</span>
        <span class="font-black text-blue-600 text-2xl"><?php echo $statNew; ?></span>
    </div>
    <div class="bg-white p-5 rounded-2xl border border-slate-200/60 shadow-sm">
        <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-wider block mb-1">In Follow-Up</span>
        <span class="font-black text-amber-600 text-2xl"><?php echo $statActive; ?></span>
    </div>
    <div class="bg-white p-5 rounded-2xl border border-slate-200/60 shadow-sm">
        <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-wider block mb-1">Converted Leads</span>
        <span class="font-black text-emerald-600 text-2xl"><?php echo $statClosed; ?></span>
    </div>
</div>

<!-- API Integration Information Card -->
<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6 mb-8">
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h3 class="text-base font-extrabold text-slate-800 mb-1 flex items-center gap-1.5">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                Google Sheet API Security Key
            </h3>
            <p class="text-slate-400 text-xs font-light">Copy this secret key and use it in your Google Apps Script configuration file under <code>X-API-Key</code>.</p>
        </div>
        <div>
            <div class="flex items-center gap-2">
                <code class="px-3.5 py-2 bg-slate-100 rounded-lg text-xs font-bold text-slate-700 select-all border border-slate-200/60"><?php echo htmlspecialchars($apiKey); ?></code>
                <button onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($apiKey); ?>'); alert('API Key copied to clipboard!');" class="px-3 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-bold transition">Copy</button>
            </div>
        </div>
    </div>
</div>

<div class="row g-5">
    <!-- Main Leads Column -->
    <div class="col-xl-8">
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                <div>
                    <h3 class="text-base font-extrabold text-slate-800 mb-0.5">Imported Google Sheet Leads</h3>
                    <p class="text-slate-400 text-xs font-light">Showing all leads imported directly via the API endpoint.</p>
                </div>
            </div>

            <!-- Pipeline Filters -->
            <form action="google-sheet-leads.php" method="GET" class="row g-2 bg-slate-50 p-4 rounded-xl mb-6 border border-slate-200/50">
                <div class="col-md-3">
                    <select name="campaign_name" class="w-full px-3 py-2 rounded bg-white border border-slate-200 text-xs">
                        <option value="">All Campaigns</option>
                        <?php foreach ($campaignsList as $cName): ?>
                            <option value="<?php echo htmlspecialchars($cName); ?>" <?php echo $filterCampaign === $cName ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <select name="status" class="w-full px-3 py-2 rounded bg-white border border-slate-200 text-xs">
                        <option value="">All Pipeline Statuses</option>
                        <option value="new" <?php echo $filterStatus === 'new' ? 'selected' : ''; ?>>New</option>
                        <option value="contacting" <?php echo $filterStatus === 'contacting' ? 'selected' : ''; ?>>Contacting</option>
                        <option value="qualified" <?php echo $filterStatus === 'qualified' ? 'selected' : ''; ?>>Qualified</option>
                        <option value="lost" <?php echo $filterStatus === 'lost' ? 'selected' : ''; ?>>Lost</option>
                        <option value="closed" <?php echo $filterStatus === 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>

                <div class="col-md-2.5 flex items-center gap-1">
                    <input type="date" name="start_date" value="<?php echo $filterStart; ?>" class="w-full px-2 py-1.5 rounded bg-white border border-slate-200 text-xs" placeholder="Start Date">
                </div>
                <div class="col-md-2.5 flex items-center gap-1">
                    <input type="date" name="end_date" value="<?php echo $filterEnd; ?>" class="w-full px-2 py-1.5 rounded bg-white border border-slate-200 text-xs" placeholder="End Date">
                </div>
                
                <div class="col-md-1 flex items-end">
                    <button type="submit" class="w-full py-2 bg-slate-800 hover:bg-slate-700 text-white rounded text-xs font-bold transition">
                        Apply
                    </button>
                </div>
            </form>

            <!-- Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle text-sm">
                    <thead class="text-[10px] text-slate-400 font-bold uppercase tracking-wider border-b border-slate-150">
                        <tr>
                            <th class="pb-3">Client Details</th>
                            <th class="pb-3">Ad Campaign Source</th>
                            <th class="pb-3">Lead Answers / Profile</th>
                            <th class="pb-3">Representative</th>
                            <th class="pb-3">Pipeline Status</th>
                            <th class="pb-3 text-end">Update</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($leads)): ?>
                            <tr>
                                <td colspan="6" class="py-6 text-center text-slate-400">No imported Google Sheet leads found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($leads as $lead): ?>
                                <tr>
                                    <!-- Client -->
                                    <td class="py-3">
                                        <div class="font-bold text-slate-800 leading-snug"><?php echo htmlspecialchars($lead['name']); ?></div>
                                        <div class="text-[11px] text-slate-500 font-medium"><?php echo htmlspecialchars($lead['phone']); ?></div>
                                        <div class="text-[10px] text-slate-400"><?php echo htmlspecialchars($lead['email'] ?? 'No email'); ?></div>
                                        <div class="text-[9px] bg-slate-100 px-1.5 py-0.5 rounded text-slate-500 inline-block mt-1 font-mono">Meta ID: <?php echo htmlspecialchars($lead['meta_lead_id']); ?></div>
                                    </td>
                                    
                                    <!-- Campaign Source -->
                                    <td class="py-3">
                                        <div class="font-bold text-slate-700 leading-snug"><?php echo htmlspecialchars($lead['campaign_name'] ?? 'General Campaign'); ?></div>
                                        <div class="text-[10px] text-slate-400">Form: <?php echo htmlspecialchars($lead['form_name'] ?? 'General Form'); ?></div>
                                        <div class="text-[9px] text-slate-400 font-light flex items-center gap-1.5 flex-wrap mt-1">
                                            <span class="px-1.5 py-0.5 rounded bg-blue-50 text-blue-600 border border-blue-100 uppercase tracking-wide text-[8px] font-bold"><?php echo htmlspecialchars($lead['platform'] ?? 'Meta'); ?></span>
                                            <?php if ($lead['is_organic']): ?>
                                                <span class="px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-600 border border-emerald-100 uppercase tracking-wide text-[8px] font-bold">Organic</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <!-- Answers / Survey -->
                                    <td class="py-3 text-xs">
                                        <div class="space-y-0.5 text-slate-600">
                                            <?php if ($lead['are_you_looking_for']): ?>
                                                <div><strong>Looking for:</strong> <?php echo htmlspecialchars($lead['are_you_looking_for']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($lead['budget']): ?>
                                                <div><strong>Budget:</strong> <span class="text-brand-500 font-bold"><?php echo htmlspecialchars($lead['budget']); ?></span></div>
                                            <?php endif; ?>
                                            <?php if ($lead['purchase_time']): ?>
                                                <div><strong>Timeframe:</strong> <?php echo htmlspecialchars($lead['purchase_time']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($lead['have_you_invested_in_property_before']): ?>
                                                <div><strong>Invested before:</strong> <?php echo htmlspecialchars($lead['have_you_invested_in_property_before']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- Representative Assignment -->
                                    <td class="py-3">
                                        <form action="google-sheet-leads.php?<?php echo $_SERVER['QUERY_STRING'] ?? ''; ?>" method="POST" class="flex items-center gap-1">
                                            <input type="hidden" name="inquiry_id" value="<?php echo $lead['id']; ?>">
                                            <input type="hidden" name="update_assignment" value="1">
                                            
                                            <select name="assigned_to" onchange="this.form.submit()" class="px-2 py-1 bg-slate-50 border border-slate-200 text-xs rounded focus:outline-none">
                                                <option value="">Unassigned</option>
                                                <?php foreach ($agents as $agent): ?>
                                                    <option value="<?php echo $agent['id']; ?>" <?php echo $lead['assigned_to'] == $agent['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($agent['username']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </td>
                                    
                                    <!-- Status -->
                                    <td class="py-3">
                                        <?php
                                        $col = 'bg-slate-100 text-slate-600';
                                        if ($lead['status'] === 'new') $col = 'bg-blue-100 text-blue-700 border border-blue-200';
                                        elseif ($lead['status'] === 'contacting') $col = 'bg-amber-100 text-amber-700 border border-amber-200';
                                        elseif ($lead['status'] === 'qualified') $col = 'bg-emerald-100 text-emerald-700 border border-emerald-250';
                                        elseif ($lead['status'] === 'lost') $col = 'bg-rose-100 text-rose-700 border border-rose-200';
                                        elseif ($lead['status'] === 'closed') $col = 'bg-indigo-150 text-indigo-700 border border-indigo-250';
                                        ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider <?php echo $col; ?>">
                                            <?php echo htmlspecialchars($lead['status']); ?>
                                        </span>
                                        <div class="text-[9px] text-slate-400 mt-1 font-light">Imported: <?php echo date('d-M H:i', strtotime($lead['created_at'])); ?></div>
                                    </td>
                                    
                                    <!-- Action -->
                                    <td class="py-3 text-end">
                                        <form action="google-sheet-leads.php?<?php echo $_SERVER['QUERY_STRING'] ?? ''; ?>" method="POST" class="inline-flex gap-1">
                                            <input type="hidden" name="inquiry_id" value="<?php echo $lead['id']; ?>">
                                            <input type="hidden" name="update_status" value="1">
                                            
                                            <select name="status" onchange="this.form.submit()" class="px-2 py-1 bg-slate-50 border border-slate-200 text-xs rounded font-medium focus:outline-none">
                                                <option value="new" <?php echo $lead['status'] === 'new' ? 'selected' : ''; ?>>New</option>
                                                <option value="contacting" <?php echo $lead['status'] === 'contacting' ? 'selected' : ''; ?>>Contact</option>
                                                <option value="qualified" <?php echo $lead['status'] === 'qualified' ? 'selected' : ''; ?>>Qualify</option>
                                                <option value="lost" <?php echo $lead['status'] === 'lost' ? 'selected' : ''; ?>>Lost</option>
                                                <option value="closed" <?php echo $lead['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                            </select>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Sync Log Output Panel -->
    <div class="col-xl-4">
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6 sticky top-24">
            <h3 class="text-base font-extrabold text-slate-800 mb-1 flex items-center gap-1.5">
                <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Sync Integration Log
            </h3>
            <p class="text-slate-400 text-xs font-light mb-6">Real-time sync logs. Shows the last 10 entries from API.</p>

            <div class="space-y-3 font-mono text-[10px] max-h-[500px] overflow-y-auto pr-1">
                <?php if (empty($logs)): ?>
                    <p class="text-slate-400 text-xs text-center py-6">No sync events logged yet.</p>
                <?php else: ?>
                    <?php foreach ($logs as $line): ?>
                        <?php
                        $line = trim($line);
                        if (empty($line)) continue;
                        
                        $bgColor = 'bg-slate-50 text-slate-600 border-slate-100';
                        if (strpos($line, '[SUCCESS]') !== false) {
                            $bgColor = 'bg-emerald-50 text-emerald-700 border-emerald-100/50';
                        } elseif (strpos($line, '[DUPLICATE]') !== false) {
                            $bgColor = 'bg-amber-50 text-amber-700 border-amber-100/50';
                        } elseif (strpos($line, '[VALIDATION_ERROR]') !== false || strpos($line, '[UNAUTHORIZED]') !== false || strpos($line, '[SERVER_ERROR]') !== false) {
                            $bgColor = 'bg-rose-50 text-rose-700 border-rose-100/50';
                        }
                        ?>
                        <div class="p-2.5 rounded-lg border <?php echo $bgColor; ?> break-words leading-relaxed shadow-sm">
                            <?php echo htmlspecialchars($line); ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
