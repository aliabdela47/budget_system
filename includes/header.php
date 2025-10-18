<?php
// Get user data including profile picture
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT name, profile_picture, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$user_name = $user_data['name'] ?? $_SESSION['username'];
$profile_picture = $user_data['profile_picture'] ?? '';
$user_email = $user_data['email'] ?? '';
?>
<!-- Modern Header - Fixed Full Width -->
<div class="w-full px-6"> <!-- Added container with same padding as main content -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 p-6 rounded-2xl gradient-header text-white shadow-xl transform transition-all duration-300" id="mainHeader">
        <!-- Removed w-full from this div and added it to parent container -->
        <div class="flex-1 w-full">
            <h2 class="text-2xl md:text-3xl font-bold mb-2 flex items-center">
            <!--    <i class="fas fa-gas-pump mr-4 text-white"></i> -->
                Afar-RHB Financial System
            </h2>
            
            <div class="modern-card bg-white bg-opacity-10 rounded-xl p-4 max-w-md backdrop-blur-sm border border-white border-opacity-20">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                        <i class="fas fa-user-circle text-white"></i>
                    </div>
                    <div>
                        <span class="text-white font-semibold block">
                            Welcome, <?php echo htmlspecialchars($user_name); ?>!
                        </span>
                        <span class="text-blue-100 text-sm">
                            <?php echo ucfirst($_SESSION['role']); ?> Account
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="flex items-center space-x-4 mt-6 md:mt-0">
            <!-- User Profile Badge -->
            <div class="user-profile relative" id="userProfile">
                <div class="flex items-center space-x-3 modern-card bg-white bg-opacity-10 rounded-xl p-3 backdrop-blur-sm border border-white border-opacity-20 cursor-pointer hover:bg-opacity-20 transition-all duration-200">
                    <div class="text-right hidden md:block">
                        <div class="text-white font-semibold text-sm">
                            <?php echo htmlspecialchars($user_name); ?>
                        </div>
                        <div class="text-blue-100 text-xs">
                            <?php echo ucfirst($_SESSION['role']); ?>
                        </div>
                    </div>
                    <img
                    src="<?php echo $profile_picture ? htmlspecialchars($profile_picture) : 'assets/default-avatar.png'; ?>"
                    alt="<?php echo htmlspecialchars($user_name); ?>"
                    class="w-10 h-10 rounded-full border-2 border-white/30 hover:border-white/50 transition-all duration-200 shadow-md"
                    onerror="this.src='assets/default-avatar.png'"
                    >
                    <i class="fas fa-chevron-down text-white text-sm transition-transform duration-200" id="userChevron"></i>
                </div>

                <!-- User Dropdown Menu -->
                <div class="absolute top-full right-0 mt-2 w-64 bg-white rounded-xl shadow-2xl border border-gray-200 py-2 z-50 user-dropdown hidden" id="userDropdown">
                    <div class="user-dropdown-header px-4 py-3 border-b border-gray-100">
                        <div class="flex items-center space-x-3">
                            <img
                            src="<?php echo $profile_picture ? htmlspecialchars($profile_picture) : 'assets/default-avatar.png'; ?>"
                            alt="<?php echo htmlspecialchars($user_name); ?>"
                            class="w-12 h-12 rounded-full border-2 border-indigo-100 shadow-sm"
                            onerror="this.src='assets/default-avatar.png'"
                            >
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-gray-900 text-lg truncate">
                                    <?php echo htmlspecialchars($user_name); ?>
                                </div>
                                <div class="text-gray-600 text-sm truncate">
                                    <?php echo htmlspecialchars($user_email); ?>
                                </div>
                                <div class="text-indigo-600 text-xs font-medium mt-1 px-2 py-1 bg-indigo-50 rounded-full inline-block">
                                    <?php echo ucfirst($_SESSION['role']); ?> Account
                                </div>
                            </div>
                        </div>
                    </div>

                    <a href="user_settings.php" class="user-dropdown-item flex items-center px-4 py-3 text-gray-700 hover:bg-gray-50 transition-colors duration-150">
                        <div class="user-dropdown-icon w-8">
                            <i class="fas fa-cog text-gray-400"></i>
                        </div>
                        <span class="ml-3 font-medium">User Settings</span>
                    </a>

                    <a href="edit_profile.php" class="user-dropdown-item flex items-center px-4 py-3 text-gray-700 hover:bg-gray-50 transition-colors duration-150">
                        <div class="user-dropdown-icon w-8">
                            <i class="fas fa-user-edit text-gray-400"></i>
                        </div>
                        <span class="ml-3 font-medium">Edit Profile</span>
                    </a>

                    <a href="change_password.php" class="user-dropdown-item flex items-center px-4 py-3 text-gray-700 hover:bg-gray-50 transition-colors duration-150">
                        <div class="user-dropdown-icon w-8">
                            <i class="fas fa-key text-gray-400"></i>
                        </div>
                        <span class="ml-3 font-medium">Change Password</span>
                    </a>

                    <div class="border-t border-gray-100 my-1"></div>

                    <a href="logout.php" class="user-dropdown-item flex items-center px-4 py-3 text-red-600 hover:bg-red-50 transition-colors duration-150 logout">
                        <div class="user-dropdown-icon w-8">
                            <i class="fas fa-sign-out-alt"></i>
                        </div>
                        <span class="ml-3 font-medium">Logout</span>
                    </a>
                </div>
            </div>

            <!-- Mobile Sidebar Toggle Button -->
            <!-- Mobile Toggle Button for Sidebar -->
            <button class="mobile-sidebar-toggle lg:hidden" id="mobileSidebarToggle">
                <i class="fa-solid fa-bars"></i>
            </button>

            <!-- Desktop Sidebar Toggle Button -->
            <button class="hidden lg:flex bg-white bg-opacity-20 hover:bg-opacity-30 text-white p-3 rounded-xl shadow-lg transition-all duration-200 backdrop-blur-sm border border-white border-opacity-20 hover:scale-105 active:scale-95 desktop-sidebar-toggle" id="desktopSidebarToggle">
                <i class="fas fa-chevron-left text-sm transition-transform duration-300" id="desktopToggleIcon"></i>
            </button>
        </div>
    </div>
