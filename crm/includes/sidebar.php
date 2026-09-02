<?php
// crm/includes/sidebar.php
require_once __DIR__ . '/../../includes/auth_helper.php';

$activePage = basename($_SERVER['PHP_SELF']);
?>
<!-- CRM Sidebar Navigation Panel -->
<aside id="crm-sidebar"
    class="w-64 bg-white text-slate-600 flex flex-col h-full border-r border-slate-200 absolute md:relative z-50 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out">
    <!-- Sidebar Header Logo -->
    <div class="h-16 flex items-center justify-between px-4 border-b border-slate-200 bg-slate-50">
        <a class="flex items-center" href="../index.php" target="_blank">
            <img src="../assets/images/logo.png" alt="Logo" style="height:36px;width:auto;object-fit:contain;">
        </a>
        <button class="md:hidden p-1 rounded hover:bg-slate-800 text-slate-400 focus:outline-none"
            onclick="document.getElementById('crm-sidebar').classList.add('-translate-x-full')">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>

    <!-- Sidebar Navigation Links -->
    <nav class="flex-1 px-4 py-6 space-y-1.5 overflow-y-auto">

        <?php $isSalesEmployee = ($currentUser['role_name'] === 'Sales Employee'); ?>

        <?php if ($isSalesEmployee): ?>
            <!-- SALES EMPLOYEE MENU -->
            <a href="index.php"
                class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-100 hover:text-blue-700 transition text-sm font-semibold <?php echo $activePage == 'index.php' ? 'sidebar-active' : 'text-slate-600'; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                    </path>
                </svg>
                Leads
            </a>

        <?php else: ?>
            <!-- ADMIN MENU -->
            <!-- Dashboard Link -->
            <a href="index.php"
                class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-100 hover:text-blue-700 transition text-sm font-semibold <?php echo $activePage == 'index.php' ? 'sidebar-active' : 'text-slate-600'; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z">
                    </path>
                </svg>
                Dashboard
            </a>
            <!-- New Links -->
            <a href="allotment-types.php" style="display: none;"
                class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-100 hover:text-blue-700 transition text-sm font-semibold <?php echo $activePage == 'allotment-types.php' ? 'sidebar-active' : 'text-slate-600'; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                Allotment Types
            </a>
            <a href="analytics.php" style="display: none;"
                class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-100 hover:text-blue-700 transition text-sm font-semibold <?php echo $activePage == 'analytics.php' ? 'sidebar-active' : 'text-slate-600'; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h18v18H3V3z" />
                </svg>
                Analytics
            </a>

            <!-- Properties CRUD (Checked dynamically) -->
            <?php if (has_permission('properties', 'view')): ?>
                <a href="properties.php" style="display: none;"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-100 hover:text-blue-700 transition text-sm font-semibold <?php echo $activePage == 'properties.php' ? 'sidebar-active' : 'text-slate-600'; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                        </path>
                    </svg>
                    Property Listings
                </a>
            <?php endif; ?>

            <!-- Categories CRUD (Checked dynamically) -->
            <?php if (has_permission('categories', 'view')): ?>
                <a href="categories.php" style="display: none;"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-100 hover:text-blue-700 transition text-sm font-semibold <?php echo $activePage == 'categories.php' ? 'sidebar-active' : 'text-slate-600'; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10">
                        </path>
                    </svg>
                    Categories
                </a>
            <?php endif; ?>

            <!-- Inquiries Pipeline (Checked dynamically) -->
            <?php if (has_permission('inquiries', 'view')): ?>
                <a href="inquiries.php" style="display: none;"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-100 hover:text-blue-700 transition text-sm font-semibold <?php echo $activePage == 'inquiries.php' ? 'sidebar-active' : 'text-slate-600'; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                        </path>
                    </svg>
                    Inquiries / Leads
                </a>
            <?php endif; ?>

            <!-- Meta Ads campaigns (Checked dynamically) -->
            <?php if (has_permission('meta_ads', 'view')): ?>
                <a href="meta-ads.php" style="display: none;"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-100 hover:text-blue-700 transition text-sm font-semibold <?php echo $activePage == 'meta-ads.php' ? 'sidebar-active' : 'text-slate-600'; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 12l3-3 3 3 4-4M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                    Meta Ads Manager
                </a>
            <?php endif; ?>

            <!-- Google Sheet Leads (Checked dynamically) -->
            <?php if (has_permission('inquiries', 'view')): ?>
                <a href="google-sheet-leads.php" style="display: none;"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-100 hover:text-blue-700 transition text-sm font-semibold <?php echo $activePage == 'google-sheet-leads.php' ? 'sidebar-active' : 'text-slate-600'; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                    Google Sheet Leads
                </a>
            <?php endif; ?>

            <!-- Excel Sheet Import Accordion (Checked dynamically) -->
            <?php
            $excelPages = ['excel-import.php', 'excel-leads.php'];
            $excelActive = in_array($activePage, $excelPages);
            ?>
            <?php if (has_permission('inquiries', 'create') || has_permission('inquiries', 'view')): ?>
                <!-- Parent toggle button -->
                <button onclick="toggleExcelMenu()" id="excelMenuToggle"
                    class="w-full flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-100 hover:text-blue-700 transition text-sm font-semibold <?php echo $excelActive ? 'sidebar-active' : 'text-slate-600'; ?>">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                        </path>
                    </svg>
                    <span class="flex-1 text-left">Excel Sheet Import</span>
                    <svg id="excelChevron"
                        class="w-4 h-4 flex-shrink-0 transition-transform duration-200 <?php echo $excelActive ? 'rotate-180' : ''; ?>"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>

                <!-- Submenu -->
                <div id="excelSubmenu" class="<?php echo $excelActive ? '' : 'hidden'; ?> pl-4 space-y-0.5 mt-0.5">
                    <!-- Import File -->
                    <?php if (has_permission('inquiries', 'create')): ?>
                        <a href="excel-import.php"
                            class="flex items-center gap-3 px-4 py-2.5 rounded-xl hover:bg-slate-100 hover:text-blue-700 transition text-xs font-semibold <?php echo $activePage == 'excel-import.php' ? 'sidebar-active' : 'text-slate-500'; ?>">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                            </svg>
                            Import File
                        </a>
                    <?php endif; ?>

                    <!-- Imported Leads List -->
                    <?php if (has_permission('inquiries', 'view')): ?>
                        <a href="excel-leads.php"
                            class="flex items-center gap-3 px-4 py-2.5 rounded-xl hover:bg-slate-100 hover:text-blue-700 transition text-xs font-semibold <?php echo $activePage == 'excel-leads.php' ? 'sidebar-active' : 'text-slate-500'; ?>">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                                </path>
                            </svg>
                            Imported Leads
                        </a>
                    <?php endif; ?>
                </div>

                <script>
                    function toggleExcelMenu() {
                        const menu = document.getElementById('excelSubmenu');
                        const chevron = document.getElementById('excelChevron');
                        menu.classList.toggle('hidden');
                        chevron.classList.toggle('rotate-180');
                    }
                </script>
            <?php endif; ?>

            <!-- Custom Roles permissions (Checked dynamically) -->
            <?php if (has_permission('roles', 'view')): ?>
                <a href="roles.php" style="display: none;"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-100 hover:text-blue-700 transition text-sm font-semibold <?php echo $activePage == 'roles.php' ? 'sidebar-active' : 'text-slate-600'; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z">
                        </path>
                    </svg>
                    Roles & Permissions
                </a>
            <?php endif; ?>

            <!-- User list managers (Checked dynamically) -->
            <?php if (has_permission('users', 'view')): ?>
                <a href="users.php"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-100 hover:text-blue-700 transition text-sm font-semibold <?php echo $activePage == 'users.php' ? 'sidebar-active' : 'text-slate-600'; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                        </path>
                    </svg>
                    Team Management
                </a>
            <?php endif; ?>
        <?php endif; // End Admin Menu ?>
    </nav>

    <!-- Sidebar Footer Actions -->
    <div class="p-4 border-t border-slate-200 bg-slate-50 text-xs">
        <a href="logout.php"
            class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-rose-500 hover:bg-rose-50 hover:text-rose-600 transition font-semibold">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1">
                </path>
            </svg>
            Sign Out
        </a>
    </div>
</aside>