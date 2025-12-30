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

// Flash message helpers (assuming they exist in init.php or are defined here)
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

// Fetch user data for header
$stmt = $pdo->prepare("SELECT name, profile_picture, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$user_name = $user_data['name'] ?? ($_SESSION['username'] ?? 'User');
$profile_picture = $user_data['profile_picture'] ?? '';
$user_email = $user_data['email'] ?? '';

// Handle POST request for new employee registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        set_flash('Invalid CSRF token. Please try again.', 'error');
        header('Location: employee-registration.php');
        exit;
    }

    // Sanitize and validate inputs
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

            // Insert into database
            $pdo->beginTransaction();

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

            $pdo->commit();
            set_flash('Employee registered successfully!', 'success');
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
        .gradient-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .modern-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-1px);
        }
        .gradient-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: all 0.3s ease;
        }
        .gradient-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
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
         /* Custom scrollbar for sidebar */
         .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 10px;
        }
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }
        /* User Profile styles from perdium.php */
        .user-profile { position: relative; cursor: pointer; }
        .user-avatar { width: 45px; height: 45px; border-radius: 50%; border: 3px solid rgba(255, 255, 255, 0.3); transition: all 0.3s ease; }
        .user-avatar:hover { border-color: rgba(255, 255, 255, 0.6); transform: scale(1.05); }
        .user-dropdown { position: absolute; top: 100%; right: 0; width: 280px; background: white; border: 1px solid #e5e7eb; border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.15); z-index: 1000; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.3s ease; }
        .user-dropdown.show { opacity: 1; visibility: visible; transform: translateY(0); }
        .user-dropdown-header { padding: 20px; border-bottom: 1px solid #f3f4f6; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 16px 16px 0 0; }
        .user-dropdown-item { padding: 12px 20px; display: flex; align-items: center; color: #4b5563; text-decoration: none; transition: all 0.2s ease; border-bottom: 1px solid #f9fafb; }
        .user-dropdown-item:hover { background: #f8fafc; color: #1f2937; }
        .user-dropdown-item:last-child { border-bottom: none; border-radius: 0 0 16px 16px; }
        .user-dropdown-item.logout { color: #ef4444; }
        .user-dropdown-item.logout:hover { background: #fef2f2; color: #dc2626; }
        .user-dropdown-icon { width: 20px; margin-right: 12px; text-align: center; }
    </style>
</head>
<body class="text-slate-700 flex bg-gray-50 min-h-screen">
    <?php require_once 'includes/sidebar-new.php'; ?>

    <div class="main-content flex-1 min-h-screen" id="mainContent">
        <div class="p-6">
            <?php require_once 'includes/header.php'; ?>

            <!-- Flash Message Display -->
            <?php if ($flash_message): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: <?php echo json_encode($flash_type); ?>,
                        title: <?php echo json_encode(ucfirst($flash_type) . '!'); ?>,
                        text: <?php echo json_encode($flash_message); ?>,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 4000,
                        timerProgressBar: true
                    });
                });
            </script>
            <?php endif; ?>

            <!-- Registration Form Card -->
            <div class="bg-white rounded-2xl p-8 shadow-xl mb-8 border border-gray-100">
                <h2 class="text-2xl font-bold text-slate-800 mb-6 flex items-center">
                    <i class="fas fa-user-plus mr-3 text-blue-500"></i>
                    Register New Employee
                </h2>
                <form id="registrationForm" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
                        <!-- Photo Upload Section -->
                        <div class="md:col-span-1 flex flex-col items-center justify-center">
                            <div id="photoPreviewPlaceholder" class="photo-preview-placeholder">
                                <i class="fas fa-user text-5xl text-gray-400"></i>
                            </div>
                            <img id="photoPreview" src="#" alt="Employee Photo" class="photo-preview hidden"/>
                            <label for="photo" class="mt-4 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg cursor-pointer hover:bg-gray-300 transition-colors">
                                <i class="fas fa-upload mr-2"></i>Upload Photo
                            </label>
                            <input type="file" name="photo" id="photo" class="hidden" accept="image/*">
                            <p class="text-xs text-gray-500 mt-2">Max 5MB. JPG, PNG, GIF.</p>
                        </div>

                        <!-- Form Fields Section -->
                        <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-slate-700 mb-2">Full Name (English) *</label>
                                <input type="text" name="name" id="name" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 modern-input transition-all" placeholder="e.g. John Doe">
                            </div>
                            <div>
                                <label for="name_am" class="block text-sm font-medium text-slate-700 mb-2 ethio-font">ሙሉ ስም (አማርኛ)</label>
                                <input type="text" name="name_am" id="name_am" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 modern-input transition-all ethio-font" placeholder="ለምሳሌ፡ ዮሐንስ ዶ">
                            </div>
                            <div>
                                <label for="salary" class="block text-sm font-medium text-slate-700 mb-2">Salary (ETB) *</label>
                                <input type="number" step="0.01" name="salary" id="salary" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 modern-input transition-all" placeholder="e.g. 15000.00">
                            </div>
                             <div>
                                <label for="taamagoli" class="block text-sm font-medium text-slate-700 mb-2">Position / Title *</label>
                                <input type="text" name="taamagoli" id="taamagoli" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 modern-input transition-all" placeholder="e.g. Senior Developer">
                            </div>
                            <div class="md:col-span-2">
                                <label for="directorate" class="block text-sm font-medium text-slate-700 mb-2">Directorate / Department *</label>
                                <input type="text" name="directorate" id="directorate" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 modern-input transition-all" placeholder="e.g. IT Department">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-4 pt-4">
                        <a href="perdium.php" class="px-6 py-3 bg-gray-300 text-gray-700 rounded-xl hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-all font-medium">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Per Diem
                        </a>
                        <button type="submit" class="px-6 py-3 gradient-button text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium shadow-lg hover:shadow-xl transform hover:scale-105">
                            <i class="fas fa-save mr-2"></i>Register Employee
                        </button>
                    </div>
                </form>
            </div>

            <!-- Employee List Table -->
            <div class="bg-white rounded-2xl p-6 shadow-xl border border-gray-100">
                <h2 class="text-2xl font-bold text-slate-800 mb-6 flex items-center">
                    <i class="fas fa-users mr-3 text-blue-500"></i>Registered Employees
                </h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="gradient-header">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Photo</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Position</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Department</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Salary</th>
                            </tr>
                        </thead>
                        <tbody id="employeeTableBody" class="bg-white divide-y divide-gray-200">
                            <!-- Rows will be loaded via AJAX -->
                            <tr><td colspan="5" class="p-8 text-center text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading employees...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Photo Preview Logic
            $('#photo').on('change', function(event) {
                const [file] = event.target.files;
                if (file) {
                    $('#photoPreview').attr('src', URL.createObjectURL(file)).removeClass('hidden');
                    $('#photoPreviewPlaceholder').addClass('hidden');
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
                        if (response.success && response.employees.length > 0) {
                            response.employees.forEach(emp => {
                                const photoHtml = emp.photo ?
                                    `<img src="${emp.photo}" alt="${emp.name}" class="h-10 w-10 rounded-full object-cover">` :
                                    `<div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center"><i class="fas fa-user text-gray-500"></i></div>`;
                                
                                const salaryFormatted = parseFloat(emp.salary).toLocaleString('en-US', { style: 'currency', currency: 'ETB' });

                                const row = `
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 whitespace-nowrap">${photoHtml}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="font-medium text-gray-900">${emp.name || ''}</div>
                                            <div class="text-sm text-gray-500 ethio-font">${emp.name_am || ''}</div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">${emp.taamagoli || ''}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">${emp.directorate || ''}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-800">${salaryFormatted}</td>
                                    </tr>
                                `;
                                tbody.append(row);
                            });
                        } else {
                            tbody.html('<tr><td colspan="5" class="p-8 text-center text-gray-500">No employees found.</td></tr>');
                        }
                    },
                    error: function() {
                        $('#employeeTableBody').html('<tr><td colspan="5" class="p-8 text-center text-red-500">Failed to load employee data.</td></tr>');
                    }
                });
            }

            loadEmployees();

            // Handle user profile dropdown from perdium.php
            $('.user-profile').on('click', function(e) {
                e.stopPropagation();
                $('.user-dropdown').toggleClass('show');
            });
            $(document).on('click', function() {
                $('.user-dropdown').removeClass('show');
            });

        });

        // Sidebar toggle logic from perdium.php
        document.getElementById('sidebarToggle')?.addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('mainContent').classList.toggle('expanded');
        });
    </script>
</body>
</html>