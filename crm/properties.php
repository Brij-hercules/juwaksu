<?php
// crm/properties.php
$pageTitle = "Property Management";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/db.php';

// Verify view permission
require_permission('properties', 'view');

$successMsg = '';
$errorMsg = '';

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch categories for form selects & filters
$categories = [];
try {
    $categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
} catch (\Exception $e) {
    //
}

// 1. Process Form Submit (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id   = intval($_POST['category_id']);
    $title         = trim($_POST['title']);
    $slug          = trim($_POST['slug']);
    $location      = trim($_POST['location']);
    $price         = floatval($_POST['price']);
    $price_unit    = trim($_POST['price_unit'] ?? 'Sq. Yard');
    $description   = trim($_POST['description']);
    $beds          = intval($_POST['beds'] ?? 0);
    $baths         = intval($_POST['baths'] ?? 0);
    $area_sqft     = intval($_POST['area_sqft']);
    $featured      = isset($_POST['featured']) ? 1 : 0;
    $is_kisan_kota = isset($_POST['is_kisan_kota']) ? 1 : 0;
    $status        = $_POST['status']; // 'active', 'inactive', 'sold'
    
    // Auto slug if empty
    if (empty($slug)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
    }
    
    // File upload directory setup
    $uploadDir = __DIR__ . '/../uploads/properties/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $mainImageRelativePath = '';
    
    // Handle main image upload
    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['main_image']['tmp_name'];
        $fileName = $_FILES['main_image']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
        $destPath = $uploadDir . $newFileName;
        
        if (move_uploaded_file($fileTmpPath, $destPath)) {
            $mainImageRelativePath = 'uploads/properties/' . $newFileName;
        }
    }
    
    if (empty($title) || $category_id <= 0 || $price <= 0 || $area_sqft <= 0) {
        $errorMsg = "Please fill in all required fields (Title, Category, Price, and Area).";
    } else {
        if ($id > 0) {
            // Edit
            if (!has_permission('properties', 'edit')) {
                $errorMsg = "You do not have permission to edit properties.";
            } else {
                try {
                    // Get current property details (to preserve main image if not re-uploaded)
                    $stmtGet = $pdo->prepare("SELECT main_image FROM properties WHERE id = ?");
                    $stmtGet->execute([$id]);
                    $currProp = $stmtGet->fetch();
                    
                    if (empty($mainImageRelativePath)) {
                        $mainImageRelativePath = $currProp['main_image'];
                    }
                    
                    $stmtUpdate = $pdo->prepare("
                        UPDATE properties 
                        SET category_id = ?, title = ?, slug = ?, location = ?, price = ?, price_unit = ?, description = ?, beds = ?, baths = ?, area_sqft = ?, featured = ?, is_kisan_kota = ?, status = ?, main_image = ? 
                        WHERE id = ?
                    ");
                    $stmtUpdate->execute([$category_id, $title, $slug, $location, $price, $price_unit, $description, $beds, $baths, $area_sqft, $featured, $is_kisan_kota, $status, $mainImageRelativePath, $id]);
                    
                    // Handle Gallery additions
                    if (isset($_FILES['gallery_images'])) {
                        $files = $_FILES['gallery_images'];
                        for ($i = 0; $i < count($files['name']); $i++) {
                            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                                $tmpPath = $files['tmp_name'][$i];
                                $name = $files['name'][$i];
                                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                                $newName = md5(time() . $name . $i) . '.' . $ext;
                                
                                if (move_uploaded_file($tmpPath, $uploadDir . $newName)) {
                                    $stmtGal = $pdo->prepare("INSERT INTO property_gallery (property_id, image_path) VALUES (?, ?)");
                                    $stmtGal->execute([$id, 'uploads/properties/' . $newName]);
                                }
                            }
                        }
                    }
                    
                    $successMsg = "Property updated successfully.";
                    $action = 'list';
                } catch (\PDOException $e) {
                    $errorMsg = "Error updating property: " . $e->getMessage();
                }
            }
        } else {
            // Create
            if (!has_permission('properties', 'create')) {
                $errorMsg = "You do not have permission to create properties.";
            } else {
                try {
                    // Set default image if none uploaded
                    if (empty($mainImageRelativePath)) {
                        $mainImageRelativePath = 'assets/images/wave-city-ext.jpg';
                    }
                    
                    $stmtInsert = $pdo->prepare("
                        INSERT INTO properties (category_id, title, slug, location, price, price_unit, description, beds, baths, area_sqft, featured, is_kisan_kota, status, main_image) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmtInsert->execute([$category_id, $title, $slug, $location, $price, $price_unit, $description, $beds, $baths, $area_sqft, $featured, $is_kisan_kota, $status, $mainImageRelativePath]);
                    $newPropertyId = $pdo->lastInsertId();
                    
                    // Handle Gallery images
                    if (isset($_FILES['gallery_images'])) {
                        $files = $_FILES['gallery_images'];
                        for ($i = 0; $i < count($files['name']); $i++) {
                            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                                $tmpPath = $files['tmp_name'][$i];
                                $name = $files['name'][$i];
                                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                                $newName = md5(time() . $name . $i) . '.' . $ext;
                                
                                if (move_uploaded_file($tmpPath, $uploadDir . $newName)) {
                                    $stmtGal = $pdo->prepare("INSERT INTO property_gallery (property_id, image_path) VALUES (?, ?)");
                                    $stmtGal->execute([$newPropertyId, 'uploads/properties/' . $newName]);
                                }
                            }
                        }
                    }
                    
                    $successMsg = "Property added successfully.";
                    $action = 'list';
                } catch (\PDOException $e) {
                    $errorMsg = "Error inserting property: " . $e->getMessage();
                }
            }
        }
    }
}

