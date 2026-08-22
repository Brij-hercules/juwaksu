<?php
// crm/roles.php
$pageTitle = "Roles & Capability Matrix";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/db.php';

// Verify view permission
require_permission('roles', 'view');

$successMsg = '';
$errorMsg = '';

// Active role selection
$selectedRoleId = isset($_GET['role_id']) ? intval($_GET['role_id']) : 0;

// Modules available in the CRM
$modules = ['properties', 'categories', 'inquiries', 'meta_ads', 'roles', 'users'];

// 1. Save Role Capability Matrix
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_permissions'])) {
    if (!has_permission('roles', 'edit')) {
        $errorMsg = "You do not have permission to modify roles.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Clear current permissions for this role
            $stmtClear = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $stmtClear->execute([$selectedRoleId]);
            
            // Insert checked permissions
            $stmtInsert = $pdo->prepare("
                INSERT INTO role_permissions (role_id, module_name, can_view, can_create, can_edit, can_delete) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($modules as $mod) {
                $canView   = isset($_POST['perms'][$mod]['view']) ? 1 : 0;
                $canCreate = isset($_POST['perms'][$mod]['create']) ? 1 : 0;
                $canEdit   = isset($_POST['perms'][$mod]['edit']) ? 1 : 0;
                $canDelete = isset($_POST['perms'][$mod]['delete']) ? 1 : 0;
                
                $stmtInsert->execute([$selectedRoleId, $mod, $canView, $canCreate, $canEdit, $canDelete]);
            }
            
            $pdo->commit();
            $successMsg = "Capability permissions matrix updated successfully.";
        } catch (\Exception $e) {
            $pdo->rollBack();
            $errorMsg = "Transaction failed: " . $e->getMessage();
        }
    }
}

// 2. Create custom role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_role'])) {
    $roleName = trim($_POST['role_name']);
    $roleDesc = trim($_POST['description']);
    
    if (empty($roleName)) {
        $errorMsg = "Role name is required.";
    } else {
        if (!has_permission('roles', 'create')) {
            $errorMsg = "You do not have permission to create custom roles.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO roles (role_name, description) VALUES (?, ?)");
                $stmt->execute([$roleName, $roleDesc]);
                $newId = $pdo->lastInsertId();
                
                // Initialize empty permissions matrix
                $stmtPerm = $pdo->prepare("INSERT INTO role_permissions (role_id, module_name) VALUES (?, ?)");
                foreach ($modules as $mod) {
                    $stmtPerm->execute([$newId, $mod]);
                }
                
                $successMsg = "Custom Role '{$roleName}' created. Set its permissions below.";
                $selectedRoleId = $newId;
            } catch (\PDOException $e) {
                $errorMsg = "Error creating role: " . $e->getMessage();
            }
        }
    }
}

// Fetch all roles
$roles = [];
try {
    $roles = $pdo->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();
    if ($selectedRoleId <= 0 && !empty($roles)) {
        $selectedRoleId = $roles[0]['id'];
    }
} catch (\Exception $e) {
    //
}

