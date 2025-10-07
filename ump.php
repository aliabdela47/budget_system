<?php
require_once 'includes/init.php';
//require_once 'includes/sidebar.php'; // This is included directly below for full context

require_admin(); // Admin only

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
            } else {
                // Handle move_uploaded_file failure if necessary
                $_SESSION['message'] = 'Failed to upload profile picture.';
                $_SESSION['message_type'] = 'error';
            }
        } else {
            $_SESSION['message'] = 'Invalid file type for profile picture. Allowed types: jpg, gif, png, jpeg.';
            $_SESSION['message_type'] = 'error';
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
        $_SESSION['message'] = 'User updated successfully';
        $_SESSION['message_type'] = 'success';
        // Redirect to clear POST data and avoid re-submission
        header('Location: users_management.php');
        exit;
    } else {
        // For new users, password is required
        if (!$password) {
            $_SESSION['message'] = 'Password is required for new users';
            $_SESSION['message_type'] = 'error';
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, name, password_hash, role, profile_picture) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $name, $password, $role, $profilePicture]);
            $_SESSION['message'] = 'User added successfully';
            $_SESSION['message_type'] = 'success';
            // Redirect to clear POST data and avoid re-submission
            header('Location: users_management.php');
            exit;
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
    $_SESSION['message'] = 'User deleted successfully';
    $_SESSION['message_type'] = 'success';
    header('Location: users_management.php');
    exit;
}