// 2. Delete Property
if ($action === 'delete' && $id > 0) {
    if (!has_permission('properties', 'delete')) {
        $errorMsg = "You do not have permission to delete properties.";
    } else {
        try {
            $stmtDel = $pdo->prepare("DELETE FROM properties WHERE id = ?");
            $stmtDel->execute([$id]);
            $successMsg = "Property deleted successfully.";
        } catch (\PDOException $e) {
            $errorMsg = "Error deleting property: " . $e->getMessage();
        }
    }
    $action = 'list';
}

// Fetch single property details for editing
$editProperty = null;
$editGallery = [];
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
    $stmt->execute([$id]);
    $editProperty = $stmt->fetch();
    
    if ($editProperty) {
        $stmtGal = $pdo->prepare("SELECT * FROM property_gallery WHERE property_id = ?");
        $stmtGal->execute([$id]);
        $editGallery = $stmtGal->fetchAll();
    }
}

// 3. Filter criteria (respecting exports later)
$filterCategory = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$filterStatus   = isset($_GET['status']) ? trim($_GET['status']) : '';
$filterKisan    = isset($_GET['is_kisan_kota']) ? trim($_GET['is_kisan_kota']) : '';

// Build Query
$queryStr = "
    SELECT p.*, c.name as category_name 
    FROM properties p 
    JOIN categories c ON p.category_id = c.id 
    WHERE 1=1
";
$params = [];

if ($filterCategory > 0) {
    $queryStr .= " AND p.category_id = ?";
    $params[] = $filterCategory;
}
if (!empty($filterStatus)) {
    $queryStr .= " AND p.status = ?";
    $params[] = $filterStatus;
}
if ($filterKisan !== '') {
    $queryStr .= " AND p.is_kisan_kota = ?";
    $params[] = intval($filterKisan);
}

$queryStr .= " ORDER BY p.id DESC";

try {
    $stmtList = $pdo->prepare($queryStr);
    $stmtList->execute($params);
    $properties = $stmtList->fetchAll();
} catch (\Exception $e) {
    $properties = [];
    $errorMsg = "Database query error: " . $e->getMessage();
}
?>

<!-- Alert Feedback -->
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

