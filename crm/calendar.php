<?php
// crm/calendar.php
$pageTitle = "Lead Alerts Calendar";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/lead_status_helper.php';

$userId          = $currentUser['id'];
$isSalesEmployee = ($currentUser['role_name'] === 'Sales Employee');

// Date filter — default to today
$today      = date('Y-m-d');
$filterDate = !empty($_GET['date']) ? $_GET['date'] : $today;
$isToday    = ($filterDate === $today);

// Build query — only show alerts for the logged-in employee's leads (or all for admin)
$where  = "WHERE DATE(l.alert_datetime) = ? AND l.alert_datetime IS NOT NULL";
$params = [$filterDate];

if ($isSalesEmployee) {
    $where .= " AND l.changed_by = ?";
    $params[] = $userId;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            l.id as log_id,
            l.alert_datetime,
            l.comment,
            l.new_status,
            l.old_status,
            l.created_at as logged_at,
            i.id as lead_id,
            i.name as lead_name,
            i.phone as lead_phone,
            i.status as current_status,
            i.campaign_name,
            u.username as set_by
        FROM lead_status_log l
        JOIN inquiries i ON l.inquiry_id = i.id
        JOIN users u ON l.changed_by = u.id
        $where
        ORDER BY l.alert_datetime ASC
    ");
    $stmt->execute($params);
    $alerts = $stmt->fetchAll();

    // Count today's alerts
    $todayParams = [$today];
    if ($isSalesEmployee) $todayParams[] = $userId;
    $todayWhere = "WHERE DATE(l.alert_datetime) = ? AND l.alert_datetime IS NOT NULL" . ($isSalesEmployee ? " AND l.changed_by = ?" : "");
    $todayCount = $pdo->prepare("SELECT COUNT(*) FROM lead_status_log l JOIN inquiries i ON l.inquiry_id = i.id $todayWhere");
    $todayCount->execute($todayParams);
    $todayTotal = (int)$todayCount->fetchColumn();

} catch (\Exception $e) {
    $alerts = [];
    $todayTotal = 0;
}
?>

<!-- Header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-black text-slate-800 flex items-center gap-2">
            <svg class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            Lead Alerts Calendar
        </h1>
        <p class="text-slate-400 text-xs font-light mt-0.5">Reminder alerts set during lead status updates and notes.</p>
    </div>

    <div class="flex items-center gap-2 flex-wrap">
        <!-- Today Button -->
        <a href="calendar.php" 
           class="flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-bold transition shadow-sm
                  <?= $isToday ? 'bg-amber-500 text-white shadow-amber-200' : 'bg-white border border-slate-200 text-slate-600 hover:bg-amber-50 hover:border-amber-300 hover:text-amber-700' ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
            Today's Notifications
            <?php if ($todayTotal > 0): ?>
                <span class="ml-1 bg-white text-amber-600 rounded-full px-1.5 py-0.5 text-[10px] font-black"><?= $todayTotal ?></span>
            <?php endif; ?>
        </a>

        <!-- Date Picker -->
        <form action="calendar.php" method="GET" class="flex items-center gap-2">
            <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>"
                   class="px-3 py-2 bg-white border border-slate-200 text-xs rounded-xl focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400">
            <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold transition">
                Go
            </button>
        </form>
    </div>
</div>

<!-- Date display banner -->
<div class="bg-gradient-to-r <?= $isToday ? 'from-amber-500 to-amber-600' : 'from-indigo-600 to-indigo-800' ?> text-white rounded-2xl px-6 py-4 mb-6 flex items-center justify-between shadow-lg">
    <div>
        <p class="text-xs font-bold uppercase tracking-widest opacity-80 mb-0.5"><?= $isToday ? "Today's Alerts" : "Alerts for" ?></p>
        <p class="text-xl font-black"><?= date('l, d F Y', strtotime($filterDate)) ?></p>
    </div>
    <div class="text-right">
        <p class="text-3xl font-black"><?= count($alerts) ?></p>
        <p class="text-xs font-bold opacity-80">alert<?= count($alerts) !== 1 ? 's' : '' ?></p>
    </div>
</div>

