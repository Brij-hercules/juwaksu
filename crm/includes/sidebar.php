<?php
// crm/includes/sidebar.php
require_once __DIR__ . '/../../includes/auth_helper.php';

$activePage = basename($_SERVER['PHP_SELF']);
?>
<!-- CRM Sidebar Navigation Panel -->
<aside id="crm-sidebar" class="w-64 bg-slate-900 text-slate-400 flex flex-col h-full border-r border-slate-800 absolute md:relative z-50 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out">
    <!-- Sidebar Header Logo -->
    <div class="h-16 flex items-center justify-between px-6 border-b border-slate-800/80 bg-slate-950/40">
        <a class="flex items-center gap-1.5 font-extrabold text-lg tracking-tight text-white" href="../index.php" target="_blank">
            <span class="text-brand-gold">WAVE</span>CRM
        </a>
        <button class="md:hidden p-1 rounded hover:bg-slate-800 text-slate-400 focus:outline-none" onclick="document.getElementById('crm-sidebar').classList.add('-translate-x-full')">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>
    
    <!-- Sidebar Navigation Links -->
    <nav class="flex-1 px-4 py-6 space-y-1.5 overflow-y-auto">
        <!-- Dashboard Link (Always Visible to Logged-in team members) -->
        <a href="index.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 hover:text-white transition text-sm font-semibold <?php echo $activePage == 'index.php' ? 'sidebar-active text-white' : 'text-slate-400'; ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z"></path></svg>
            Dashboard
        </a>
        
        <!-- Properties CRUD (Checked dynamically) -->
        <?php if (has_permission('properties', 'view')): ?>
            <a href="properties.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 hover:text-white transition text-sm font-semibold <?php echo $activePage == 'properties.php' ? 'sidebar-active text-white' : 'text-slate-400'; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                Property Listings
            </a>
        <?php endif; ?>
        
        <!-- Categories CRUD (Checked dynamically) -->
        <?php if (has_permission('categories', 'view')): ?>
            <a href="categories.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 hover:text-white transition text-sm font-semibold <?php echo $activePage == 'categories.php' ? 'sidebar-active text-white' : 'text-slate-400'; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                Categories
            </a>
        <?php endif; ?>
        
        <!-- Inquiries Pipeline (Checked dynamically) -->
        <?php if (has_permission('inquiries', 'view')): ?>
            <a href="inquiries.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 hover:text-white transition text-sm font-semibold <?php echo $activePage == 'inquiries.php' ? 'sidebar-active text-white' : 'text-slate-400'; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                Inquiries / Leads
            </a>
        <?php endif; ?>
        
        <!-- Meta Ads campaigns (Checked dynamically) -->
        <?php if (has_permission('meta_ads', 'view')): ?>
            <a href="meta-ads.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 hover:text-white transition text-sm font-semibold <?php echo $activePage == 'meta-ads.php' ? 'sidebar-active text-white' : 'text-slate-400'; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                Meta Ads Manager
            </a>
        <?php endif; ?>
        
        <!-- Custom Roles permissions (Checked dynamically) -->
        <?php if (has_permission('roles', 'view')): ?>
            <a href="roles.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 hover:text-white transition text-sm font-semibold <?php echo $activePage == 'roles.php' ? 'sidebar-active text-white' : 'text-slate-400'; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                Roles & Permissions
            </a>
        <?php endif; ?>
        
        <!-- User list managers (Checked dynamically) -->
        <?php if (has_permission('users', 'view')): ?>
            <a href="users.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 hover:text-white transition text-sm font-semibold <?php echo $activePage == 'users.php' ? 'sidebar-active text-white' : 'text-slate-400'; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                Team Management
            </a>
        <?php endif; ?>
    </nav>
    
    <!-- Sidebar Footer Actions -->
    <div class="p-4 border-t border-slate-800 bg-slate-950/40 text-xs">
        <a href="logout.php" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-rose-400 hover:bg-rose-500/10 hover:text-rose-300 transition font-semibold">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
            Sign Out
        </a>
    </div>
</aside>