<?php if (($action === 'add' && has_permission('properties', 'create')) || ($action === 'edit' && $editProperty && has_permission('properties', 'edit'))): ?>
    <!-- Form Page (Create / Edit) -->
    <div class="bg-white rounded-3xl border border-slate-200/60 shadow-sm p-8 max-w-4xl mx-auto">
        <h3 class="text-lg font-black text-slate-800 mb-6"><?php echo $action === 'edit' ? 'Edit Property: ' . htmlspecialchars($editProperty['title']) : 'Add New Property Listing'; ?></h3>
        
        <form action="properties.php?action=<?php echo $action; ?><?php echo $id > 0 ? '&id='.$id : ''; ?>" method="POST" enctype="multipart/form-data" class="space-y-6">
            <!-- Row 1: Title & Category -->
            <div class="row g-4">
                <div class="col-md-8">
                    <label for="title" class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Property Title *</label>
                    <input type="text" name="title" id="title" required value="<?php echo $editProperty ? htmlspecialchars($editProperty['title']) : ''; ?>" class="w-full px-3 py-2.5 rounded-lg bg-slate-50 border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-brand-gold focus:bg-white text-slate-800" placeholder="e.g. Wave City Premium Residential Plots NH-24">
                </div>
                <div class="col-md-4">
                    <label for="category_id" class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Category *</label>
                    <select name="category_id" id="category_id" required class="w-full px-3 py-2.5 rounded-lg bg-slate-50 border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-brand-gold focus:bg-white text-slate-800">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo ($editProperty && $editProperty['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Row 2: Location & Slug -->
            <div class="row g-4">
                <div class="col-md-6">
                    <label for="location" class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Location / Address *</label>
                    <input type="text" name="location" id="location" required value="<?php echo $editProperty ? htmlspecialchars($editProperty['location']) : ''; ?>" class="w-full px-3 py-2.5 rounded-lg bg-slate-50 border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-brand-gold focus:bg-white text-slate-800" placeholder="e.g. Wave City, Ghaziabad">
                </div>
                <div class="col-md-6">
                    <label for="slug" class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Slug (Auto Generated if Blank)</label>
                    <input type="text" name="slug" id="slug" value="<?php echo $editProperty ? htmlspecialchars($editProperty['slug']) : ''; ?>" class="w-full px-3 py-2.5 rounded-lg bg-slate-50 border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-brand-gold focus:bg-white text-slate-800" placeholder="e.g. wave-city-premium-plots">
                </div>
            </div>

            <!-- Row 3: Pricing & Area -->
            <div class="row g-4">
                <div class="col-md-4">
                    <label for="price" class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Starting Price *</label>
                    <input type="number" step="0.01" name="price" id="price" required value="<?php echo $editProperty ? floatval($editProperty['price']) : ''; ?>" class="w-full px-3 py-2.5 rounded-lg bg-slate-50 border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-brand-gold focus:bg-white text-slate-800" placeholder="e.g. 32990">
                </div>
                <div class="col-md-4">
                    <label for="price_unit" class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Price Unit *</label>
                    <select name="price_unit" id="price_unit" class="w-full px-3 py-2.5 rounded-lg bg-slate-50 border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-brand-gold focus:bg-white text-slate-800">
                        <option value="Sq. Yard" <?php echo ($editProperty && $editProperty['price_unit'] === 'Sq. Yard') ? 'selected' : ''; ?>>Per Sq. Yard</option>
                        <option value="Total" <?php echo ($editProperty && $editProperty['price_unit'] === 'Total') ? 'selected' : ''; ?>>Total Price</option>
                        <option value="Sq. Ft" <?php echo ($editProperty && $editProperty['price_unit'] === 'Sq. Ft') ? 'selected' : ''; ?>>Per Sq. Ft</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="area_sqft" class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Area (Sq. Ft) *</label>
                    <input type="number" name="area_sqft" id="area_sqft" required value="<?php echo $editProperty ? intval($editProperty['area_sqft']) : ''; ?>" class="w-full px-3 py-2.5 rounded-lg bg-slate-50 border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-brand-gold focus:bg-white text-slate-800" placeholder="e.g. 1080">
                </div>
            </div>

            <!-- Row 4: Statuses & Featured flags -->
            <div class="row g-4 items-center">
                <div class="col-md-4">
                    <label for="status" class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Listing Status</label>
                    <select name="status" id="status" class="w-full px-3 py-2.5 rounded-lg bg-slate-50 border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-brand-gold focus:bg-white text-slate-800">
                        <option value="active" <?php echo ($editProperty && $editProperty['status'] === 'active') ? 'selected' : ''; ?>>Active / Listed</option>
                        <option value="inactive" <?php echo ($editProperty && $editProperty['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive / Draft</option>
                        <option value="sold" <?php echo ($editProperty && $editProperty['status'] === 'sold') ? 'selected' : ''; ?>>Sold Out</option>
                    </select>
                </div>
                <div class="col-md-4 flex items-center gap-2 pt-4">
                    <input type="checkbox" name="featured" id="featured" value="1" <?php echo ($editProperty && $editProperty['featured']) ? 'checked' : ''; ?> class="w-4 h-4 text-brand-gold focus:ring-brand-gold rounded border-slate-300">
                    <label for="featured" class="text-xs font-bold text-slate-700 select-none">Feature on Homepage</label>
                </div>
                <div class="col-md-4 flex items-center gap-2 pt-4">
                    <input type="checkbox" name="is_kisan_kota" id="is_kisan_kota" value="1" <?php echo ($editProperty && $editProperty['is_kisan_kota']) ? 'checked' : ''; ?> class="w-4 h-4 text-brand-gold focus:ring-brand-gold rounded border-slate-300">
                    <label for="is_kisan_kota" class="text-xs font-bold text-slate-700 select-none">Kisaan Quota 8% Plot</label>
                </div>
            </div>

            <!-- Row 5: Image uploads -->
            <div class="row g-4">
                <div class="col-md-6">
                    <label for="main_image" class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Main Property Photo</label>
                    <input type="file" name="main_image" id="main_image" accept="image/*" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-sm rounded-lg text-slate-800 focus:outline-none">
                    <?php if ($editProperty && $editProperty['main_image']): ?>
                        <div class="mt-2 text-xs text-slate-400">Current: <code><?php echo basename($editProperty['main_image']); ?></code></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label for="gallery_images" class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Gallery Upload (Select Multiple)</label>
                    <input type="file" name="gallery_images[]" id="gallery_images" accept="image/*" multiple class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-sm rounded-lg text-slate-800 focus:outline-none">
                </div>
            </div>

            <!-- Row 6: Description -->
            <div>
                <label for="description" class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Detailed Description</label>
                <textarea name="description" id="description" rows="5" class="w-full px-3 py-2.5 rounded-lg bg-slate-50 border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-brand-gold focus:bg-white text-slate-800" placeholder="Details about ownership documentation, layout blueprints, roads connectivity, allotment status, etc."><?php echo $editProperty ? htmlspecialchars($editProperty['description']) : ''; ?></textarea>
            </div>

            <!-- Action buttons -->
            <div class="flex gap-4 pt-4 border-t border-slate-100">
                <button type="submit" class="px-6 py-2.5 bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs rounded-lg transition shadow">
                    Save Property Listing
                </button>
                <a href="properties.php" class="px-6 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-lg transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>

<?php else: ?>
    <!-- List / View Directory Page -->
    <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6">
        
        <!-- Top controls & Add/Export options -->
        <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
            <div>
                <h3 class="text-base font-extrabold text-slate-800 mb-0.5">Properties Database</h3>
                <p class="text-slate-400 text-xs font-light">Filter, modify or delete active properties listings.</p>
            </div>
            
            <div class="flex items-center gap-2">
                <!-- Dynamic Export endpoints mapping current active filters -->
                <?php
                $exportQuery = http_build_query([
                    'module' => 'properties',
                    'category_id' => $filterCategory,
                    'status' => $filterStatus,
                    'is_kisan_kota' => $filterKisan
                ]);
                ?>
                <a href="export.php?<?php echo $exportQuery; ?>&format=csv" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold transition flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    CSV Export
                </a>
                <a href="export.php?<?php echo $exportQuery; ?>&format=pdf" target="_blank" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold transition flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    PDF Export
                </a>
                
                <?php if (has_permission('properties', 'create')): ?>
                    <a href="properties.php?action=add" class="px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white rounded-lg text-xs font-bold transition flex items-center gap-1">
                        + Add Property
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Inline Filters block -->
        <form action="properties.php" method="GET" class="row g-3 bg-slate-50 p-4 rounded-xl mb-6 border border-slate-200/50">
            <div class="col-md-4">
                <label for="category_id" class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Filter Category</label>
                <select name="category_id" id="category_id" class="w-full px-3 py-2 rounded-lg bg-white border border-slate-200 text-xs">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $filterCategory == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="status" class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Filter Status</label>
                <select name="status" id="status" class="w-full px-3 py-2 rounded-lg bg-white border border-slate-200 text-xs">
                    <option value="">All Statuses</option>
                    <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="sold" <?php echo $filterStatus === 'sold' ? 'selected' : ''; ?>>Sold Out</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="is_kisan_kota" class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Allotment Type</label>
                <select name="is_kisan_kota" id="is_kisan_kota" class="w-full px-3 py-2 rounded-lg bg-white border border-slate-200 text-xs">
                    <option value="">All Types</option>
                    <option value="1" <?php echo $filterKisan === '1' ? 'selected' : ''; ?>>Kisan Kota (8% Quota) Only</option>
                    <option value="0" <?php echo $filterKisan === '0' ? 'selected' : ''; ?>>General Freehold Only</option>
                </select>
            </div>
            
            <div class="col-md-2 flex items-end">
                <button type="submit" class="w-full py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-lg text-xs font-bold transition">
                    Apply Filter
                </button>
            </div>
        </form>

        <!-- Table Grid -->
        <div class="table-responsive">
            <table class="table table-hover align-middle text-sm">
                <thead class="text-[10px] text-slate-400 font-bold uppercase tracking-wider border-b border-slate-150">
                    <tr>
                        <th class="pb-3">Preview</th>
                        <th class="pb-3">Title Details</th>
                        <th class="pb-3">Category</th>
                        <th class="pb-3">Price</th>
                        <th class="pb-3">Status</th>
                        <th class="pb-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($properties)): ?>
                        <tr>
                            <td colspan="6" class="py-6 text-center text-slate-400">No properties matching filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($properties as $prop): ?>
                            <tr>
                                <td class="py-3">
                                    <img src="../<?php echo htmlspecialchars($prop['main_image']); ?>" class="w-12 h-10 object-cover rounded-lg border shadow-sm" alt="">
                                </td>
                                <td class="py-3">
                                    <div class="font-bold text-slate-800 leading-snug"><?php echo htmlspecialchars($prop['title']); ?></div>
                                    <div class="text-[10px] text-slate-400 font-semibold uppercase flex items-center gap-1.5 mt-0.5">
                                        <span><?php echo htmlspecialchars($prop['location']); ?></span>
                                        <span>•</span>
                                        <span><?php echo number_format($prop['area_sqft']); ?> Sq. Ft</span>
                                        <?php if ($prop['is_kisan_kota']): ?>
                                            <span>•</span>
                                            <span class="text-amber-500 font-bold uppercase">Kisan Kota</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-3 text-slate-500 font-semibold"><?php echo htmlspecialchars($prop['category_name']); ?></td>
                                <td class="py-3 font-bold text-brand-500">₹<?php echo number_format($prop['price']); ?><span class="text-[10px] text-slate-400 font-normal">/<?php echo htmlspecialchars($prop['price_unit']); ?></span></td>
                                <td class="py-3">
                                    <?php
                                    $col = 'bg-slate-100 text-slate-600';
                                    if ($prop['status'] === 'active') $col = 'bg-emerald-100 text-emerald-700';
                                    elseif ($prop['status'] === 'sold') $col = 'bg-rose-100 text-rose-700';
                                    ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?php echo $col; ?>">
                                        <?php echo htmlspecialchars($prop['status']); ?>
                                    </span>
                                </td>
                                <td class="py-3 text-end">
                                    <div class="inline-flex gap-2">
                                        <a href="../property.php?slug=<?php echo htmlspecialchars($prop['slug']); ?>" target="_blank" class="px-2 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-xs font-bold transition">
                                            View
                                        </a>
                                        <?php if (has_permission('properties', 'edit')): ?>
                                            <a href="properties.php?action=edit&id=<?php echo $prop['id']; ?>" class="px-2 py-1.5 bg-slate-100 hover:bg-brand-50 hover:text-brand-gold text-slate-600 rounded-lg text-xs font-bold transition">
                                                Edit
                                            </a>
                                        <?php endif; ?>
                                        <?php if (has_permission('properties', 'delete')): ?>
                                            <a href="properties.php?action=delete&id=<?php echo $prop['id']; ?>" onclick="return confirm('Are you sure you want to delete this property listing?')" class="px-2 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg text-xs font-bold transition">
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
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
