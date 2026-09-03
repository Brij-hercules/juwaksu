<?php
// migrate-cli.php
require_once __DIR__ . '/config/db.php';

echo "Starting Status Flow Migration...\n";

try {
    // 1. Add new columns
    echo "Adding new columns to inquiries table...\n";
    $pdo->exec("
        ALTER TABLE inquiries
        ADD COLUMN scheduled_datetime DATETIME NULL,
        ADD COLUMN schedule_note VARCHAR(255) NULL
    ");
    echo "Columns added (or already exist).\n";
} catch (\Exception $e) {
    echo "Column add error (maybe they exist?): " . $e->getMessage() . "\n";
}

try {
    // 2. Create lead_status_log table
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
    echo "Table created.\n";
} catch (\Exception $e) {
    echo "Table creation error: " . $e->getMessage() . "\n";
}

try {
    // 3. Migrate existing old statuses to new workflow statuses
    echo "Migrating old statuses to new values...\n";
    
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
        echo "Mapped '$old' to '$new' : " . $stmt->rowCount() . " rows updated.\n";
    }
    
    echo "Migration complete! Total rows updated: $updatedCount\n";
    
} catch (\Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}