// Fetch permissions for active selected role
$rolePermissions = [];
if ($selectedRoleId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM role_permissions WHERE role_id = ?");
        $stmt->execute([$selectedRoleId]);
        foreach ($stmt->fetchAll() as $row) {
            $rolePermissions[$row['module_name']] = $row;
        }
    } catch (\Exception $e) {
        //
    }
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
    
    <!-- Left panel: Roles List and add role -->
    <div class="col-lg-4">
        <!-- Roles List -->
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6 mb-6">
            <h3 class="text-base font-extrabold text-slate-800 mb-4">Roles Directory</h3>
            
            <div class="list-group list-group-flush space-y-1">
                <?php foreach ($roles as $r): ?>
                    <a href="roles.php?role_id=<?php echo $r['id']; ?>" class="list-group-item list-group-item-action border-0 px-4 py-3 rounded-xl hover:bg-slate-50 transition text-sm flex justify-between items-center <?php echo $selectedRoleId === intval($r['id']) ? 'bg-brand-50 border-l-4 border-brand-gold font-bold text-brand-500' : 'text-slate-600'; ?>">
                        <div>
                            <div><?php echo htmlspecialchars($r['role_name']); ?></div>
                            <span class="text-[10px] text-slate-400 font-light"><?php echo htmlspecialchars($r['description']); ?></span>
                        </div>
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Add Custom Role Form -->
        <?php if (has_permission('roles', 'create')): ?>
            <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6">
                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-4">Create Custom Role</h3>
                
                <form action="roles.php?role_id=<?php echo $selectedRoleId; ?>" method="POST" class="space-y-4">
                    <input type="hidden" name="create_role" value="1">
                    
                    <div>
                        <label for="role_name" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Role Name</label>
                        <input type="text" name="role_name" id="role_name" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs rounded focus:outline-none focus:ring-2 focus:ring-brand-gold text-slate-800" placeholder="e.g. Sales Manager">
                    </div>
                    
                    <div>
                        <label for="description" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Description</label>
                        <textarea name="description" id="description" rows="2" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-xs rounded focus:outline-none focus:ring-2 focus:ring-brand-gold text-slate-800" placeholder="Brief notes..."></textarea>
                    </div>
                    
                    <button type="submit" class="w-full py-2 bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs rounded-lg transition shadow">
                        + Add Role
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Right Panel: Capabilities Matrix Form -->
    <div class="col-lg-8">
        <?php if ($selectedRoleId > 0): ?>
            <?php
            // Find current active role metadata
            $selectedRoleName = '';
            foreach ($roles as $r) {
                if (intval($r['id']) === $selectedRoleId) {
                    $selectedRoleName = $r['role_name'];
                    break;
                }
            }
            ?>
            <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-6 border-b border-slate-100 pb-4">
                    <div>
                        <h3 class="text-base font-extrabold text-slate-800">Capability Matrix: <span class="text-brand-500"><?php echo htmlspecialchars($selectedRoleName); ?></span></h3>
                        <p class="text-slate-400 text-xs mt-0.5">Toggle section view/edit permissions below. Changes take effect on next page load.</p>
                    </div>
                    
                    <?php if ($selectedRoleName === 'Admin'): ?>
                        <span class="px-2.5 py-1 bg-amber-50 text-amber-700 text-[10px] font-extrabold border border-amber-200 rounded uppercase">
                            Admin has full access
                        </span>
                    <?php endif; ?>
                </div>
                
                <form action="roles.php?role_id=<?php echo $selectedRoleId; ?>" method="POST">
                    <input type="hidden" name="save_permissions" value="1">
                    
                    <div class="table-responsive">
                        <table class="table table-hover align-middle text-sm">
                            <thead class="text-[10px] text-slate-400 font-bold uppercase tracking-wider border-b border-slate-150">
                                <tr>
                                    <th class="pb-3">Module Section</th>
                                    <th class="pb-3 text-center">View (Tab Link)</th>
                                    <th class="pb-3 text-center">Create</th>
                                    <th class="pb-3 text-center">Edit / Update</th>
                                    <th class="pb-3 text-center">Delete</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($modules as $mod): ?>
                                    <?php
                                    $perm = $rolePermissions[$mod] ?? ['can_view' => 0, 'can_create' => 0, 'can_edit' => 0, 'can_delete' => 0];
                                    $disabled = ($selectedRoleName === 'Admin') ? 'disabled checked' : '';
                                    ?>
                                    <tr>
                                        <td class="py-3 font-bold text-slate-800">
                                            <?php echo ucfirst(str_replace('_', ' ', $mod)); ?>
                                        </td>
                                        <td class="py-3 text-center">
                                            <input type="checkbox" name="perms[<?php echo $mod; ?>][view]" value="1" <?php echo $perm['can_view'] ? 'checked' : ''; ?> <?php echo $disabled; ?> class="w-4 h-4 text-brand-gold focus:ring-brand-gold rounded border-slate-300">
                                        </td>
                                        <td class="py-3 text-center">
                                            <input type="checkbox" name="perms[<?php echo $mod; ?>][create]" value="1" <?php echo $perm['can_create'] ? 'checked' : ''; ?> <?php echo $disabled; ?> class="w-4 h-4 text-brand-gold focus:ring-brand-gold rounded border-slate-300">
                                        </td>
                                        <td class="py-3 text-center">
                                            <input type="checkbox" name="perms[<?php echo $mod; ?>][edit]" value="1" <?php echo $perm['can_edit'] ? 'checked' : ''; ?> <?php echo $disabled; ?> class="w-4 h-4 text-brand-gold focus:ring-brand-gold rounded border-slate-300">
                                        </td>
                                        <td class="py-3 text-center">
                                            <input type="checkbox" name="perms[<?php echo $mod; ?>][delete]" value="1" <?php echo $perm['can_delete'] ? 'checked' : ''; ?> <?php echo $disabled; ?> class="w-4 h-4 text-brand-gold focus:ring-brand-gold rounded border-slate-300">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($selectedRoleName !== 'Admin'): ?>
                        <div class="mt-6 pt-4 border-t flex justify-end">
                            <button type="submit" class="px-6 py-2.5 bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs rounded-lg transition shadow">
                                Save Capabilities Matrix
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        <?php endif; ?>
    </div>
    
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
