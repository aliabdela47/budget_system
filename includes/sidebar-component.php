<?php
// sidebar-component.php
// Get current page for active state and check user role
$current_page = basename($_SERVER['PHP_SELF']);
$is_admin = ($_SESSION['role'] ?? '') === 'admin';
?>
<!-- Sidebar Component -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-inner">
        <div class="sidebar-header">
            <a href="dashboard.php" class="logo">
                <div class="logo-image">
                    <img src="images/bureau-logo.png" alt="Q.A.R.D Biiro Logo" class="logo-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="logo-fallback">
                        <i class="fas fa-wallet"></i>
                    </div>
                </div>
                <span class="logo-text">Qaafiyat Biiro</span>
            </a>
            <!-- Desktop toggle lives in header; avoid duplicate here -->
        </div>
        
        <!-- Search Bar -->
        <div class="search-container">
            <div class="search-input-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="search-input" id="sidebarSearch" placeholder="Search...">
                <button class="search-clear" id="searchClear">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="search-results" id="searchResults"></div>
        </div>
        
        <div class="nav-menu">
            <!-- Dashboard Section -->
            <div class="nav-section-title">DASHBOARD</div>
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt nav-icon"></i>
                    <div class="nav-content">
                        <span class="nav-text">Dashboard</span>
                        <span class="nav-subtitle"></span>
                    </div>
                </a>
            </div>

            <!-- Budgets and Finances Section -->
            <?php if ($is_admin): ?>
            <div class="nav-section-title">BADGETS AND FINANCES</div>
            <div class="nav-item">
                <a href="budget_adding.php" class="nav-link <?php echo $current_page == 'budget_adding.php' ? 'active' : ''; ?>">
                    <i class="fas fa-money-bill-wave nav-icon"></i>
                    <div class="nav-content">
                        <span class="nav-text">BUDGETS</span>
                        <span class="nav-subtitle">Miizaniyyata</span>
                    </div>
                </a>
            </div>
            <?php endif; ?>

            <!-- Per Diem and Fuel Management Section -->
            <div class="nav-section-title">PER DIUM AND FUEL MANAGEMENT</div>
            <div class="nav-item">
                <a href="perdium.php" class="nav-link <?php echo $current_page == 'perdium.php' ? 'active' : ''; ?>">
                    <i class="fas fa-dollar-sign nav-icon"></i>
                    <div class="nav-content">
                        <span class="nav-text">PERDIUM</span>
                        <span class="nav-subtitle">Ayroh Assentah Mekla</span>
                    </div>
                </a>
            </div>

            <div class="nav-item">
                <a href="fuel_management.php" class="nav-link <?php echo $current_page == 'fuel_management.php' ? 'active' : ''; ?>">
                    <i class="fas fa-gas-pump nav-icon"></i>
                    <div class="nav-content">
                        <span class="nav-text">FUEL</span>
                        <span class="nav-subtitle">Siragle Mekla</span>
                    </div>
                </a>
            </div>

            <div class="nav-item">
                <a href="transaction.php" class="nav-link <?php echo $current_page == 'transaction.php' ? 'active' : ''; ?>">
                    <i class="fas fa-exchange-alt nav-icon"></i>
                    <div class="nav-content">
                        <span class="nav-text">GENERAL TRANSACTIONS</span>
                        <span class="nav-subtitle">Amolladi Maaliyyah Tabaatabsa</span>
                    </div>
                </a>
            </div>

            <!-- Employees and Payroll Section -->
            <?php if ($is_admin): ?>
            <div class="nav-section-title">EMPLOYEES AND PAYROL</div>
            <div class="nav-item">
                <a href="employee-registration.php" class="nav-link <?php echo $current_page == 'employee-registration.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users-cog nav-icon"></i>
                    <div class="nav-content">
                        <span class="nav-text">EMPLOYEES</span>
                        <span class="nav-subtitle">Taama Abeyniiti</span>
                    </div>
                </a>
            </div>

            <div class="nav-item">
                <a href="payroll.php" class="nav-link <?php echo $current_page == 'payroll.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-invoice-dollar nav-icon"></i>
                    <div class="nav-content">
                        <span class="nav-text">PAYROLL</span>
                        <span class="nav-subtitle">Qasbi Meklah Rorta</span>
                    </div>
                </a>
            </div>
            <?php endif; ?>

            <!-- Reports Section -->
            <div class="nav-section-title">REPORTS</div>
            <div class="nav-item">
                <a href="reports.php" class="nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar nav-icon"></i>
                    <div class="nav-content">
                        <span class="nav-text">REPORTS</span>
                        <span class="nav-subtitle">Gabbaaqu</span>
                    </div>
                </a>
            </div>

            <!-- Administration Section -->
            <?php if ($is_admin): ?>
            <div class="nav-section-title">ADMINISTRATION</div>
            <div class="nav-item">
                <a href="users_management.php" class="nav-link <?php echo $current_page == 'users_management.php' ? 'active' : ''; ?>">
                   <i class="fa-solid fa-users"></i>
                    <div class="nav-content">
                        <span class="nav-text">USERS</span>
                        <span class="nav-subtitle">Xoqoysimenit</span>
                    </div>
                </a>
            </div>

            <div class="nav-item">
                <a href="settings.php" class="nav-link <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cogs nav-icon"></i>
                    <div class="nav-content">
                        <span class="nav-text">SYSTEM SETTINGS</span>
                        <span class="nav-subtitle">Maknay Guddaaqa</span>
                    </div>
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Sidebar Footer -->
        <div class="sidebar-footer">
            <!-- Dark Mode Toggle -->
            <div class="theme-toggle-container">
                <div class="theme-toggle">
                    <i class="fas fa-sun"></i>
                    <label class="toggle-switch">
                        <input type="checkbox" id="darkModeToggle">
                        <span class="toggle-slider"></span>
                    </label>
                    <i class="fas fa-moon"></i>
                </div>
                <span class="theme-toggle-text">Dark Mode</span>
            </div>
            
            <!-- Logout Button -->
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span class="logout-text">Logout</span>
            </a>
        </div>
    </div>
