<?php
// crm/migrate-status.php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/lead_status_helper.php';

if (!in_array($currentUser['role_name'], ['Admin', 'Super Admin'])) {
    die("Only admins can run this migration script.");
}

echo "<h2>Starting Status Flow Migration...</h2>";

try {
    // 1. Add new columns
    echo "<p>Adding new columns to inquiries table...</p>";
    $pdo->exec("
        ALTER TABLE inquiries
        ADD COLUMN scheduled_datetime DATETIME NULL,
        ADD COLUMN schedule_note VARCHAR(255) NULL
    ");
    echo "<p>Columns added (or already exist).</p>";
} catch (\Exception $e) {
    echo "<p style='color:red;'>Column add error (maybe they exist?): " . $e->getMessage() . "</p>";
}

try {
    // 2. Create lead_status_log table
    echo "<p>Creating lead_status_log table...</p>";
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
    echo "<p>Table created.</p>";
} catch (\Exception $e) {
    echo "<p style='color:red;'>Table creation error: " . $e->getMessage() . "</p>";
}

try {
    // 3. Migrate existing old statuses to new workflow statuses
    echo "<p>Migrating old statuses to new values...</p>";
    
    // mapping
    $mapping = [
        'new'        => 'fresh_lead',
        'contacting' => 'connected',
        'qualified'  => 'interested',
        'lost'       => 'sale_lost',
        'closed'     => 'booking_done'
    ];
    
    $updatedCount = 0;
    foreach ($mapping as $old => $new) {
        $stmt = $pdo->prepare("UPDATE inquiries SET status = ? WHERE status = ?");
        $stmt->execute([$new, $old]);
        $updatedCount += $stmt->rowCount();
        echo "<br>Mapped '$old' to '$new' : " . $stmt->rowCount() . " rows updated.";
    }
    
    echo "<p><strong>Migration complete! Total rows updated: $updatedCount</strong></p>";
    
} catch (\Exception $e) {
    echo "<p style='color:red;'>Migration error: " . $e->getMessage() . "</p>";
}

echo "<br><br><a href='index.php'>Return to Dashboard</a>";

require_once __DIR__ . '/includes/footer.php';
