<?php
require_once 'includes/init.php';

// CSRF token generation
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Helper function to check CSRF token
function csrf_check($token) {
    return hash_equals($_SESSION['csrf'] ?? '', $token ?? '');
}

// Flash message helpers
if (!function_exists('set_flash')) {
    function set_flash($msg, $type = 'info') {
        $_SESSION['flash_message'] = $msg;
        $_SESSION['flash_type'] = $type;
    }
}

// User Authentication Check
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: login.php');
    exit;
}

// Check if user is admin for edit/delete permissions
$is_admin = (($_SESSION['role'] ?? '') === 'admin');

// Fetch user data for header
$stmt = $pdo->prepare("SELECT name, profile_picture, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$user_name = $user_data['name'] ?? ($_SESSION['username'] ?? 'User');
$profile_picture = $user_data['profile_picture'] ?? '';
$user_email = $user_data['email'] ?? '';

// Fetch departments from budget_owners table
$departments = $pdo->query("SELECT DISTINCT name FROM budget_owners ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

// Handle POST request for new employee registration and updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        set_flash('Invalid CSRF token. Please try again.', 'error');
        header('Location: employee-registration.php');
        exit;
    }

    // Sanitize and validate inputs
    $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
    $is_update = ($_POST['action'] ?? '') === 'update' && $id;
    
    $name = trim($_POST['name'] ?? '');
    $name_am = trim($_POST['name_am'] ?? '');
    $salary = filter_input(INPUT_POST, 'salary', FILTER_VALIDATE_FLOAT);
    $taamagoli = trim($_POST['taamagoli'] ?? '');
    $directorate = trim($_POST['directorate'] ?? '');
    $photo_path = null;

    // Basic Validation
    if (empty($name) || empty($taamagoli) || empty($directorate) || $salary === false || $salary <= 0) {
        set_flash('Please fill all required fields with valid data.', 'error');
    } else {
        try {
            // Handle file upload
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/employee_photos/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $file_info = new SplFileInfo($_FILES['photo']['name']);
                $extension = strtolower($file_info->getExtension());
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

                if (in_array($extension, $allowed_extensions)) {
                    if ($_FILES['photo']['size'] <= 5 * 1024 * 1024) { // 5MB limit
                        $unique_filename = uniqid('emp_', true) . '.' . $extension;
                        $destination = $upload_dir . $unique_filename;

                        if (move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                            $photo_path = $destination;
                        } else {
                            throw new Exception('Failed to move uploaded file.');
                        }
                    } else {
                        throw new Exception('File is too large. Maximum size is 5MB.');
                    }
                } else {
                    throw new Exception('Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.');
                }
            }

            $pdo->beginTransaction();

            if ($is_update) {
                // Update existing employee
                if ($photo_path) {
                    // Get old photo path to delete it
                    $old_photo_stmt = $pdo->prepare("SELECT photo FROM emp_list WHERE id = ?");
                    $old_photo_stmt->execute([$id]);
                    $old_photo = $old_photo_stmt->fetchColumn();
                    
                    $sql = "UPDATE emp_list SET name = ?, name_am = ?, salary = ?, taamagoli = ?, directorate = ?, photo = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$name, $name_am, $salary, $taamagoli, $directorate, $photo_path, $id]);
                    
                    // Delete old photo file
                    if ($old_photo && file_exists($old_photo)) {
                        unlink($old_photo);
                    }
                } else {
                    $sql = "UPDATE emp_list SET name = ?, name_am = ?, salary = ?, taamagoli = ?, directorate = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$name, $name_am, $salary, $taamagoli, $directorate, $id]);
                }
                set_flash('Employee updated successfully!', 'success');
            } else {
                // Insert new employee
                $sql = "INSERT INTO emp_list (name, name_am, salary, taamagoli, directorate, photo, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $name,
                    $name_am,
                    $salary,
                    $taamagoli,
                    $directorate,
                    $photo_path,
                    date('Y-m-d')
                ]);
                set_flash('Employee registered successfully!', 'success');
            }

            $pdo->commit();
            header('Location: employee-registration.php');
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('Error: ' . $e->getMessage(), 'error');
        }
    }
}

