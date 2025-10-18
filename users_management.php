<?php
require_once 'includes/init.php';

require_admin(); // Admin only

if (($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['flash_error'] = 'You are not authorized to access that page.';
    header('Location: dashboard.php');
    exit;
}

$is_officer = ($_SESSION['role'] == 'officer');

// Fetch user's name from database
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT name, profile_picture, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$user_name = $user_data['name'] ?? $_SESSION['username'];
$profile_picture = $user_data['profile_picture'] ?? '';
$user_email = $user_data['email'] ?? '';

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function csrf_check($t) {
    return hash_equals($_SESSION['csrf'] ?? '', $t ?? '');
}

// Create uploads directory if it doesn't exist
if (!file_exists('uploads')) {
    mkdir('uploads', 0777, true);
}

// Flash message system
function set_flash($msg, $type = 'info') {
    $_SESSION['flash_message'] = $msg;
    $_SESSION['flash_type'] = $type;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        http_response_code(400);
        set_flash('Security token validation failed', 'error');
        header('Location: users_management.php');
        exit;
    }

    $username = trim($_POST['username']);
    $name = trim($_POST['name']);
    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;
    $role = $_POST['role'];
    $email = trim($_POST['email'] ?? '');
    
    // Handle file upload
    $profilePicture = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['profile_picture']['tmp_name'];
        $fileName = $_FILES['profile_picture']['name'];
        $fileSize = $_FILES['profile_picture']['size'];
        $fileType = $_FILES['profile_picture']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        // Sanitize file name
        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;

        // Check if file type is allowed
        $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg');
        if (in_array($fileExtension, $allowedfileExtensions)) {
            // Directory to upload to
            $uploadFileDir = 'uploads/';
            $dest_path = $uploadFileDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $profilePicture = $dest_path;
                
                // Delete old profile picture if it exists and we're updating
                if (isset($_POST['id']) && !empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
                    unlink($user['profile_picture']);
                }
            }
        }
    }

    try {
        if (isset($_POST['id']) && $_POST['action'] == 'update') {
            // Update existing user
            if ($password) {
                if ($profilePicture) {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, name = ?, email = ?, password_hash = ?, role = ?, profile_picture = ? WHERE id = ?");
                    $stmt->execute([$username, $name, $email, $password, $role, $profilePicture, $_POST['id']]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, name = ?, email = ?, password_hash = ?, role = ? WHERE id = ?");
                    $stmt->execute([$username, $name, $email, $password, $role, $_POST['id']]);
                }
            } else {
                if ($profilePicture) {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, name = ?, email = ?, role = ?, profile_picture = ? WHERE id = ?");
                    $stmt->execute([$username, $name, $email, $role, $profilePicture, $_POST['id']]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, name = ?, email = ?, role = ? WHERE id = ?");
                    $stmt->execute([$username, $name, $email, $role, $_POST['id']]);
                }
            }
            set_flash('User updated successfully', 'success');
        } else {
            // Create new user
            if (!$password) {
                set_flash('Password is required for new users', 'error');
                header('Location: users_management.php');
                exit;
            }
            
            // Check if username already exists
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $checkStmt->execute([$username]);
            if ($checkStmt->fetch()) {
                set_flash('Username already exists', 'error');
                header('Location: users_management.php');
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO users (username, name, email, password_hash, role, profile_picture) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $name, $email, $password, $role, $profilePicture]);
            set_flash('User added successfully', 'success');
        }
        
        header('Location: users_management.php');
        exit;
        
    } catch (Exception $e) {
        set_flash('Error saving user: ' . $e->getMessage(), 'error');
        header('Location: users_management.php');
        exit;
    }
}

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    if (!isset($_GET['csrf']) || !csrf_check($_GET['csrf'])) {
        set_flash('Security token validation failed', 'error');
        header('Location: users_management.php');
        exit;
    }

    try {
        // Delete profile picture if it exists
        $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $userToDelete = $stmt->fetch();
        
        if ($userToDelete && !empty($userToDelete['profile_picture']) && file_exists($userToDelete['profile_picture'])) {
            unlink($userToDelete['profile_picture']);
        }
        
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        set_flash('User deleted successfully', 'success');
        
    } catch (Exception $e) {
        set_flash('Error deleting user: ' . $e->getMessage(), 'error');
    }
    
    header('Location: users_management.php');
    exit;
}