</div>

<script>
    // User Profile Functions
    function toggleUserDropdown() {
        const dropdown = document.getElementById('userDropdown');
        const chevron = document.getElementById('userChevron');
        dropdown.classList.toggle('hidden');
        chevron.classList.toggle('rotate-180');
    }

    // Close user dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const userProfile = document.getElementById('userProfile');
        const dropdown = document.getElementById('userDropdown');

        if (userProfile && !userProfile.contains(event.target)) {
            dropdown.classList.add('hidden');
            document.getElementById('userChevron').classList.remove('rotate-180');
        }
    });

    // Initialize user profile click handler
    document.addEventListener('DOMContentLoaded', function() {
        const userProfile = document.getElementById('userProfile');
        if (userProfile) {
            userProfile.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleUserDropdown();
            });
        }

        // Sidebar Toggle Functionality - COMPLETELY REWRITTEN
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
                console.log('Toggle sidebar called');

                if (isMobile()) {
                    // Mobile behavior
                    const isOpen = sidebar.classList.contains('mobile-open');
                    console.log('Mobile toggle, current state:', isOpen);

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
                    console.log('Desktop toggle, current state:', isCollapsed);

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
                    console.log('Mobile toggle clicked');
                    toggleSidebar();
                });
            }

            // Desktop toggle event
            if (desktopToggle) {
                desktopToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Desktop toggle clicked');
                    toggleSidebar();
                });
            }

            // Close sidebar when overlay is clicked (mobile only)
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Overlay clicked');
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
                            console.log('Sidebar link clicked on mobile');
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
            document.addEventListener('click',
                function(e) {
                    if (isMobile() && sidebar.classList.contains('mobile-open')) {
                        const isClickInsideSidebar = sidebar.contains(e.target);
                        const isClickOnToggle = mobileToggle.contains(e.target);

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