// Handle DELETE request
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (!$is_admin) {
        set_flash('You do not have permission to delete employees.', 'error');
        header('Location: employee-registration.php');
        exit;
    }
    
    $delete_id = (int)$_GET['id'];
    try {
        $pdo->beginTransaction();
        
        // Get photo path to delete file
        $photo_stmt = $pdo->prepare("SELECT photo FROM emp_list WHERE id = ?");
        $photo_stmt->execute([$delete_id]);
        $photo_path = $photo_stmt->fetchColumn();
        
        // Delete employee
        $delete_stmt = $pdo->prepare("DELETE FROM emp_list WHERE id = ?");
        $delete_stmt->execute([$delete_id]);
        
        // Delete photo file if exists
        if ($photo_path && file_exists($photo_path)) {
            unlink($photo_path);
        }
        
        $pdo->commit();
        set_flash('Employee deleted successfully!', 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('Error deleting employee: ' . $e->getMessage(), 'error');
    }
    header('Location: employee-registration.php');
    exit;
}

// Fetch employee for editing
$edit_employee = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM emp_list WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_employee = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch flash messages
$flash_message = $_SESSION['flash_message'] ?? null;
$flash_type = $_SESSION['flash_type'] ?? null;
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php
    $pageTitle = 'Employee Registration';
    require_once 'includes/head.php';
    ?>
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

            /* Modern table styles */
            .table-modern {
                min-width: 800px;
                /* Minimum table width for mobile */
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

            /* Card hover effects */
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
            /* Responsive text handling */
            .employee-name-cell {
                max-width: 150px;
                min-width: 120px;
            }

            .owner-name-cell {
                max-width: 120px;
                min-width: 100px;
            }

            .city-name-cell {
                max-width: 100px;
                min-width: 80px;
            }

            .actions-cell {
                min-width: 140px;
            }
        }

        /* Better table cell handling */
        .employee-name-cell {
            white-space: normal !important;
            word-wrap: break-word;
            max-width: 200px;
        }

        .owner-name-cell {
            white-space: normal !important;
            word-wrap: break-word;
        }

        .city-name-cell {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Pulse animation for important elements */
        @keyframes gentle-pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        .pulse-gentle {
            animation: gentle-pulse 2s infinite;
        }

        /* Glass morphism effect */
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* User Profile Styles */
        .user-profile {
            position: relative;
            cursor: pointer;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }

        .user-avatar:hover {
            border-color: rgba(255, 255, 255, 0.6);
            transform: scale(1.05);
        }

        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 280px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }

        .user-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .user-dropdown-header {
            padding: 20px;
            border-bottom: 1px solid #f3f4f6;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px 16px 0 0;
        }

        .user-dropdown-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            color: #4b5563;
            text-decoration: none;
            transition: all 0.2s ease;
            border-bottom: 1px solid #f9fafb;
        }

        .user-dropdown-item:hover {
            background: #f8fafc;
            color: #1f2937;
        }

        .user-dropdown-item:last-child {
            border-bottom: none;
            border-radius: 0 0 16px 16px;
        }

        .user-dropdown-item.logout {
            color: #ef4444;
        }

        .user-dropdown-item.logout:hover {
            background: #fef2f2;
            color: #dc2626;
        }

        .user-dropdown-icon {
            width: 20px;
            margin-right: 12px;
            text-align: center;
        }

        /* Employee Registration Specific Styles */
        .photo-preview {
            width: 128px;
            height: 128px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .photo-preview-placeholder {
            width: 128px;
            height: 128px;
            border-radius: 50%;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid #fff;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .ethio-font {
            font-family: 'Nyala', 'Abyssinica SIL', 'GF Zemen', sans-serif;
        }
        .employee-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .employee-card h3 {
            margin: 0 0 15px 0;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .employee-card-content {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .employee-card-photo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255,255,255,0.3);
        }
        .employee-card-details {
            flex: 1;
        }
        .employee-card-name {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .employee-card-position {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        .employee-card-department {
            font-size: 0.9rem;
            opacity: 0.8;
            margin-bottom: 5px;
        }
        .employee-card-salary {
            font-size: 1rem;
            font-weight: 600;
            opacity: 0.9;
        }
        .row-selected {
            background-color: #e3f2fd !important;
            border-left: 4px solid #2196f3;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .action-btn {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            transition: all 0.2s ease;
        }
        .btn-edit {
            background-color: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
        }
        .btn-edit:hover {
            background-color: #bbdefb;
        }
        .btn-delete {
            background-color: #ffebee;
            color: #d32f2f;
            border: 1px solid #ffcdd2;
        }
        .btn-delete:hover {
            background-color: #ffcdd2;
        }
        .btn-print {
            background-color: #e8f5e9;
            color: #388e3c;
            border: 1px solid #c8e6c9;
        }
        .btn-print:hover {
            background-color: #c8e6c9;
        }
        .select2-container--classic .select2-selection--single {
            height: 48px;
            border: 1px solid #d1d5db;
            border-radius: 12px;
        }
        .select2-container--classic .select2-selection--single .select2-selection__rendered {
            line-height: 48px;
            padding-left: 16px;
        }
        .select2-container--classic .select2-selection--single .select2-selection__arrow {
            height: 46px;
        }
        .info-card {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        /* Ensure sidebar covers full viewport height */
        .sidebar {
            height: 100vh !important;
            overflow-y: auto;
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

        /* Welcome animations */
        @keyframes gentle-bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }

        .welcome-bounce {
            animation: gentle-bounce 2s infinite;
        }

        /* Gradient text effect */
        .gradient-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Pulse animation for welcome elements */
        @keyframes welcome-pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }

        .welcome-pulse {
            animation: welcome-pulse 2s ease-in-out infinite;
        }
    </style>
</head>
<body class="text-slate-700 flex bg-gray-50 min-h-screen">
    <?php require_once 'includes/sidebar-component.php'; ?>

    <div class="main-content flex-1 min-h-screen" id="mainContent">
        <?php require_once 'includes/header.php'; ?>

        <div class="content-container">
            <!-- Enhanced Flash Messaging System -->
            <?php if ($flash_message): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    <?php
                    // Ensure flash_message is properly formatted
                    $flash_message_text = $flash_message;
                    $flash_message_type = $flash_type ?? 'info';

                    // Handle JSON strings if they exist
                    if (is_string($flash_message_text) && strpos($flash_message_text, '{') === 0) {
                        try {
                            $decoded = json_decode($flash_message_text, true);
                            if (isset($decoded['message'])) {
                                $flash_message_text = $decoded['message'];
                            }
                            if (isset($decoded['type'])) {
                                $flash_message_type = $decoded['type'];
                            }
                        } catch (e) {
                            // Keep original message if JSON decode fails
                        }
                    }
                    ?>

                    const message = <?php echo json_encode($flash_message_text); ?>;
                    const messageType = <?php echo json_encode($flash_message_type); ?>;

                    // Special handling for welcome messages
                    if (message.toLowerCase().includes('welcome') || message.toLowerCase().includes('welcome back')) {
                        showWelcomeMessage(message);
                    } else {
                        showRegularFlashMessage(message, messageType);
                    }

                    function showWelcomeMessage(welcomeText) {
                        Swal.fire({
                            title: '<div class="flex items-center justify-center mb-4">' +
                            '<div class="w-16 h-16 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center mr-4">' +
                            '<i class="fas fa-user-check text-2xl text-white"></i>' +
                            '</div>' +
                            '<div class="text-left">' +
                            '<h2 class="text-2xl font-bold text-gray-800">Welcome Back!</h2>' +
                            '<p class="text-gray-600">Great to see you again</p>' +
                            '</div>' +
                            '</div>',
                            html: `
                            <div class="text-center py-4">
                            <div class="mb-6">
                            <div class="w-20 h-20 bg-gradient-to-r from-green-400 to-blue-500 rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg">
                            <i class="fas fa-smile-beam text-3xl text-white"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-800 mb-2">${welcomeText}</h3>
                            <p class="text-gray-600">You have successfully logged in to the Financial Management Portal</p>
                            </div>

                            <div class="grid grid-cols-3 gap-4 mb-6">
                            <div class="text-center">
                            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-wallet text-blue-600"></i>
                            </div>
                            <span class="text-sm text-gray-600">Per Diem</span>
                            </div>
                            <div class="text-center">
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-gas-pump text-green-600"></i>
                            </div>
                            <span class="text-sm text-gray-600">Fuel</span>
                            </div>
                            <div class="text-center">
                            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-chart-bar text-purple-600"></i>
                            </div>
                            <span class="text-sm text-gray-600">Reports</span>
                            </div>
                            </div>

                            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-4 border border-blue-200">
                            <div class="flex items-center justify-center space-x-2 text-blue-700">
                            <i class="fas fa-clock"></i>
                            <span class="text-sm font-medium">Login Time: ${new Date().toLocaleTimeString()}</span>
                            </div>
                            </div>
                            </div>
                            `,
                            showConfirmButton: true,
                            confirmButtonText: 'Continue to Dashboard',
                            confirmButtonColor: '#3b82f6',
                            background: '#ffffff',
                            width: '500px',
                            customClass: {
                                popup: 'rounded-2xl shadow-2xl border border-gray-200',
                                confirmButton: 'px-6 py-3 rounded-lg font-semibold shadow-lg hover:shadow-xl transition-all duration-200'
                            },
                            showClass: {
                                popup: 'animate__animated animate__fadeInDown animate__faster'
                            },
                            hideClass: {
                                popup: 'animate__animated animate__fadeOutUp animate__faster'
                            },
                            timer: 5000,
                            timerProgressBar: true,
                            didOpen: () => {
                                // Add some interactive effects
                                const popup = Swal.getPopup();
                                popup.style.transform = 'scale(0.95)';
                                setTimeout(() => {
                                    popup.style.transform = 'scale(1)';
                                    popup.style.transition = 'transform 0.3s ease';
                                }, 100);
                            }
                        });
                    }

                    function showRegularFlashMessage(message, type) {
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

                        const config = toastConfigs[type] || toastConfigs.info;

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
                    }
                });
            </script>
            <?php endif; ?>

            <!-- Registration Form Card -->
            <div class="bg-white rounded-2xl p-8 shadow-xl mb-8 border border-gray-100">
                <h2 class="text-2xl font-bold text-slate-800 mb-6 flex items-center">
                    <i class="fas fa-user-plus mr-3 text-blue-500"></i>
                    <?php echo isset($edit_employee) ? 'Edit Employee' : 'Register New Employee'; ?>
                </h2>
                <form id="registrationForm" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
                    <?php if (isset($edit_employee)): ?>
                        <input type="hidden" name="id" value="<?php echo (int)$edit_employee['id']; ?>">
                        <input type="hidden" name="action" value="update">
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
                        <!-- Photo Upload Section -->
                        <div class="md:col-span-1 flex flex-col items-center justify-center">
                            <div id="photoPreviewPlaceholder" class="photo-preview-placeholder <?php echo (isset($edit_employee) && $edit_employee['photo']) ? 'hidden' : ''; ?>">
                                <i class="fas fa-user text-5xl text-gray-400"></i>
                            </div>
                            <img id="photoPreview" src="<?php echo isset($edit_employee) && $edit_employee['photo'] ? htmlspecialchars($edit_employee['photo']) : '#'; ?>" 
                                 alt="Employee Photo" class="photo-preview <?php echo (isset($edit_employee) && $edit_employee['photo']) ? '' : 'hidden'; ?>">
                            <label for="photo" class="mt-4 px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-lg cursor-pointer hover:from-blue-600 hover:to-indigo-700 transition-all duration-200 font-medium">
                                <i class="fas fa-upload mr-2"></i>Upload Photo
                            </label>
                            <input type="file" name="photo" id="photo" class="hidden" accept="image/*">
                            <p class="text-xs text-gray-500 mt-2">Max 5MB. JPG, PNG, GIF.</p>
                        </div>

                        <!-- Form Fields Section -->
                        <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-slate-700 mb-2">Full Name (English) *</label>
                                <input type="text" name="name" id="name" required 
                                       value="<?php echo isset($edit_employee) ? htmlspecialchars($edit_employee['name']) : (isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 modern-input transition-all" 
                                       placeholder="e.g. John Doe">
                            </div>
                            <div>
                                <label for="name_am" class="block text-sm font-medium text-slate-700 mb-2 ethio-font">ሙሉ ስም (አማርኛ)</label>
                                <input type="text" name="name_am" id="name_am" 
                                       value="<?php echo isset($edit_employee) ? htmlspecialchars($edit_employee['name_am']) : (isset($_POST['name_am']) ? htmlspecialchars($_POST['name_am']) : ''); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 modern-input transition-all ethio-font" 
                                       placeholder="ለምሳሌ፡ ዮሐንስ ዶ">
                            </div>
                            <div>
                                <label for="salary" class="block text-sm font-medium text-slate-700 mb-2">Salary (ETB) *</label>
                                <input type="number" step="0.01" name="salary" id="salary" required 
                                       value="<?php echo isset($edit_employee) ? htmlspecialchars($edit_employee['salary']) : (isset($_POST['salary']) ? htmlspecialchars($_POST['salary']) : ''); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 modern-input transition-all" 
                                       placeholder="e.g. 15000.00">
                            </div>
                            <div>
                                <label for="taamagoli" class="block text-sm font-medium text-slate-700 mb-2">Position / Title *</label>
                                <input type="text" name="taamagoli" id="taamagoli" required 
                                       value="<?php echo isset($edit_employee) ? htmlspecialchars($edit_employee['taamagoli']) : (isset($_POST['taamagoli']) ? htmlspecialchars($_POST['taamagoli']) : ''); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 modern-input transition-all" 
                                       placeholder="e.g. Senior Developer">
                            </div>
                            <div class="md:col-span-2">
                                <label for="directorate" class="block text-sm font-medium text-slate-700 mb-2">Directorate / Department *</label>
                                <select name="directorate" id="directorate" required class="w-full select2 modern-input">
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept); ?>"
                                            <?php
                                            $selected = false;
                                            if (isset($edit_employee) && $edit_employee['directorate'] == $dept) $selected = true;
                                            if (isset($_POST['directorate']) && $_POST['directorate'] == $dept) $selected = true;
                                            echo $selected ? 'selected' : '';
                                            ?>>
                                            <?php echo htmlspecialchars($dept); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-4 pt-4">
                        <?php if (isset($edit_employee)): ?>
                            <a href="employee-registration.php" class="px-6 py-3 bg-gray-300 text-gray-700 rounded-xl hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-all duration-200 font-medium">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </a>
                        <?php else: ?>
                            <a href="perdium.php" class="px-6 py-3 bg-gray-300 text-gray-700 rounded-xl hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-all duration-200 font-medium">
                                <i class="fas fa-arrow-left mr-2"></i>Back to Per Diem
                            </a>
                        <?php endif; ?>
                        <button id="submitBtn" type="submit" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-xl hover:from-blue-600 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200 font-medium shadow-lg hover:shadow-xl transform hover:scale-105">
                            <i class="fas fa-save mr-2"></i>
                            <?php echo isset($edit_employee) ? 'Update Employee' : 'Register Employee'; ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Employee Details Preview Card -->
            <div id="employeePreviewCard" class="employee-card hidden">
                <h3>Employee Details</h3>
                <div class="employee-card-content">
                    <img id="previewPhoto" src="#" alt="Employee Photo" class="employee-card-photo hidden">
                    <div id="previewPhotoPlaceholder" class="employee-card-photo bg-white bg-opacity-20 flex items-center justify-center">
                        <i class="fas fa-user text-3xl text-white"></i>
                    </div>
                    <div class="employee-card-details">
                        <div id="previewName" class="employee-card-name">-</div>
                        <div id="previewPosition" class="employee-card-position">-</div>
                        <div id="previewDepartment" class="employee-card-department">-</div>
                        <div id="previewSalary" class="employee-card-salary">-</div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="bg-white rounded-2xl p-6 shadow-xl mb-6 border border-gray-100">
                <h3 class="text-lg font-semibold text-slate-800 mb-4 flex items-center">
                    <i class="fas fa-filter mr-2 text-blue-500"></i>Filter Employees
                </h3>
                <div class="grid md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Department</label>
                        <select id="filterDepartment" class="w-full select2 modern-input">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Position</label>
                        <input type="text" id="filterPosition" placeholder="Filter by position..." class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 modern-input transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Salary Range</label>
                        <select id="filterSalary" class="w-full select2 modern-input">
                            <option value="">Any Salary</option>
                            <option value="0-5000">0 - 5,000 ETB</option>
                            <option value="5000-10000">5,000 - 10,000 ETB</option>
                            <option value="10000-20000">10,000 - 20,000 ETB</option>
                            <option value="20000+">20,000+ ETB</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Live Search Box -->
            <div class="mb-6">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                    <input type="text" id="searchInput" placeholder="Search employees by name, position, department..." class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent modern-input transition-all duration-200" onkeyup="filterEmployees()">
                </div>
            </div>

            <!-- Employee List Table -->
            <div class="bg-white rounded-2xl p-6 shadow-xl border border-gray-100">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <h2 class="text-2xl font-bold text-slate-800 flex items-center">
                        <i class="fas fa-users mr-3 text-blue-500"></i>Registered Employees
                    </h2>
                    <div class="mt-4 md:mt-0 flex space-x-4">
                        <button id="printAllEmployees" class="px-4 py-2 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-lg hover:from-green-600 hover:to-emerald-700 transition-all duration-200 font-medium shadow-lg hover:shadow-xl flex items-center">
                            <i class="fas fa-print mr-2"></i> Print All
                        </button>
                        <button id="exportEmployees" class="px-4 py-2 bg-gradient-to-r from-purple-500 to-indigo-600 text-white rounded-lg hover:from-purple-600 hover:to-indigo-700 transition-all duration-200 font-medium shadow-lg hover:shadow-xl flex items-center">
                            <i class="fas fa-file-export mr-2"></i> Export
                        </button>
                    </div>
                </div>
                <div class="table-responsive overflow-x-auto custom-scrollbar">
                    <table class="min-w-full divide-y divide-gray-200 table-modern">
                        <thead class="bg-gradient-to-r from-blue-500 to-indigo-600">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Photo</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Position</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Department</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Salary</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="employeeTableBody" class="bg-white divide-y divide-gray-200">
                            <!-- Rows will be loaded via AJAX -->
                            <tr><td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">
                                <div class="flex flex-col items-center justify-center py-4">
                                    <i class="fas fa-spinner fa-spin text-2xl text-blue-500 mb-2"></i>
                                    <span>Loading employees...</span>
                                </div>
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            const csrfToken = <?php echo json_encode($_SESSION['csrf']); ?>;
            let currentSelectedRow = null;
            let employeesData = [];

            // Initialize Select2
            $('.select2').select2({
                theme: 'classic',
                width: '100%',
                dropdownCssClass: 'rounded-xl shadow-xl border border-gray-200'
            });

            // Photo Preview Logic
            $('#photo').on('change', function(event) {
                const [file] = event.target.files;
                if (file) {
                    $('#photoPreview').attr('src', URL.createObjectURL(file)).removeClass('hidden');
                    $('#photoPreviewPlaceholder').addClass('hidden');
                }
            });

            // Update confirmation for form submission
            $('#registrationForm').on('submit', function(e) {
                if ($('input[name="action"]').val() === 'update') {
                    e.preventDefault();
                    
                    Swal.fire({
                        title: '<div class="flex items-center justify-center mb-4"><i class="fas fa-user-edit text-4xl text-blue-500 mr-3"></i><span class="text-2xl font-bold text-gray-800">Confirm Update</span></div>',
                        html: `
                        <div class="text-center py-4">
                        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-exclamation-circle text-2xl text-blue-600"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Are you sure you want to update this employee?</h3>
                        <p class="text-gray-600 mb-4">This will permanently update the employee's information in the system.</p>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mt-4">
                        <p class="text-sm text-blue-700 flex items-center justify-center">
                        <i class="fas fa-info-circle mr-2"></i>
                        Please review all changes before confirming.
                        </p>
                        </div>
                        </div>
                        `,
                        showCancelButton: true,
                        confirmButtonColor: '#3b82f6',
                        cancelButtonColor: '#6b7280',
                        confirmButtonText: '<i class="fas fa-save mr-2"></i>Yes, Update Employee',
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
                                title: '<div class="flex items-center justify-center"><i class="fas fa-spinner fa-spin text-2xl text-blue-500 mr-3"></i><span class="text-lg">Updating Employee...</span></div>',
                                text: 'Please wait while we update the employee information',
                                allowOutsideClick: false,
                                showConfirmButton: false,
                                background: '#fff',
                                customClass: {
                                    popup: 'rounded-2xl shadow-2xl'
                                }
                            });
                            
                            // Submit the form
                            $('#registrationForm').off('submit').submit();
                        }
                    });
                }
            });

            // Load employees into the table
            function loadEmployees() {
                $.ajax({
                    url: 'ajax_get_employees.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        const tbody = $('#employeeTableBody');
                        tbody.empty();
                        employeesData = response.employees || [];
                        
                        if (employeesData.length > 0) {
                            employeesData.forEach((emp, index) => {
                                const photoHtml = emp.photo ?
                                    `<img src="${emp.photo}" alt="${emp.name}" class="h-10 w-10 rounded-full object-cover">` :
                                    `<div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center"><i class="fas fa-user text-gray-500"></i></div>`;
                                
                                const salaryFormatted = parseFloat(emp.salary).toLocaleString('en-US', { 
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2 
                                });

                                let actions = '';
                                <?php if ($is_admin): ?>
                                actions = `
                                    <div class="flex flex-wrap gap-1">
                                        <a href="?action=edit&id=${emp.id}" class="px-3 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-lg hover:from-blue-600 hover:to-indigo-700 transition-all duration-200 shadow-sm flex items-center text-xs">
                                            <i class="fas fa-edit mr-1"></i> Edit
                                        </a>
                                        <a href="#" onclick="deleteEmployee(${emp.id})" class="px-3 py-2 bg-gradient-to-r from-red-500 to-pink-600 text-white rounded-lg hover:from-red-600 hover:to-pink-700 transition-all duration-200 shadow-sm flex items-center text-xs">
                                            <i class="fas fa-trash mr-1"></i> Delete
                                        </a>
                                        <a href="reports/employee_report.php?id=${emp.id}" target="_blank" class="px-3 py-2 bg-gradient-to-r from-emerald-500 to-green-600 text-white rounded-lg hover:from-emerald-600 hover:to-green-700 transition-all duration-200 shadow-sm flex items-center text-xs">
                                            <i class="fas fa-print mr-1"></i> Print
                                        </a>
                                    </div>
                                `;
                                <?php else: ?>
                                actions = `
                                    <div class="flex flex-wrap gap-1">
                                        <a href="reports/employee_report.php?id=${emp.id}" target="_blank" class="px-3 py-2 bg-gradient-to-r from-emerald-500 to-green-600 text-white rounded-lg hover:from-emerald-600 hover:to-green-700 transition-all duration-200 shadow-sm flex items-center text-xs">
                                            <i class="fas fa-print mr-1"></i> Print
                                        </a>
                                    </div>
                                `;
                                <?php endif; ?>

                                const row = `
                                    <tr class="employee-row hover:bg-gray-50 cursor-pointer transition-colors duration-150" data-index="${index}" data-id="${emp.id}">
                                        <td class="px-4 py-3 whitespace-nowrap">${photoHtml}</td>
                                        <td class="px-4 py-3 whitespace-nowrap employee-name-cell">
                                            <div class="font-medium text-gray-900">${emp.name || ''}</div>
                                            <div class="text-sm text-gray-500 ethio-font">${emp.name_am || ''}</div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">${emp.taamagoli || ''}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">${emp.directorate || ''}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-800">${salaryFormatted} ETB</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm actions-cell">${actions}</td>
                                    </tr>
                                `;
                                tbody.append(row);
                            });

                            // Add click event to rows
                            $('.employee-row').on('click', function(e) {
                                if ($(e.target).closest('a').length) return; // Don't trigger if clicking on action links
                                
                                const index = $(this).data('index');
                                selectEmployeeRow(index);
                            });

                            // Select first row by default
                            if (employeesData.length > 0) {
                                selectEmployeeRow(0);
                            }
                        } else {
                            tbody.html('<tr><td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500"><div class="flex flex-col items-center justify-center py-4"><i class="fas fa-inbox text-3xl text-gray-300 mb-2"></i><span>No employees found.</span></div></td></tr>');
                            $('#employeePreviewCard').addClass('hidden');
                        }
                    },
                    error: function() {
                        $('#employeeTableBody').html('<tr><td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500"><div class="flex flex-col items-center justify-center py-4"><i class="fas fa-exclamation-triangle text-2xl text-red-500 mb-2"></i><span>Failed to load employee data.</span></div></td></tr>');
                    }
                });
            }

            // Select employee row and show preview
            function selectEmployeeRow(index) {
                $('.employee-row').removeClass('row-selected');
                $(`.employee-row[data-index="${index}"]`).addClass('row-selected');
                currentSelectedRow = index;
                
                const emp = employeesData[index];
                updateEmployeePreview(emp);
            }

            // Update employee preview card
            function updateEmployeePreview(emp) {
                $('#employeePreviewCard').removeClass('hidden');
                
                if (emp.photo) {
                    $('#previewPhoto').attr('src', emp.photo).removeClass('hidden');
                    $('#previewPhotoPlaceholder').addClass('hidden');
                } else {
                    $('#previewPhoto').addClass('hidden');
                    $('#previewPhotoPlaceholder').removeClass('hidden');
                }
                
                $('#previewName').text(emp.name || '-');
                $('#previewPosition').text(emp.taamagoli || '-');
                $('#previewDepartment').text(emp.directorate || '-');
                $('#previewSalary').text(parseFloat(emp.salary).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }) + ' ETB');
            }

            // Keyboard navigation
            $(document).on('keydown', function(e) {
                if (employeesData.length === 0) return;
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    const nextIndex = currentSelectedRow === null ? 0 : Math.min(currentSelectedRow + 1, employeesData.length - 1);
                    selectEmployeeRow(nextIndex);
                    scrollToRow(nextIndex);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    const prevIndex = currentSelectedRow === null ? 0 : Math.max(currentSelectedRow - 1, 0);
                    selectEmployeeRow(prevIndex);
                    scrollToRow(prevIndex);
                }
            });

            // Scroll to make row visible
            function scrollToRow(index) {
                const row = $(`.employee-row[data-index="${index}"]`)[0];
                if (row) {
                    row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }

            // Search functionality
            $('#searchInput').on('input', function() {
                filterEmployees();
            });

            // Filter employees by department, position, and salary
            function filterEmployees() {
                const searchTerm = $('#searchInput').val().toLowerCase();
                const departmentFilter = $('#filterDepartment').val();
                const positionFilter = $('#filterPosition').val().toLowerCase();
                const salaryFilter = $('#filterSalary').val();
                
                $('.employee-row').each(function() {
                    const row = $(this);
                    const text = row.text().toLowerCase();
                    const department = row.find('td:nth-child(4)').text();
                    const position = row.find('td:nth-child(3)').text().toLowerCase();
                    const salaryText = row.find('td:nth-child(5)').text();
                    const salary = parseFloat(salaryText.replace(/[^0-9.]/g, ''));
                    
                    let matchesSearch = text.includes(searchTerm);
                    let matchesDepartment = !departmentFilter || department === departmentFilter;
                    let matchesPosition = !positionFilter || position.includes(positionFilter);
                    let matchesSalary = true;
                    
                    if (salaryFilter) {
                        if (salaryFilter === '0-5000') matchesSalary = salary <= 5000;
                        else if (salaryFilter === '5000-10000') matchesSalary = salary > 5000 && salary <= 10000;
                        else if (salaryFilter === '10000-20000') matchesSalary = salary > 10000 && salary <= 20000;
                        else if (salaryFilter === '20000+') matchesSalary = salary > 20000;
                    }
                    
                    row.toggle(matchesSearch && matchesDepartment && matchesPosition && matchesSalary);
                });
            }

            // Apply filters when they change
            $('#filterDepartment, #filterPosition, #filterSalary').on('change input', function() {
                filterEmployees();
            });

            // Print all employees
            $('#printAllEmployees').on('click', function() {
                window.open('reports/employee_list_report.php', '_blank');
            });

            // Export employees
            $('#exportEmployees').on('click', function() {
                Swal.fire({
                    title: 'Export Employees',
                    text: 'This feature will be available soon.',
                    icon: 'info',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#3b82f6'
                });
            });

            // Initial load
            loadEmployees();

            // Mobile sidebar toggle
            $('#sidebarToggle').on('click', function() {
                $('#sidebar').toggleClass('active');
            });
        });

        // Delete employee function
        function deleteEmployee(id) {
            Swal.fire({
                title: '<div class="flex items-center justify-center mb-4"><i class="fas fa-trash-alt text-4xl text-red-500 mr-3"></i><span class="text-2xl font-bold text-gray-800">Confirm Deletion</span></div>',
                html: `
                <div class="text-center py-4">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-exclamation-triangle text-2xl text-red-600"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Are you sure you want to delete this employee?</h3>
                <p class="text-gray-600 mb-4">This action cannot be undone and will permanently remove the employee from the system.</p>
                <div class="bg-red-50 border border-red-200 rounded-lg p-3 mt-4">
                <p class="text-sm text-red-700 flex items-center justify-center">
                <i class="fas fa-info-circle mr-2"></i>
                This will also remove any associated perdium transactions.
                </p>
                </div>
                </div>
                `,
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-trash mr-2"></i>Yes, Delete It',
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
                        title: '<div class="flex items-center justify-center"><i class="fas fa-spinner fa-spin text-2xl text-blue-500 mr-3"></i><span class="text-lg">Deleting Employee...</span></div>',
                        text: 'Please wait while we remove the employee',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        background: '#fff',
                        customClass: {
                            popup: 'rounded-2xl shadow-2xl'
                        }
                    });
                    window.location.href = `employee-registration.php?action=delete&id=${id}`;
                }
            });
        }
    </script>
</body>
</html>