</div>

<!-- Mobile Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<style>
/* CSS Variables for Theme */
:root {
    --sidebar-bg: linear-gradient(135deg, #cc2b5e, #753a88);
    --sidebar-text: #ffffff;
    --sidebar-hover: rgba(255, 255, 255, 0.15);
    --sidebar-active: rgba(255, 255, 255, 0.2);
    --sidebar-border: rgba(255, 255, 255, 0.1);
    --sidebar-section: rgba(255, 255, 255, 0.6);
    
    --main-bg: #f8f9fa;
    --main-text: #333333;
    --card-bg: #ffffff;
    --card-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}
.sidebar.collapsed .theme-toggle-container {
    justify-content: center;
}


[data-theme="dark"] {
    --main-bg: #1a1a1a;
    --main-text: #e0e0e0;
    --card-bg: #2d2d2d;
    --card-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
}

/* Ensure full height for html and body */
html, body { height: 100%; margin: 0; padding: 0; }

/* Sidebar Component Styles */
.sidebar {
    width: 280px;
    background: var(--sidebar-bg);
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 5px 0 15px rgba(0, 0, 0, 0.3);
    z-index: 1000;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.sidebar-inner { display: flex; flex-direction: column; height: 100%; overflow-y: auto; }
.sidebar.collapsed { width: 80px; }

/* Header */
.sidebar-header {
    padding: 20px;
    display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid var(--sidebar-border);
    flex-shrink: 0;
}
.logo { display: flex; align-items: center; gap: 12px; font-size: 18px; font-weight: 700; color: var(--sidebar-text); text-decoration: none; }
.logo-image { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(255, 255, 255, 0.2); transition: all 0.3s ease; }
.logo-img { width: 100%; height: 100%; object-fit: contain; padding: 4px; border-radius: 8px; }
.logo-fallback { display: none; width: 100%; height: 100%; align-items: center; justify-content: center; background: linear-gradient(135deg, #cc2b5e, #753a88); color: white; font-size: 18px; border-radius: 8px; }
.logo:hover .logo-image { background: rgba(255, 255, 255, 0.2); border-color: rgba(255, 255, 255, 0.3); transform: scale(1.05); }
.logo-text { transition: opacity 0.3s ease; white-space: nowrap; }
.sidebar.collapsed .logo-text { opacity: 0; width: 0; overflow: hidden; }

/* Search */
.search-container { padding: 15px; border-bottom: 1px solid var(--sidebar-border); position: relative; flex-shrink: 0; }
.search-input-wrapper { position: relative; display: flex; align-items: center; }
.search-icon { position: absolute; left: 12px; color: rgba(255, 255, 255, 0.7); font-size: 14px; z-index: 1; }
.search-input { width: 100%; padding: 10px 15px 10px 35px; border-radius: 6px; border: none; background: rgba(255, 255, 255, 0.1); color: var(--sidebar-text); font-size: 14px; transition: all 0.3s ease; }
.search-input::placeholder { color: rgba(255, 255, 255, 0.6); }
.search-input:focus { outline: none; background: rgba(255, 255, 255, 0.15); box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.2); }
.search-clear { position: absolute; right: 10px; background: none; border: none; color: rgba(255, 255, 255, 0.7); cursor: pointer; display: none; }
.search-results { position: absolute; top: 100%; left: 15px; right: 15px; background: var(--card-bg); border-radius: 6px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2); max-height: 300px; overflow-y: auto; z-index: 1001; display: none; }
.search-result-item { padding: 12px 15px; border-bottom: 1px solid rgba(0, 0, 0, 0.05); cursor: pointer; transition: background 0.2s ease; color: var(--main-text); }
.search-result-item:hover { background: rgba(0, 0, 0, 0.05); }
.search-result-item:last-child { border-bottom: none; }
.search-result-title { font-weight: 600; margin-bottom: 4px; }
.search-result-subtitle { font-size: 12px; color: #666; }

.sidebar.collapsed .search-container { padding: 10px; }
.sidebar.collapsed .search-input { padding: 8px 8px 8px 30px; font-size: 0; }
.sidebar.collapsed .search-input::placeholder { color: transparent; }

/* Navigation */
.nav-menu { padding: 20px 0; flex: 1; }
.nav-section-title { padding: 20px 25px 10px 25px; color: var(--sidebar-section); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; transition: opacity 0.3s ease; }
.sidebar.collapsed .nav-section-title { opacity: 0; height: 0; padding: 0; overflow: hidden; }
.nav-item { margin-bottom: 5px; }
.nav-link { display: flex; align-items: center; padding: 14px 25px; color: rgba(255, 255, 255, 0.9); text-decoration: none; transition: all 0.3s ease; font-size: 14px; font-weight: 500; border-left: 3px solid transparent; position: relative; }
.nav-link:hover { background: var(--sidebar-hover); color: #fff; border-left: 3px solid #fff; transform: translateX(5px); }
.nav-link.active { background: var(--sidebar-active); color: #fff; border-left: 3px solid #fff; font-weight: 600; }
.nav-icon { font-size: 16px; margin-right: 15px; width: 20px; text-align: center; transition: transform 0.3s ease; }
.nav-link:hover .nav-icon { transform: scale(1.1); }
.nav-content { display: flex; flex-direction: column; flex: 1; transition: opacity 0.3s ease; overflow: hidden; }
.nav-text { font-weight: 600; margin-bottom: 2px; white-space: nowrap; }
.nav-subtitle { font-size: 11px; opacity: 0.8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sidebar.collapsed .nav-content { opacity: 0; width: 0; overflow: hidden; }
.sidebar.collapsed .nav-link { justify-content: center; padding: 16px 0; }
.sidebar.collapsed .nav-icon { margin-right: 0; font-size: 18px; }

/* Footer */
.sidebar-footer { padding: 20px; border-top: 1px solid var(--sidebar-border); margin-top: auto; flex-shrink: 0; }
.theme-toggle-container { display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; }
.theme-toggle { display: flex; align-items: center; gap: 8px; color: var(--sidebar-text); }
.toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(255, 255, 255, 0.2); transition: .4s; border-radius: 24px; }
.toggle-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
input:checked + .toggle-slider { background-color: rgba(255, 255, 255, 0.5); }
input:checked + .toggle-slider:before { transform: translateX(20px); }
.theme-toggle-text { color: var(--sidebar-text); font-size: 14px; transition: opacity 0.3s ease; }
.sidebar.collapsed .theme-toggle-text { opacity: 0; width: 0; overflow: hidden; }

.logout-btn { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; padding: 12px; background: rgba(255, 255, 255, 0.1); color: var(--sidebar-text); text-decoration: none; border-radius: 6px; transition: all 0.3s ease; font-weight: 500; border: none; cursor: pointer; }
.logout-btn:hover { background: rgba(255, 255, 255, 0.2); transform: translateY(-2px); }
.logout-text { transition: opacity 0.3s ease; }
.sidebar.collapsed .logout-text { opacity: 0; width: 0; overflow: hidden; }

/* Adjust logo in collapsed state */
.sidebar.collapsed .logo-image { width: 35px; height: 35px; margin: 0 auto; }
.sidebar.collapsed .logo { justify-content: center; gap: 0; }

/* Mobile Overlay */
.sidebar-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(5px);
    z-index: 999; display: none; opacity: 0; transition: opacity 0.3s ease;
}
.sidebar-overlay.active { display: block; opacity: 1; }

/* Mobile Styles */
@media (max-width: 1023px) {
    .sidebar { transform: translateX(-100%); width: 280px; }
    .sidebar.mobile-open { transform: translateX(0); }
    .sidebar .logo-text, .sidebar .nav-text { opacity: 1; width: auto; }
    .sidebar .nav-link { justify-content: flex-start; padding: 14px 25px; }
    .sidebar .nav-icon { margin-right: 15px; }
    .sidebar-overlay.active { display: block; }
    body.sidebar-open { overflow: hidden; }
}

/* Desktop specific */
@media (min-width: 1024px) {
    .sidebar-overlay { display: none !important; }
}

/* Scrollbar Styling for sidebar-inner */
.sidebar-inner::-webkit-scrollbar { width: 4px; }
.sidebar-inner::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.1); border-radius: 2px; }
.sidebar-inner::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.3); border-radius: 2px; }
.sidebar-inner::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.5); }

