<?php
// assumes session started in includes/init.php
$current_page = strtolower(basename($_SERVER['PHP_SELF']));
$role = $_SESSION['role'] ?? ''; // 'admin' | 'officer'

// Helper for active class
function nav_active($file, $current_page) {
    return (strtolower($file) === $current_page) ? 'active' : '';
}

// Define menus by role
$admin_menu = [
    ['label' => 'Dashboard',    'url' => 'dashboard.php',        'icon' => 'fa-tachometer-alt'],
    ['label' => 'Budgets',      'url' => 'budget_adding.php',    'icon' => 'fa-plus-circle'],
    ['label' => 'Perdium',      'url' => 'perdium.php',          'icon' => 'fa-dollar-sign'],
    ['label' => 'Fuel',         'url' => 'fuel_management.php',  'icon' => 'fa-gas-pump'],
    ['label' => 'Transactions', 'url' => 'transaction.php',      'icon' => 'fa-exchange-alt'],
    ['label' => 'Reports',      'url' => 'reports.php',          'icon' => 'fa-chart-line'],
    ['label' => 'Online Users', 'url' => 'online_users.php',     'icon' => 'fa-users-rays'], // <-- ADD THIS LINE
    ['label' => 'Users',      'url' => 'users_management.php',   'icon' => 'fa-light fa-users'],
    ['label' => 'Settings',     'url' => 'settings.php',         'icon' => 'fa-cog'],
];

$officer_menu = [
    ['label' => 'Dashboard',    'url' => 'dashboard.php',        'icon' => 'fa-tachometer-alt'],
    ['label' => 'Perdium',      'url' => 'perdium.php',          'icon' => 'fa-dollar-sign'],
    ['label' => 'Fuel',         'url' => 'fuel_management.php',  'icon' => 'fa-gas-pump'],
    ['label' => 'Transactions', 'url' => 'transaction.php',      'icon' => 'fa-exchange-alt'],
    ['label' => 'Reports',      'url' => 'reports.php',          'icon' => 'fa-chart-line'],
];

// Pick the correct menu
$menu = ($role === 'admin') ? $admin_menu : $officer_menu;
?>
<style>
    /* --- Modern Sidebar Styles --- */
    .sidebar { width: 260px; transition: transform 0.3s ease-in-out; }
    .sidebar.collapsed { transform: translateX(-100%); }
    .sidebar .nav-link {
        display: flex; align-items: center; padding: 0.9rem 1.25rem;
        font-size: 0.95rem; font-weight: 500; color: rgba(255, 255, 255, 0.75);
        border-radius: 0.5rem; transition: all 0.2s ease; margin: 0.25rem 0;
    }
    .sidebar .nav-link:hover { background-color: rgba(255, 255, 255, 0.1); color: #fff; }
    .sidebar .nav-link.active { background-color: rgba(255, 255, 255, 0.2); color: #fff; font-weight: 600; }
    .sidebar .nav-link i { width: 1.5rem; font-size: 1.1em; margin-right: 0.75rem; }
    @media (max-width: 768px) {
        .sidebar { position: fixed; top: 0; left: 0; height: 100%; z-index: 1000; transform: translateX(-100%); }
        .sidebar.active { transform: translateX(0); }
        .sidebar-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; display: none; }
        .sidebar.active + .sidebar-overlay { display: block; }
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

    <!-- Navigation -->
    <nav class="flex-1 mt-6 space-y-2">
        <p class="px-4 text-xs font-semibold text-white/50 uppercase">Menu</p>

        <?php foreach ($menu as $item): ?>
            <a href="<?php echo $item['url']; ?>"
               class="nav-link <?php echo nav_active($item['url'], $current_page); ?>">
                <i class="fas <?php echo $item['icon']; ?>"></i>
                <span><?php echo htmlspecialchars($item['label']); ?></span>
            </a>
        <?php endforeach; ?>

        <?php if ($role === 'admin'): ?>
            <p class="px-4 pt-4 text-xs font-semibold text-white/50 uppercase">Admin</p>
            <a href="settings.php" class="nav-link <?php echo nav_active('settings.php', $current_page); ?>">
                <i class="fas fa-cog"></i><span>Settings</span>
            </a>
        <?php endif; ?>
        <?php if ($role === 'admin'): ?>

            <a href="users_management.php" class="nav-link <?php echo nav_active('users_management.php', $current_page); ?>">
                <i class="fa-light fa-users"></i><span>Users</span>
            </a>
        <?php endif; ?>
    </nav>

    <!-- Logout -->
    <div class="mt-auto pt-4 border-t border-white/20">
        <a href="logout.php" class="nav-link">
            <i class="fas fa-sign-out-alt"></i><span>Logout</span>
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
    const toggleBtnMobile  = document.getElementById('sidebarToggleMobile'); // define in page header if needed
    const overlay = document.getElementById('sidebarOverlay');

    const toggleDesktopSidebar = () => {
        if (sidebar && mainContent) {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        }
    };
    const toggleMobileSidebar = () => {
        if (sidebar) sidebar.classList.toggle('active');
    };

    if (toggleBtnDesktop) toggleBtnDesktop.addEventListener('click', toggleDesktopSidebar);
    if (toggleBtnMobile)  toggleBtnMobile.addEventListener('click', toggleMobileSidebar);
    if (overlay)          overlay.addEventListener('click', toggleMobileSidebar);
});
</script>