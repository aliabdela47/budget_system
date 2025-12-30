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
                    <i class="fas fa-plus-circle"></i> Budgets
                </a>
            </li>
            <?php if ($_SESSION['role'] == 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings_owners.php">
                    <i class="fas fa-building"></i> Settings
                </a>
            </li>
            <?php if ($_SESSION['role'] == 'admin'): ?>
            <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users_management.php' ? 'active' : ''; ?>" href="users_management.php">
                <i class="fas fa-users"></i> Users
            </a>
        </li>
        <?php endif; ?>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'perdium.php' ? 'active' : ''; ?>" href="perdium.php">
                <i class="fas fa-dollar-sign"></i> Perdium
                </a>
        </li>
         <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'fuel_management.php' ? 'active' : ''; ?>" href="fuel_management.php">
                <i class="fas fa-gas-pump"></i> Fuel
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'transaction.php' ? 'active' : ''; ?>" href="transaction.php">
                <i class="fas fa-exchange-alt"></i> Transactions
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </li>
    </ul>
    
        <!-- In your sidebar.php file -->
<li>
    <a href="budget_selection.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10">
        <i class="fas fa-exchange-alt w-5"></i>
        <span class="ml-3">Change Budget Type</span>
    </a>
</li>
      
</div>