/* Resize Handle */
.sidebar-resize-handle {
    position: absolute; top: 0; right: 0; width: 5px; height: 100%; cursor: col-resize; z-index: 1001;
}
.sidebar-resize-handle:hover, .sidebar-resize-handle.resizing { background: rgba(255, 255, 255, 0.2); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const mainContent = document.getElementById('mainContent');
    const darkModeToggle = document.getElementById('darkModeToggle');
    const searchInput = document.getElementById('sidebarSearch');
    const searchClear = document.getElementById('searchClear');
    const searchResults = document.getElementById('searchResults');

    // Dark Mode Toggle
    if (darkModeToggle) {
        const savedTheme = localStorage.getItem('theme') || 'light';
        if (savedTheme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            darkModeToggle.checked = true;
        }
        darkModeToggle.addEventListener('change', function() {
            if (this.checked) {
                document.documentElement.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
            } else {
                document.documentElement.setAttribute('data-theme', 'light');
                localStorage.setItem('theme', 'light');
            }
        });
    }

    // Search data (example)
    const searchData = [
        { title: "Dashboard", subtitle: "", link: "dashboard.php", section: "DASHBOARD" },
        { title: "BUDGETS", subtitle: "Miizaniyyata", link: "budget_adding.php", section: "BADGETS AND FINANCES" },
        { title: "PERDIUM", subtitle: "Ayroh Assentah Mekla", link: "perdium.php", section: "PER DIUM AND FUEL MANAGEMENT" },
        { title: "FUEL", subtitle: "Siragle Mekla", link: "fuel_management.php", section: "PER DIUM AND FUEL MANAGEMENT" },
        { title: "GENERAL TRANSACTIONS", subtitle: "Amolladi Maaliyyah Tabaatabsa", link: "transaction.php", section: "PER DIUM AND FUEL MANAGEMENT" },
        { title: "EMPLOYEES", subtitle: "Taama Abeyniiti", link: "employee-registration.php", section: "EMPLOYEES AND PAYROL" },
        { title: "PAYROLL", subtitle: "Qasbi Meklah Rorta", link: "payroll.php", section: "EMPLOYEES AND PAYROL" },
        { title: "REPORTS", subtitle: "Gabbaaqu", link: "reports.php", section: "REPORTS" },
        { title: "USERS", subtitle: "Xoqoysimenit", link: "users_management.php", section: "ADMINISTRATION" },
        { title: "SYSTEM SETTINGS", subtitle: "Maknay Guddaaqa", link: "settings.php", section: "ADMINISTRATION" }
    ];

    // Search functionality
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            if (query.length > 0) {
                searchClear.style.display = 'flex';
                const results = searchData.filter(item =>
                    item.title.toLowerCase().includes(query) ||
                    item.subtitle.toLowerCase().includes(query) ||
                    item.section.toLowerCase().includes(query)
                );
                if (results.length > 0) {
                    searchResults.innerHTML = '';
                    results.forEach(result => {
                        const resultItem = document.createElement('div');
                        resultItem.className = 'search-result-item';
                        resultItem.innerHTML = `
                            <div class="search-result-title">${result.title}</div>
                            <div class="search-result-subtitle">${result.subtitle}</div>
                        `;
                        resultItem.addEventListener('click', function() {
                            window.location.href = result.link;
                        });
                        searchResults.appendChild(resultItem);
                    });
                    searchResults.style.display = 'block';
                } else {
                    searchResults.innerHTML = '<div class="search-result-item">No results found</div>';
                    searchResults.style.display = 'block';
                }
            } else {
                searchClear.style.display = 'none';
                searchResults.style.display = 'none';
            }
        });

        if (searchClear) {
            searchClear.addEventListener('click', function() {
                searchInput.value = '';
                searchResults.style.display = 'none';
                this.style.display = 'none';
                searchInput.focus();
            });
        }

        document.addEventListener('click', function(event) {
            if (!searchInput.contains(event.target) && !searchResults.contains(event.target)) {
                searchResults.style.display = 'none';
            }
        });
    }

    // Resize functionality (desktop)
    const resizeHandle = document.createElement('div');
    resizeHandle.className = 'sidebar-resize-handle';
    sidebar.appendChild(resizeHandle);

    let isResizing = false;
    resizeHandle.addEventListener('mousedown', function(e) {
        isResizing = true;
        document.body.style.cursor = 'col-resize';
        document.body.style.userSelect = 'none';
        resizeHandle.classList.add('resizing');
        e.preventDefault();
    });

    document.addEventListener('mousemove', function(e) {
        if (!isResizing) return;
        const newWidth = e.clientX;
        if (newWidth > 200 && newWidth < 500) {
            sidebar.style.width = newWidth + 'px';
            // Let header script handle main content margin via class; avoid inline margin-hacks here
        }
    });

    document.addEventListener('mouseup', function() {
        if (isResizing) {
            isResizing = false;
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            resizeHandle.classList.remove('resizing');
        }
    });
});
</script>