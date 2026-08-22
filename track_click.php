<?php
// track_click.php
require_once __DIR__ . '/config/db.php';

// Retrieve UTM codes & click parameters
$campaign = isset($_GET['utm_campaign']) ? htmlspecialchars(trim($_GET['utm_campaign'])) : 'General Meta Campaign';
$adName = isset($_GET['utm_ad']) ? htmlspecialchars(trim($_GET['utm_ad'])) : 'Ad Variant A';
$redirectUrl = isset($_GET['redirect']) ? trim($_GET['redirect']) : 'index.php';

// Basic safety check for redirect URL
if (empty($redirectUrl) || strpos($redirectUrl, 'http') === 0 && strpos($redirectUrl, $_SERVER['HTTP_HOST']) === false) {
    $redirectUrl = 'index.php';
}

$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Insert click record
try {
    $stmt = $pdo->prepare("
        INSERT INTO meta_ads_clicks (campaign_name, ad_name, ip_address, user_agent) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$campaign, $adName, $ipAddress, $userAgent]);
} catch (\Exception $e) {
    // Fail silently to ensure user experience isn't disrupted by click tracking errors
}

// Perform redirection
header("Location: " . $redirectUrl);
exit;
?>
