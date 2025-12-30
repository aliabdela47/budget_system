<?php
// This code assumes a session has already been started in the parent file (e.g., dashboard.php)
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
    /* --- Modern Sidebar Styles --- */
    .sidebar {
        width: 260px;
        transition: transform 0.3s ease-in-out;
    }
    .sidebar.collapsed {
        transform: translateX(-100%);
    }
    .sidebar .nav-link {
        display: flex;
        align-items: center;
        padding: 0.9rem 1.25rem;
        font-size: 0.95rem;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.75);
        border-radius: 0.5rem;
        transition: all 0.2s ease;
        margin: 0.25rem 0;
    }
    .sidebar .nav-link:hover {
        background-color: rgba(255, 255, 255, 0.1);
        color: white;
    }
    .sidebar .nav-link.active {
        background-color: rgba(255, 255, 255, 0.2);
        color: white;
        font-weight: 600;
    }
    .sidebar .nav-link i {
        width: 1.5rem; /* Ensures icons are aligned */
        font-size: 1.1em;
        margin-right: 0.75rem;
    }
    /* Responsive styles for mobile */
    @media (max-width: 768px) {
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            z-index: 1000;
            transform: translateX(-100%);
        }
        .sidebar.active {
            transform: translateX(0);
        }
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            display: none;
        }
        .sidebar.active + .sidebar-overlay {
            display: block;
        }
    }
</style>

<aside id="sidebar" class="sidebar bg-gradient-to-b from-primary to-secondary text-white h-screen p-4 flex flex-col shadow-lg">
    <!-- Logo and Header -->
    <div class="flex items-center justify-between pb-4 border-b border-white/20">
        <a href="dashboard.php" class="flex items-center gap-3">
            <i class="fas fa-wallet text-amber-300 text-3xl"></i>
            <h1 class="text-xl font-bold">Budget System</h1>
        </a>
        <button id="sidebarToggleDesktop" class="hidden md:block text-white/70 hover:text-white">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 mt-6 space-y-2">
        <?php 
            $nav_items = [
                'Dashboard' => ['url' => 'dashboard.php', 'icon' => 'fa-tachometer-alt'],
                'Transaction' => ['url' => 'transaction.php', 'icon' => 'fa-exchange-alt'],
                'Fuel Management' => ['url' => 'fuel_management.php', 'icon' => 'fa-gas-pump'],
                'Perdium Management' => ['url' => 'perdium.php', 'icon' => 'fa-dollar-sign'],
            ];

            $admin_items = [
                'Budget Adding' => ['url' => 'budget_adding.php', 'icon' => 'fa-plus-circle'],
                'Settings Owners' => ['url' => 'settings_owners.php', 'icon' => 'fa-building'],
                'Settings Codes' => ['url' => 'settings_codes.php', 'icon' => 'fa-code'],
                'Users Management' => ['url' => 'users_management.php', 'icon' => 'fa-users'],
            ];
        ?>
        
        <p class="px-4 text-xs font-semibold text-white/50 uppercase">Menu</p>
        <?php foreach($nav_items as $name => $item): ?>
            <a href="<?php echo $item['url']; ?>" class="nav-link <?php echo $current_page == $item['url'] ? 'active' : ''; ?>">
                <i class="fas <?php echo $item['icon']; ?>"></i>
                <span><?php echo $name; ?></span>
            </a>
        <?php endforeach; ?>

        <?php if ($_SESSION['role'] == 'admin'): ?>
            <p class="px-4 pt-4 text-xs font-semibold text-white/50 uppercase">Admin Settings</p>
            <?php foreach($admin_items as $name => $item): ?>
                <a href="<?php echo $item['url']; ?>" class="nav-link <?php echo $current_page == $item['url'] ? 'active' : ''; ?>">
                    <i class="fas <?php echo $item['icon']; ?>"></i>
                    <span><?php echo $name; ?></span>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </nav>
    
    <!-- Logout Button -->
    <div class="mt-auto pt-4 border-t border-white/20">
        <a href="logout.php" class="nav-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<!-- Overlay for mobile view -->
<div id="sidebarOverlay" class="sidebar-overlay"></div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    const toggleBtnDesktop = document.getElementById('sidebarToggleDesktop');
    const toggleBtnMobile = document.getElementById('sidebarToggleMobile'); // This is in dashboard.php
    const overlay = document.getElementById('sidebarOverlay');

    // Function to toggle sidebar for desktop
    const toggleDesktopSidebar = () => {
        if (sidebar && mainContent) {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded'); // You'll add this class in dashboard CSS
        }
    };

    // Function to toggle sidebar for mobile
    const toggleMobileSidebar = () => {
        if(sidebar) {
            sidebar.classList.toggle('active');
        }
    };

    if (toggleBtnDesktop) {
        toggleBtnDesktop.addEventListener('click', toggleDesktopSidebar);
    }
    if (toggleBtnMobile) {
        toggleBtnMobile.addEventListener('click', toggleMobileSidebar);
    }
    if (overlay) {
        overlay.addEventListener('click', toggleMobileSidebar);
    }
});
</script>