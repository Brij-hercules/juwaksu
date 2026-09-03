<?php
require_once __DIR__ . '/config/db.php';

try {
    echo "Altering inquiries table status column...\n";
    $pdo->exec("ALTER TABLE inquiries MODIFY COLUMN status VARCHAR(50) DEFAULT 'fresh_lead'");
    echo "Column type changed to VARCHAR(50).\n";
    
    // Now run the mapping just in case it failed before
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
    echo "Error: " . $e->getMessage() . "\n";
}
