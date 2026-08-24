<?php
// includes/footer.php
require_once __DIR__ . '/../config/db.php';

$footerCategories = [];
try {
    $stmt = $pdo->query("SELECT name, slug FROM categories ORDER BY name ASC LIMIT 5");
    $footerCategories = $stmt->fetchAll();
} catch (\Exception $e) {
    $footerCategories = [
        ['name' => 'Residential Plots', 'slug' => 'residential-plots'],
        ['name' => 'Kisan Kota Plots', 'slug' => 'kisan-kota-plots']
    ];
}
?>
</main>

<!-- Footer -->
<footer class="bg-slate-900 text-slate-400 py-12 border-t border-slate-800">
    <div class="container">
        <div class="row g-4">
            <!-- Branding & Tagline -->
            <div class="col-lg-4 col-md-6">
                <div class="mb-4">
                    <img src="assets/images/logo.png" alt="Prime Hashtag Properties Logo" style="height:48px;width:auto;object-fit:contain;filter:brightness(0) invert(1);">
                </div>
                <p class="text-sm leading-relaxed mb-4">
                    Providing premier freehold residential, commercial, and 8% Kisan Quota allotment plots in Wave City,
                    Ghaziabad. Secure your investment in future-ready developments.
                </p>
                <div class="text-xs text-slate-500">
                    &copy; <?php echo date('Y'); ?> Prime Hashtag Properties. All rights reserved.
                </div>
            </div>

            <!-- Categories Quick Links -->
            <div class="col-lg-3 col-md-6">
                <h5 class="text-white font-semibold text-sm mb-4 uppercase tracking-wider">Quick Categories</h5>
                <ul class="list-unstyled space-y-2 text-sm">
                    <?php foreach ($footerCategories as $cat): ?>
                        <li>
                            <!-- <a href="category.php?slug=<?php echo htmlspecialchars($cat['slug']); ?>" class="hover:text-brand-gold transition duration-200"> -->
                            <?php echo htmlspecialchars($cat['name']); ?>
                            <!-- </a> -->
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Contact & Visit Info -->
            <div class="col-lg-3 col-md-6">
                <h5 class="text-white font-semibold text-sm mb-4 uppercase tracking-wider">Contact & Site Office</h5>
                <p class="text-sm mb-2 leading-relaxed">
                    <strong>Wave City NH-24</strong><br>
                    India
                </p>
                <p class="text-sm">
                    <strong>Phone:</strong> +91 12345 67890<br>
                    <strong>Email:</strong> sales@primehashtag.com
                </p>
            </div>

            <!-- Marketing Meta Info -->
            <div class="col-lg-2 col-md-6">
                <h5 class="text-white font-semibold text-sm mb-4 uppercase tracking-wider">Useful Information</h5>
                <ul class="list-unstyled space-y-2 text-sm">
                    <li>
                        <span
                            class="inline-flex items-center gap-1.5 text-xs bg-emerald-500/10 text-emerald-400 px-2 py-0.5 rounded border border-emerald-500/20">
                            100% Freehold
                        </span>
                    </li>
                    <li>
                        <span
                            class="inline-flex items-center gap-1.5 text-xs bg-amber-500/10 text-amber-400 px-2 py-0.5 rounded border border-amber-500/20">
                            Kisan Allotments
                        </span>
                    </li>
                    <li>
                        <span
                            class="inline-flex items-center gap-1.5 text-xs bg-indigo-500/10 text-indigo-400 px-2 py-0.5 rounded border border-indigo-500/20">
                            Gated Gird
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- CRM link bottom shortcut -->
        <div class="mt-8 pt-8 border-t border-slate-800/60 text-center text-xs text-slate-600">
            Are you an agent? <a href="crm/login.php"
                class="text-blue-400 hover:text-blue-300 underline transition">Login to CRM Dashboard</a>
        </div>
    </div>
</footer>

<!-- Bootstrap Bundle with Popper JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Micro-animations & scroll reveals handler -->
<script>
    document.addEventListener("DOMContentLoaded", function () {
        // Simple intersection observer for reveals
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fade-in-up');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.scroll-reveal').forEach(el => {
            el.style.opacity = 0;
            observer.observe(el);
        });
    });
</script>
</body>

</html>