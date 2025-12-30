<?php
// Get current page for active state and check user role
$current_page = basename($_SERVER['PHP_SELF']);
$is_admin = ($_SESSION['role'] ?? '') === 'admin';
?>

<!-- Modern Enhanced Sidebar -->
<aside id="sidebar" class="gradient-sidebar text-white w-64 h-screen fixed left-0 top-0 z-50 flex flex-col transition-all duration-300 ease-in-out border-r border-white/20 shadow-2xl transform -translate-x-full lg:translate-x-0" data-collapsed="false">

    <!-- Sidebar Header with Toggle -->
    <div class="flex items-center justify-between p-4 border-b border-white/20 h-16 shrink-0 bg-white/10 backdrop-blur-sm">
        <div class="flex items-center gap-3 overflow-hidden transition-all duration-300">
            <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center shrink-0 shadow-lg">
                <i class="fas fa-wallet text-white text-sm"></i>
            </div>
            <div class="flex flex-col">
                <h2 class="text-base font-bold text-white whitespace-nowrap">Q.A.R.D Biiro</h2>
                <p class="text-xs text-white/90 whitespace-nowrap">Financial Management</p>
            </div>
        </div>
        
        <!-- Desktop Toggle Button -->
        <button class="desktop-sidebar-toggle hidden lg:flex items-center justify-center w-8 h-8 rounded-lg bg-white/20 hover:bg-white/30 text-white transition-all duration-200 hover:scale-105">
            <i class="fas fa-chevron-left text-xs transition-transform duration-300"></i>
        </button>
    </div>

    <!-- Navigation Menu -->
    <nav class="flex-grow overflow-y-auto p-4 space-y-1 scrollbar-thin">
        <ul class="space-y-1">
            <!-- Dashboard -->
            <li>
                <a href="dashboard.php" class="sidebar-item group <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                    <div class="sidebar-icon-wrapper">
                        <i class="fas fa-tachometer-alt sidebar-icon"></i>
                    </div>
                    <span class="sidebar-text">Dashboard</span>
                    <span class="active-indicator"></span>
                </a>
            </li>

            <!-- Budgets Section -->
            <li class="section-title">
                <span class="sidebar-text">Financial Management</span>
                <div class="section-line"></div>
            </li>

            <!-- Budgets (Admin Only) -->
            <?php if ($is_admin): ?>
            <li>
                <a href="budget_adding.php" class="sidebar-item group <?php echo $current_page == 'budget_adding.php' ? 'active' : ''; ?>">
                    <div class="sidebar-icon-wrapper">
                        <i class="fas fa-money-bill-wave sidebar-icon"></i>
                    </div>
                    <span class="sidebar-text">Budgets</span>
                    <span class="active-indicator"></span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Per Diem Section -->
            <li class="section-title">
                <span class="sidebar-text">Per Diem Management</span>
                <div class="section-line"></div>
            </li>

            <!-- Per Diem -->
            <li>
                <a href="perdium.php" class="sidebar-item group <?php echo $current_page == 'perdium.php' ? 'active' : ''; ?>">
                    <div class="sidebar-icon-wrapper">
                        <i class="fas fa-dollar-sign sidebar-icon"></i>
                    </div>
                    <span class="sidebar-text">Per Diem</span>
                    <span class="active-indicator"></span>
                </a>
            </li>

            <!-- Employees (Admin Only) -->
            <?php if ($is_admin): ?>
            <li>
                <a href="employee-registration.php" class="sidebar-item group child-link <?php echo $current_page == 'employee-registration.php' ? 'active' : ''; ?>">
                    <div class="sidebar-icon-wrapper">
                        <i class="fas fa-users-cog sidebar-icon"></i>
                    </div>
                    <span class="sidebar-text">Employees</span>
                    <span class="active-indicator"></span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Payroll (Admin Only) -->
            <?php if ($is_admin): ?>
            <li>
                <a href="payroll.php" class="sidebar-item group child-link <?php echo $current_page == 'payroll.php' ? 'active' : ''; ?>">
                    <div class="sidebar-icon-wrapper">
                        <i class="fas fa-file-invoice-dollar sidebar-icon"></i>
                    </div>
                    <span class="sidebar-text">Payroll</span>
                    <span class="active-indicator"></span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Fuel Management -->
            <li>
                <a href="fuel_management.php" class="sidebar-item group <?php echo $current_page == 'fuel_management.php' ? 'active' : ''; ?>">
                    <div class="sidebar-icon-wrapper">
                        <i class="fas fa-gas-pump sidebar-icon"></i>
                    </div>
                    <span class="sidebar-text">Fuel</span>
                    <span class="active-indicator"></span>
                </a>
            </li>

            <!-- Reports -->
            <li>
                <a href="reports.php" class="sidebar-item group <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                    <div class="sidebar-icon-wrapper">
                        <i class="fas fa-chart-bar sidebar-icon"></i>
                    </div>
                    <span class="sidebar-text">Reports</span>
                    <span class="active-indicator"></span>
                </a>
            </li>
        </ul>

        <!-- Admin Section -->
        <?php if ($is_admin): ?>
        <ul class="pt-4 mt-4 border-t border-white/20 space-y-1">
            <li class="section-title">
                <span class="sidebar-text">Administration</span>
                <div class="section-line"></div>
            </li>
            <!-- User Management -->
            <li>
                <a href="users_management.php" class="sidebar-item group <?php echo $current_page == 'users_management.php' ? 'active' : ''; ?>">
                    <div class="sidebar-icon-wrapper">
                        <i class="fas fa-users-shield sidebar-icon"></i>
                    </div>
                    <span class="sidebar-text">Users</span>
                    <span class="active-indicator"></span>
                </a>
            </li>
            <!-- System Settings -->
            <li>
                <a href="settings.php" class="sidebar-item group <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
                    <div class="sidebar-icon-wrapper">
                        <i class="fas fa-cogs sidebar-icon"></i>
                    </div>
                    <span class="sidebar-text">System Settings</span>
                    <span class="active-indicator"></span>
                </a>
            </li>
        </ul>
        <?php endif; ?>
    </nav>

    <!-- Sidebar Footer / User Info -->
    <div class="p-4 border-t border-white/20 shrink-0 bg-white/10 backdrop-blur-sm">
        <a href="profile.php" class="sidebar-item group <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
            <div class="relative">
                <img 
                    src="<?php echo !empty($profile_picture) ? htmlspecialchars($profile_picture) : 'assets/default-avatar.png'; ?>" 
                    alt="<?php echo htmlspecialchars($user_name); ?>" 
                    class="w-8 h-8 rounded-full border-2 border-white/40 group-hover:border-white/60 transition-all duration-200 shrink-0 shadow-md"
                    onerror="this.src='assets/default-avatar.png'"
                >
                <div class="absolute -bottom-1 -right-1 w-3 h-3 bg-green-400 rounded-full border-2 border-white"></div>
            </div>
            <div class="overflow-hidden sidebar-text transition-all duration-300">
                <p class="text-sm font-semibold text-white truncate"><?php echo htmlspecialchars($user_name); ?></p>
                <p class="text-xs text-white/90 truncate"><?php echo ucfirst($_SESSION['role']); ?></p>
            </div>
        </a>
         <a href="logout.php" class="sidebar-item group mt-2 text-red-200 hover:bg-red-500/30 hover:!text-white transition-all duration-200">
            <div class="sidebar-icon-wrapper">
                <i class="fas fa-sign-out-alt sidebar-icon"></i>
            </div>
            <span class="sidebar-text">Logout</span>
         </a>
    </div>

