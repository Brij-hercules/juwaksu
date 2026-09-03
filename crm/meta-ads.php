<?php
// crm/meta-ads.php
$pageTitle = "Meta Ads Marketing Dashboard";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/lead_status_helper.php';

// Verify view permission
require_permission('meta_ads', 'view');

$successMsg = '';
$errorMsg = '';

// 1. Handle API Settings Toggle (Live vs Mock)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $mode = isset($_POST['meta_mock_mode']) ? '1' : '0';
    $token = trim($_POST['meta_access_token']);
    $adAccount = trim($_POST['meta_ad_account_id']);

    try {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('meta_mock_mode', ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        $stmt->execute([$mode]);

        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('meta_access_token', ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        $stmt->execute([$token]);

        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('meta_ad_account_id', ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        $stmt->execute([$adAccount]);

        $successMsg = "Meta Integration settings updated successfully.";
    } catch (\PDOException $e) {
        $errorMsg = "Error updating settings: " . $e->getMessage();
    }
}

// 2. Fetch Settings
$settings = [];
try {
    foreach ($pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll() as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (\Exception $e) {
    //
}

$mockMode = ($settings['meta_mock_mode'] ?? '1') === '1';

// 3. Filters
$filterCampaign = isset($_GET['campaign_name']) ? trim($_GET['campaign_name']) : '';
$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';
$filterStart = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$filterEnd = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// 4. Fetch leads from database matching filters
$leadsQuery = "SELECT * FROM inquiries WHERE source = 'meta_ads'";
$leadsParams = [];

if (!empty($filterCampaign)) {
    $leadsQuery .= " AND campaign_name = ?";
    $leadsParams[] = $filterCampaign;
}
if (!empty($filterStatus)) {
    $leadsQuery .= " AND status = ?";
    $leadsParams[] = $filterStatus;
}
if (!empty($filterStart)) {
    $leadsQuery .= " AND DATE(created_at) >= ?";
    $leadsParams[] = $filterStart;
}
if (!empty($filterEnd)) {
    $leadsQuery .= " AND DATE(created_at) <= ?";
    $leadsParams[] = $filterEnd;
}
$leadsQuery .= " ORDER BY id DESC";

try {
    $stmtLeads = $pdo->prepare($leadsQuery);
    $stmtLeads->execute($leadsParams);
    $metaLeads = $stmtLeads->fetchAll();

    // Fetch unique campaign names for filtering dropdown
    $campaignsList = $pdo->query("SELECT DISTINCT campaign_name FROM inquiries WHERE source = 'meta_ads' AND campaign_name IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);

    // Fetch Meta click logs
    $clicksQuery = "SELECT * FROM meta_ads_clicks ORDER BY id DESC LIMIT 15";
    $metaClicks = $pdo->query($clicksQuery)->fetchAll();
} catch (\Exception $e) {
    $metaLeads = [];
    $campaignsList = [];
    $metaClicks = [];
    $errorMsg = "Database error: " . $e->getMessage();
}

// 5. Mock Campaign Stats (if in mock mode)
$mockCampaigns = [
    ['name' => 'Kisan Kota Plots Campaign', 'clicks' => 342, 'leads' => 12, 'budget' => '₹15,000', 'spent' => '₹8,450', 'status' => 'ACTIVE'],
    ['name' => 'Wave City Extension Plots', 'clicks' => 520, 'leads' => 24, 'budget' => '₹25,000', 'spent' => '₹22,900', 'status' => 'ACTIVE'],
    ['name' => 'Premium Villas Campaign', 'clicks' => 129, 'leads' => 4, 'budget' => '₹10,000', 'spent' => '₹3,400', 'status' => 'PAUSED']
];
?>

<!-- Alerts -->
<?php if (!empty($successMsg)): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 rounded-xl bg-emerald-50 text-emerald-700 shadow-sm text-xs p-4 mb-6"
        role="alert">
        <strong>Success!</strong> <?php echo $successMsg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($errorMsg)): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 rounded-xl bg-rose-50 text-rose-700 shadow-sm text-xs p-4 mb-6"
        role="alert">
        <strong>Error!</strong> <?php echo $errorMsg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Header & settings toggler row -->
<div class="flex flex-wrap justify-between items-center gap-4 mb-8">
    <div>
        <h2 class="text-xl font-black text-slate-800 leading-none">Meta Facebook Ads</h2>
        <p class="text-slate-400 text-xs mt-1">Connect Marketing API and inspect clicks, UTM campaigns and incoming lead
            details.</p>
    </div>

    <!-- <div class="flex items-center gap-2">
        <button type="button" data-bs-toggle="modal" data-bs-target="#settingsModal" class="px-4 py-2 bg-slate-900 text-white rounded-lg text-xs font-bold transition flex items-center gap-1.5 shadow">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            API Credentials Setting
        </button>
    </div> -->
</div>

<!-- Integration Mode Banner -->
<div
    class="bg-blue-600 text-white p-5 rounded-2xl shadow-sm border border-blue-700 mb-8 flex items-center justify-between flex-wrap gap-4">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center font-bold">f</div>
        <div>
            <div class="font-bold text-sm">Meta Marketing API Status</div>
            <div class="text-xs text-blue-100">
                <?php echo $mockMode ? 'Simulating performance tracking (MOCK mode active)' : 'Connected dynamically via Graph API (LIVE mode)'; ?>
            </div>
        </div>
    </div>

    <span class="px-3 py-1 bg-white/20 rounded-full text-[10px] font-extrabold uppercase border border-white/30">
        <?php echo $mockMode ? 'Mock Simulated' : 'Live Connected'; ?>
    </span>
</div>

<!-- Mock Campaign list (active progress cards) -->
<div class="row g-4 mb-8">
    <?php foreach ($mockCampaigns as $camp): ?>
        <div class="col-lg-4">
            <div class="bg-white p-6 rounded-2xl border border-slate-200/60 shadow-sm relative overflow-hidden">
                <span
                    class="absolute top-4 right-4 text-[9px] font-extrabold px-2 py-0.5 rounded <?php echo $camp['status'] === 'ACTIVE' ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : 'bg-slate-100 text-slate-500'; ?>">
                    <?php echo $camp['status']; ?>
                </span>

                <h4 class="font-extrabold text-sm text-slate-800 mb-4"><?php echo htmlspecialchars($camp['name']); ?></h4>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider block mb-0.5">Clicks
                            Tracked</span>
                        <span class="font-black text-slate-800 text-lg"><?php echo $camp['clicks']; ?></span>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider block mb-0.5">Leads
                            Captured</span>
                        <span class="font-black text-brand-500 text-lg"><?php echo $camp['leads']; ?></span>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider block mb-0.5">Spent
                            Budget</span>
                        <span class="font-bold text-slate-800 text-xs"><?php echo $camp['spent']; ?> /
                            <?php echo $camp['budget']; ?></span>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider block mb-0.5">UTM
                            Click Link</span>
                        <a href="../track_click.php?utm_campaign=<?php echo urlencode($camp['name']); ?>&utm_ad=VariantA&redirect=index.php"
                            target="_blank" class="text-[10px] text-brand-500 underline font-bold">Copy Link</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-5">
    <!-- Meta Leads captured table -->
    <div class="col-lg-8">
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6">

            <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                <h3 class="text-base font-extrabold text-slate-800">Captured Leads via Facebook Forms</h3>

                <?php
                $exportQuery = http_build_query([
                    'module' => 'meta_leads',
                    'campaign_name' => $filterCampaign,
                    'status' => $filterStatus,
                    'start_date' => $filterStart,
                    'end_date' => $filterEnd
                ]);
                ?>
                <div class="flex gap-2">
                    <a href="export.php?<?php echo $exportQuery; ?>&format=csv"
                        class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold transition flex items-center gap-1">
                        CSV Export
                    </a>
                    <a href="export.php?<?php echo $exportQuery; ?>&format=pdf" target="_blank"
                        class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold transition flex items-center gap-1">
                        PDF Export
                    </a>
                </div>
            </div>

            <!-- Inline filters directly inside the table container -->
            <form action="meta-ads.php" method="GET"
                class="row g-2 bg-slate-50 p-3.5 rounded-xl mb-4 border border-slate-200/50">
                <div class="col-md-3">
                    <select name="campaign_name"
                        class="w-full px-2 py-1.5 rounded bg-white border border-slate-200 text-xs">
                        <option value="">All Campaigns</option>
                        <?php foreach ($campaignsList as $cName): ?>
                            <option value="<?php echo htmlspecialchars($cName); ?>" <?php echo $filterCampaign === $cName ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <select name="status" class="w-full px-2 py-1.5 rounded bg-white border border-slate-200 text-xs">
                        <option value="">All Statuses</option>
                        <?php foreach (LEAD_STATUSES as $k => $v): ?>
                            <option value="<?= $k ?>" <?= $filterStatus === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <input type="date" name="start_date" value="<?php echo $filterStart; ?>"
                        class="w-full px-2 py-1 rounded bg-white border border-slate-200 text-[10px]"
                        placeholder="Start Date">
                </div>
                <div class="col-md-2">
                    <input type="date" name="end_date" value="<?php echo $filterEnd; ?>"
                        class="w-full px-2 py-1 rounded bg-white border border-slate-200 text-[10px]"
                        placeholder="End Date">
                </div>

                <div class="col-md-2">
                    <button type="submit"
                        class="w-full py-1.5 bg-slate-800 hover:bg-slate-700 text-white rounded text-xs font-bold transition">
                        Apply
                    </button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle text-sm">
                    <thead
                        class="text-[10px] text-slate-400 font-bold uppercase tracking-wider border-b border-slate-100">
                        <tr>
                            <th class="pb-3">Lead Info</th>
                            <th class="pb-3">Campaign Source</th>
                            <th class="pb-3">Date</th>
                            <th class="pb-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php if (empty($metaLeads)): ?>
                            <tr>
                                <td colspan="4" class="py-6 text-center text-slate-400">No leads captured matching filters.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($metaLeads as $lead): ?>
                                <tr>
                                    <td class="py-3">
                                        <div class="font-bold text-slate-800 leading-snug">
                                            <?php echo htmlspecialchars($lead['name']); ?></div>
                                        <div class="text-[11px] text-slate-500 font-medium">
                                            <?php echo htmlspecialchars($lead['phone']); ?></div>
                                        <div class="text-[10px] text-slate-400 leading-none">
                                            <?php echo htmlspecialchars($lead['email']); ?></div>
                                    </td>
                                    <td class="py-3 font-semibold text-brand-500">
                                        <?php echo htmlspecialchars($lead['campaign_name'] ?? 'General Facebook Campaign'); ?>
                                    </td>
                                    <td class="py-3 text-xs text-slate-500">
                                        <?php echo date('d-M-y H:i', strtotime($lead['created_at'])); ?></td>
                                    <td class="py-3">
                                        <?= get_status_badge($lead['status']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <!-- Ad click log details panel -->
    <div class="col-lg-4">
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6">
            <h3 class="text-base font-extrabold text-slate-800 mb-4">Ad Click Analytics Tracker</h3>
            <p class="text-slate-400 text-xs font-light mb-6">Real-time log of UTM campaign link clickthroughs.</p>

            <div class="space-y-4">
                <?php if (empty($metaClicks)): ?>
                    <p class="text-slate-400 text-xs text-center py-6">No clicks logged yet.</p>
                <?php else: ?>
                    <?php foreach ($metaClicks as $click): ?>
                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-100 flex justify-between gap-3 text-xs">
                            <div>
                                <div class="font-bold text-slate-800 truncate max-w-[150px]">
                                    <?php echo htmlspecialchars($click['campaign_name']); ?></div>
                                <div class="text-[10px] text-slate-400"><?php echo htmlspecialchars($click['ad_name']); ?> • IP:
                                    <?php echo htmlspecialchars($click['ip_address']); ?></div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <span
                                    class="text-[9px] bg-indigo-50 text-brand-500 font-bold px-2 py-0.5 rounded block mb-1">Click
                                    Logged</span>
                                <span
                                    class="text-[10px] text-slate-400"><?php echo date('H:i', strtotime($click['clicked_at'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Settings Modal for Meta Credentials API Integration -->
<div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-3xl overflow-hidden border-0 shadow-2xl bg-white">
            <div class="modal-header bg-slate-900 border-0 text-white p-4">
                <h5 class="modal-title font-bold" id="settingsModalLabel">Meta API Integration Settings</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form action="meta-ads.php" method="POST" class="p-6 space-y-4">
                <input type="hidden" name="save_settings" value="1">

                <!-- Toggle -->
                <div class="flex items-center justify-between p-3 bg-slate-50 border rounded-xl">
                    <div>
                        <div class="font-bold text-xs text-slate-800">API Mock Mode</div>
                        <div class="text-[10px] text-slate-400">Simulate Meta Graph API response data.</div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="meta_mock_mode" id="meta_mock_mode"
                            value="1" <?php echo $mockMode ? 'checked' : ''; ?>>
                    </div>
                </div>

                <div>
                    <label for="meta_access_token"
                        class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Meta
                        Marketing Access Token</label>
                    <textarea name="meta_access_token" id="meta_access_token" rows="3"
                        class="w-full px-3 py-2.5 rounded-lg bg-slate-50 border border-slate-200 text-xs focus:outline-none focus:ring-2 focus:ring-brand-gold focus:bg-white text-slate-800"
                        placeholder="EAA..."><?php echo htmlspecialchars($settings['meta_access_token'] ?? ''); ?></textarea>
                </div>

                <div>
                    <label for="meta_ad_account_id"
                        class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Meta Ad
                        Account ID</label>
                    <input type="text" name="meta_ad_account_id" id="meta_ad_account_id"
                        value="<?php echo htmlspecialchars($settings['meta_ad_account_id'] ?? ''); ?>"
                        class="w-full px-3 py-2.5 rounded-lg bg-slate-50 border border-slate-200 text-xs focus:outline-none focus:ring-2 focus:ring-brand-gold focus:bg-white text-slate-800"
                        placeholder="act_...">
                </div>

                <div class="pt-4 border-t flex justify-end gap-3">
                    <button type="submit"
                        class="px-5 py-2 bg-brand-500 hover:bg-brand-600 text-white text-xs font-bold rounded-lg transition shadow">
                        Save Configuration
                    </button>
                    <button type="button"
                        class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-bold rounded-lg transition"
                        data-bs-dismiss="modal">
                        Close
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>