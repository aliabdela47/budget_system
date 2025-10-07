<?php
// assumes session started in includes/init.php
$current_page = strtolower(basename($_SERVER['PHP_SELF']));
$role = $_SESSION['role'] ?? ''; // 'admin' | 'officer'
$user_name = $_SESSION['username'] ?? 'User';
$user_id = $_SESSION['user_id'] ?? '';

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
    ['label' => 'Online Users', 'url' => 'online_users.php',     'icon' => 'fa-users-rays'],
    ['label' => 'Users',        'url' => 'users_management.php', 'icon' => 'fa-light fa-users'],
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

// Get user initials for avatar
$initials = '';
if (!empty($user_name)) {
    $name_parts = explode(' ', $user_name);
    $initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
}
?>
<style>
    /* Enhanced Sidebar Styles */
    .sidebar { 
        width: 280px; 
        background: linear-gradient(180deg, #7c3aed 0%, #4f46e5 100%);
        box-shadow: 0 0 30px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        backdrop-filter: blur(10px);
    }
    
    .sidebar.collapsed { 
        transform: translateX(-280px); 
    }
    
    .user-profile {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        margin: 1rem;
        border-radius: 16px;
        padding: 1.5rem;
        text-align: center;
    }
    
    .user-avatar {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #fbbf24, #f59e0b);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 1.5rem;
        font-weight: 700;
        color: white;
        border: 3px solid rgba(255, 255, 255, 0.3);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }
    
    .user-info h3 {
        color: white;
        font-size: 1.1rem;
        font-weight: 600;
        margin: 0 0 0.25rem 0;
    }
    
    .user-info .role {
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.85rem;
        background: rgba(255, 255, 255, 0.1);
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        display: inline-block;
    }
    
    .nav-section {
        padding: 0 1rem;
        margin-bottom: 1.5rem;
    }
    
    .nav-section-title {
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.75rem;
        padding-left: 0.5rem;
    }
    
    .nav-link {
        display: flex;
        align-items: center;
        padding: 0.875rem 1rem;
        font-size: 0.95rem;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.8);
        border-radius: 12px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        margin: 0.25rem 0;
        position: relative;
        overflow: hidden;
    }
    
    .nav-link::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: 3px;
        background: #fbbf24;
        transform: scaleY(0);
        transition: transform 0.3s ease;
    }
    
    .nav-link:hover {
        background: rgba(255, 255, 255, 0.15);
        color: white;
        transform: translateX(5px);
    }
    
    .nav-link:hover::before {
        transform: scaleY(1);
    }
    
    .nav-link.active {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    
    .nav-link.active::before {
        transform: scaleY(1);
    }
    
    .nav-link i {
        width: 1.5rem;
        font-size: 1.1em;
        margin-right: 0.75rem;
        transition: transform 0.3s ease;
    }
    
    .nav-link:hover i {
        transform: scale(1.1);
    }
    
    .sidebar-footer {
        margin-top: auto;
        padding: 1rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .logout-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        padding: 0.875rem;
        background: rgba(239, 68, 68, 0.2);
        color: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(239, 68, 68, 0.3);
        border-radius: 12px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .logout-btn:hover {
        background: rgba(239, 68, 68, 0.3);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(239, 68, 68, 0.2);
    }
    
    .sidebar-toggle {
        position: fixed;
        top: 1rem;
        left: 1rem;
        z-index: 1100;
        background: rgba(79, 70, 229, 0.9);
        border: none;
        border-radius: 12px;
        color: white;
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    
    .sidebar-toggle:hover {
        background: #4f46e5;
        transform: scale(1.05);
    }
    
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
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
            backdrop-filter: blur(5px);
        }
        
        .sidebar.active + .sidebar-overlay {
            display: block;
        }
    }
</style>

<!-- Mobile Toggle Button -->
<button class="sidebar-toggle md:hidden" id="sidebarToggleMobile">
    <i class="fas fa-bars text-lg"></i>
</button>

<aside id="sidebar" class="sidebar text-white h-screen flex flex-col">
    <!-- Logo and Header -->
    <div class="flex items-center justify-between p-6 pb-4 border-b border-white/10">
        <a href="dashboard.php" class="flex items-center gap-3">
            <div class="w-12 h-12 bg-amber-400 rounded-xl flex items-center justify-center shadow-lg">
                <i class="fas fa-wallet text-white text-xl"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold">Budget System</h1>
                <p class="text-white/60 text-sm">Financial Management</p>
            </div>
        </a>
        <button id="sidebarToggleDesktop" class="hidden md:block text-white/70 hover:text-white transition-colors">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>

    <!-- User Profile -->
    <div class="user-profile">
        <div class="user-avatar">
            <?php echo $initials; ?>
        </div>
        <div class="user-info">
            <h3><?php echo htmlspecialchars($name); ?></h3>
            <div class="role"><?php echo htmlspecialchars(ucfirst($role)); ?></div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto">
        <div class="nav-section">
            <p class="nav-section-title">Main Menu</p>
            <?php foreach ($menu as $item): ?>
                <a href="<?php echo $item['url']; ?>"
                   class="nav-link <?php echo nav_active($item['url'], $current_page); ?>">
                    <i class="fas <?php echo $item['icon']; ?>"></i>
                    <span><?php echo htmlspecialchars($item['label']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($role === 'admin'): ?>
            <div class="nav-section">
                <p class="nav-section-title">Administration</p>
                <a href="settings.php" class="nav-link <?php echo nav_active('settings.php', $current_page); ?>">
                    <i class="fas fa-cog"></i>
                    <span>System Settings</span>
                </a>
                <a href="users_management.php" class="nav-link <?php echo nav_active('users_management.php', $current_page); ?>">
                    <i class="fas fa-users-cog"></i>
                    <span>User Management</span>
                </a>
            </div>
        <?php endif; ?>
    </nav>

    <!-- Footer with Logout -->
    <div class="sidebar-footer">
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt mr-2"></i>
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
    const toggleBtnMobile = document.getElementById('sidebarToggleMobile');
    const overlay = document.getElementById('sidebarOverlay');

    const toggleDesktopSidebar = () => {
        if (sidebar && mainContent) {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            
            // Update toggle button icon
            const icon = toggleBtnDesktop.querySelector('i');
            if (sidebar.classList.contains('collapsed')) {
                icon.className = 'fas fa-chevron-right';
            } else {
                icon.className = 'fas fa-chevron-left';
            }
        }
    };

    const toggleMobileSidebar = () => {
        if (sidebar) {
            sidebar.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
        }
    };

    // Event listeners
    if (toggleBtnDesktop) toggleBtnDesktop.addEventListener('click', toggleDesktopSidebar);
    if (toggleBtnMobile) toggleBtnMobile.addEventListener('click', toggleMobileSidebar);
    if (overlay) overlay.addEventListener('click', toggleMobileSidebar);

    // Close sidebar when clicking on a link (mobile)
    if (window.innerWidth < 768) {
        const navLinks = sidebar.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', toggleMobileSidebar);
        });
    }

    // Handle window resize
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 768) {
            sidebar.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
});
</script>