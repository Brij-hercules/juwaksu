<?php
// crm/includes/header.php
require_once __DIR__ . '/../../includes/auth_helper.php';

// Route protection
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$currentUser = get_current_user_details();
if (!$currentUser) {
    // If session user was deleted or inactive
    header('Location: logout.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . " | CRM Dashboard" : "CRM Panel | Prime Properties"; ?></title>
    
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
                            50:  '#eff6ff',
                            100: '#dbeafe',
                            500: '#2563eb',
                            600: '#1d4ed8',
                            700: '#1e40af',
                            gold: '#2563eb',
                            goldDark: '#1d4ed8',
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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .sidebar-active {
            background-color: rgba(37, 99, 235, 0.15);
            color: #93c5fd !important;
            border-left: 4px solid #2563eb;
        }
        /* Custom scrollbar for dashboards */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
    </style>
</head>
<body class="h-full flex overflow-hidden font-sans bg-slate-50">
    <!-- Main Dashboard Container -->
    <div class="flex flex-1 w-full overflow-hidden">
        
        <!-- Sidebar Navigation Panel Included Here -->
        <?php require_once __DIR__ . '/sidebar.php'; ?>
        
        <!-- Right Content Body Wrapper -->
        <div class="flex-grow flex flex-col min-w-0 overflow-y-auto bg-slate-50">
            
            <!-- Top Admin Header -->
            <header class="bg-white border-b border-slate-200 py-3.5 px-6 flex items-center justify-between sticky top-0 z-40 shadow-sm">
                <!-- Mobile Navigation Menu Toggle Button -->
                <div class="flex items-center gap-3">
                    <button class="md:hidden p-2 text-slate-500 hover:bg-slate-100 rounded-lg focus:outline-none" type="button" onclick="document.getElementById('crm-sidebar').classList.toggle('-translate-x-full')">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    <!-- Logo in top bar for mobile -->
                    <a href="../index.php" class="md:hidden">
                        <img src="../assets/images/logo.png" alt="Logo" style="height:32px;width:auto;object-fit:contain;">
                    </a>
                    <h2 class="text-lg font-black text-slate-800 tracking-tight flex items-center gap-2">
                        <?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : "CRM Dashboard"; ?>
                    </h2>
                </div>
                
                <!-- Quick User Profile Action Info -->
                <div class="flex items-center gap-4">
                    <div class="text-right hidden sm:block">
                        <div class="text-sm font-bold text-slate-800 leading-none mb-0.5"><?php echo htmlspecialchars($currentUser['username']); ?></div>
                        <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider"><?php echo htmlspecialchars($currentUser['role_name']); ?></div>
                    </div>
                    
                    <!-- Avatar / Initial logo -->
                    <div class="w-10 h-10 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-sm border border-blue-400 shadow">
                        <?php echo strtoupper(substr($currentUser['username'], 0, 1)); ?>
                    </div>
                </div>
            </header>
            
            <!-- Page Contents Begin -->
            <div class="p-6 max-w-[1600px] w-full mx-auto">