</aside>

<!-- Mobile Overlay -->
<div class="sidebar-overlay fixed inset-0 bg-black/60 backdrop-blur-sm z-40 lg:hidden hidden" id="sidebarOverlay"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar Toggle Functionality
    function initializeSidebar() {
        const mobileToggle = document.getElementById('mobileSidebarToggle');
        const desktopToggle = document.getElementById('desktopSidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const mainContent = document.getElementById('mainContent');
        const desktopIcon = document.getElementById('desktopToggleIcon');

        // Check if we're on mobile
        function isMobile() {
            return window.innerWidth < 1024;
        }

        // Toggle sidebar function
        function toggleSidebar() {
            if (isMobile()) {
                // Mobile behavior
                const isOpen = sidebar.classList.contains('mobile-open');
                
                if (isOpen) {
                    sidebar.classList.remove('mobile-open');
                    if (sidebarOverlay) sidebarOverlay.classList.add('hidden');
                    document.body.style.overflow = '';
                } else {
                    sidebar.classList.add('mobile-open');
                    if (sidebarOverlay) sidebarOverlay.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                }
            } else {
                // Desktop behavior
                const isCollapsed = sidebar.classList.contains('collapsed');
                
                if (isCollapsed) {
                    sidebar.classList.remove('collapsed');
                    mainContent.classList.remove('sidebar-collapsed');
                    if (desktopIcon) desktopIcon.classList.remove('rotate-180');
                } else {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('sidebar-collapsed');
                    if (desktopIcon) desktopIcon.classList.add('rotate-180');
                }
            }
        }

        // Mobile toggle event
        if (mobileToggle) {
            mobileToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
        }

        // Desktop toggle event
        if (desktopToggle) {
            desktopToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
        }

        // Close sidebar when overlay is clicked (mobile only)
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function(e) {
                e.preventDefault();
                if (isMobile()) {
                    sidebar.classList.remove('mobile-open');
                    sidebarOverlay.classList.add('hidden');
                    document.body.style.overflow = '';
                }
            });
        }

        // Close sidebar when clicking on links (mobile only)
        if (sidebar) {
            const sidebarLinks = sidebar.querySelectorAll('a');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    if (isMobile()) {
                        sidebar.classList.remove('mobile-open');
                        if (sidebarOverlay) sidebarOverlay.classList.add('hidden');
                        document.body.style.overflow = '';
                    }
                });
            });
        }

        // Handle window resize
        window.addEventListener('resize', function() {
            if (!isMobile()) {
                // Reset mobile state when switching to desktop
                sidebar.classList.remove('mobile-open');
                if (sidebarOverlay) sidebarOverlay.classList.add('hidden');
                document.body.style.overflow = '';
            }
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (isMobile() && sidebar.classList.contains('mobile-open')) {
                const isClickInsideSidebar = sidebar.contains(e.target);
                const isClickOnToggle = mobileToggle && mobileToggle.contains(e.target);
                
                if (!isClickInsideSidebar && !isClickOnToggle) {
                    sidebar.classList.remove('mobile-open');
                    if (sidebarOverlay) sidebarOverlay.classList.add('hidden');
                    document.body.style.overflow = '';
                }
            }
        });

        // Prevent sidebar close when clicking inside sidebar
        if (sidebar) {
            sidebar.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
    }

    // Initialize sidebar when DOM is loaded
    initializeSidebar();
});
</script>

