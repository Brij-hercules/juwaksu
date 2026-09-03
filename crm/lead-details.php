<?php
// crm/lead-details.php
$pageTitle = "Lead Details";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/lead_status_helper.php';

$leadId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$userId = $currentUser['id'];
$isAdmin = in_array($currentUser['role_name'], ['Admin', 'Meta Manager']);

$successMsg = '';
$errorMsg   = '';

// Handle POST: Update Status (and Add Note)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus  = trim($_POST['new_status']);
    $oldStatus  = normalize_status(trim($_POST['old_status']));
    $noteText   = trim($_POST['note_text']);
    $schedDate  = !empty($_POST['scheduled_datetime']) ? trim($_POST['scheduled_datetime']) : null;
    $hasAlert   = isset($_POST['lead_alert']) && $_POST['lead_alert'] === '1';
    $alertDate  = ($hasAlert && !empty($_POST['alert_datetime'])) ? trim($_POST['alert_datetime']) : null;
    
    // Only update if valid status
    if (isset(LEAD_STATUSES[$newStatus])) {
        try {
            $pdo->beginTransaction();
            
            // 1. Update Lead status (and scheduled_datetime only if a schedule was provided)
            $stmt = $pdo->prepare("UPDATE inquiries SET status = ?, scheduled_datetime = ? WHERE id = ?");
            $stmt->execute([$newStatus, $schedDate ?: $alertDate, $leadId]);
            
            // 2. Add to unified log (with alert_datetime column)
            $stmtLog = $pdo->prepare("INSERT INTO lead_status_log (inquiry_id, changed_by, old_status, new_status, comment, scheduled_datetime, alert_datetime) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtLog->execute([$leadId, $userId, $oldStatus, $newStatus, $noteText, $schedDate, $alertDate]);
            
            $pdo->commit();
            $successMsg = "Lead updated successfully.";
        } catch (\PDOException $e) {
            $pdo->rollBack();
            $errorMsg = "Error updating lead: " . $e->getMessage();
        }
    } else {
        $errorMsg = "Invalid status selected.";
    }
}

// Handle POST: Add Note only (no status change)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $noteText      = trim($_POST['note_text']);
    $currentStatus = trim($_POST['current_status']);
    $hasAlert      = isset($_POST['lead_alert']) && $_POST['lead_alert'] === '1';
    $alertDate     = ($hasAlert && !empty($_POST['alert_datetime'])) ? trim($_POST['alert_datetime']) : null;
    if (!empty($noteText)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO lead_status_log (inquiry_id, changed_by, old_status, new_status, comment, alert_datetime) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$leadId, $userId, $currentStatus, $currentStatus, $noteText, $alertDate]);
            // Update lead's scheduled_datetime if alert is set
            if ($alertDate) {
                $pdo->prepare("UPDATE inquiries SET scheduled_datetime = ? WHERE id = ?")->execute([$alertDate, $leadId]);
            }
            $pdo->commit();
            $successMsg = "Note added successfully.";
        } catch (\PDOException $e) {
            $pdo->rollBack();
            $errorMsg = "Error adding note: " . $e->getMessage();
        }
    }
}

