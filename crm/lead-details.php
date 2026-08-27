<?php
// crm/lead-details.php
$pageTitle = "Lead Details";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/db.php';

$leadId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$userId = $currentUser['id'];
$isAdmin = in_array($currentUser['role_name'], ['Admin', 'Meta Manager']);

$successMsg = '';
$errorMsg   = '';

// Handle POST: Update Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = trim($_POST['status']);
    try {
        $stmt = $pdo->prepare("UPDATE inquiries SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $leadId]);
        $successMsg = "Lead status updated to " . htmlspecialchars($newStatus) . ".";
    } catch (\PDOException $e) {
        $errorMsg = "Error updating status: " . $e->getMessage();
    }
}

// Handle POST: Add Note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $noteText = trim($_POST['note_text']);
    if (!empty($noteText)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO lead_notes (inquiry_id, user_id, note_text) VALUES (?, ?, ?)");
            $stmt->execute([$leadId, $userId, $noteText]);
            $successMsg = "Note added successfully.";
        } catch (\PDOException $e) {
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

    // Access control: Sales Employee can only view their own assigned leads
    if (!$isAdmin && $lead['assigned_to'] != $userId) {
        die("<div class='p-8 text-center text-rose-500 font-bold'>You do not have permission to view this lead.</div>");
    }

    // Fetch Notes
    $stmtNotes = $pdo->prepare("
        SELECT n.*, u.username, u.role_id, r.role_name
        FROM lead_notes n
        JOIN users u ON n.user_id = u.id
        JOIN roles r ON u.role_id = r.id
        WHERE n.inquiry_id = ?
        ORDER BY n.created_at DESC
    ");
    $stmtNotes->execute([$leadId]);
    $notes = $stmtNotes->fetchAll();

} catch (\Exception $e) {
    die("<div class='p-8 text-center text-rose-500 font-bold'>Database error: " . $e->getMessage() . "</div>");
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
            <form action="lead-details.php?id=<?= $leadId ?>" method="POST" class="flex gap-2">
                <input type="hidden" name="update_status" value="1">
                <select name="status" class="flex-1 px-3 py-2 bg-slate-50 border border-slate-200 text-sm font-bold rounded-lg focus:outline-none">
                    <?php foreach (['new'=>'New','contacting'=>'Contacting','qualified'=>'Qualified','lost'=>'Lost','closed'=>'Closed / Confirmed'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= $lead['status']===$v?'selected':'' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-bold transition">Update</button>
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
                    <a href="tel:<?php echo htmlspecialchars($lead['phone']); ?>" class="font-bold text-slate-800 hover:text-emerald-600 transition">
                        <?php echo htmlspecialchars($lead['phone']); ?>
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
            
            <!-- Add Note Form -->
            <form action="lead-details.php?id=<?= $leadId ?>" method="POST" class="mb-8">
                <input type="hidden" name="add_note" value="1">
                <textarea name="note_text" rows="3" required
                    placeholder="Type details about your call or conversation with the client..."
                    class="w-full p-4 bg-slate-50 border border-slate-200 text-sm rounded-xl focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition resize-none mb-3"></textarea>
                <div class="text-right">
                    <button type="submit" class="px-5 py-2.5 bg-slate-800 hover:bg-brand-600 text-white rounded-xl text-sm font-bold transition">
                        Save Note
                    </button>
                </div>
            </form>

            <!-- Timeline -->
            <div class="flex-1 overflow-y-auto pr-2 space-y-6">
                <?php if (empty($notes)): ?>
                    <div class="text-center py-12 text-slate-400 text-sm">
                        <div class="text-4xl mb-3">📝</div>
                        No notes added yet. Start typing above.
                    </div>
                <?php else: ?>
                    <div class="relative border-l-2 border-slate-100 ml-4 pl-6 space-y-8 pb-4">
                        <?php foreach ($notes as $note): ?>
                            <div class="relative">
                                <!-- Dot -->
                                <div class="absolute -left-[31px] top-1 w-4 h-4 rounded-full border-2 border-white <?php echo $note['role_name'] === 'Admin' ? 'bg-amber-500' : 'bg-blue-500'; ?>"></div>
                                
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
                                
                                <div class="p-4 bg-slate-50 border border-slate-100 rounded-xl text-sm text-slate-700 whitespace-pre-wrap leading-relaxed shadow-sm"><?php echo htmlspecialchars($note['note_text']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
