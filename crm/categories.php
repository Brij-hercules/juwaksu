<?php
// crm/categories.php
$pageTitle = "Category Management";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/db.php';

// Verify view permission
require_permission('categories', 'view');

$successMsg = '';
$errorMsg = '';

// Handle CRUD operations
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Create / Update category
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $slug = trim($_POST['slug']);
    $description = trim($_POST['description']);
    
    // Automatically generate slug if empty
    if (empty($slug)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    }
    
    if (empty($name)) {
        $errorMsg = "Category name is required.";
    } else {
        if ($id > 0) {
            // Edit check
            if (!has_permission('categories', 'edit')) {
                $errorMsg = "You do not have permission to edit categories.";
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, description = ? WHERE id = ?");
                    $stmt->execute([$name, $slug, $description, $id]);
                    $successMsg = "Category updated successfully.";
                    $action = 'list';
                } catch (\PDOException $e) {
                    $errorMsg = "Error updating category: " . $e->getMessage();
                }
            }
        } else {
            // Create check
            if (!has_permission('categories', 'create')) {
                $errorMsg = "You do not have permission to create categories.";
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $slug, $description]);
                    $successMsg = "Category added successfully.";
                    $action = 'list';
                } catch (\PDOException $e) {
                    $errorMsg = "Error creating category: " . $e->getMessage();
                }
            }
        }
    }
}

// Delete category
if ($action === 'delete') {
    if (!has_permission('categories', 'delete')) {
        $errorMsg = "You do not have permission to delete categories.";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            $successMsg = "Category deleted successfully.";
        } catch (\PDOException $e) {
            $errorMsg = "Cannot delete category because it contains associated properties. Re-assign them first.";
        }
    }
    $action = 'list';
}

// Fetch single category for editing
$editCategory = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $editCategory = $stmt->fetch();
}

// Fetch all categories
$categories = [];
try {
    $categories = $pdo->query("SELECT * FROM categories ORDER BY id DESC")->fetchAll();
} catch (\Exception $e) {
    $errorMsg = "Error fetching categories: " . $e->getMessage();
}
?>

<!-- Feedback alerts -->
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
    <!-- CRUD Form Panel (Visible when adding or editing, and checked permissions) -->
    <?php if (($action === 'add' && has_permission('categories', 'create')) || ($action === 'edit' && $editCategory && has_permission('categories', 'edit'))): ?>
        <div class="col-lg-4">
            <div class="bg-white p-6 rounded-2xl border border-slate-200/60 shadow-sm">
                <h3 class="text-base font-extrabold text-slate-800 mb-6"><?php echo $action === 'edit' ? 'Edit Category' : 'Create New Category'; ?></h3>
                
                <form action="categories.php?action=<?php echo $action; ?><?php echo $id > 0 ? '&id='.$id : ''; ?>" method="POST" class="space-y-4">
                    <div>
                        <label for="name" class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Category Name</label>
                        <input type="text" name="name" id="name" required value="<?php echo $editCategory ? htmlspecialchars($editCategory['name']) : ''; ?>" class="w-full px-3 py-2.5 rounded-lg bg-slate-50 border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-brand-gold focus:bg-white text-slate-800" placeholder="e.g. Apartments">
                    </div>
                    
                    <div>
                        <label for="slug" class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Slug (Optional)</label>
                        <input type="text" name="slug" id="slug" value="<?php echo $editCategory ? htmlspecialchars($editCategory['slug']) : ''; ?>" class="w-full px-3 py-2.5 rounded-lg bg-slate-50 border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-brand-gold focus:bg-white text-slate-800" placeholder="e.g. apartments">
                    </div>
                    
                    <div>
                        <label for="description" class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Description</label>
                        <textarea name="description" id="description" rows="4" class="w-full px-3 py-2.5 rounded-lg bg-slate-50 border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-brand-gold focus:bg-white text-slate-800" placeholder="Brief details about properties matching this category."><?php echo $editCategory ? htmlspecialchars($editCategory['description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="flex-grow py-2.5 bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs rounded-lg transition shadow">
                            Save Category
                        </button>
                        <a href="categories.php" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-lg transition text-center">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Categories Data Table Panel -->
    <div class="col">
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                <h3 class="text-base font-extrabold text-slate-800">Categories Directory</h3>
                
                <div class="flex items-center gap-2">
                    <!-- Export CSV/PDF links -->
                    <a href="export.php?module=categories&format=csv" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold transition flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        CSV Export
                    </a>
                    <a href="export.php?module=categories&format=pdf" target="_blank" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold transition flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        PDF Export
                    </a>
                    
                    <?php if (has_permission('categories', 'create') && $action !== 'add'): ?>
                        <a href="categories.php?action=add" class="px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white rounded-lg text-xs font-bold transition flex items-center gap-1">
                            + Add Category
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover align-middle text-sm">
                    <thead class="text-[10px] text-slate-400 font-bold uppercase tracking-wider border-b border-slate-150">
                        <tr>
                            <th class="pb-3">ID</th>
                            <th class="pb-3">Category Name</th>
                            <th class="pb-3">Slug</th>
                            <th class="pb-3">Description</th>
                            <th class="pb-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="5" class="py-6 text-center text-slate-400">No categories registered yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td class="py-3 font-bold text-slate-800">#<?php echo $cat['id']; ?></td>
                                    <td class="py-3 font-bold text-brand-500"><?php echo htmlspecialchars($cat['name']); ?></td>
                                    <td class="py-3 text-slate-500"><code><?php echo htmlspecialchars($cat['slug']); ?></code></td>
                                    <td class="py-3 text-slate-500 max-w-xs truncate"><?php echo htmlspecialchars($cat['description']); ?></td>
                                    <td class="py-3 text-end">
                                        <div class="inline-flex gap-2">
                                            <?php if (has_permission('categories', 'edit')): ?>
                                                <a href="categories.php?action=edit&id=<?php echo $cat['id']; ?>" class="px-2.5 py-1.5 bg-slate-100 hover:bg-brand-50 hover:text-brand-gold text-slate-600 rounded-lg text-xs font-bold transition">
                                                    Edit
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if (has_permission('categories', 'delete')): ?>
                                                <a href="categories.php?action=delete&id=<?php echo $cat['id']; ?>" onclick="return confirm('Are you sure you want to delete this category? All sub-properties will prevent delete if not cleared.')" class="px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg text-xs font-bold transition">
                                                    Delete
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
