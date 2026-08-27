<?php
// config/update_users.php — Run once then delete
require_once __DIR__ . '/db.php';

try {
    // ── 1. Update admin credentials ───────────────────────────────────────
    $newUsername = 'PHashtagg';
    $newPassword = password_hash('@Onetwothree4', PASSWORD_BCRYPT);

    // Find admin by current username OR by Admin role
    $stmt = $pdo->prepare("
        UPDATE users SET username = ?, password_hash = ?
        WHERE username = 'admin'
    ");
    $stmt->execute([$newUsername, $newPassword]);
    echo "✅ Admin updated → username: $newUsername\n";

    // ── 2. Get Sales Employee role ID ─────────────────────────────────────
    $roleRow = $pdo->query("SELECT id FROM roles WHERE role_name = 'Sales Employee' LIMIT 1")->fetch();
    if (!$roleRow) {
        die("❌ 'Sales Employee' role not found!\n");
    }
    $salesRoleId = $roleRow['id'];

    // ── 3. Insert 4 new sales employees ──────────────────────────────────
    $employees = [
        ['email' => 'farhankhan905820@gmail.com', 'phone_pass' => '7983043773'],
        ['email' => 'jaseemahmad125@gmail.com', 'phone_pass' => '8979929714'],
        ['email' => 'armaan.properties05@gmail.com', 'phone_pass' => '7678218542'],
        ['email' => 'a8130821979@gmail.com', 'phone_pass' => '8745061459'],
    ];

    $stmtInsert = $pdo->prepare("
        INSERT INTO users (username, email, password_hash, role_id, status)
        VALUES (?, ?, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), status = 'active'
    ");

    foreach ($employees as $emp) {
        $username = $emp['email']; // email = username
        $hash = password_hash($emp['phone_pass'], PASSWORD_BCRYPT);
        $stmtInsert->execute([$username, $emp['email'], $hash, $salesRoleId]);
        echo "✅ Sales Employee added → {$emp['email']} | Password: {$emp['phone_pass']}\n";
    }

    echo "\n🎉 All done! Delete this file now for security.\n";

} catch (\PDOException $e) {
    die("❌ Error: " . $e->getMessage() . "\n");
}
?>