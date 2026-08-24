<?php
// crm/allotment-types.php
$pageTitle = "Allotment Types Overview";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/db.php';

// Permission check (optional)
require_permission('properties', 'view');

// Fetch allotment type counts
try {
    $stmt = $pdo->query("SELECT allotment_type, COUNT(*) AS cnt FROM properties GROUP BY allotment_type");
    $allotments = $stmt->fetchAll();
} catch (\Exception $e) {
    $allotments = [];
    $errorMsg = "Database error: " . $e->getMessage();
}
?>
<?php if (!empty($errorMsg)): ?>
    <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($errorMsg); ?></div>
<?php endif; ?>
<div class="bg-white rounded-3xl border border-slate-200/60 shadow-sm p-8 max-w-5xl mx-auto my-8">
    <h1 class="text-2xl font-black text-slate-800 mb-6">Allotment Types</h1>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <?php foreach ($allotments as $row): ?>
            <div class="flex items-center gap-4 p-4 bg-white/70 backdrop-filter backdrop-blur-md rounded-xl border border-slate-200/30 shadow-sm hover:shadow-md transition">
                <div class="w-12 h-12 flex items-center justify-center bg-indigo-50 text-indigo-600 rounded-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </div>
                <div>
                    <div class="text-sm font-medium text-slate-600 uppercase tracking-wider">
                        <?php echo htmlspecialchars($row['allotment_type'] ?: 'Unspecified'); ?>
                    </div>
                    <div class="text-2xl font-black text-slate-800">
                        <?php echo number_format($row['cnt']); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