// Get message and type for flash message display
$message = $_SESSION['message'] ?? null;
$message_type = $_SESSION['message_type'] ?? 'info';
unset($_SESSION['message'], $_SESSION['message_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Include head.php -->
    <?php require_once 'includes/head.php'; ?>

    <!-- Custom CSS for this page if needed, otherwise rely on includes/head.php -->
    <style>
        /* Additional styles for password toggle button */
        .input-group {
            position: relative;
            display: flex;
            align-items: center;
            border: 1px solid #d1d5db; /* Tailwind's border-gray-300 */
            border-radius: 0.5rem; /* Tailwind's rounded-lg */
            padding: 0.5rem 0.75rem; /* Tailwind's px-2 py-1.5 equivalent for input */
            transition: all 0.2s ease-in-out;
        }
        .input-group:focus-within {
            border-color: #4f46e5; /* Tailwind's border-primary */
            box-shadow: 0 0 0 1px #4f46e5; /* Tailwind's ring-primary */
        }
        .input-group input[type="password"],
        .input-group input[type="text"],
        .input-group select {
            border: none;
            background: transparent;
            outline: none;
            width: 100%;
            padding-right: 2.5rem; /* Space for the toggle icon */
        }
        .input-group .toggle-password {
            position: absolute;
            right: 0.75rem; /* Adjust as needed */
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #64748b; /* Tailwind's text-slate-500 */
            transition: color 0.2s;
        }
        .input-group .toggle-password:hover {
            color: #4f46e5; /* Tailwind's text-primary */
        }
        .profile-picture-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e5e7eb; /* Tailwind's border-gray-200 */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .file-upload label {
            background-color: #4f46e5; /* Tailwind's bg-primary */
            color: white;
            padding: 0.6rem 0.9rem; /* Adjust padding */
            border-radius: 50%; /* Make it circular */
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: background-color 0.2s;
        }
        .file-upload label:hover {
            background-color: #4338ca; /* Tailwind's hover:bg-primary */
        }
        .file-upload-input {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            opacity: 0; cursor: pointer;
        }
    </style>
</head>
<body class="text-slate-700 flex bg-gray-100">
    
    <!-- Sidebar -->
    <?php require_once 'includes/sidebar.php'; ?>

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
                    <!-- Mobile Toggle Button -->
                    <button class="bg-slate-200 hover:bg-slate-300 text-slate-700 p-2 rounded-lg md:hidden shadow-sm" id="sidebarToggleMobile">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>

            <!-- Flash Message Display -->
            <?php if ($message): ?>
                <div id="message" class="fade-out mb-6 p-4 rounded-lg <?php echo $message_type == 'error' ? 'bg-red-100 text-red-700' : ($message_type == 'success' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'); ?>">
                    <div class="flex justify-between items-center">
                        <p><i class="fas <?php echo $message_type == 'error' ? 'fa-times-circle' : ($message_type == 'success' ? 'fa-check-circle' : 'fa-info-circle'); ?> mr-2"></i> <?php echo htmlspecialchars($message); ?></p>
                        <button onclick="document.getElementById('message').classList.add('hide')" class="text-lg">&times;</button>
                    </div>
                </div>
                <script>setTimeout(()=>{const m=document.getElementById('message');if(m){m.classList.add('hide');setTimeout(()=>m.remove(),500);}},5000);</script>
            <?php endif; ?>

            <!-- User Form -->
            <div class="bg-white rounded-xl p-6 card-hover mb-8">
                <h2 class="text-xl font-bold text-slate-800 mb-6"><?php echo $user ? 'Edit User' : 'Add New User'; ?></h2>
                
                <form method="post" enctype="multipart/form-data" class="space-y-6">
                    <?php echo csrf_field(); // Add CSRF token if you have it implemented in init.php ?>
                    <?php if ($user): ?>
                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                        <input type="hidden" name="action" value="update">
                    <?php endif; ?>
                    
                    <div class="flex flex-col items-center mb-6">
                        <div class="relative mb-4">
                            <img id="profile-preview" src="<?php echo !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : 'https://via.placeholder.com/120x120?text=Upload+Photo'; ?>" 
                                 class="profile-picture-preview shadow-md" alt="Profile Preview">
                            <div class="absolute bottom-0 right-0 bg-white rounded-full p-1 shadow-md">
                                <label class="file-upload cursor-pointer">
                                    <i class="fas fa-camera text-primary text-xl"></i> <!-- Adjusted icon color and size -->
                                    <input type="file" name="profile_picture" id="profile_picture" class="file-upload-input" accept="image/*">
                                </label>
                            </div>
                        </div>
                        <p class="text-sm text-slate-500 mt-2">Click the camera icon to upload a profile picture</p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                            <div class="input-group">
                                <input type="text" name="username" value="<?php echo $user ? htmlspecialchars($user['username']) : ''; ?>" required>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Full Name</label>
                            <div class="input-group">
                                <input type="text" name="name" value="<?php echo $user ? htmlspecialchars($user['name']) : ''; ?>" required>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                            <div class="input-group relative">
                                <input type="password" name="password" id="password-field" 
                                       placeholder="<?php echo $user ? 'Leave blank to keep current password' : 'Required for new users'; ?>" 
                                       class="pr-10"> <!-- Tailwind's pr-8 might be better for padding -->
                                <!-- Password Toggle Button -->
                                <span class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer" onclick="togglePasswordVisibility()">
                                    <i class="fas fa-eye text-slate-500" id="password-icon"></i>
                                </span>
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
                    
                    <div class="flex flex-wrap space-x-4 mt-6">
                        <button type="submit" class="btn-primary shadow-md hover:shadow-lg">
                            <i class="fas <?php echo $user ? 'fa-sync' : 'fa-plus'; ?> mr-2"></i>
                            <?php echo $user ? 'Update User' : 'Add User'; ?>
                        </button>
                        <?php if ($user): ?>
                            <a href="users_management.php" class="btn-secondary shadow-md hover:shadow-lg">
                                <i class="fas fa-times mr-2"></i> Cancel
                            </a>
                        <?php endif; ?>
                        <button type="button" class="btn-info shadow-md hover:shadow-lg" onclick="window.print()">
                            <i class="fas fa-print mr-2"></i> Print User List
                        </button>
                    </div>
                </form>
            </div>

            <!-- Existing Users -->
            <div class="bg-white rounded-xl p-6 card-hover">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <h2 class="text-xl font-bold text-slate-800">Existing Users</h2>
                    <span class="bg-blue-100 text-blue-800 text-sm font-medium px-3 py-1 rounded-full mt-2 md:mt-0">
                        <?php echo count($users); ?> users
                    </span>
                </div>
                
                <div class="overflow-x-auto rounded-lg shadow-md">
                    <table class="user-table w-full">
                        <thead>
                            <tr>
                                <th class="px-6 py-4">Profile</th>
                                <th class="px-6 py-4">ID</th>
                                <th class="px-6 py-4">Username</th>
                                <th class="px-6 py-4">Full Name</th>
                                <th class="px-6 py-4">Role</th>
                                <th class="px-6 py-4 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $index => $u): ?>
                                <tr class="hover:bg-gray-50 transition-colors duration-150">
                                    <td class="px-6 py-4 text-center">
                                        <img src="<?php echo !empty($u['profile_picture']) ? htmlspecialchars($u['profile_picture']) : 'https://via.placeholder.com/40x40?text=U'; ?>" 
                                             class="profile-picture mx-auto" alt="Profile Picture">
                                    </td>
                                    <td class="px-6 py-4 font-medium"><?php echo $u['id']; ?></td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($u['username']); ?></td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($u['name']); ?></td>
                                    <td class="px-6 py-4">
                                        <span class="role-badge <?php echo $u['role'] == 'admin' ? 'role-admin' : 'role-officer'; ?>">
                                            <?php echo htmlspecialchars($u['role']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
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
        // Function to toggle password visibility
        function togglePasswordVisibility() {
            const passwordField = document.getElementById('password-field');
            const passwordIcon = document.getElementById('password-icon');

            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                passwordIcon.classList.remove('fa-eye');
                passwordIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                passwordIcon.classList.remove('fa-eye-slash');
                passwordIcon.classList.add('fa-eye');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Sidebar Toggle Logic
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const toggleBtnDesktop = document.getElementById('sidebarToggleDesktop'); // Assumes this ID exists in sidebar.php
            const toggleBtnMobile  = document.getElementById('sidebarToggleMobile'); // Button in the header for mobile
            const overlay = document.getElementById('sidebarOverlay'); // Assumes this ID exists in sidebar.php

            const toggleSidebar = () => {
                if (sidebar && mainContent) {
                    sidebar.classList.toggle('collapsed'); // For desktop
                    sidebar.classList.toggle('active');    // For mobile
                    mainContent.classList.toggle('expanded');
                }
            };

            if (toggleBtnDesktop) toggleBtnDesktop.addEventListener('click', toggleSidebar);
            if (toggleBtnMobile)  toggleBtnMobile.addEventListener('click', toggleSidebar);
            if (overlay)          overlay.addEventListener('click', toggleSidebar); // Close sidebar when overlay is clicked

            // Profile picture preview
            const profilePictureInput = document.getElementById('profile_picture');
            const profilePreview = document.getElementById('profile-preview');
            
            if (profilePictureInput && profilePreview) {
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
