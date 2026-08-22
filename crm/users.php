<?php
// crm/users.php
$pageTitle = "Team Account Management";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/db.php';

// Verify view permission
require_permission('users', 'view');

$successMsg = '';
$errorMsg = '';

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch all roles for user form mapping
$roles = [];
try {
    $roles = $pdo->query("SELECT * FROM roles ORDER BY role_name ASC")->fetchAll();
} catch (\Exception $e) {
    //
}

// 1. Handle Form Submit (Add / Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $role_id  = intval($_POST['role_id']);
    $status   = $_POST['status'] ?? 'active';
    $password = trim($_POST['password'] ?? '');
    
    if (empty($username) || empty($email) || $role_id <= 0) {
        $errorMsg = "Please fill in all required fields.";
    } else {
        if ($id > 0) {
            // Edit User
            if (!has_permission('users', 'edit')) {
                $errorMsg = "You do not have permission to edit users.";
            } else {
                try {
                    // Update main details
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role_id = ?, status = ? WHERE id = ?");
                    $stmt->execute([$username, $email, $role_id, $status, $id]);
                    
                    // If password was input, update password hash
                    if (!empty($password)) {
                        $passHash = password_hash($password, PASSWORD_BCRYPT);
                        $stmtPass = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                        $stmtPass->execute([$passHash, $id]);
                    }
                    
                    $successMsg = "User account updated successfully.";
                    $action = 'list';
                } catch (\PDOException $e) {
                    $errorMsg = "Error updating user: " . $e->getMessage();
                }
            }
        } else {
            // Add User
            if (!has_permission('users', 'create')) {
                $errorMsg = "You do not have permission to create user accounts.";
            } elseif (empty($password)) {
                $errorMsg = "Password is required for new accounts.";
            } else {
                try {
                    $passHash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role_id, status) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $passHash, $role_id, $status]);
                    
                    $successMsg = "User account created successfully.";
                    $action = 'list';
                } catch (\PDOException $e) {
                    $errorMsg = "Error creating user: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch single user details for editing
$editUser = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $editUser = $stmt->fetch();
}

// Fetch all users list
$users = [];
try {
    $users = $pdo->query("
        SELECT u.*, r.role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        ORDER BY u.id DESC
    ")->fetchAll();
} catch (\Exception $e) {
    $errorMsg = "Error listing users: " . $e->getMessage();
}
?>

<!-- Alerts -->
<?php if (!empty($successMsg)): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 rounded-xl bg-emerald-50 text-emerald-700 shadow-sm text-xs p-4 mb-6" role="alert">
        <strong>Success!</strong> <?php echo $successMsg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($errorMsg)): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 rounded-xl bg-rose-50 text-rose-700 shadow-sm text-xs p-4 mb-6" role="alert">
        <strong>Error!</strong> <?php echo $errorMsg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-5">
    
    <!-- Left Panel: Form Form (Visible on Add or Edit, checked permissions) -->
    <?php if (($action === 'add' && has_permission('users', 'create')) || ($action === 'edit' && $editUser && has_permission('users', 'edit'))): ?>
        <div class="col-lg-4">
            <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6">
                <h3 class="text-base font-extrabold text-slate-800 mb-6"><?php echo $action === 'edit' ? 'Edit User Details' : 'Create User Account'; ?></h3>
                
                <form action="users.php?action=<?php echo $action; ?><?php echo $id > 0 ? '&id='.$id : ''; ?>" method="POST" class="space-y-4">
                    <div>
                        <label for="username" class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Username *</label>
                        <input type="text" name="username" id="username" required value="<?php echo $editUser ? htmlspecialchars($editUser['username']) : ''; ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs rounded focus:outline-none focus:ring-2 focus:ring-brand-gold text-slate-800" placeholder="e.g. sales_john">
                    </div>
                    
                    <div>
                        <label for="email" class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Email Address *</label>
                        <input type="email" name="email" id="email" required value="<?php echo $editUser ? htmlspecialchars($editUser['email']) : ''; ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs rounded focus:outline-none focus:ring-2 focus:ring-brand-gold text-slate-800" placeholder="e.g. john@primehashtag.com">
                    </div>
                    
                    <div>
                        <label for="role_id" class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">User Role Capability *</label>
                        <select name="role_id" id="role_id" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs rounded focus:outline-none focus:ring-2 focus:ring-brand-gold text-slate-800">
                            <option value="">Select Role</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?php echo $r['id']; ?>" <?php echo ($editUser && $editUser['role_id'] == $r['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($r['role_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="status" class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Account Status</label>
                        <select name="status" id="status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs rounded focus:outline-none focus:ring-2 focus:ring-brand-gold text-slate-800">
                            <option value="active" <?php echo ($editUser && $editUser['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($editUser && $editUser['status'] === 'inactive') ? 'selected' : ''; ?>>Suspended / Inactive</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">
                            Password <?php echo $action === 'edit' ? '(Leave blank to keep current)' : '*'; ?>
                        </label>
                        <input type="password" name="password" id="password" <?php echo $action === 'add' ? 'required' : ''; ?> class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs rounded focus:outline-none focus:ring-2 focus:ring-brand-gold text-slate-800" placeholder="••••••••">
                    </div>
                    
                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="flex-grow py-2 bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs rounded transition shadow">
                            Save User
                        </button>
                        <a href="users.php" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded transition text-center">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Right Panel: Users Directory table list -->
    <div class="col">
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                <h3 class="text-base font-extrabold text-slate-800">Workspace Accounts Directory</h3>
                
                <div class="flex items-center gap-2">
                    <a href="export.php?module=users&format=csv" class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold transition flex items-center gap-1">
                        CSV Export
                    </a>
                    <a href="export.php?module=users&format=pdf" target="_blank" class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold transition flex items-center gap-1">
                        PDF Export
                    </a>
                    
                    <?php if (has_permission('users', 'create') && $action !== 'add'): ?>
                        <a href="users.php?action=add" class="px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white rounded-lg text-xs font-bold transition">
                            + Add Team Member
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover align-middle text-sm">
                    <thead class="text-[10px] text-slate-400 font-bold uppercase tracking-wider border-b border-slate-150">
                        <tr>
                            <th class="pb-3">Agent ID</th>
                            <th class="pb-3">Username Details</th>
                            <th class="pb-3">Email Address</th>
                            <th class="pb-3">Role</th>
                            <th class="pb-3">Status</th>
                            <th class="pb-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td class="py-3 font-bold text-slate-800">#<?php echo $u['id']; ?></td>
                                <td class="py-3 font-bold text-brand-500"><?php echo htmlspecialchars($u['username']); ?></td>
                                <td class="py-3 text-slate-500 font-medium"><?php echo htmlspecialchars($u['email']); ?></td>
                                <td class="py-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 border">
                                        <?php echo htmlspecialchars($u['role_name']); ?>
                                    </span>
                                </td>
                                <td class="py-3">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?php echo $u['status'] === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?>">
                                        <?php echo htmlspecialchars($u['status']); ?>
                                    </span>
                                </td>
                                <td class="py-3 text-end">
                                    <?php if (has_permission('users', 'edit')): ?>
                                        <a href="users.php?action=edit&id=<?php echo $u['id']; ?>" class="px-2.5 py-1.5 bg-slate-100 hover:bg-brand-50 hover:text-brand-gold text-slate-600 rounded-lg text-xs font-bold transition">
                                            Edit / Reset
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