// Fetch Lead Details
try {
    $stmt = $pdo->prepare("
        SELECT i.*, u.username as agent_name 
        FROM inquiries i 
        LEFT JOIN users u ON i.assigned_to = u.id 
        WHERE i.id = ?
    ");
    $stmt->execute([$leadId]);
    $lead = $stmt->fetch();

    if (!$lead) {
        die("<div class='p-8 text-center text-rose-500 font-bold'>Lead not found.</div>");
    }

    // Access control
    if (!$isAdmin && $lead['assigned_to'] != $userId) {
        die("<div class='p-8 text-center text-rose-500 font-bold'>You do not have permission to view this lead.</div>");
    }

    // Fetch Unified Timeline (lead_status_log + legacy lead_notes)
    // We UNION them to keep legacy notes visible in the timeline
    $stmtNotes = $pdo->prepare("
        SELECT l.id, l.old_status, l.new_status, l.comment, l.scheduled_datetime, l.created_at, 
               u.username, u.role_id, r.role_name
        FROM lead_status_log l
        JOIN users u ON l.changed_by = u.id
        JOIN roles r ON u.role_id = r.id
        WHERE l.inquiry_id = ?
        
        UNION ALL
        
        SELECT n.id, NULL as old_status, NULL as new_status, n.note_text as comment, NULL as scheduled_datetime, n.created_at,
               u.username, u.role_id, r.role_name
        FROM lead_notes n
        JOIN users u ON n.user_id = u.id
        JOIN roles r ON u.role_id = r.id
        WHERE n.inquiry_id = ?
        
        ORDER BY created_at DESC
    ");
    $stmtNotes->execute([$leadId, $leadId]);
    $notes = $stmtNotes->fetchAll();

} catch (\Exception $e) {
    die("<div class='p-8 text-center text-rose-500 font-bold'>Database error: " . $e->getMessage() . "</div>");
}
<?php
// Helper: Format phone to Indian format (+91 XXXXX XXXXX)
function formatIndianPhone($phone) {
    // Remove all non-digit characters except leading +
    $clean = preg_replace('/[^\d]/', '', $phone);
    // Remove country code 91 if present (10-digit numbers)
    if (strlen($clean) === 12 && substr($clean, 0, 2) === '91') {
        $clean = substr($clean, 2);
    } elseif (strlen($clean) === 13 && substr($clean, 0, 3) === '091') {
        $clean = substr($clean, 3);
    }
    // Format as +91 XXXXX XXXXX if 10 digits
    if (strlen($clean) === 10) {
        return '+91 ' . substr($clean, 0, 5) . ' ' . substr($clean, 5);
    }
    // Fallback: return as-is
    return $phone;
}
?>

<div class="flex items-center gap-4 mb-6">
    <a href="javascript:history.back()" class="p-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
    </a>
    <div>
        <h2 class="text-xl font-black text-slate-800">Lead: <?php echo htmlspecialchars($lead['name']); ?></h2>
        <div class="text-xs text-slate-400 mt-1 flex gap-3">
            <span>ID: #<?php echo $lead['id']; ?></span>
            <span>Created: <?php echo date('d M Y, h:i A', strtotime($lead['created_at'])); ?></span>
        </div>
    </div>
</div>

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

<div class="row g-5">
    <!-- LEFT: Lead Details -->
    <div class="col-lg-4">
        <!-- Status Box -->
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6 mb-5">
            <h3 class="text-xs font-extrabold text-slate-400 uppercase tracking-wider mb-4">Pipeline Status</h3>
            
            <div class="mb-5">
                <span class="text-xs text-slate-500 font-medium mr-2">Current:</span>
                <?= get_status_badge($lead['status']) ?>
            </div>

            <?php 
            // Normalize status in case the lead still has an old/legacy status (e.g. 'new', 'contacting', 'lost')
            $currKey    = normalize_status($lead['status']);
            $currConfig = LEAD_STATUSES[$currKey] ?? null;
            $nextOptions = $currConfig ? $currConfig['next'] : [];
            ?>

            <?php if (!empty($nextOptions)): ?>
                <div class="space-y-3 border-t border-slate-100 pt-4">
                    <p class="text-xs font-bold text-slate-600 mb-2">Update Stage To:</p>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($nextOptions as $nextKey): 
                            $nConf = LEAD_STATUSES[$nextKey];
                        ?>
                            <button type="button" onclick="openStatusForm('<?= $nextKey ?>', '<?= addslashes($nConf['label']) ?>', <?= $nConf['schedule'] ? 'true' : 'false' ?>)"
                                class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-slate-700 text-xs font-bold hover:bg-slate-50 hover:border-slate-300 transition shadow-sm">
                                <?= htmlspecialchars($nConf['label']) ?> →
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="mt-4 pt-4 border-t border-slate-100 text-center">
                    <span class="inline-block px-3 py-1 bg-slate-50 text-slate-400 text-xs font-bold rounded-lg border border-slate-100">🏁 Final Stage Reached</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Hidden Form for Status Update -->
        <div id="statusFormContainer" class="hidden bg-white rounded-2xl border border-blue-200 shadow-sm p-6 mb-5 relative overflow-hidden">
            <div class="absolute top-0 left-0 w-1 h-full bg-blue-500"></div>
            <h3 class="text-sm font-extrabold text-slate-800 mb-4" id="statusFormTitle">Update Status</h3>
            <form action="lead-details.php?id=<?= $leadId ?>" method="POST" class="space-y-4">
                <input type="hidden" name="update_status" value="1">
                <input type="hidden" name="old_status" value="<?= htmlspecialchars($currKey) ?>">
                <input type="hidden" name="new_status" id="new_status_input" value="">

                <div id="scheduleContainer" class="hidden">
                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Schedule Date & Time *</label>
                    <input type="datetime-local" name="scheduled_datetime" id="scheduled_datetime" 
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs rounded-lg focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Update Comment *</label>
                    <textarea name="note_text" rows="2" required placeholder="Add notes for this status change..."
                        class="w-full p-3 bg-slate-50 border border-slate-200 text-sm rounded-xl focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 resize-none"></textarea>
                </div>

                <!-- Lead Alert Checkbox -->
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-3">
                    <label class="flex items-center gap-2.5 cursor-pointer select-none">
                        <input type="checkbox" name="lead_alert" id="statusAlertCheckbox" value="1"
                            onchange="toggleAlertBox('statusAlertDateBox', this)"
                            class="w-4 h-4 rounded border-amber-400 text-amber-500 accent-amber-500 cursor-pointer">
                        <span class="text-xs font-bold text-amber-700 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                            Lead Alert — Set Reminder
                        </span>
                    </label>
                    <div id="statusAlertDateBox" class="hidden mt-2.5">
                        <label class="block text-[10px] font-bold text-amber-600 uppercase tracking-widest mb-1">Alert Date & Time</label>
                        <input type="datetime-local" name="alert_datetime" id="statusAlertDatetime"
                            class="w-full px-3 py-2 bg-white border border-amber-300 text-xs rounded-lg focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                        <p class="text-[10px] text-amber-600 mt-1">You will see this lead in Calendar on the selected date.</p>
                    </div>
                </div>
                
                <div class="flex gap-2 pt-2">
                    <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-bold transition shadow-sm">Save Update</button>
                    <button type="button" onclick="closeStatusForm()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-sm font-bold transition">Cancel</button>
                </div>
            </form>
        </div>

        <!-- Contact Info -->
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6 mb-5">
            <h3 class="text-xs font-extrabold text-slate-400 uppercase tracking-wider mb-4">Contact Info</h3>
            <div class="space-y-4">
                <div class="flex items-center gap-3 text-sm">
                    <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </div>
                    <div class="font-bold text-slate-800 break-all"><?php echo htmlspecialchars($lead['name']); ?></div>
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <div class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                    </div>
                    <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^\d+]/', '', $lead['phone'])); ?>" class="font-bold text-slate-800 hover:text-emerald-600 transition">
                        <?php echo htmlspecialchars(formatIndianPhone($lead['phone'])); ?>
                    </a>
                </div>
                <?php if ($lead['email']): ?>
                <div class="flex items-center gap-3 text-sm">
                    <div class="w-8 h-8 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    </div>
                    <a href="mailto:<?php echo htmlspecialchars($lead['email']); ?>" class="font-bold text-slate-800 hover:text-amber-600 transition break-all">
                        <?php echo htmlspecialchars($lead['email']); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Survey / Requirement Info -->
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6">
            <h3 class="text-xs font-extrabold text-slate-400 uppercase tracking-wider mb-4">Requirements & Source</h3>
            
            <div class="text-xs space-y-3">
                <div class="grid grid-cols-2 gap-2 border-b border-slate-50 pb-2">
                    <span class="text-slate-400">Source</span>
                    <span class="font-bold text-slate-800"><?php echo $lead['source'] === 'meta_ads' ? 'Meta Ads' : 'Website'; ?></span>
                </div>
                <?php if ($lead['campaign_name']): ?>
                <div class="grid grid-cols-2 gap-2 border-b border-slate-50 pb-2">
                    <span class="text-slate-400">Campaign</span>
                    <span class="font-bold text-slate-800"><?php echo htmlspecialchars($lead['campaign_name']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($lead['are_you_looking_for']): ?>
                <div class="grid grid-cols-2 gap-2 border-b border-slate-50 pb-2">
                    <span class="text-slate-400">Looking For</span>
                    <span class="font-bold text-slate-800"><?php echo htmlspecialchars($lead['are_you_looking_for']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($lead['budget']): ?>
                <div class="grid grid-cols-2 gap-2 border-b border-slate-50 pb-2">
                    <span class="text-slate-400">Budget</span>
                    <span class="font-bold text-slate-800"><?php echo htmlspecialchars($lead['budget']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($lead['purchase_time']): ?>
                <div class="grid grid-cols-2 gap-2 border-b border-slate-50 pb-2">
                    <span class="text-slate-400">Timeline</span>
                    <span class="font-bold text-slate-800"><?php echo htmlspecialchars($lead['purchase_time']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($lead['have_you_invested_in_property_before']): ?>
                <div class="grid grid-cols-2 gap-2 border-b border-slate-50 pb-2">
                    <span class="text-slate-400">Invested Before</span>
                    <span class="font-bold text-slate-800"><?php echo htmlspecialchars($lead['have_you_invested_in_property_before']); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="pt-2">
                    <span class="text-slate-400 block mb-1">Message / Notes:</span>
                    <div class="p-3 bg-slate-50 rounded-xl text-slate-700 whitespace-pre-wrap">
                        <?php echo htmlspecialchars($lead['message'] ?: 'No initial message provided.'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT: Notes & Timeline -->
    <div class="col-lg-8">
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6 flex flex-col h-full">
            <h3 class="text-base font-extrabold text-slate-800 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-brand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
                Conversation Notes
            </h3>
            
            <!-- Add Note Form (Generic Note without status change) -->
            <form action="lead-details.php?id=<?= $leadId ?>" method="POST" class="mb-8">
                <input type="hidden" name="add_note" value="1">
                <input type="hidden" name="current_status" value="<?= htmlspecialchars($lead['status']) ?>">
                <textarea name="note_text" rows="3" required
                    placeholder="Type a general note or conversation detail (does not change status)..."
                    class="w-full p-4 bg-slate-50 border border-slate-200 text-sm rounded-xl focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition resize-none mb-3"></textarea>

                <!-- Lead Alert Checkbox -->
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-3">
                    <label class="flex items-center gap-2.5 cursor-pointer select-none">
                        <input type="checkbox" name="lead_alert" id="noteAlertCheckbox" value="1"
                            onchange="toggleAlertBox('noteAlertDateBox', this)"
                            class="w-4 h-4 rounded border-amber-400 text-amber-500 accent-amber-500 cursor-pointer">
                        <span class="text-xs font-bold text-amber-700 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                            Lead Alert — Set Reminder
                        </span>
                    </label>
                    <div id="noteAlertDateBox" class="hidden mt-2.5">
                        <label class="block text-[10px] font-bold text-amber-600 uppercase tracking-widest mb-1">Alert Date & Time</label>
                        <input type="datetime-local" name="alert_datetime" id="noteAlertDatetime"
                            class="w-full px-3 py-2 bg-white border border-amber-300 text-xs rounded-lg focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                        <p class="text-[10px] text-amber-600 mt-1">You will see this lead in Calendar on the selected date.</p>
                    </div>
                </div>

                <div class="text-right">
                    <button type="submit" class="px-5 py-2.5 bg-slate-800 hover:bg-brand-600 text-white rounded-xl text-sm font-bold transition shadow-sm">
                        Add Note
                    </button>
                </div>
            </form>

            <!-- Timeline -->
            <div class="flex-1 overflow-y-auto pr-2 space-y-6">
                <?php if (empty($notes)): ?>
                    <div class="text-center py-12 text-slate-400 text-sm">
                        <div class="text-4xl mb-3">📝</div>
                        No activity recorded yet.
                    </div>
                <?php else: ?>
                    <div class="relative border-l-2 border-slate-100 ml-4 pl-6 space-y-8 pb-4">
                        <?php foreach ($notes as $note): 
                            $isStatusChange = ($note['old_status'] !== $note['new_status'] && !empty($note['new_status']));
                        ?>
                            <div class="relative">
                                <!-- Dot -->
                                <div class="absolute -left-[31px] top-1 w-4 h-4 rounded-full border-2 border-white <?php echo $isStatusChange ? 'bg-blue-500' : 'bg-slate-300'; ?>"></div>
                                
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="font-extrabold text-sm text-slate-800"><?php echo htmlspecialchars($note['username']); ?></span>
                                        <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-widest <?php echo $note['role_name'] === 'Admin' ? 'bg-amber-50 text-amber-600' : 'bg-blue-50 text-blue-600'; ?>">
                                            <?php echo htmlspecialchars($note['role_name']); ?>
                                        </span>
                                    </div>
                                    <span class="text-xs text-slate-400 font-medium">
                                        <?php echo date('d M Y, h:i A', strtotime($note['created_at'])); ?>
                                    </span>
                                </div>
                                
                                <?php if ($isStatusChange): ?>
                                    <div class="flex items-center gap-2 mb-2 text-xs font-bold text-slate-500">
                                        <span><?= $note['old_status'] ? get_status_label($note['old_status']) : 'Created' ?></span>
                                        <svg class="w-3 h-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                        <?= get_status_badge($note['new_status']) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($note['comment'])): ?>
                                <div class="p-4 bg-slate-50 border border-slate-100 rounded-xl text-sm text-slate-700 whitespace-pre-wrap leading-relaxed shadow-sm"><?php echo htmlspecialchars($note['comment']); ?></div>
                                <?php endif; ?>

                                <?php if (!empty($note['scheduled_datetime'])): ?>
                                    <div class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 bg-indigo-50 text-indigo-700 rounded-lg border border-indigo-100 text-xs font-bold">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        Scheduled: <?= date('d M Y, h:i A', strtotime($note['scheduled_datetime'])) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($note['alert_datetime'])): ?>
                                    <div class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 bg-amber-50 text-amber-700 rounded-lg border border-amber-200 text-xs font-bold">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                                        🔔 Alert Set: <?= date('d M Y, h:i A', strtotime($note['alert_datetime'])) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function openStatusForm(nextKey, label, requiresSchedule) {
    document.getElementById('statusFormContainer').classList.remove('hidden');
    document.getElementById('statusFormTitle').innerText = 'Update Status to: ' + label;
    document.getElementById('new_status_input').value = nextKey;
    
    const sched = document.getElementById('scheduleContainer');
    const schedInput = document.getElementById('scheduled_datetime');
    if (requiresSchedule) {
        sched.classList.remove('hidden');
        schedInput.required = true;
    } else {
        sched.classList.add('hidden');
        schedInput.required = false;
        schedInput.value = '';
    }
}
function closeStatusForm() {
    document.getElementById('statusFormContainer').classList.add('hidden');
}
function toggleAlertBox(boxId, checkbox) {
    var box = document.getElementById(boxId);
    if (checkbox.checked) {
        box.classList.remove('hidden');
    } else {
        box.classList.add('hidden');
        // Clear the datetime input inside
        var dtInput = box.querySelector('input[type="datetime-local"]');
        if (dtInput) dtInput.value = '';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
