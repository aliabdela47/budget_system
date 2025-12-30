<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-wallet fa-2x"></i>
        <h3>Budget System</h3>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <?php if ($_SESSION['role'] == 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'budget_adding.php' ? 'active' : ''; ?>" href="budget_adding.php">
                    <i class="fas fa-plus-circle"></i> Budget Adding
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings_owners.php' ? 'active' : ''; ?>" href="settings_owners.php">
                    <i class="fas fa-building"></i> Settings Owners
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings_codes.php' ? 'active' : ''; ?>" href="settings_codes.php">
                    <i class="fas fa-code"></i> Settings Codes
                </a>
            </li>
        <?php endif; ?>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'transaction.php' ? 'active' : ''; ?>" href="transaction.php">
                <i class="fas fa-exchange-alt"></i> Transaction
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'fuel_management.php' ? 'active' : ''; ?>" href="fuel_management.php">
                <i class="fas fa-gas-pump"></i> Fuel Management
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users_management.php' ? 'active' : ''; ?>" href="users_management.php">
                <i class="fas fa-users"></i> Users Management
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </li>
    </ul>
</div>

<div class="main-content">
    <button class="btn btn-primary sidebar-toggle d-md-none" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>