<style>
    /* Enhanced Sidebar Styles with Identical Header Background */
    .gradient-sidebar {
        background: linear-gradient(135deg, 
            #667eea 0%, 
            #764ba2 25%, 
            #f093fb 50%, 
            #f5576c 75%, 
            #4facfe 100%);
        background-size: 400% 400%;
        animation: gradientShift 15s ease infinite;
    }

    @keyframes gradientShift {
        0% { background-position: 0% 50% }
        50% { background-position: 100% 50% }
        100% { background-position: 0% 50% }
    }

    /* Mobile Sidebar Styles */
    #sidebar {
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    #sidebar.mobile-open {
        transform: translateX(0) !important;
    }

    /* Mobile specific */
    @media (max-width: 1023px) {
        #sidebar {
            transform: translateX(-100%);
            width: 280px;
        }
        
        #sidebar.mobile-open {
            transform: translateX(0) !important;
            box-shadow: 8px 0 32px rgba(0, 0, 0, 0.4);
        }
    }

    /* Desktop specific */
    @media (min-width: 1024px) {
        #sidebar {
            transform: translateX(0) !important;
        }
        
        #sidebar.collapsed {
            width: 5rem;
        }
        
        #sidebar.collapsed .sidebar-text,
        #sidebar.collapsed .section-title .sidebar-text {
            opacity: 0;
            width: 0;
            pointer-events: none;
        }
        
        #sidebar.collapsed .section-title {
            padding: 1rem 0.25rem 0.5rem;
        }
        
        #sidebar.collapsed .sidebar-item.child-link {
            padding-left: 1rem;
            margin-left: 0;
        }
    }

    /* Sidebar Items with Improved Contrast */
    .sidebar-item {
        position: relative;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem;
        border-radius: 0.75rem;
        text-decoration: none;
        color: rgba(255, 255, 255, 0.95);
        font-weight: 500;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
        border: 1px solid transparent;
    }

    .sidebar-item::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 3px;
        height: 0;
        background: rgba(255, 255, 255, 0.9);
        border-radius: 0 4px 4px 0;
        transition: height 0.3s ease;
    }

    .sidebar-item:hover {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        transform: translateX(4px);
        border-color: rgba(255, 255, 255, 0.3);
        backdrop-filter: blur(10px);
    }

    .sidebar-item:hover::before {
        height: 60%;
    }

    .sidebar-item.active {
        background: rgba(255, 255, 255, 0.25);
        color: white;
        font-weight: 600;
        transform: translateX(4px);
        border-color: rgba(255, 255, 255, 0.4);
        backdrop-filter: blur(10px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .sidebar-item.active::before {
        height: 80%;
        background: white;
    }

    .sidebar-item.child-link {
        padding-left: 3rem;
        margin-left: 0.5rem;
        border-left: 2px solid rgba(255, 255, 255, 0.3);
    }

    .sidebar-icon-wrapper {
        width: 1.5rem;
        height: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: transform 0.2s ease;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .sidebar-item:hover .sidebar-icon-wrapper {
        transform: scale(1.1);
        background: rgba(255, 255, 255, 0.2);
    }

    .sidebar-item.active .sidebar-icon-wrapper {
        transform: scale(1.15);
        background: rgba(255, 255, 255, 0.25);
    }

    .sidebar-icon {
        width: 1rem;
        text-align: center;
        transition: all 0.3s ease;
        color: rgba(255, 255, 255, 0.9);
    }

    .sidebar-item:hover .sidebar-icon {
        color: white;
    }

    .sidebar-item.active .sidebar-icon {
        color: white;
    }

    .sidebar-text {
        white-space: nowrap;
        opacity: 1;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        flex: 1;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.95);
    }

    .sidebar-item:hover .sidebar-text {
        color: white;
        font-weight: 600;
    }

    .sidebar-item.active .sidebar-text {
        color: white;
        font-weight: 700;
    }

    .active-indicator {
        width: 6px;
        height: 6px;
        background: white;
        border-radius: 50%;
        opacity: 0;
        transition: opacity 0.3s ease;
        box-shadow: 0 0 8px rgba(255, 255, 255, 0.5);
    }

    .sidebar-item.active .active-indicator {
        opacity: 1;
    }

    .section-title {
        position: relative;
        padding: 1rem 0.75rem 0.5rem;
        margin-top: 0.5rem;
    }

    .section-title .sidebar-text {
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        color: rgba(255, 255, 255, 0.8);
        text-transform: uppercase;
    }

    .section-line {
        position: absolute;
        bottom: 0;
        left: 0.75rem;
        right: 0.75rem;
        height: 1px;
        background: linear-gradient(to right, transparent, rgba(255, 255, 255, 0.4), transparent);
    }

    /* Custom scrollbar for sidebar */
    .scrollbar-thin {
        scrollbar-width: thin;
        scrollbar-color: rgba(255, 255, 255, 0.4) transparent;
    }

    .scrollbar-thin::-webkit-scrollbar {
        width: 4px;
    }

    .scrollbar-thin::-webkit-scrollbar-track {
        background: transparent;
    }

    .scrollbar-thin::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.4);
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .scrollbar-thin::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.6);
    }

    /* Enhanced glass effect for header areas */
    .backdrop-blur-sm {
        backdrop-filter: blur(8px);
    }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const mainContent = document.getElementById('mainContent');
    const chevronIcon = sidebarToggle?.querySelector('i');

    function isMobile() {
        return window.innerWidth < 1024;
    }

    // Toggle sidebar function
    function toggleSidebar() {
        if (isMobile()) {
            // Mobile behavior
            sidebar.classList.toggle('mobile-open');
            sidebarOverlay.classList.toggle('hidden');
            document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
        } else {
            // Desktop behavior - toggle hover effect
            sidebar.classList.toggle('collapsed');
            if (chevronIcon) {
                chevronIcon.classList.toggle('fa-chevron-left');
                chevronIcon.classList.toggle('fa-chevron-right');
                chevronIcon.classList.toggle('rotate-180');
            }
        }
    }

    // Event listeners
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }

    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', toggleSidebar);
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('mobile-open');
            sidebarOverlay.classList.add('hidden');
            document.body.style.overflow = '';
        });
    }

    // Close sidebar when clicking on a link (mobile)
    sidebar.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            if (isMobile()) {
                sidebar.classList.remove('mobile-open');
                sidebarOverlay.classList.add('hidden');
                document.body.style.overflow = '';
            }
        });
    });

    // Handle window resize
    window.addEventListener('resize', () => {
        if (!isMobile()) {
            sidebar.classList.remove('mobile-open');
            sidebarOverlay.classList.add('hidden');
            document.body.style.overflow = '';
        }
    });

    // Add hover effect for desktop
    if (!isMobile()) {
        sidebar.addEventListener('mouseenter', () => {
            if (sidebar.classList.contains('collapsed')) {
                sidebar.classList.remove('collapsed');
            }
        });

        sidebar.addEventListener('mouseleave', () => {
            if (!sidebar.classList.contains('collapsed')) {
                sidebar.classList.add('collapsed');
            }
        });
    }
});
</script>