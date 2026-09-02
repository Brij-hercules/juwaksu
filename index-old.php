<?php
// index.php
$pageTitle = "Premium Plots & Kisan Kota Allotments in Wave City";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/db.php';

// Fetch featured properties
$featuredProperties = [];
try {
    $stmt = $pdo->query("
        SELECT p.*, c.name as category_name 
        FROM properties p 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.status = 'active' AND p.featured = 1 
        ORDER BY p.id DESC
    ");
    $featuredProperties = $stmt->fetchAll();
} catch (\Exception $e) {
    // Graceful fallback empty array
}
?>

<!-- Hero Banner Section -->
<section
    class="relative bg-white text-slate-800 py-24 md:py-32 overflow-hidden border-b border-slate-100">
    <!-- Overlay/Background elements -->
    <div
        class="absolute inset-0 opacity-40 bg-[radial-gradient(#2563eb_1px,transparent_1px)] [background-size:16px_16px]">
    </div>

    <div class="container relative z-10">
        <div class="row align-items-center g-5">
            <!-- Left Column: Copy -->
            <div class="col-lg-7 text-left lg:text-left animate-fade-in-up">
                <span
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-blue-50 text-blue-600 border border-blue-200 text-xs font-bold uppercase tracking-widest mb-4">
                    <svg class="w-3 h-3 text-blue-600 animate-pulse" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z"
                            clip-rule="evenodd"></path>
                    </svg>
                    Adjacent to Wave City, Ghaziabad
                </span>

                <h1 class="text-4xl md:text-6xl font-black tracking-tight leading-none mb-4 text-slate-900">
                    Own Your Land. <br>
                    <span class="text-blue-600">Build Your Future.</span>
                </h1>

                <p class="text-slate-600 text-lg md:text-xl font-light mb-8 leading-relaxed mx-auto lg:mx-0">
                    Premium freehold residential plots and limited <strong class="text-slate-800 font-semibold">8% Kisaan
                        Quota</strong> allotments in Wave City's developed phase. Possession handover,
                    construction-ready.
                </p>

                <div class="flex flex-wrap gap-4 justify-center lg:justify-start">
                    <a href="#featured-listings"
                        class="px-8 py-3.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition duration-300 shadow-xl transform hover:-translate-y-1">
                        Explore Plots
                    </a>
                    <a href="#book-visit"
                        class="px-8 py-3.5 bg-white hover:bg-slate-50 text-slate-700 font-semibold rounded-xl transition duration-300 border border-slate-200 shadow-sm">
                        Book Free Site Visit
                    </a>
                </div>
            </div>

            <!-- Right Column: Visual highlights card -->
            <div class="col-lg-5 animate-fade-in-up delay-200">
                <div
                    class="bg-white border border-slate-200 p-6 md:p-8 rounded-3xl shadow-xl relative">
                    <div
                        class="absolute -top-4 -right-4 bg-rose-600 text-white font-bold text-xs uppercase tracking-widest px-4 py-2 rounded-xl rotate-12 shadow-lg">
                        Best Rates!
                    </div>

                    <h3 class="text-2xl font-extrabold text-slate-900 mb-4">Wave City Extension</h3>

                    <div class="space-y-4">
                        <div class="flex items-baseline gap-2 pb-3 border-b border-slate-100">
                            <span class="text-slate-500 text-sm">Starting Price:</span>
                            <span class="text-3xl font-black text-blue-600">₹32,990</span>
                            <span class="text-slate-500 text-xs">/ Sq. Yard</span>
                        </div>

                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                                <div class="text-slate-500 text-xs">Quota</div>
                                <div class="font-semibold text-slate-800">8% Kisaan Plots</div>
                            </div>
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                                <div class="text-slate-500 text-xs">Status</div>
                                <div class="font-semibold text-emerald-600">Ready to Construct</div>
                            </div>
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                                <div class="text-slate-500 text-xs">Ownership</div>
                                <div class="font-semibold text-slate-800">100% Freehold</div>
                            </div>
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                                <div class="text-slate-500 text-xs">Development</div>
                                <div class="font-semibold text-slate-800">Gated & Wide Roads</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- [DEMO HIDE START] Stats Bar -->
<?php /* DEMO HIDE
<!-- Stats Bar / Key Highlights -->
<section class="bg-white py-8 border-b border-slate-100 shadow-sm relative z-20">
<div class="container">
<div class="row text-center g-4">
<div class="col-md-3 col-6">
<h4 class="text-3xl font-extrabold text-brand-500 mb-1">100%</h4>
<p class="text-slate-500 text-sm">Freehold Ownership</p>
</div>
<div class="col-md-3 col-6">
<h4 class="text-3xl font-extrabold text-brand-500 mb-1">8%</h4>
<p class="text-slate-500 text-sm">Kisaan Quota Allotment</p>
</div>
<div class="col-md-3 col-6">
<h4 class="text-3xl font-extrabold text-brand-500 mb-1">Wide</h4>
<p class="text-slate-500 text-sm">Paved Connected Roads</p>
</div>
<div class="col-md-3 col-6">
<h4 class="text-3xl font-extrabold text-brand-500 mb-1">NH-24</h4>
<p class="text-slate-500 text-sm">Prime Ghaziabad Locality</p>
</div>
</div>
</div>
</section>
DEMO HIDE */ ?>
<!-- [DEMO HIDE END] Stats Bar -->

<!-- [DEMO HIDE START] Category Grid -->
<?php /* DEMO HIDE
<!-- Category Selection Grid -->
<section class="py-16 bg-slate-50">
<div class="container">
<div class="text-center max-w-2xl mx-auto mb-12 scroll-reveal">
<h2 class="text-3xl font-black text-slate-800 mb-2">Browse Property Categories</h2>
<p class="text-slate-500">Filter properties dynamically and view available plot chunks or files.</p>
</div>

<div class="row g-4 justify-content-center">
<!-- Category Card 1 -->
<div class="col-lg-3 col-md-6 scroll-reveal">
<a href="category.php?slug=residential-plots" class="group block bg-white p-6 rounded-2xl shadow-sm hover:shadow-xl transition duration-300 border border-slate-200/50 hover:border-brand-gold relative overflow-hidden">
<div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center mb-4 group-hover:bg-brand-500 group-hover:text-white transition duration-300">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
</div>
<h3 class="font-extrabold text-lg text-slate-800 mb-1">Residential Plots</h3>
<p class="text-xs text-slate-400">Regular plots ready to build premium homes.</p>
</a>
</div>
<!-- Category Card 2 -->
<div class="col-lg-3 col-md-6 scroll-reveal delay-100">
<a href="category.php?slug=kisan-kota-plots" class="group block bg-white p-6 rounded-2xl shadow-sm hover:shadow-xl transition duration-300 border border-slate-200/50 hover:border-brand-gold relative overflow-hidden">
<div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center mb-4 group-hover:bg-brand-500 group-hover:text-white transition duration-300">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
</div>
<h3 class="font-extrabold text-lg text-slate-800 mb-1">Kisan Kota 8% Plots</h3>
<p class="text-xs text-slate-400">High appreciation original allottee quota land.</p>
</a>
</div>
<!-- Category Card 3 -->
<div class="col-lg-3 col-md-6 scroll-reveal delay-200">
<a href="category.php?slug=villas" class="group block bg-white p-6 rounded-2xl shadow-sm hover:shadow-xl transition duration-300 border border-slate-200/50 hover:border-brand-gold relative overflow-hidden">
<div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center mb-4 group-hover:bg-brand-500 group-hover:text-white transition duration-300">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
</div>
<h3 class="font-extrabold text-lg text-slate-800 mb-1">Luxury Villas</h3>
<p class="text-xs text-slate-400">Premium independent villas & duplexes.</p>
</a>
</div>
<!-- Category Card 4 -->
<div class="col-lg-3 col-md-6 scroll-reveal delay-300">
<a href="category.php?slug=commercial" class="group block bg-white p-6 rounded-2xl shadow-sm hover:shadow-xl transition duration-300 border border-slate-200/50 hover:border-brand-gold relative overflow-hidden">
<div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center mb-4 group-hover:bg-brand-500 group-hover:text-white transition duration-300">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
</div>
<h3 class="font-extrabold text-lg text-slate-800 mb-1">Commercial</h3>
<p class="text-xs text-slate-400">High ROI shops and business plots.</p>
</a>
</div>
</div>
</div>
</section>
DEMO HIDE */ ?>
<!-- [DEMO HIDE END] Category Grid -->

<!-- Featured Listings Section -->
<section id="featured-listings" class="py-20 bg-white" style="display:none;">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end mb-12 scroll-reveal">
            <div>
                <span class="text-brand-gold font-bold uppercase tracking-wider text-xs block mb-1">Highly
                    Recommended</span>
                <h2 class="text-3xl font-black text-slate-800">Featured Properties</h2>
            </div>
            <a href="category.php?slug=residential-plots"
                class="text-brand-500 font-bold hover:text-brand-gold transition duration-200 flex items-center gap-1">
                View All Plots
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3">
                    </path>
                </svg>
            </a>
        </div>

        <div class="row g-4">
            <?php if (empty($featuredProperties)): ?>
                <div class="col-12 text-center py-12">
                    <p class="text-slate-500">No properties featured at the moment. Check back soon!</p>
                </div>
            <?php else: ?>
                <?php foreach ($featuredProperties as $prop): ?>
                    <div class="col-lg-4 col-md-6 scroll-reveal">
                        <div
                            class="bg-white rounded-3xl overflow-hidden shadow-md hover:shadow-2xl transition duration-300 border border-slate-100 flex flex-col h-full group">
                            <!-- Image container with badges -->
                            <div class="relative overflow-hidden aspect-[4/3] bg-slate-100">
                                <?php if ($prop['is_kisan_kota']): ?>
                                    <span
                                        class="absolute top-4 left-4 z-10 bg-amber-500 text-slate-950 font-extrabold text-[10px] uppercase tracking-wider px-3 py-1.5 rounded-full shadow border border-amber-600">
                                        Kisan Kota 8%
                                    </span>
                                <?php endif; ?>

                                <span
                                    class="absolute top-4 right-4 z-10 bg-brand-600/90 backdrop-blur-sm text-white font-semibold text-[10px] uppercase tracking-wider px-3 py-1.5 rounded-full shadow">
                                    <?php echo htmlspecialchars($prop['category_name']); ?>
                                </span>

                                <img src="<?php echo htmlspecialchars($prop['main_image']); ?>"
                                    alt="<?php echo htmlspecialchars($prop['title']); ?>"
                                    class="w-full h-full object-cover group-hover:scale-115 transition duration-500">
                            </div>

                            <!-- Card Body -->
                            <div class="p-6 flex flex-col flex-grow">
                                <span class="text-xs text-slate-400 font-medium flex items-center gap-1 mb-2">
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                        </path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    <?php echo htmlspecialchars($prop['location']); ?>
                                </span>

                                <h3
                                    class="font-extrabold text-xl text-slate-800 mb-2 leading-tight group-hover:text-brand-500 transition duration-300">
                                    <?php echo htmlspecialchars($prop['title']); ?>
                                </h3>

                                <p class="text-sm text-slate-500 font-light mb-4 line-clamp-2">
                                    <?php echo htmlspecialchars($prop['description']); ?>
                                </p>

                                <!-- Core specs -->
                                <div class="grid grid-cols-3 gap-2 border-t border-b border-slate-100 py-3 mb-4 text-center">
                                    <div>
                                        <div class="text-slate-400 text-[10px] uppercase tracking-wider font-semibold">Area
                                        </div>
                                        <div class="font-bold text-slate-800 text-sm">
                                            <?php echo number_format($prop['area_sqft']); ?> Sq. Ft
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-slate-400 text-[10px] uppercase tracking-wider font-semibold">Type
                                        </div>
                                        <div class="font-bold text-slate-800 text-sm">
                                            <?php echo $prop['is_kisan_kota'] ? '8% Quota' : 'Freehold'; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-slate-400 text-[10px] uppercase tracking-wider font-semibold">Price
                                            Unit</div>
                                        <div class="font-bold text-slate-800 text-sm">
                                            /<?php echo htmlspecialchars($prop['price_unit']); ?></div>
                                    </div>
                                </div>

                                <!-- Price & Action -->
                                <div class="mt-auto pt-2 flex items-center justify-between">
                                    <div>
                                        <span
                                            class="text-xs text-slate-400 block uppercase tracking-wider font-semibold">Starting
                                            Price</span>
                                        <span
                                            class="text-2xl font-black text-brand-500">₹<?php echo number_format($prop['price']); ?></span>
                                    </div>

                                    <a href="property.php?slug=<?php echo htmlspecialchars($prop['slug']); ?>"
                                        class="px-4 py-2.5 bg-slate-100 hover:bg-brand-gold hover:text-slate-950 text-slate-700 font-bold text-xs rounded-xl transition duration-300">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- [DEMO HIDE START] Booking Form -->
<?php /* DEMO HIDE
<!-- Booking & Inquiry Form Section -->
<section id="book-visit" class="py-20 bg-slate-100 border-t border-slate-200/50">
<div class="container">
<div class="row justify-content-center">
<div class="col-xl-8 col-lg-10">
<div class="bg-white rounded-3xl shadow-xl overflow-hidden border border-slate-200/50 scroll-reveal">
<div class="row g-0">
    <!-- Branding Info Panel -->
    <div class="col-md-5 bg-gradient-to-br from-brand-600 to-indigo-900 text-white p-8 md:p-12 flex flex-col justify-between">
        <div>
            <span class="text-brand-gold font-bold text-xs uppercase tracking-widest block mb-1">Book Today</span>
            <h3 class="text-2xl font-black mb-4">Request a Quote & Site Visit</h3>
            <p class="text-slate-300 text-sm font-light leading-relaxed">
                Schedule a direct site visit to Wave City NH-24. See demarcated plots, Kisan Quota layout, and meet our senior sales consultants.
            </p>
        </div>

        <div class="space-y-3 text-xs text-slate-300 mt-8">
            <div class="flex items-center gap-3">
                <svg class="w-4 h-4 text-brand-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span>Immediate allotment support</span>
            </div>
            <div class="flex items-center gap-3">
                <svg class="w-4 h-4 text-brand-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span>Free site transport assistance</span>
            </div>
        </div>
    </div>

    <!-- Form Panel -->
    <div class="col-md-7 p-8 md:p-12">
        <h4 class="text-xl font-bold text-slate-800 mb-6">Enter Details</h4>

        <!-- Display session messages if any -->
        <?php if (isset($_SESSION['inquiry_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 rounded-xl bg-emerald-50 text-emerald-700 shadow-sm text-sm" role="alert">
                <strong>Success!</strong> <?php echo $_SESSION['inquiry_success']; unset($_SESSION['inquiry_success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form action="submit_inquiry.php" method="POST" class="space-y-4">
            <input type="hidden" name="source" value="website">

            <div>
                <label for="name" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Full Name</label>
                <input type="text" name="name" id="name" required class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-brand-gold focus:bg-white transition text-sm text-slate-800" placeholder="e.g. Rahul Sharma">
            </div>

            <div class="row g-3">
                <div class="col-sm-6">
                    <label for="email" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Email Address</label>
                    <input type="email" name="email" id="email" required class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-brand-gold focus:bg-white transition text-sm text-slate-800" placeholder="e.g. name@domain.com">
                </div>
                <div class="col-sm-6">
                    <label for="phone" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Contact Number</label>
                    <input type="tel" name="phone" id="phone" required class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-brand-gold focus:bg-white transition text-sm text-slate-800" placeholder="e.g. +91 99887 76655">
                </div>
            </div>

            <div>
                <label for="message" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Your Inquiry</label>
                <textarea name="message" id="message" rows="4" required class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-brand-gold focus:bg-white transition text-sm text-slate-800" placeholder="Let us know your requirements, preferred plot sizes or allotment queries."></textarea>
            </div>

            <button type="submit" class="w-full py-3.5 bg-brand-500 hover:bg-brand-600 text-white font-bold rounded-xl transition duration-300 shadow-md">
                Submit Inquiry Request
            </button>
        </form>
    </div>
</div>
</div>
</div>
</div>
</div>
</section>
DEMO HIDE */ ?>
<!-- [DEMO HIDE END] Booking Form -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>