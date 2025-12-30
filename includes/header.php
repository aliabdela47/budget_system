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
<div class="w-full px-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 p-6 rounded-2xl gradient-header text-white shadow-xl transform transition-all duration-300" id="mainHeader">
        <div class="flex-1 w-full">
            <h2 class="text-2xl md:text-3xl font-bold mb-2 flex items-center">
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
                <div class="flex items-center space-x-3 modern-card bg-white bg-opacity-10 rounded-xl p-3 backdrop-blur-sm border border-white border-opacity-20 cursor-pointer hover:bg-opacity-20 transition-all">
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
            <button class="mobile-sidebar-toggle lg:hidden bg-white bg-opacity-20 hover:bg-opacity-30 text-white p-3 rounded-xl shadow-lg transition-all backdrop-blur-sm border border-white border-opacity-20" id="mobileSidebarToggle" type="button" aria-label="Open sidebar">
                <i class="fa-solid fa-bars"></i>
            </button>

            <!-- Desktop Sidebar Toggle Button (Header shortcut) -->
            <button class="hidden lg:flex bg-white bg-opacity-20 hover:bg-opacity-30 text-white p-3 rounded-xl shadow-lg transition-all duration-200 backdrop-blur-sm border border-white border-opacity-20" id="headerDesktopSidebarToggle" type="button" aria-label="Collapse sidebar">
                <i class="fas fa-chevron-left text-sm transition-transform duration-300" id="desktopToggleIcon"></i>
            </button>
        </div>
    </div>
</div>

<script>
// User Profile Dropdown
function toggleUserDropdown() {
    // ... (rest of dropdown logic is fine)
}
document.addEventListener('click', function(event) {
    // ... (rest of dropdown logic is fine)
});

document.addEventListener('DOMContentLoaded', function() {
    const userProfile = document.getElementById('userProfile');
    if (userProfile) {
        userProfile.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleUserDropdown();
        });
    }

    // === START OF SIDEBAR FIX ===
    const mobileToggle = document.getElementById('mobileSidebarToggle');
    const desktopHeaderToggle = document.getElementById('headerDesktopSidebarToggle');
    
    // THIS IS THE CORRECTED LINE:
    const sidebar = document.getElementById('sidebar'); 
    
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const mainContent = document.getElementById('mainContent');
    const desktopIcon = document.getElementById('desktopToggleIcon');

    const isMobile = () => window.innerWidth < 1024;

    // Check if elements exist before adding listeners
    if (mobileToggle && sidebar && sidebarOverlay) {
        mobileToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            sidebar.classList.add('mobile-open');
            sidebarOverlay.classList.add('active');
            document.body.classList.add('sidebar-open');
        });
    }

    if (desktopHeaderToggle && sidebar) {
        desktopHeaderToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (isMobile()) return; // ignore on mobile
            
            const willCollapse = !sidebar.classList.contains('collapsed');
            sidebar.classList.toggle('collapsed', willCollapse);
            
            if (mainContent) {
                // Ensure mainContent ID exists on your main content wrapper
                mainContent.classList.toggle('sidebar-collapsed', willCollapse);
            }
            if (desktopIcon) {
                desktopIcon.classList.toggle('rotate-180', willCollapse);
            }
        });
    }

    if (sidebarOverlay && sidebar) {
        sidebarOverlay.addEventListener('click', function(e) {
            e.preventDefault();
            if (isMobile()) {
                sidebar.classList.remove('mobile-open');
                sidebarOverlay.classList.remove('active');
                document.body.classList.remove('sidebar-open');
            }
        });
    }

    window.addEventListener('resize', function() {
        if (!isMobile()) {
            sidebar.classList.remove('mobile-open');
            sidebarOverlay && sidebarOverlay.classList.remove('active');
            document.body.classList.remove('sidebar-open');
        }
    });
    // === END OF SIDEBAR FIX ===
});
</script>