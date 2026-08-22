<?php
// property.php
require_once __DIR__ . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

$property = null;
if (!empty($slug)) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name 
        FROM properties p 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.slug = ? AND p.status = 'active'
    ");
    $stmt->execute([$slug]);
    $property = $stmt->fetch();
}

// Redirect or show 404 if property not found
if (!$property) {
    $pageTitle = "Property Not Found";
    require_once __DIR__ . '/includes/header.php';
    echo '<section class="py-20 bg-slate-50 text-center min-h-[50vh] flex items-center">
        <div class="container max-w-xl">
            <h1 class="text-4xl font-extrabold text-slate-800 mb-4">Property Not Found</h1>
            <p class="text-slate-500 mb-8">The listing you requested is inactive, sold, or doesn\'t exist.</p>
            <a href="index.php" class="px-6 py-3 bg-brand-500 hover:bg-brand-600 text-white font-bold rounded-xl transition shadow">Return to Home</a>
        </div>
    </section>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Fetch property gallery images
$gallery = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM property_gallery WHERE property_id = ? ORDER BY id ASC");
    $stmt->execute([$property['id']]);
    $gallery = $stmt->fetchAll();
} catch (\Exception $e) {
    // Fallback
}

$pageTitle = $property['title'];
require_once __DIR__ . '/includes/header.php';
?>

