<?php
require_once 'includes/init.php';
require_once 'includes/sidebar.php';

require_admin(); // Admin only
// session_start();
// include 'includes/db.php';
//require_once 'includes/functions.php';
//require_once DIR . '/includes/functions.php';
if (($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['flash_error'] = 'You are not authorized to access that page.';
    header('Location: dashboard.php');
    exit;
}


$is_officer = ($_SESSION['role'] == 'officer');

// Fetch user's name from database
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$user_name = $user_data['name'] ?? $_SESSION['username'];

// Create uploads directory if it doesn't exist
if (!file_exists('uploads')) {
    mkdir('uploads', 0777, true);
}

$users = $pdo->query("SELECT * FROM users")->fetchAll();
$user = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $user = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $name = $_POST['name'];
    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;
    $role = $_POST['role'];
    
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

    if (isset($_POST['id']) && $_POST['action'] == 'update') {
        if ($password) {
            if ($profilePicture) {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, name = ?, password_hash = ?, role = ?, profile_picture = ? WHERE id = ?");
                $stmt->execute([$username, $name, $password, $role, $profilePicture, $_POST['id']]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, name = ?, password_hash = ?, role = ? WHERE id = ?");
                $stmt->execute([$username, $name, $password, $role, $_POST['id']]);
            }
        } else {
            if ($profilePicture) {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, name = ?, role = ?, profile_picture = ? WHERE id = ?");
                $stmt->execute([$username, $name, $role, $profilePicture, $_POST['id']]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, name = ?, role = ? WHERE id = ?");
                $stmt->execute([$username, $name, $role, $_POST['id']]);
            }
        }
        $message = 'User updated successfully';
    } else {
        // For new users, password is required
        if (!$password) {
            $message = 'Password is required for new users';
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, name, password_hash, role, profile_picture) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $name, $password, $role, $profilePicture]);
            $message = 'User added successfully';
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    // Delete profile picture if it exists
    $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $userToDelete = $stmt->fetch();
    
    if ($userToDelete && !empty($userToDelete['profile_picture']) && file_exists($userToDelete['profile_picture'])) {
        unlink($userToDelete['profile_picture']);
    }
    
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $message = 'User deleted successfully';
    header('Location: users_management.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Users Management - Budget System</title>
    <script src="css/tailwind.css"></script>
    <link rel="stylesheet" href="css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4f46e5',
                        secondary: '#7c3aed',
                        light: '#f8fafc',
                        lighter: '#f1f5f9',
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            min-height: 100vh;
            color: #334155;
        }
        
        .ethiopic {
            font-family: 'Noto Sans Ethiopic', sans-serif;
        }
        
        .card-hover {
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .card-hover:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .sidebar {
            width: 260px;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            z-index: 1000;
            background: linear-gradient(180deg, #4f46e5 0%, #7c3aed 100%);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            color: white;
            transition: transform 0.3s ease;
        }
        
        .sidebar.collapsed {
            transform: translateX(-260px);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-260px);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
        }
        
        .main-content {
            transition: margin-left 0.3s ease;
            margin-left: 260px;
            width: calc(100% - 260px);
        }
        
        .main-content.expanded {
            margin-left: 0;
            width: 100%;
        }
        
        .input-group {
            transition: all 0.3s ease;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.5rem 0.75rem;
            display: flex;
            align-items: center;
        }
        
        .input-group:focus-within {
            transform: translateY(-2px);
            border-color: #4f46e5;
            box-shadow: 0 0 0 1px #4f46e5;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        .shake {
            animation: shake 0.5s;
        }
        
        input, select, textarea {
            outline: none;
            width: 100%;
            background: transparent;
        }
        
        .btn-primary {
            background-color: #4f46e5;
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .btn-primary:hover {
            background-color: #4338ca;
        }
        
        .btn-secondary {
            background-color: #6b7280;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .btn-secondary:hover {
            background-color: #4b5563;
        }
        
        .btn-danger {
            background-color: #ef4444;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .btn-danger:hover {
            background-color: #dc2626;
        }
        
        .btn-info {
            background-color: #06b6d4;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .btn-info:hover {
            background-color: #0891b2;
        }
        
        .profile-picture {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .profile-picture-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e5e7eb;
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
        
        /* Enhanced table styles */
        .user-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .user-table th {
            background-color: #4f46e5;
            color: white;
            padding: 0.75rem 1.5rem;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }
        
        .user-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .user-table tr:last-child td {
            border-bottom: none;
        }
        
        .user-table tr:hover {
            background-color: #f8fafc;
        }
        
        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .role-admin {
            background-color: #ede9fe;
            color: #5b21b6;
        }
        
        .role-officer {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .action-button {
            padding: 0.5rem;
            border-radius: 0.375rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .edit-button {
            background-color: #e0e7ff;
            color: #3730a3;
        }
        
        .edit-button:hover {
            background-color: #c7d2fe;
        }
        
        .delete-button {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .delete-button:hover {
            background-color: #fecaca;
        }
    </style>
</head>
<body class="text-slate-700 flex">
    <!-- Sidebar -->
   

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="p-6">
            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 p-6 bg-white rounded-xl shadow-sm">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-slate-800">
                        Users Management
                    </h1>
                    <p class="text-slate-600 mt-2">Add and manage system users</p>
                </div>
                <div class="flex items-center space-x-4 mt-4 md:mt-0">
                    <button class="bg-slate-200 hover:bg-slate-300 text-slate-700 p-2 rounded-lg md:hidden shadow-sm" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>

            <!-- User Form -->
            <div class="bg-white rounded-xl p-6 card-hover mb-8">
                <h2 class="text-xl font-bold text-slate-800 mb-6">User Form</h2>
                <?php if (isset($message)): ?>
                    <div class="bg-blue-50 text-blue-700 p-4 rounded-lg mb-6">
                        <i class="fas fa-info-circle mr-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                <form method="post" enctype="multipart/form-data" class="space-y-6">
                    <?php if ($user): ?>
                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                        <input type="hidden" name="action" value="update">
                    <?php endif; ?>
                    
                    <div class="flex flex-col items-center mb-6">
                        <div class="relative mb-4">
                            <img id="profile-preview" src="<?php echo !empty($user['profile_picture']) ? $user['profile_picture'] : 'https://via.placeholder.com/120x120?text=Upload+Photo'; ?>" 
                                 class="profile-picture-preview shadow-md" alt="Profile Preview">
                            <div class="absolute bottom-0 right-0 bg-white rounded-full p-1 shadow-md">
                                <label class="file-upload bg-primary text-white p-2 rounded-full cursor-pointer">
                                    <i class="fas fa-camera"></i>
                                    <input type="file" name="profile_picture" id="profile_picture" class="file-upload-input" accept="image/*">
                                </label>
                            </div>
                        </div>
                        <p class="text-sm text-slate-500">Click the camera icon to upload a profile picture</p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                            <div class="input-group">
                                <input type="text" name="username" value="<?php echo $user ? $user['username'] : ''; ?>" required>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Full Name</label>
                            <div class="input-group">
                                <input type="text" name="name" value="<?php echo $user ? $user['name'] : ''; ?>" required>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                            <div class="input-group">
                                <input type="password" name="password" placeholder="<?php echo $user ? 'Leave blank to keep current password' : 'Required for new users'; ?>">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Role</label>
                            <div class="input-group">
                                <select name="role" required>
                                    <option value="admin" <?php echo $user && $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="officer" <?php echo $user && $user['role'] == 'officer' ? 'selected' : ''; ?>>Officer</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex space-x-4 mt-6">
                        <button type="submit" class="btn-primary">
                            <i class="fas <?php echo $user ? 'fa-sync' : 'fa-plus'; ?> mr-2"></i>
                            <?php echo $user ? 'Update User' : 'Add User'; ?>
                        </button>
                        <?php if ($user): ?>
                            <a href="users_management.php" class="btn-secondary">
                                <i class="fas fa-times mr-2"></i> Cancel
                            </a>
                        <?php endif; ?>
                        <button type="button" class="btn-info" onclick="window.print()">
                            <i class="fas fa-print mr-2"></i> Print
                        </button>
                    </div>
                </form>
            </div>

            <!-- Existing Users -->
            <div class="bg-white rounded-xl p-6 card-hover">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-slate-800">Existing Users</h2>
                    <span class="bg-blue-100 text-blue-800 text-sm font-medium px-3 py-1 rounded-full">
                        <?php echo count($users); ?> users
                    </span>
                </div>
                
                <div class="overflow-x-auto rounded-lg shadow">
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>Profile</th>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Role</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $index => $u): ?>
                                <tr>
                                    <td class="text-center">
                                        <img src="<?php echo !empty($u['profile_picture']) ? $u['profile_picture'] : 'https://via.placeholder.com/40x40?text=U'; ?>" 
                                             class="profile-picture mx-auto" alt="Profile Picture">
                                    </td>
                                    <td class="font-medium"><?php echo $u['id']; ?></td>
                                    <td><?php echo htmlspecialchars($u['username']); ?></td>
                                    <td><?php echo htmlspecialchars($u['name']); ?></td>
                                    <td>
                                        <span class="role-badge <?php echo $u['role'] == 'admin' ? 'role-admin' : 'role-officer'; ?>">
                                            <?php echo $u['role']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons justify-center">
                                            <a href="?action=edit&id=<?php echo $u['id']; ?>" class="action-button edit-button" title="Edit User">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?action=delete&id=<?php echo $u['id']; ?>" class="action-button delete-button" title="Delete User" onclick="return confirm('Are you sure you want to delete this user?')">
                                                <i class="fas fa-trash"></i>
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

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const toggleBtn = document.getElementById('sidebarToggle');
            
            if (toggleBtn && sidebar) {
                toggleBtn.addEventListener('click', () => {
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('expanded');
                });
            }
            
            // Profile picture preview
            const profilePictureInput = document.getElementById('profile_picture');
            const profilePreview = document.getElementById('profile-preview');
            
            if (profilePictureInput) {
                profilePictureInput.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.addEventListener('load', function() {
                            profilePreview.src = reader.result;
                        });
                        reader.readAsDataURL(file);
                    }
                });
            }
        });

        // Simple confirmation for delete actions
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButtons = document.querySelectorAll('a[href*="action=delete"]');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to delete this user?')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>
