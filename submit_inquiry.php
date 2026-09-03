<?php
// submit_inquiry.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/lead_status_helper.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input data
    $property_id = isset($_POST['property_id']) && !empty($_POST['property_id']) ? intval($_POST['property_id']) : null;
    $name = isset($_POST['name']) ? htmlspecialchars(trim($_POST['name'])) : '';
    $email = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL) : '';
    $phone = isset($_POST['phone']) ? htmlspecialchars(trim($_POST['phone'])) : '';
    $message = isset($_POST['message']) ? htmlspecialchars(trim($_POST['message'])) : '';
    $source = isset($_POST['source']) && in_array($_POST['source'], ['website', 'meta_ads']) ? $_POST['source'] : 'website';
    $campaign_name = isset($_POST['campaign_name']) ? htmlspecialchars(trim($_POST['campaign_name'])) : null;

    if (!empty($name) && !empty($phone)) {
        try {
            // Auto-assign to the Sales Employee with the fewest leads
            $assignedTo = get_next_sales_employee($pdo);

            $stmt = $pdo->prepare("
                INSERT INTO inquiries (property_id, name, email, phone, message, status, source, campaign_name, assigned_to) 
                VALUES (?, ?, ?, ?, ?, 'fresh_lead', ?, ?, ?)
            ");
            $stmt->execute([$property_id, $name, $email, $phone, $message, $source, $campaign_name, $assignedTo]);
            
            $_SESSION['inquiry_success'] = "Your inquiry has been successfully captured. Our representative will contact you shortly!";
        } catch (\PDOException $e) {
            $_SESSION['inquiry_error'] = "Something went wrong saving your inquiry: " . $e->getMessage();
        }
    } else {
        $_SESSION['inquiry_error'] = "Name and Phone fields are required.";
    }
}

// Redirect back to referring page or default to home
$redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
header("Location: " . $redirect);
exit;
?>
