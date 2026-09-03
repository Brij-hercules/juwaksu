<?php
// crm/index.php
$pageTitle = "CRM Overview";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/lead_status_helper.php';

$isSalesEmployee = ($currentUser['role_name'] === 'Sales Employee');
$userId = $currentUser['id'];

// Default variables for both roles
$recentInquiries = [];

try {
    if ($isSalesEmployee) {
        // --- SALES EMPLOYEE STATS ---
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

        // Fetch Due Reminders
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

        // Recent assigned leads
        $stmtRecent = $pdo->prepare("
            SELECT i.*, p.title as property_title 
            FROM inquiries i 
            LEFT JOIN properties p ON i.property_id = p.id 
            WHERE i.assigned_to = ?
            ORDER BY i.id DESC 
            LIMIT 5
        ");
        $stmtRecent->execute([$userId]);
        $recentInquiries = $stmtRecent->fetchAll();

    } else {
        // --- ADMIN / GLOBAL STATS ---
        $statProperties = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
        $statCategories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
        $statInquiries  = $pdo->query("SELECT COUNT(*) FROM inquiries")->fetchColumn();
        $statMetaLeads  = $pdo->query("SELECT COUNT(*) FROM inquiries WHERE source = 'meta_ads'")->fetchColumn();
        $statMetaClicks = $pdo->query("SELECT COUNT(*) FROM meta_ads_clicks")->fetchColumn();
        
        // Recent all leads
        $stmtRecent = $pdo->query("
            SELECT i.*, p.title as property_title 
            FROM inquiries i 
            LEFT JOIN properties p ON i.property_id = p.id 
            ORDER BY i.id DESC 
            LIMIT 5
        ");
        $recentInquiries = $stmtRecent->fetchAll();
    }
} catch (\Exception $e) {
    // Graceful fallback
}
?>

<!-- Greetings Card banner -->
<div class="bg-gradient-to-r from-brand-600 via-indigo-900 to-indigo-950 p-8 rounded-3xl shadow-lg border border-indigo-850/50 mb-8 text-white relative overflow-hidden">
    <div class="absolute -right-20 -top-20 w-60 h-60 bg-amber-500/10 rounded-full blur-2xl"></div>
    <div class="relative z-10 max-w-xl">
        <h1 class="text-3xl font-black mb-2">Welcome Back, <?php echo htmlspecialchars($currentUser['username']); ?>!</h1>
        <p class="text-indigo-200/80 font-light text-sm leading-relaxed">
            <?php if ($isSalesEmployee): ?>
                Here is your assigned leads overview. You can manage your pipeline, update statuses, and follow up with clients.
            <?php else: ?>
                Here is your real estate pipeline overview. You can manage property details, respond to customer inquiries, and track performance of Meta Ads campaigns.
            <?php endif; ?>
        </p>
    </div>
</div>

<?php if ($isSalesEmployee && !empty($dueReminders)): ?>
    <div class="alert bg-rose-50 border border-rose-200 text-rose-700 rounded-2xl p-4 mb-8 flex items-center justify-between shadow-sm" role="alert">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <strong class="font-bold text-sm">Action Required!</strong>
                <p class="text-xs mt-0.5">You have <?= count($dueReminders) ?> scheduled follow-up(s) that are currently due or overdue.</p>
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
        
        <!-- Sales Stat 1: Total Leads -->
        <div class="col-xl-3 col-sm-6">
            <div class="bg-white p-6 rounded-2xl border border-slate-200/60 shadow-sm flex items-center gap-4 hover:shadow-md transition duration-200">
                <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block mb-0.5">Total Leads</span>
                    <span class="text-2xl font-black text-slate-800"><?php echo number_format($statTotalLeads ?? 0); ?></span>
                </div>
            </div>
        </div>

        <!-- Sales Stat 2: Confirm Leads -->
        <div class="col-xl-3 col-sm-6">
            <div class="bg-white p-6 rounded-2xl border border-slate-200/60 shadow-sm flex items-center gap-4 hover:shadow-md transition duration-200">
                <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block mb-0.5">Confirm Leads</span>
                    <span class="text-2xl font-black text-slate-800"><?php echo number_format($statConfirmLeads ?? 0); ?></span>
                </div>
            </div>
        </div>

        <!-- Sales Stat 3: Loss Leads -->
        <div class="col-xl-3 col-sm-6">
            <div class="bg-white p-6 rounded-2xl border border-slate-200/60 shadow-sm flex items-center gap-4 hover:shadow-md transition duration-200">
                <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block mb-0.5">Loss Leads</span>
                    <span class="text-2xl font-black text-slate-800"><?php echo number_format($statLossLeads ?? 0); ?></span>
                </div>
            </div>
        </div>

        <!-- Sales Stat 4: Waiting Leads -->
        <div class="col-xl-3 col-sm-6">
            <div class="bg-white p-6 rounded-2xl border border-slate-200/60 shadow-sm flex items-center gap-4 hover:shadow-md transition duration-200">
                <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block mb-0.5">Waiting Leads</span>
                    <span class="text-2xl font-black text-slate-800"><?php echo number_format($statWaitLeads ?? 0); ?></span>
                </div>
            </div>
        </div>

    <?php else: ?>

        <!-- Admin Stat 1: Properties -->
        <div class="col-xl-3 col-sm-6">
            <div class="bg-white p-6 rounded-2xl border border-slate-200/60 shadow-sm flex items-center gap-4 hover:shadow-md transition duration-200">
                <div class="w-12 h-12 bg-indigo-50 text-brand-500 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block mb-0.5">Total Listings</span>
                    <span class="text-2xl font-black text-slate-800"><?php echo number_format($statProperties ?? 0); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Admin Stat 2: Inquiries -->
        <div class="col-xl-3 col-sm-6">
            <div class="bg-white p-6 rounded-2xl border border-slate-200/60 shadow-sm flex items-center gap-4 hover:shadow-md transition duration-200">
                <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block mb-0.5">Total Leads</span>
                    <span class="text-2xl font-black text-slate-800"><?php echo number_format($statInquiries ?? 0); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Admin Stat 3: Meta Leads -->
        <div class="col-xl-3 col-sm-6">
            <div class="bg-white p-6 rounded-2xl border border-slate-200/60 shadow-sm flex items-center gap-4 hover:shadow-md transition duration-200">
                <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block mb-0.5">Meta Campaign Leads</span>
                    <span class="text-2xl font-black text-slate-800"><?php echo number_format($statMetaLeads ?? 0); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Admin Stat 4: Meta Click Track -->
        <div class="col-xl-3 col-sm-6">
            <div class="bg-white p-6 rounded-2xl border border-slate-200/60 shadow-sm flex items-center gap-4 hover:shadow-md transition duration-200">
                <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"></path></svg>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block mb-0.5">Meta Ads Clicks</span>
                    <span class="text-2xl font-black text-slate-800"><?php echo number_format($statMetaClicks ?? 0); ?></span>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<div class="row g-5">
    <!-- Left panel: Recent Leads Inbound -->
    <div class="col-lg-8">
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-base font-extrabold text-slate-800">Recent Leads &amp; Inquiries</h3>
            </div>
            
            <?php if (empty($recentInquiries)): ?>
                <p class="text-slate-400 text-xs text-center py-6">No inquiry submissions recorded yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-borderless align-middle text-sm">
                        <thead class="text-[10px] text-slate-400 font-bold uppercase tracking-wider border-b border-slate-100">
                            <tr>
                                <th class="pb-3">Client</th>
                                <th class="pb-3">Inquiry Message</th>
                                <th class="pb-3">Source</th>
                                <th class="pb-3">Status</th>
                                <th class="pb-3 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach ($recentInquiries as $inq): ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="py-3">
                                        <div class="font-bold text-slate-800 leading-snug"><?php echo htmlspecialchars($inq['name']); ?></div>
                                        <div class="text-[10px] text-slate-400"><?php echo htmlspecialchars($inq['phone']); ?></div>
                                    </td>
                                    <td class="py-3">
                                        <span class="text-slate-600 block line-clamp-1 max-w-xs"><?php echo htmlspecialchars($inq['message']); ?></span>
                                        <?php if ($inq['property_title']): ?>
                                            <span class="text-[10px] text-brand-500 font-medium">Re: <?php echo htmlspecialchars($inq['property_title']); ?></span>
                                        <?php else: ?>
                                            <span class="text-[10px] text-slate-400">General Consultation</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3">
                                        <?php if ($inq['source'] === 'meta_ads'): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-600 border border-blue-150">
                                                Meta Ads
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-bold bg-purple-50 text-purple-600 border border-purple-150">
                                                Website
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3">
                                        <?= get_status_badge($inq['status']) ?>
                                    </td>
                                    <td class="py-3 text-end">
                                        <a href="lead-details.php?id=<?php echo $inq['id']; ?>" class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-100 hover:bg-brand-500 hover:text-white text-slate-600 rounded text-xs font-bold transition">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($isSalesEmployee && !empty($dueReminders)): ?>
<!-- Reminders Modal -->
<div class="modal fade" id="remindersModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-2xl border-0 shadow-xl overflow-hidden">
            <div class="modal-header bg-rose-600 text-white border-0 py-4 px-6">
                <h5 class="modal-title font-black text-lg flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    Action Required: Due Scheduled Tasks
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
    // Auto-open reminders modal on login/page load if tasks are due
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