// Fetch users and current user for edit
$users = $pdo->query("SELECT * FROM users ORDER BY name")->fetchAll();
$user = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $user = $stmt->fetch();
}

// Get flash messages
$flash_message = $_SESSION['flash_message'] ?? null;
$flash_type = $_SESSION['flash_type'] ?? null;
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php
    $pageTitle = 'Users Management - AFAR-RHB Financial System';
    require_once 'includes/head.php';
    ?>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Modern gradient backgrounds and animations */
        .gradient-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .gradient-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .gradient-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: all 0.3s ease;
        }

        .gradient-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        /* Mobile Responsive Table */
        @media (max-width: 768px) {
            .table-responsive {
                display: block;
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .table-modern {
                min-width: 800px;
            }

            .table-modern th,
            .table-modern td {
                padding: 8px 12px;
                font-size: 0.875rem;
            }

            .table-modern thead {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }

            .table-modern tbody tr {
                transition: all 0.2s ease;
            }

            .table-modern tbody tr:hover {
                background-color: #f8fafc;
                transform: scale(1.01);
            }
        }

        /* Modern card styles */
        .modern-card {
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .modern-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        /* Input focus effects */
        .modern-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-1px);
        }

        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
        }

        /* Password input group */
        .password-input-group {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            padding: 4px;
        }

        .password-toggle:hover {
            color: #374151;
        }

        /* Role badges */
        .role-badge-admin {
            background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
            color: white;
        }

        .role-badge-officer {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-edit {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
        }

        .btn-delete {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .btn-print {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            color: white;
        }

        .btn-cancel {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            color: white;
        }

        /* Profile picture styles */
        .profile-picture-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .profile-picture-preview:hover {
            border-color: #667eea;
            transform: scale(1.05);
        }

        .file-upload {
            position: relative;
            display: inline-block;
        }

        .file-upload-input {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        /* Form center alignment */
        .form-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        /* Print styles */
        @media print {
            .no-print {
                display: none !important;
            }
            
            .print-table {
                width: 100%;
                border-collapse: collapse;
            }
            
            .print-table th,
            .print-table td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }
            
            .print-table th {
                background-color: #f8f9fa;
                font-weight: bold;
            }
            
            .print-header {
                text-align: center;
                margin-bottom: 20px;
                border-bottom: 2px solid #333;
                padding-bottom: 10px;
            }
        }

        /* Main Content Layout */
        .main-content {
            margin-left: 280px;
            transition: margin-left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 100vh;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

        /* When sidebar is collapsed on desktop */
        .sidebar.collapsed ~ .main-content {
            margin-left: 80px;
        }

        /* Mobile full width */
        @media (max-width: 1023px) {
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }

        /* Content Container */
        .content-container {
            padding: 2rem;
            width: 100%;
            box-sizing: border-box;
        }
    </style>
</head>
<body class="text-slate-700 flex bg-gray-50 min-h-screen">
    
   <?php require_once  'includes/sidebar-component.php'; ?>
   
    <!-- Main Content -->
    <div class="main-content flex-1 min-h-screen" id="mainContent">
        <?php require_once 'includes/header.php'; ?>

        <div class="p-6">
            <!-- Flash Messages -->
            <?php if ($flash_message): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const message = <?php echo json_encode($flash_message); ?>;
                    const messageType = <?php echo json_encode($flash_type); ?>;

                    const toastConfigs = {
                        success: {
                            icon: 'success',
                            title: 'Success!',
                            background: '#f0f9ff',
                            iconColor: '#10b981',
                            timer: 4000
                        },
                        error: {
                            icon: 'error',
                            title: 'Error!',
                            background: '#fef2f2',
                            iconColor: '#ef4444',
                            timer: 5000
                        },
                        warning: {
                            icon: 'warning',
                            title: 'Warning!',
                            background: '#fffbeb',
                            iconColor: '#f59e0b',
                            timer: 4500
                        },
                        info: {
                            icon: 'info',
                            title: 'Information',
                            background: '#eff6ff',
                            iconColor: '#3b82f6',
                            timer: 4000
                        }
                    };

                    const config = toastConfigs[messageType] || toastConfigs.info;

                    Swal.fire({
                        icon: config.icon,
                        title: config.title,
                        text: message,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: config.timer,
                        timerProgressBar: true,
                        background: config.background,
                        iconColor: config.iconColor,
                        customClass: {
                            popup: 'rounded-xl shadow-xl border border-gray-200'
                        },
                        didOpen: (toast) => {
                            toast.addEventListener('mouseenter', Swal.stopTimer);
                            toast.addEventListener('mouseleave', Swal.resumeTimer);
                        }
                    });
                });
            </script>
            <?php endif; ?>

            <!-- User Form Card -->
            <div class="bg-white rounded-2xl p-8 shadow-xl mb-8 border border-gray-100 modern-card">
                <h2 class="text-2xl font-bold text-slate-800 mb-6 flex items-center">
                    <i class="fas <?php echo isset($user) ? 'fa-user-edit' : 'fa-user-plus'; ?> mr-3 text-blue-500"></i>
                    <?php echo isset($user) ? 'Edit User' : 'Add New User'; ?>
                </h2>
                
                <form method="post" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
                    <?php if (isset($user)): ?>
                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                        <input type="hidden" name="action" value="update">
                    <?php endif; ?>
                    
                    <!-- Profile Picture Upload -->
                    <div class="flex flex-col items-center mb-6">
                        <div class="relative mb-4">
                            <img id="profile-preview" 
                                 src="<?php echo !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : 'assets/default-avatar.png'; ?>" 
                                 class="profile-picture-preview shadow-lg" 
                                 alt="Profile Preview"
                                 onerror="this.src='assets/default-avatar.png'">
                            <div class="absolute bottom-0 right-0 bg-white rounded-full p-2 shadow-lg">
                                <label class="file-upload bg-gradient-to-r from-blue-500 to-indigo-600 text-white p-2 rounded-full cursor-pointer hover:from-blue-600 hover:to-indigo-700 transition-all duration-200">
                                    <i class="fas fa-camera text-sm"></i>
                                    <input type="file" name="profile_picture" id="profile_picture" class="file-upload-input" accept="image/*">
                                </label>
                            </div>
                        </div>
                        <p class="text-sm text-slate-500 text-center">
                            Click the camera icon to upload a profile picture<br>
                            <span class="text-xs">Supported formats: JPG, PNG, GIF (Max: 5MB)</span>
                        </p>
                    </div>
                    
                    <!-- Form Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Username -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                <i class="fas fa-user mr-2 text-blue-500"></i>Username *
                            </label>
                            <input type="text" name="username" value="<?php echo isset($user) ? htmlspecialchars($user['username']) : ''; ?>" 
                                   required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent modern-input transition-all duration-200" 
                                   placeholder="Enter username">
                        </div>
                        
                        <!-- Full Name -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                <i class="fas fa-id-card mr-2 text-green-500"></i>Full Name *
                            </label>
                            <input type="text" name="name" value="<?php echo isset($user) ? htmlspecialchars($user['name']) : ''; ?>" 
                                   required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent modern-input transition-all duration-200" 
                                   placeholder="Enter full name">
                        </div>
                        
                        <!-- Email -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                <i class="fas fa-envelope mr-2 text-purple-500"></i>Email Address
                            </label>
                            <input type="email" name="email" value="<?php echo isset($user) ? htmlspecialchars($user['email'] ?? '') : ''; ?>" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent modern-input transition-all duration-200" 
                                   placeholder="Enter email address">
                        </div>
                        
                        <!-- Password -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                <i class="fas fa-lock mr-2 text-red-500"></i>Password <?php echo isset($user) ? '(Leave blank to keep current)' : '*' ; ?>
                            </label>
                            <div class="password-input-group">
                                <input type="password" name="password" id="password" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent modern-input transition-all duration-200 pr-10" 
                                       placeholder="<?php echo isset($user) ? 'Enter new password to change' : 'Enter password'; ?>" 
                                       <?php echo !isset($user) ? 'required' : ''; ?>>
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility()">
                                    <i class="fas fa-eye" id="password-toggle-icon"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Role -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                <i class="fas fa-user-tag mr-2 text-orange-500"></i>Role *
                            </label>
                            <select name="role" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent modern-input transition-all duration-200">
                                <option value="admin" <?php echo isset($user) && $user['role'] == 'admin' ? 'selected' : ''; ?>>Administrator</option>
                                <option value="officer" <?php echo isset($user) && $user['role'] == 'officer' ? 'selected' : ''; ?>>Officer</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Form Actions - Centered -->
                    <div class="form-actions pt-6 border-t border-gray-200">
                        <button type="submit" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-xl hover:from-blue-600 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200 font-medium shadow-lg hover:shadow-xl transform hover:scale-105 flex items-center">
                            <i class="fas <?php echo isset($user) ? 'fa-sync' : 'fa-plus'; ?> mr-2"></i>
                            <?php echo isset($user) ? 'Update User' : 'Add User'; ?>
                        </button>
                        
                        <?php if (isset($user)): ?>
                            <a href="users_management.php" class="px-6 py-3 bg-gradient-to-r from-gray-500 to-gray-600 text-white rounded-xl hover:from-gray-600 hover:to-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-all duration-200 font-medium shadow-lg hover:shadow-xl transform hover:scale-105 flex items-center no-print">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </a>
                        <?php endif; ?>
                        
                        <button type="button" onclick="printUserReport()" class="px-6 py-3 bg-gradient-to-r from-cyan-500 to-blue-500 text-white rounded-xl hover:from-cyan-600 hover:to-blue-600 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all duration-200 font-medium shadow-lg hover:shadow-xl transform hover:scale-105 flex items-center no-print">
                            <i class="fas fa-print mr-2"></i>Print Report
                        </button>
                    </div>
                </form>
            </div>

            <!-- Users List Card -->
            <div class="bg-white rounded-2xl p-6 shadow-xl border border-gray-100 modern-card">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-slate-800 flex items-center">
                        <i class="fas fa-users mr-3 text-blue-500"></i>System Users
                    </h2>
                    <span class="bg-gradient-to-r from-blue-500 to-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-full shadow-lg">
                        <i class="fas fa-user-friends mr-1"></i>
                        <?php echo count($users); ?> users
                    </span>
                </div>
                
                <!-- Search Box -->
                <div class="mb-6">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text" id="searchInput" placeholder="Search users by name, username, or role..." 
                               class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent modern-input transition-all duration-200" 
                               onkeyup="filterUsers()">
                    </div>
                </div>
                
                <div class="table-responsive overflow-x-auto custom-scrollbar">
                    <table class="min-w-full divide-y divide-gray-200 table-modern">
                        <thead class="bg-gradient-to-r from-blue-500 to-indigo-600">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Profile</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">User Details</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Role</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="usersTable">
                            <?php foreach ($users as $u): ?>
                                <tr class="hover:bg-gray-50 transition-colors duration-150">
                                    <td class="px-4 py-3 text-center">
                                        <img src="<?php echo !empty($u['profile_picture']) ? htmlspecialchars($u['profile_picture']) : 'assets/default-avatar.png'; ?>" 
                                             class="w-12 h-12 rounded-full border-2 border-gray-200 object-cover mx-auto shadow-sm"
                                             onerror="this.src='assets/default-avatar.png'"
                                             alt="Profile">
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($u['name']); ?></div>
                                        <div class="text-sm text-gray-500">@<?php echo htmlspecialchars($u['username']); ?></div>
                                        <?php if (!empty($u['email'])): ?>
                                        <div class="text-xs text-gray-400"><?php echo htmlspecialchars($u['email']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?php echo $u['role'] == 'admin' ? 'role-badge-admin' : 'role-badge-officer'; ?>">
                                            <i class="fas <?php echo $u['role'] == 'admin' ? 'fa-shield-alt' : 'fa-user-check'; ?> mr-1"></i>
                                            <?php echo ucfirst($u['role']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <i class="fas fa-circle text-green-500 mr-1" style="font-size: 6px;"></i>
                                            Active
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="action-buttons">
                                            <a href="?action=edit&id=<?php echo $u['id']; ?>" 
                                               class="px-3 py-2 btn-edit text-white rounded-lg hover:opacity-90 transition-all duration-200 shadow-sm flex items-center text-xs no-print">
                                                <i class="fas fa-edit mr-1"></i> Edit
                                            </a>
                                            <a href="#" 
                                               onclick="confirmDelete(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['name'])); ?>')" 
                                               class="px-3 py-2 btn-delete text-white rounded-lg hover:opacity-90 transition-all duration-200 shadow-sm flex items-center text-xs no-print">
                                                <i class="fas fa-trash mr-1"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Report Section (Hidden) -->
    <div id="printSection" class="hidden">
        <div class="print-header">
            <h1>Users Management Report</h1>
            <p>Generated on: <?php echo date('Y-m-d H:i:s'); ?></p>
            <p>Total Users: <?php echo count($users); ?></p>
        </div>
        <table class="print-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?php echo $u['id']; ?></td>
                    <td><?php echo htmlspecialchars($u['name']); ?></td>
                    <td><?php echo htmlspecialchars($u['username']); ?></td>
                    <td><?php echo htmlspecialchars($u['email'] ?? 'N/A'); ?></td>
                    <td><?php echo ucfirst($u['role']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Password visibility toggle
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('password-toggle-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Profile picture preview
        document.getElementById('profile_picture')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profile-preview').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

        // User search/filter
        function filterUsers() {
            const filter = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#usersTable tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        }

        // Delete confirmation with SweetAlert2
        function confirmDelete(userId, userName) {
            const csrfToken = '<?php echo $_SESSION['csrf']; ?>';
            
            Swal.fire({
                title: '<div class="flex items-center justify-center mb-4"><i class="fas fa-trash-alt text-4xl text-red-500 mr-3"></i><span class="text-2xl font-bold text-gray-800">Confirm Deletion</span></div>',
                html: `
                <div class="text-center py-4">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-exclamation-triangle text-2xl text-red-600"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Are you sure you want to delete user?</h3>
                    <p class="text-gray-600 mb-4">User: <strong>${userName}</strong></p>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-3 mt-4">
                        <p class="text-sm text-red-700 flex items-center justify-center">
                            <i class="fas fa-info-circle mr-2"></i>
                            This action cannot be undone and will permanently remove the user from the system.
                        </p>
                    </div>
                </div>
                `,
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-trash mr-2"></i>Yes, Delete User',
                cancelButtonText: '<i class="fas fa-times mr-2"></i>Cancel',
                background: '#fff',
                customClass: {
                    popup: 'rounded-2xl shadow-2xl border border-gray-200',
                    confirmButton: 'px-6 py-3 rounded-lg font-semibold',
                    cancelButton: 'px-6 py-3 rounded-lg font-semibold'
                },
                showClass: {
                    popup: 'animate__animated animate__fadeInDown'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOutUp'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: '<div class="flex items-center justify-center"><i class="fas fa-spinner fa-spin text-2xl text-blue-500 mr-3"></i><span class="text-lg">Deleting User...</span></div>',
                        text: 'Please wait while we remove the user',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        background: '#fff',
                        customClass: {
                            popup: 'rounded-2xl shadow-2xl'
                        }
                    });
                    
                    // Redirect to delete URL
                    window.location.href = `?action=delete&id=${userId}&csrf=${csrfToken}`;
                }
            });
        }

        // Print user report
        function printUserReport() {
            const printContent = document.getElementById('printSection').innerHTML;
            const originalContent = document.body.innerHTML;
            
            document.body.innerHTML = printContent;
            window.print();
            document.body.innerHTML = originalContent;
            
            // Restore event listeners
            window.location.reload();
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Any additional initialization can go here
        });
    </script>
</body>
</html>