<!-- Navigation arrows -->
<div class="flex gap-2 mb-6">
    <a href="calendar.php?date=<?= date('Y-m-d', strtotime($filterDate . ' -1 day')) ?>"
       class="px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-xl text-xs font-bold hover:bg-slate-50 transition">
       ← Previous Day
    </a>
    <a href="calendar.php?date=<?= date('Y-m-d', strtotime($filterDate . ' +1 day')) ?>"
       class="px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-xl text-xs font-bold hover:bg-slate-50 transition">
       Next Day →
    </a>
</div>

<!-- Alerts List -->
<?php if (empty($alerts)): ?>
    <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-12 text-center">
        <div class="text-5xl mb-4">🔕</div>
        <h3 class="font-bold text-slate-700 text-base mb-1">No alerts for this date</h3>
        <p class="text-slate-400 text-xs">When a Lead Alert is set during a note or status update, it will appear here on the scheduled date.</p>
        <?php if (!$isToday): ?>
            <a href="calendar.php" class="mt-4 inline-block px-4 py-2 bg-amber-500 text-white rounded-xl text-xs font-bold hover:bg-amber-600 transition">View Today</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="space-y-4">
        <?php foreach ($alerts as $alert): 
            $alertTime   = strtotime($alert['alert_datetime']);
            $isPast      = $alertTime <= time();
            $isUpcoming  = $alertTime > time() && $alertTime <= (time() + 3600); // next 1 hour
            $cardBg      = $isPast ? 'border-rose-200 bg-rose-50' : ($isUpcoming ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-white');
            $timeBg      = $isPast ? 'bg-rose-100 text-rose-700' : ($isUpcoming ? 'bg-amber-100 text-amber-700' : 'bg-indigo-50 text-indigo-700');
        ?>
            <div class="rounded-2xl border <?= $cardBg ?> p-5 shadow-sm hover:shadow-md transition">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <!-- Left: Lead Info -->
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2 flex-wrap">
                            <?php if ($isPast): ?>
                                <span class="px-2 py-0.5 bg-rose-200 text-rose-800 text-[10px] font-black uppercase rounded-full">⚠ Overdue</span>
                            <?php elseif ($isUpcoming): ?>
                                <span class="px-2 py-0.5 bg-amber-200 text-amber-800 text-[10px] font-black uppercase rounded-full">⏰ Upcoming</span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 bg-indigo-100 text-indigo-700 text-[10px] font-black uppercase rounded-full">📅 Scheduled</span>
                            <?php endif; ?>
                            <?= get_status_badge($alert['current_status']) ?>
                        </div>

                        <h3 class="font-black text-slate-800 text-base"><?= htmlspecialchars($alert['lead_name']) ?></h3>
                        <p class="text-slate-500 text-xs font-medium"><?= htmlspecialchars($alert['lead_phone']) ?></p>
                        <?php if ($alert['campaign_name']): ?>
                            <p class="text-slate-400 text-[10px] mt-0.5"><?= htmlspecialchars($alert['campaign_name']) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($alert['comment'])): ?>
                            <div class="mt-3 p-3 bg-white/70 border border-slate-100 rounded-xl text-xs text-slate-700 leading-relaxed">
                                <span class="font-bold text-slate-400 block text-[10px] uppercase tracking-wider mb-1">Note when alert was set:</span>
                                <?= htmlspecialchars($alert['comment']) ?>
                            </div>
                        <?php endif; ?>

                        <p class="text-[10px] text-slate-400 mt-2">Set by <strong><?= htmlspecialchars($alert['set_by']) ?></strong> on <?= date('d M Y, h:i A', strtotime($alert['logged_at'])) ?></p>
                    </div>

                    <!-- Right: Time + Action -->
                    <div class="flex flex-col items-end gap-3 flex-shrink-0">
                        <div class="<?= $timeBg ?> px-4 py-2 rounded-xl text-center">
                            <p class="text-lg font-black leading-none"><?= date('h:i A', $alertTime) ?></p>
                            <p class="text-[10px] font-bold uppercase tracking-wider mt-0.5 opacity-70"><?= date('d M', $alertTime) ?></p>
                        </div>
                        <a href="lead-details.php?id=<?= $alert['lead_id'] ?>"
                           class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold transition whitespace-nowrap">
                            Open Lead →
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
