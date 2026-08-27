<?php
// config/migrate_google_sheet.php
require_once __DIR__ . '/db.php';

try {
    echo "Starting Google Sheet database migration...\n";

    // 1. Add new columns to inquiries table
    $columnsToAdd = [
        'meta_lead_id' => "VARCHAR(64) NULL UNIQUE AFTER property_id",
        'ad_id' => "VARCHAR(64) NULL AFTER campaign_name",
        'ad_name' => "VARCHAR(255) NULL AFTER ad_id",
        'adset_id' => "VARCHAR(64) NULL AFTER ad_name",
        'adset_name' => "VARCHAR(255) NULL AFTER adset_id",
        'campaign_id' => "VARCHAR(64) NULL AFTER campaign_name",
        'form_id' => "VARCHAR(64) NULL AFTER campaign_id",
        'form_name' => "VARCHAR(255) NULL AFTER form_id",
        'is_organic' => "TINYINT(1) DEFAULT 0 AFTER form_name",
        'platform' => "VARCHAR(50) NULL AFTER is_organic",
        'are_you_looking_for' => "VARCHAR(255) NULL AFTER platform",
        'budget' => "VARCHAR(100) NULL AFTER are_you_looking_for",
        'purchase_time' => "VARCHAR(100) NULL AFTER budget",
        'have_you_invested_in_property_before' => "VARCHAR(10) NULL AFTER purchase_time",
        'lead_status' => "VARCHAR(50) NULL AFTER have_you_invested_in_property_before"
    ];

    // Get existing columns
    $existingColumns = $pdo->query("DESCRIBE inquiries")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columnsToAdd as $colName => $colDef) {
        if (!in_array($colName, $existingColumns)) {
            $pdo->exec("ALTER TABLE inquiries ADD COLUMN $colName $colDef");
            echo "Added column '$colName'.\n";
        } else {
            echo "Column '$colName' already exists. Skipping.\n";
        }
    }

    // 2. Add API key to settings table if not exists
    $stmtKey = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'google_sheet_api_key'");
    $stmtKey->execute();
    $keyExists = $stmtKey->fetch();

    if (!$keyExists) {
        // Generate secure 32 character API key
        $apiKey = bin2hex(random_bytes(16)); // 32 characters
        $stmtInsert = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('google_sheet_api_key', ?)");
        $stmtInsert->execute([$apiKey]);
        echo "Generated new API key: $apiKey\n";
        echo "Please save this key for setting up the Google Apps Script!\n";
    } else {
        echo "Google Sheet API key already exists in settings.\n";
    }

    echo "Migration completed successfully!\n";

} catch (\PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
?>
