<?php
// api/google-sheet-lead.php

header("Content-Type: application/json; charset=UTF-8");

// Set up log file path
$logDir = __DIR__ . '/logs';
$logFile = $logDir . '/google_sheet_sync.log';

/**
 * Log message helper
 */
function writeLog($level, $message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMsg = "[$timestamp] [$level] $message\n";
    // Ensure log directory exists
    if (!is_dir(dirname($logFile))) {
        @mkdir(dirname($logFile), 0755, true);
    }
    @file_put_contents($logFile, $logMsg, FILE_APPEND);
}

/**
 * Mask sensitive data for safe logging
 */
function maskPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) <= 4) {
        return str_repeat('*', strlen($phone));
    }
    return str_repeat('*', strlen($phone) - 4) . substr($phone, -4);
}

function maskEmail($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return '***';
    }
    $parts = explode('@', $email);
    $name = $parts[0];
    $domain = $parts[1];
    if (strlen($name) <= 2) {
        return str_repeat('*', strlen($name)) . '@' . $domain;
    }
    return substr($name, 0, 1) . str_repeat('*', strlen($name) - 2) . substr($name, -1) . '@' . $domain;
}

try {
    // 1. Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            "success" => false,
            "message" => "Method Not Allowed. Only POST is supported."
        ]);
        exit;
    }

    // 2. Fetch connection and settings
    require_once __DIR__ . '/../config/db.php';

    // 3. API Key Authorization
    $apiKeyHeader = null;
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    if (isset($headers['x-api-key'])) {
        $apiKeyHeader = $headers['x-api-key'];
    } elseif (isset($_SERVER['HTTP_X_API_KEY'])) {
        $apiKeyHeader = $_SERVER['HTTP_X_API_KEY'];
    }

    if (empty($apiKeyHeader)) {
        writeLog('UNAUTHORIZED', 'Missing X-API-Key header.');
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Unauthorized: Missing API Key"
        ]);
        exit;
    }

    // Retrieve stored key
    $stmtKey = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'google_sheet_api_key'");
    $stmtKey->execute();
    $dbKey = $stmtKey->fetchColumn();

    if (!$dbKey || !hash_equals($dbKey, $apiKeyHeader)) {
        writeLog('UNAUTHORIZED', 'Invalid X-API-Key provided.');
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Unauthorized: Invalid API Key"
        ]);
        exit;
    }

    // 4. Retrieve and decode JSON input
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if ($data === null) {
        writeLog('VALIDATION_ERROR', 'Malformed JSON payload received.');
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid JSON payload"
        ]);
        exit;
    }

    // 5. Input Validation
    $requiredFields = ['id', 'full_name', 'phone_number'];
    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            $missingFields[] = $field;
        }
    }

    if (!empty($missingFields)) {
        $msg = "Missing required fields: " . implode(', ', $missingFields);
        writeLog('VALIDATION_ERROR', $msg);
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => $msg
        ]);
        exit;
    }

    $externalId = trim((string)$data['id']);
    $fullName = trim((string)$data['full_name']);
    $phoneNumber = trim((string)$data['phone_number']);
    $email = isset($data['email']) ? trim((string)$data['email']) : '';

    // Validate email format if provided
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "Invalid email format: " . maskEmail($email);
        writeLog('VALIDATION_ERROR', "Lead ID $externalId | $msg");
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid email format"
        ]);
        exit;
    }

    // 6. Duplicate Protection Check
    
    // a. Check by external ID (meta_lead_id)
    $stmtDupId = $pdo->prepare("SELECT id FROM inquiries WHERE meta_lead_id = ?");
    $stmtDupId->execute([$externalId]);
    if ($stmtDupId->fetch()) {
        writeLog('DUPLICATE', "Lead ID $externalId | Lead already exists by external ID");
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "duplicate" => true,
            "message" => "Lead already exists"
        ]);
        exit;
    }

    // b. Check by normalized phone number
    $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
    // Standardize to matching last 10 digits for Indian numbers, or exact match if short
    $phoneMatchPattern = $cleanPhone;
    if (strlen($cleanPhone) >= 10) {
        $phoneMatchPattern = '%' . substr($cleanPhone, -10);
    }
    
    $stmtDupPhone = $pdo->prepare("
        SELECT id, meta_lead_id 
        FROM inquiries 
        WHERE REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', '') LIKE ?
    ");
    $stmtDupPhone->execute([$phoneMatchPattern]);
    $dupLead = $stmtDupPhone->fetch();

    if ($dupLead) {
        $dupExtId = $dupLead['meta_lead_id'] ?? 'N/A';
        writeLog('DUPLICATE', "Lead ID $externalId | Duplicate phone matches existing lead ID {$dupLead['id']} (External: $dupExtId)");
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "duplicate" => true,
            "message" => "Lead already exists"
        ]);
        exit;
    }

    // 7. Insert the new lead
    // Map incoming variables
    $createdTime = isset($data['created_time']) ? trim((string)$data['created_time']) : null;
    
    // Convert created_time to standard SQL format (Y-m-d H:i:s)
    $createdAt = date('Y-m-d H:i:s');
    if ($createdTime) {
        $parsedTime = strtotime($createdTime);
        if ($parsedTime !== false) {
            $createdAt = date('Y-m-d H:i:s', $parsedTime);
        }
    }

    $adId = isset($data['ad_id']) ? trim((string)$data['ad_id']) : null;
    $adName = isset($data['ad_name']) ? trim((string)$data['ad_name']) : null;
    $adsetId = isset($data['adset_id']) ? trim((string)$data['adset_id']) : null;
    $adsetName = isset($data['adset_name']) ? trim((string)$data['adset_name']) : null;
    $campaignId = isset($data['campaign_id']) ? trim((string)$data['campaign_id']) : null;
    $campaignName = isset($data['campaign_name']) ? trim((string)$data['campaign_name']) : null;
    $formId = isset($data['form_id']) ? trim((string)$data['form_id']) : null;
    $formName = isset($data['form_name']) ? trim((string)$data['form_name']) : null;
    
    $isOrganic = 0;
    if (isset($data['is_organic'])) {
        $isOrganic = ($data['is_organic'] === true || $data['is_organic'] === 1 || strtolower((string)$data['is_organic']) === 'true') ? 1 : 0;
    }
    
    $platform = isset($data['platform']) ? trim((string)$data['platform']) : null;
    $lookingFor = isset($data['are_you_looking_for']) ? trim((string)$data['are_you_looking_for']) : null;
    $budget = isset($data['budget']) ? trim((string)$data['budget']) : null;
    $purchaseTime = isset($data['purchase_time']) ? trim((string)$data['purchase_time']) : null;
    $investedBefore = isset($data['have_you_invested_in_property_before']) ? trim((string)$data['have_you_invested_in_property_before']) : null;
    $leadStatus = isset($data['lead_status']) ? trim((string)$data['lead_status']) : 'received';

    // Construct inquiry message field using structured meta answers for the CRM view
    $msgContent = "Google Sheet Sync Lead.\n";
    if ($lookingFor) $msgContent .= "Looking For: $lookingFor\n";
    if ($budget) $msgContent .= "Budget: $budget\n";
    if ($purchaseTime) $msgContent .= "Purchase Time: $purchaseTime\n";
    if ($investedBefore) $msgContent .= "Invested Before: $investedBefore\n";
    if ($formName) $msgContent .= "Form: $formName\n";

    // Prepare insert statement
    $sql = "INSERT INTO inquiries (
                property_id, name, email, phone, message, status, source, campaign_name,
                meta_lead_id, ad_id, ad_name, adset_id, adset_name, campaign_id,
                form_id, form_name, is_organic, platform, are_you_looking_for,
                budget, purchase_time, have_you_invested_in_property_before, lead_status, created_at
            ) VALUES (
                NULL, ?, ?, ?, ?, 'fresh_lead', 'meta_ads', ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?
            )";

    $stmtInsert = $pdo->prepare($sql);
    $stmtInsert->execute([
        $fullName,
        $email === '' ? null : $email,
        $phoneNumber,
        $msgContent,
        $campaignName,
        
        $externalId,
        $adId,
        $adName,
        $adsetId,
        $adsetName,
        $campaignId,
        
        $formId,
        $formName,
        $isOrganic,
        $platform,
        $lookingFor,
        
        $budget,
        $purchaseTime,
        $investedBefore,
        $leadStatus,
        $createdAt
    ]);

    $insertedId = $pdo->lastInsertId();

    writeLog('SUCCESS', "Lead ID $externalId | Successfully inserted inquiry ID $insertedId | Name: $fullName | Phone: " . maskPhone($phoneNumber));

    http_response_code(201);
    echo json_encode([
        "success" => true,
        "duplicate" => false,
        "message" => "Lead created successfully",
        "lead_id" => (int)$insertedId
    ]);

} catch (\PDOException $dbEx) {
    writeLog('SERVER_ERROR', "Database Error: " . $dbEx->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Internal server error"
    ]);
} catch (\Exception $ex) {
    writeLog('SERVER_ERROR', "General Error: " . $ex->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Internal server error"
    ]);
}
?>
