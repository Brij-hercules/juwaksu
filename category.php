<?php
// category.php
require_once __DIR__ . '/config/db.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

// Fetch active category
$category = null;
if (!empty($slug)) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ?");
    $stmt->execute([$slug]);
    $category = $stmt->fetch();
}

// Handle non-existing category
if (!$category) {
    $pageTitle = "Categories | Wave Properties";
    require_once __DIR__ . '/includes/header.php';
    
    // Fetch all categories for user selection
    try {
        $categoriesList = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
    } catch (\Exception $e) {
        $categoriesList = [];
    }
    
    echo '<section class="py-20 bg-slate-50 text-center min-h-[50vh] flex items-center">
        <div class="container max-w-xl">
            <h1 class="text-4xl font-extrabold text-slate-800 mb-4">Category Not Found</h1>
            <p class="text-slate-500 mb-8">The category you requested does not exist or has been moved. Select one of our active categories below:</p>
            <div class="list-group shadow rounded-2xl overflow-hidden">';
            foreach ($categoriesList as $cat) {
                echo '<a href="category.php?slug='.htmlspecialchars($cat['slug']).'" class="list-group-item list-group-item-action py-3 hover:bg-brand-50 border-slate-100 font-semibold text-slate-700">'.htmlspecialchars($cat['name']).'</a>';
            }
    echo '  </div>
        </div>
    </section>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = $category['name'] . " Listings";
require_once __DIR__ . '/includes/header.php';

// Fetch properties in this category
$properties = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM properties 
        WHERE category_id = ? AND status = 'active' 
        ORDER BY id DESC
    ");
    $stmt->execute([$category['id']]);
    $properties = $stmt->fetchAll();
} catch (\Exception $e) {
    // Fallback
}
?>

<!-- Header Section -->
<section class="bg-gradient-to-br from-brand-600 to-indigo-900 text-white py-16">
    <div class="container text-center animate-fade-in-up">
        <h1 class="text-3xl md:text-5xl font-black mb-3"><?php echo htmlspecialchars($category['name']); ?></h1>
        <p class="text-slate-300 text-sm md:text-base font-light max-w-xl mx-auto leading-relaxed">
            <?php echo htmlspecialchars($category['description']); ?>
        </p>
    </div>
</section>

<!-- Properties Grid -->
<section class="py-16 bg-slate-50">
    <div class="container">
        <!-- Back link -->
        <div class="mb-8">
            <a href="index.php" class="inline-flex items-center gap-2 text-slate-500 hover:text-brand-500 transition font-medium text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Back to Home
            </a>
        </div>
        
        <?php if (empty($properties)): ?>
            <div class="bg-white rounded-3xl p-12 text-center shadow border border-slate-200/50 scroll-reveal">
                <div class="w-16 h-16 bg-amber-50 text-amber-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0a2 2 0 01-2 2H6a2 2 0 01-2-2m16 0V9a2 2 0 00-2-2H6a2 2 0 00-2 2v2M4 17h16"></path></svg>
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-2">No Properties Available</h3>
                <p class="text-slate-400 text-sm max-w-md mx-auto mb-6">There are currently no active listings listed under this category. Contact us to inquire about off-market deals.</p>
                <a href="index.php#book-visit" class="px-6 py-3 bg-brand-500 hover:bg-brand-600 text-white font-bold rounded-xl transition shadow">Book consultation</a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($properties as $prop): ?>
                    <div class="col-lg-4 col-md-6 scroll-reveal">
                        <div class="bg-white rounded-3xl overflow-hidden shadow-sm hover:shadow-2xl transition duration-300 border border-slate-100 flex flex-col h-full group">
                            <!-- Image Container -->
                            <div class="relative overflow-hidden aspect-[4/3] bg-slate-100">
                                <?php if ($prop['is_kisan_kota']): ?>
                                    <span class="absolute top-4 left-4 z-10 bg-amber-500 text-slate-950 font-extrabold text-[10px] uppercase tracking-wider px-3 py-1.5 rounded-full shadow border border-amber-600">
                                        Kisan Kota 8%
                                    </span>
                                <?php endif; ?>
                                <img src="<?php echo htmlspecialchars($prop['main_image']); ?>" alt="<?php echo htmlspecialchars($prop['title']); ?>" class="w-full h-full object-cover group-hover:scale-115 transition duration-500">
                            </div>
                            
                            <!-- Card Body -->
                            <div class="p-6 flex flex-col flex-grow">
                                <span class="text-xs text-slate-400 font-medium flex items-center gap-1 mb-2">
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    <?php echo htmlspecialchars($prop['location']); ?>
                                </span>
                                
                                <h3 class="font-extrabold text-xl text-slate-800 mb-2 leading-tight group-hover:text-brand-500 transition duration-300">
                                    <?php echo htmlspecialchars($prop['title']); ?>
                                </h3>
                                
                                <p class="text-sm text-slate-500 font-light mb-4 line-clamp-2">
                                    <?php echo htmlspecialchars($prop['description']); ?>
                                </p>
                                
                                <!-- Core Specs -->
                                <div class="grid grid-cols-3 gap-2 border-t border-b border-slate-100 py-3 mb-4 text-center">
                                    <div>
                                        <div class="text-slate-400 text-[10px] uppercase tracking-wider font-semibold">Area</div>
                                        <div class="font-bold text-slate-800 text-sm"><?php echo number_format($prop['area_sqft']); ?> Sq. Ft</div>
                                    </div>
                                    <div>
                                        <div class="text-slate-400 text-[10px] uppercase tracking-wider font-semibold">Type</div>
                                        <div class="font-bold text-slate-800 text-sm"><?php echo $prop['is_kisan_kota'] ? '8% Quota' : 'Freehold'; ?></div>
                                    </div>
                                    <div>
                                        <div class="text-slate-400 text-[10px] uppercase tracking-wider font-semibold">Price Unit</div>
                                        <div class="font-bold text-slate-800 text-sm">/<?php echo htmlspecialchars($prop['price_unit']); ?></div>
                                    </div>
                                </div>
                                
                                <!-- Price & Action -->
                                <div class="mt-auto pt-2 flex items-center justify-between">
                                    <div>
                                        <span class="text-xs text-slate-400 block uppercase tracking-wider font-semibold">Starting Price</span>
                                        <span class="text-2xl font-black text-brand-500">₹<?php echo number_format($prop['price']); ?></span>
                                    </div>
                                    
                                    <a href="property.php?slug=<?php echo htmlspecialchars($prop['slug']); ?>" class="px-4 py-2.5 bg-slate-100 hover:bg-brand-gold hover:text-slate-950 text-slate-700 font-bold text-xs rounded-xl transition duration-300">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
