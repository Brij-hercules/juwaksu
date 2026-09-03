<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/lead_status_helper.php';

try {
    $stmt = $pdo->query("SELECT id FROM inquiries WHERE assigned_to IS NULL");
    $unassigned_leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = 0;
    foreach ($unassigned_leads as $lead) {
        $assignedTo = get_next_sales_employee($pdo);
        if ($assignedTo) {
            $update = $pdo->prepare("UPDATE inquiries SET assigned_to = ? WHERE id = ?");
            $update->execute([$assignedTo, $lead['id']]);
            $count++;
        }
    }
    echo "Successfully assigned $count existing leads to Sales Employees.\n";
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}
