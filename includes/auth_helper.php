<?php
// includes/auth_helper.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

/**
 * Checks if the user is currently logged in.
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Gets the current logged in user's details.
 */
function get_current_user_details() {
    global $pdo;
    if (!is_logged_in()) {
        return null;
    }
    
    $stmt = $pdo->prepare("
        SELECT u.*, r.role_name, r.description as role_desc 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.id = ? AND u.status = 'active'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Checks if a specific role has permission for a module and action.
 * Actions: 'view', 'create', 'edit', 'delete'
 */
function has_permission($module, $action) {
    global $pdo;
    
    // Admins always have all permissions
    if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'Admin') {
        return true;
    }
    
    if (!isset($_SESSION['role_id'])) {
        return false;
    }
    
    $role_id = $_SESSION['role_id'];
    
    // Query permission table
    $stmt = $pdo->prepare("
        SELECT can_view, can_create, can_edit, can_delete 
        FROM role_permissions 
        WHERE role_id = ? AND module_name = ?
    ");
    $stmt->execute([$role_id, $module]);
    $perms = $stmt->fetch();
    
    if (!$perms) {
        return false;
    }
    
    switch ($action) {
        case 'view':
            return (bool)$perms['can_view'];
        case 'create':
            return (bool)$perms['can_create'];
        case 'edit':
            return (bool)$perms['can_edit'];
        case 'delete':
            return (bool)$perms['can_delete'];
        default:
            return false;
    }
}

/**
 * Requires a specific permission, redirecting to a 403 error page if not allowed.
 */
function require_permission($module, $action) {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
    
    if (!has_permission($module, $action)) {
        // Return 403 Forbidden header
        header('HTTP/1.1 403 Forbidden');
        echo "<!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <title>Access Denied</title>
            <script src='https://cdn.tailwindcss.com'></script>
        </head>
        <body class='bg-slate-100 flex items-center justify-center min-h-screen font-sans'>
            <div class='max-w-md w-full bg-white p-8 rounded-2xl shadow-xl text-center border border-slate-200'>
                <div class='w-20 h-20 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto mb-6'>
                    <svg class='w-10 h-10' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'/>
                    </svg>
                </div>
                <h1 class='text-2xl font-bold text-slate-800 mb-2'>Access Denied</h1>
                <p class='text-slate-500 mb-6'>You do not have permission to access the <strong>" . htmlspecialchars($module) . "</strong> module (" . htmlspecialchars($action) . " action).</p>
                <div class='flex gap-4 justify-center'>
                    <a href='index.php' class='px-5 py-2.5 bg-slate-800 text-white rounded-lg hover:bg-slate-700 transition shadow'>Go to Dashboard</a>
                    <a href='logout.php' class='px-5 py-2.5 bg-slate-200 text-slate-700 rounded-lg hover:bg-slate-300 transition'>Logout</a>
                </div>
            </div>
        </body>
        </html>";
        exit;
    }
}
?>
