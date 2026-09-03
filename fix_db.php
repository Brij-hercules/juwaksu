<?php
require_once __DIR__ . '/config/db.php';

echo "<pre>";

// 1. Fix inquiries status column (ENUM → VARCHAR)
try {
    echo "Altering inquiries.status column to VARCHAR(50)...\n";
    $pdo->exec("ALTER TABLE inquiries MODIFY COLUMN status VARCHAR(50) DEFAULT 'fresh_lead'");
    echo "✅ Done.\n\n";
} catch (\Exception $e) {
    echo "⚠ (maybe already done): " . $e->getMessage() . "\n\n";
}

// 2. Add scheduled_datetime and schedule_note to inquiries (if not exist)
try {
    echo "Adding scheduled_datetime + schedule_note columns to inquiries...\n";
    $pdo->exec("ALTER TABLE inquiries ADD COLUMN scheduled_datetime DATETIME NULL, ADD COLUMN schedule_note VARCHAR(255) NULL");
    echo "✅ Done.\n\n";
} catch (\Exception $e) {
    echo "⚠ (maybe already done): " . $e->getMessage() . "\n\n";
}

// 3. Create lead_status_log if not exists
try {
    echo "Creating lead_status_log table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lead_status_log (
          id INT AUTO_INCREMENT PRIMARY KEY,
          inquiry_id INT NOT NULL,
          changed_by INT NOT NULL,
          old_status VARCHAR(30),
          new_status VARCHAR(30) NOT NULL,
          comment TEXT NULL,
          scheduled_datetime DATETIME NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE,
          FOREIGN KEY (changed_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✅ Done.\n\n";
} catch (\Exception $e) {
    echo "⚠ (maybe already done): " . $e->getMessage() . "\n\n";
}

// 4. Add alert_datetime column to lead_status_log (NEW — for Calendar feature)
try {
    echo "Adding alert_datetime column to lead_status_log...\n";
    $pdo->exec("ALTER TABLE lead_status_log ADD COLUMN alert_datetime DATETIME NULL");
    echo "✅ Done.\n\n";
} catch (\Exception $e) {
    echo "⚠ (maybe already done): " . $e->getMessage() . "\n\n";
}

// 5. Migrate old status values → new workflow values
echo "Migrating old status values...\n";
$mapping = [
    'new'        => 'fresh_lead',
    'contacting' => 'connected',
    'qualified'  => 'interested',
    'lost'       => 'sale_lost',
    'closed'     => 'booking_done'
];

$total = 0;
foreach ($mapping as $old => $new) {
    $stmt = $pdo->prepare("UPDATE inquiries SET status = ? WHERE status = ?");
    $stmt->execute([$new, $old]);
    $cnt = $stmt->rowCount();
    $total += $cnt;
    echo "  Mapped '$old' → '$new' : $cnt rows\n";
}
echo "✅ Total migrated: $total rows\n\n";

echo "=== ALL DONE! You can now delete this file from the server. ===\n";
echo "</pre>";
echo "<br><a href='/crm/'>Go to CRM Dashboard</a>";
