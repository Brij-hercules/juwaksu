<?php
// includes/header.php
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fetch categories dynamically for the header menu
$headerCategories = [];
try {
    $stmt = $pdo->query("SELECT name, slug FROM categories ORDER BY name ASC");
    $headerCategories = $stmt->fetchAll();
} catch (\Exception $e) {
    // Fallback if db is not setup yet
    $headerCategories = [
        ['name' => 'Residential Plots', 'slug' => 'residential-plots'],
        ['name' => 'Kisan Kota Plots', 'slug' => 'kisan-kota-plots']
    ];
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo isset($pageTitle) ? $pageTitle . " | Prime Hashtag Properties" : "Prime Hashtag Properties | Premium Plots & Kisan Kota"; ?>
    </title>

    <!-- Meta tags for SEO -->
    <meta name="description"
        content="Explore construction-ready freehold residential plots, premium 8% Kisan Quota plots, and luxurious villas with Prime Hashtag. Book site visits in Wave City Ghaziabad.">
    <meta name="keywords"
        content="Wave City plots, Kisan Kota, Ghaziabad plots, Residential plots, 8% Quota plots, Real estate Ghaziabad">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Tailwind CSS (Play CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f5f7ff',
                            100: '#ebf0ff',
                            500: '#1a365d', // primary blue
                            600: '#102a43', // darker blue
                            gold: '#d4af37', // metallic gold
                            goldDark: '#aa7c11',
                        }
                    },
                    fontFamily: {
                        sans: ['Outfit', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Custom CSS/Animations -->
    <style>
        /* Smooth fade-in and scroll reveals */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.8s ease forwards;
        }

        .delay-100 {
            animation-delay: 0.1s;
        }

        .delay-200 {
            animation-delay: 0.2s;
        }

        .delay-300 {
            animation-delay: 0.3s;
        }

        /* Glassmorphism utilities */
        .glass-navbar {
            background: rgba(26, 54, 93, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        /* Premium hover transitions */
        .hover-gold:hover {
            color: #d4af37 !important;
            transition: all 0.3s ease;
        }
    </style>
</head>

<body class="bg-slate-50 text-slate-800 font-sans min-h-screen flex flex-col">

    <!-- Header / Navbar -->
    <header class="sticky top-0 z-50 glass-navbar shadow-lg border-b border-blue-900/50">
        <nav class="navbar navbar-expand-lg navbar-dark py-3">
            <div class="container">
                <!-- Branding -->
                <a class="navbar-brand flex items-center gap-2 font-extrabold text-2xl tracking-tight text-white"
                    href="index.php">
                    <span class="text-brand-gold">HASHTAG </span>PROPERTIES
                </a>

                <!-- Toggle Button for mobile -->
                <button class="navbar-toggler border-0 focus:outline-none focus:ring-2 focus:ring-brand-gold"
                    type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar"
                    aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <!-- Navbar Links -->
                <div class="collapse navbar-collapse" id="mainNavbar">
                    <ul
                        class="navbar-nav ms-auto mb-2 mb-lg-0 items-center gap-2 lg:gap-4 mt-3 lg:mt-0 text-sm font-medium">
                        <li class="nav-item">
                            <a class="nav-link text-slate-200 hover-gold <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active text-brand-gold' : ''; ?>"
                                href="index.php">Home</a>
                        </li>

                        <!-- Categories Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle text-slate-200 hover-gold" href="#" id="navbarDropdown"
                                role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Property Categories
                            </a>
                            <ul class="dropdown-menu dropdown-menu-dark bg-slate-900 border-slate-800 rounded-xl shadow-xl p-2 animate-fade-in-up"
                                aria-labelledby="navbarDropdown">
                                <?php foreach ($headerCategories as $cat): ?>
                                    <li>
                                        <a class="dropdown-item rounded-lg py-2 hover:bg-slate-800 hover:text-brand-gold text-slate-300"
                                            href="category.php?slug=<?php echo htmlspecialchars($cat['slug']); ?>">
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>

                        <!-- Static Categories Directly visible for quick access -->
                        <li class="nav-item">
                            <a class="nav-link text-slate-200 hover-gold" href="category.php?slug=kisan-kota-plots">
                                <span
                                    class="bg-amber-500/10 text-brand-gold px-2.5 py-1 rounded-full border border-amber-500/30 text-xs font-semibold uppercase tracking-wider">Kisan
                                    Kota 8%</span>
                            </a>
                        </li>

                        <!-- CRM panel link -->
                        <li class="nav-item lg:ms-4">
                            <a class="px-5 py-2.5 bg-brand-gold hover:bg-brand-goldDark text-slate-950 font-bold rounded-full transition duration-300 shadow-lg flex items-center gap-2"
                                href="crm/login.php">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin='round' stroke-width='2.5'
                                        d='M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1'>
                                    </path>
                                </svg>
                                CRM Panel
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content Area -->
    <main class="flex-grow">