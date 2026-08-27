<?php
// crm/analytics.php
$pageTitle = "CRM Analytics";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/db.php';

// Permission check (optional)
if (function_exists('require_permission')) {
  require_permission('inquiries', 'view');
}

// Data queries with fallback
$leadSources = [];
$allotments = [];
$metaClicks = [];
$inquiriesPerDay = [];
$errorMsg = '';
try {
  // Leads per source
  $stmt = $pdo->query("SELECT source, COUNT(*) AS cnt FROM inquiries GROUP BY source");
  $leadSources = $stmt->fetchAll();
  // Allotment type distribution
  $stmt = $pdo->query("SELECT allotment_type, COUNT(*) AS cnt FROM properties GROUP BY allotment_type");
  $allotments = $stmt->fetchAll();
  // Meta Ads clicks over time (requires meta_ads_clicks table with click_time)
  $stmt = $pdo->query("SELECT DATE(click_time) AS d, COUNT(*) AS cnt FROM meta_ads_clicks GROUP BY d ORDER BY d");
  $metaClicks = $stmt->fetchAll();
  // Inquiries per day (requires created_at column)
  $stmt = $pdo->query("SELECT DATE(created_at) AS d, COUNT(*) AS cnt FROM inquiries GROUP BY d ORDER BY d");
  $inquiriesPerDay = $stmt->fetchAll();
} catch (\Exception $e) {
  $errorMsg = "Database error: " . $e->getMessage();
}
?>
<?php if (!empty($errorMsg)): ?>
  <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($errorMsg); ?></div>
<?php endif; ?>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.0/dist/tailwind.min.css" rel="stylesheet">
<style>
  .chart-card {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(200, 200, 200, 0.3);
    border-radius: 1rem;
    padding: 1.5rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transition: box-shadow .3s ease;
  }

  .chart-card:hover {
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
  }
</style>
<div class="max-w-7xl mx-auto my-8">
  <h1 class="text-3xl font-bold text-slate-800 mb-6">CRM Analytics Dashboard</h1>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="chart-card">
      <h2 class="text-xl font-semibold text-slate-700 mb-4">Leads by Source</h2><canvas id="leadSourceChart"></canvas>
    </div>
    <div class="chart-card">
      <h2 class="text-xl font-semibold text-slate-700 mb-4">Allotment Type Distribution</h2><canvas
        id="allotmentChart"></canvas>
    </div>
    <div class="chart-card">
      <h2 class="text-xl font-semibold text-slate-700 mb-4">Meta Ads Clicks Over Time</h2><canvas
        id="metaClicksChart"></canvas>
    </div>
    <div class="chart-card">
      <h2 class="text-xl font-semibold text-slate-700 mb-4">Inquiries per Day</h2><canvas
        id="inquiriesDayChart"></canvas>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
  const leadSources = <?php echo json_encode($leadSources); ?>;
  const allotments = <?php echo json_encode($allotments); ?>;
  const metaClicks = <?php echo json_encode($metaClicks); ?>;
  const inquiriesDay = <?php echo json_encode($inquiriesPerDay); ?>;

  new Chart(document.getElementById('leadSourceChart'), {
    type: 'pie',
    data: {
      labels: leadSources.map(r => r.source || 'Unknown'),
      datasets: [{ data: leadSources.map(r => r.cnt), backgroundColor: ['#2563eb', '#10b981', '#f43f5e', '#6b7280', '#ff9800'] }]
    },
    options: { responsive: true }
  });

  new Chart(document.getElementById('allotmentChart'), {
    type: 'doughnut',
    data: {
      labels: allotments.map(r => r.allotment_type || 'Unspecified'),
      datasets: [{ data: allotments.map(r => r.cnt), backgroundColor: ['#3b82f6', '#14b8a6', '#f59e0b', '#e11d48', '#a78bfa'] }]
    },
    options: { responsive: true }
  });

  new Chart(document.getElementById('metaClicksChart'), {
    type: 'line',
    data: {
      labels: metaClicks.map(r => r.d),
      datasets: [{ label: 'Clicks', data: metaClicks.map(r => r.cnt), borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,0.1)', tension: 0.3, fill: true }]
    },
    options: { responsive: true }
  });

  new Chart(document.getElementById('inquiriesDayChart'), {
    type: 'bar',
    data: {
      labels: inquiriesDay.map(r => r.d),
      datasets: [{ label: 'Inquiries', data: inquiriesDay.map(r => r.cnt), backgroundColor: '#10b981' }]
    },
    options: { responsive: true }
  });
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>