<section class="py-12 bg-slate-50">
    <div class="container">
        <!-- Back Link & Breadcrumb -->
        <div class="mb-6">
            <a href="index.php" class="inline-flex items-center gap-2 text-slate-500 hover:text-brand-500 transition font-medium text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Back to Listings
            </a>
        </div>
        
        <div class="row g-5">
            <!-- Left Column: Gallery & Details -->
            <div class="col-lg-8 animate-fade-in-up">
                
                <!-- Title & Tags -->
                <div class="mb-6">
                    <div class="flex flex-wrap gap-2 mb-3">
                        <span class="bg-indigo-50 text-indigo-700 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider">
                            <?php echo htmlspecialchars($property['category_name']); ?>
                        </span>
                        <?php if ($property['is_kisan_kota']): ?>
                            <span class="bg-amber-100 text-amber-700 px-3 py-1 rounded-full text-xs font-extrabold uppercase tracking-wider border border-amber-300">
                                8% Kisan Quota Allotment
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <h1 class="text-3xl md:text-4xl font-black text-slate-900 leading-tight mb-2"><?php echo htmlspecialchars($property['title']); ?></h1>
                    
                    <p class="text-slate-400 text-sm flex items-center gap-1.5 font-light">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        <?php echo htmlspecialchars($property['location']); ?>
                    </p>
                </div>
                
                <!-- Main Image Slider / Carousel -->
                <div class="bg-white p-2 rounded-3xl shadow-sm border border-slate-200/50 mb-8 overflow-hidden">
                    <div id="propertyCarousel" class="carousel slide" data-bs-ride="carousel">
                        <div class="carousel-inner rounded-2xl overflow-hidden aspect-[16/9]">
                            <!-- Main Image Slide -->
                            <div class="carousel-item active h-full">
                                <img src="<?php echo htmlspecialchars($property['main_image']); ?>" class="w-full h-full object-cover" alt="Main View">
                            </div>
                            
                            <!-- Gallery Slides -->
                            <?php foreach ($gallery as $index => $img): ?>
                                <div class="carousel-item h-full">
                                    <img src="<?php echo htmlspecialchars($img['image_path']); ?>" class="w-full h-full object-cover" alt="Gallery View <?php echo $index + 1; ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Show Controls only if there are multiple images -->
                        <?php if (!empty($gallery)): ?>
                            <button class="carousel-control-prev" type="button" data-bs-target="#propertyCarousel" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon p-3 bg-slate-900/60 rounded-full" aria-hidden="true"></span>
                                <span class="visually-hidden">Previous</span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#propertyCarousel" data-bs-slide="next">
                                <span class="carousel-control-next-icon p-3 bg-slate-900/60 rounded-full" aria-hidden="true"></span>
                                <span class="visually-hidden">Next</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Description -->
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-200/50 mb-8">
                    <h3 class="text-xl font-bold text-slate-800 mb-4">Property Description</h3>
                    <p class="text-slate-600 font-light leading-relaxed whitespace-pre-line">
                        <?php echo htmlspecialchars($property['description']); ?>
                    </p>
                </div>
                
                <!-- Marketing Features Grid based on Kisan Kota Details -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
                    <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200/50 text-center">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block mb-1">Pricing Scheme</span>
                        <span class="font-extrabold text-slate-800 text-sm">Flexible Payment</span>
                    </div>
                    <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200/50 text-center">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block mb-1">Possession</span>
                        <span class="font-extrabold text-slate-800 text-sm">Handover Ready</span>
                    </div>
                    <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200/50 text-center">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block mb-1">Road Width</span>
                        <span class="font-extrabold text-slate-800 text-sm">Wide Internal Lanes</span>
                    </div>
                    <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200/50 text-center">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block mb-1">Legality</span>
                        <span class="font-extrabold text-slate-800 text-sm">100% Verified</span>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Inquiry Sticky Sidebar -->
            <div class="col-lg-4 animate-fade-in-up delay-100">
                <div class="sticky top-24 space-y-6">
                    
                    <!-- Price Card -->
                    <div class="bg-slate-900 text-white p-6 md:p-8 rounded-3xl shadow-xl relative overflow-hidden">
                        <div class="absolute -right-16 -bottom-16 w-36 h-36 bg-amber-500/10 rounded-full blur-2xl"></div>
                        
                        <span class="text-slate-400 text-xs uppercase tracking-wider font-semibold block mb-1">Asking Price</span>
                        
                        <div class="flex items-baseline gap-1.5 mb-4">
                            <span class="text-4xl font-black text-brand-gold">₹<?php echo number_format($property['price']); ?></span>
                            <span class="text-slate-400 text-sm">/ <?php echo htmlspecialchars($property['price_unit']); ?></span>
                        </div>
                        
                        <div class="space-y-3.5 text-xs text-slate-300 border-t border-slate-800 pt-4">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Plot Chunk Area:</span>
                                <span class="font-bold text-white"><?php echo number_format($property['area_sqft']); ?> Sq. Ft</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Property Status:</span>
                                <span class="font-bold text-emerald-400 uppercase tracking-wider"><?php echo htmlspecialchars($property['status']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Location Area:</span>
                                <span class="font-bold text-white">Wave City, NH-24</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Direct Quote Inquiry Form -->
                    <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-slate-200/50">
                        <h4 class="text-lg font-black text-slate-800 mb-1">Request Quote / Detail</h4>
                        <p class="text-slate-400 text-xs mb-6 font-light leading-relaxed">Fill out the brief contact details below. Our field expert will contact you within 15 minutes.</p>
                        
                        <!-- Display session messages if any -->
                        <?php if (isset($_SESSION['inquiry_success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show border-0 rounded-xl bg-emerald-50 text-emerald-700 shadow-sm text-xs p-3 mb-4" role="alert">
                                <?php echo $_SESSION['inquiry_success']; unset($_SESSION['inquiry_success']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form action="submit_inquiry.php" method="POST" class="space-y-4">
                            <input type="hidden" name="property_id" value="<?php echo $property['id']; ?>">
                            <input type="hidden" name="source" value="website">
                            
                            <div>
                                <label for="name" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Full Name</label>
                                <input type="text" name="name" id="name" required class="w-full px-3 py-2.5 rounded-lg bg-slate-50 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-brand-gold focus:bg-white transition text-sm text-slate-800" placeholder="e.g. Amit Patel">
                            </div>
                            
                            <div>
                                <label for="email" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Email Address</label>
                                <input type="email" name="email" id="email" required class="w-full px-3 py-2.5 rounded-lg bg-slate-50 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-brand-gold focus:bg-white transition text-sm text-slate-800" placeholder="e.g. amit@example.com">
                            </div>
                            
                            <div>
                                <label for="phone" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Phone Number</label>
                                <input type="tel" name="phone" id="phone" required class="w-full px-3 py-2.5 rounded-lg bg-slate-50 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-brand-gold focus:bg-white transition text-sm text-slate-800" placeholder="e.g. +91 99887 76655">
                            </div>
                            
                            <div>
                                <label for="message" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Message / Inquiry</label>
                                <textarea name="message" id="message" rows="3" required class="w-full px-3 py-2.5 rounded-lg bg-slate-50 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-brand-gold focus:bg-white transition text-sm text-slate-800" placeholder="I am interested in this listing. Please share floor plans or coordinates."></textarea>
                            </div>
                            
                            <button type="submit" class="w-full py-3 bg-brand-500 hover:bg-brand-600 text-white font-bold rounded-xl transition duration-300 shadow">
                                Request Quote Now
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
