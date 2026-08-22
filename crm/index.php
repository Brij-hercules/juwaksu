<?php
// crm/index.php
$pageTitle = "CRM Overview";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/db.php';

// Fetch aggregate statistics
$statProperties = 0;
$statCategories = 0;
$statInquiries = 0;
$statMetaLeads = 0;
$statMetaClicks = 0;

try {
    $statProperties = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
    $statCategories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    $statInquiries  = $pdo->query("SELECT COUNT(*) FROM inquiries")->fetchColumn();
    $statMetaLeads  = $pdo->query("SELECT COUNT(*) FROM inquiries WHERE source = 'meta_ads'")->fetchColumn();
    $statMetaClicks = $pdo->query("SELECT COUNT(*) FROM meta_ads_clicks")->fetchColumn();
    
    // Fetch recent inquiries
    $stmtRecent = $pdo->query("
        SELECT i.*, p.title as property_title 
        FROM inquiries i 
        LEFT JOIN properties p ON i.property_id = p.id 
        ORDER BY i.id DESC 
        LIMIT 5
    ");
    $recentInquiries = $stmtRecent->fetchAll();
} catch (\Exception $e) {
    $recentInquiries = [];
}
?>

<!-- Greetings Card banner -->
<div class="bg-gradient-to-r from-brand-600 via-indigo-900 to-indigo-950 p-8 rounded-3xl shadow-lg border border-indigo-850/50 mb-8 text-white relative overflow-hidden">
    <div class="absolute -right-20 -top-20 w-60 h-60 bg-amber-500/10 rounded-full blur-2xl"></div>
    <div class="relative z-10 max-w-xl">
        <h1 class="text-3xl font-black mb-2">Welcome Back, <?php echo htmlspecialchars($currentUser['username']); ?>!</h1>
        <p class="text-indigo-200/80 font-light text-sm leading-relaxed">
            Here is your real estate pipeline overview. You can manage property details, respond to customer inquiries, and track performance of Meta Ads campaigns.
        </p>
    </div>
</div>

<!-- Stats Counter Grid -->
<div class="row g-4 mb-8">
    <!-- Stat 1: Properties -->
    <div class="col-xl-3 col-sm-6">
        <div class="bg-white p-6 rounded-2xl border border-slate-200/60 shadow-sm flex items-center gap-4 hover:shadow-md transition duration-200">
            <div class="w-12 h-12 bg-indigo-50 text-brand-500 rounded-xl flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            </div>
            <div>
                <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block mb-0.5">Total Listings</span>
                <span class="text-2xl font-black text-slate-800"><?php echo number_format($statProperties); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Stat 2: Inquiries -->
    <div class="col-xl-3 col-sm-6">
        <div class="bg-white p-6 rounded-2xl border border-slate-200/60 shadow-sm flex items-center gap-4 hover:shadow-md transition duration-200">
            <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
            </div>
            <div>
                <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block mb-0.5">Total Leads</span>
                <span class="text-2xl font-black text-slate-800"><?php echo number_format($statInquiries); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Stat 3: Meta Leads -->
    <div class="col-xl-3 col-sm-6">
        <div class="bg-white p-6 rounded-2xl border border-slate-200/60 shadow-sm flex items-center gap-4 hover:shadow-md transition duration-200">
            <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            </div>
            <div>
                <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block mb-0.5">Meta Campaign Leads</span>
                <span class="text-2xl font-black text-slate-800"><?php echo number_format($statMetaLeads); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Stat 4: Meta Click Track -->
    <div class="col-xl-3 col-sm-6">
        <div class="bg-white p-6 rounded-2xl border border-slate-200/60 shadow-sm flex items-center gap-4 hover:shadow-md transition duration-200">
            <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"></path></svg>
            </div>
            <div>
                <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block mb-0.5">Meta Ads Clicks</span>
                <span class="text-2xl font-black text-slate-800"><?php echo number_format($statMetaClicks); ?></span>
            </div>
        </div>
    </div>
</div>

<div class="row g-5">
    <!-- Left panel: Recent Leads Inbound -->
    <div class="col-lg-8">
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-base font-extrabold text-slate-800">Recent Leads &amp; Inquiries</h3>
                <?php /* DEMO HIDE - View Pipeline link hidden
                <?php if (has_permission('inquiries', 'view')): ?>
                    <a href="inquiries.php" class="text-xs font-bold text-brand-500 hover:text-brand-gold transition">View Pipeline &rarr;</a>
                <?php endif; ?>
                DEMO HIDE */ ?>
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
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach ($recentInquiries as $inq): ?>
                                <tr>
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
                                        <?php
                                        $color = 'bg-slate-100 text-slate-600';
                                        if ($inq['status'] === 'new') $color = 'bg-blue-100 text-blue-700';
                                        elseif ($inq['status'] === 'qualified') $color = 'bg-emerald-100 text-emerald-700';
                                        elseif ($inq['status'] === 'contacting') $color = 'bg-amber-100 text-amber-700';
                                        ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?php echo $color; ?>">
                                            <?php echo htmlspecialchars($inq['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- [DEMO HIDE] Quick CRM Tools - hidden for client demo
    <div class="col-lg-4">
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6 mb-6">
            ...Quick CRM Tools...
        </div>
    </div>
    -->
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
