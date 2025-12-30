<?php
require_once 'includes/init.php';

// CSRF token generation for security
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// User Authentication Check
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: login.php');
    exit;
}

// Fetch user data for the header
$stmt = $pdo->prepare("SELECT name, profile_picture, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$user_name = $user_data['name'] ?? ($_SESSION['username'] ?? 'User');
$profile_picture = $user_data['profile_picture'] ?? '';
$user_email = $user_data['email'] ?? '';

// CORRECTED QUERY: Fetch all department names directly from the budget_owners table.
$budget_owners_stmt = $pdo->prepare("SELECT name FROM budget_owners ORDER BY name ASC");
$budget_owners_stmt->execute();
$budget_owners = $budget_owners_stmt->fetchAll(PDO::FETCH_ASSOC);


// This block handles the submission from the main "Register New Employee" form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $_SESSION['flash_message'] = 'Invalid CSRF token. Please try again.';
        $_SESSION['flash_type'] = 'error';
    } else {
        // Sanitize and validate inputs
        $name = trim($_POST['name'] ?? '');
        $name_am = trim($_POST['name_am'] ?? '');
        $salary = filter_input(INPUT_POST, 'salary', FILTER_VALIDATE_FLOAT);
        $taamagoli = trim($_POST['taamagoli'] ?? '');
        $directorate = trim($_POST['directorate'] ?? '');
        $photo_path = null;

        if (empty($name) || empty($taamagoli) || empty($directorate) || $salary === false || $salary <= 0) {
            $_SESSION['flash_message'] = 'Please fill all required fields with valid data.';
            $_SESSION['flash_type'] = 'error';
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

                    if (in_array($extension, $allowed_extensions) && $_FILES['photo']['size'] <= 5 * 1024 * 1024) { // 5MB limit
                        $unique_filename = uniqid('emp_', true) . '.' . $extension;
                        $destination = $upload_dir . $unique_filename;
                        if (move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                            $photo_path = $destination;
                        } else { throw new Exception('Failed to move uploaded file.'); }
                    } else { throw new Exception('Invalid file type or size.'); }
                }

                // Insert into database
                $sql = "INSERT INTO emp_list (name, name_am, salary, taamagoli, directorate, photo, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$name, $name_am, $salary, $taamagoli, $directorate, $photo_path, date('Y-m-d')]);
                
                $_SESSION['flash_message'] = 'Employee registered successfully!';
                $_SESSION['flash_type'] = 'success';
            } catch (Exception $e) {
                $_SESSION['flash_message'] = 'Error: ' . $e->getMessage();
                $_SESSION['flash_type'] = 'error';
            }
        }
    }
    header('Location: employee-registration.php');
    exit;
}

// Fetch flash messages for display
$flash_message = $_SESSION['flash_message'] ?? null;
$flash_type = $_SESSION['flash_type'] ?? null;
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php
    $pageTitle = 'Employee Management';
    require_once 'includes/head.php';
    ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* All CSS from previous versions remains the same. Styles for gradient headers, modern inputs, photo previews, modals, etc. */
        .gradient-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .modern-input:focus { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); transform: translateY(-1px); }
        .gradient-button { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); transition: all 0.3s ease; }
        .gradient-button:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3); }
        .photo-preview { width: 128px; height: 128px; border-radius: 50%; object-fit: cover; border: 4px solid #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .photo-preview-placeholder { width: 128px; height: 128px; border-radius: 50%; background-color: #e9ecef; display: flex; align-items: center; justify-content: center; border: 4px solid #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .ethio-font { font-family: 'Nyala', 'Abyssinica SIL', 'GF Zemen', sans-serif; }
        .sidebar::-webkit-scrollbar { width: 6px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.1); border-radius: 10px; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.3); border-radius: 10px; }
        .sidebar::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.5); }
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
        .modal-backdrop { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1040; display: none; align-items: center; justify-content: center; }
        .modal-content { background: white; border-radius: 1rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); width: 90%; max-width: 50rem; max-height: 90vh; overflow-y: auto; transform: scale(0.95); opacity: 0; transition: all 0.2s ease-out; }
        .modal-backdrop.show .modal-content { transform: scale(1); opacity: 1; }
        #employeeTableBody tr { cursor: pointer; }
    </style>
</head>
<body class="text-slate-700 flex bg-gray-50 min-h-screen">
    <?php require_once 'includes/sidebar-new.php'; ?>

    <div class="main-content flex-1 min-h-screen" id="mainContent">
        <div class="p-6">
            <?php require_once 'includes/header.php'; ?>

            <!-- Registration Form Card -->
            <div class="bg-white rounded-2xl p-8 shadow-xl mb-8 border border-gray-100">
                <h2 class="text-2xl font-bold text-slate-800 mb-6 flex items-center">
                    <i class="fas fa-user-plus mr-3 text-blue-500"></i> Register New Employee
                </h2>
                <form id="registrationForm" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
                    <input type="hidden" name="action" value="register">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
                        <!-- Photo Upload Section -->
                        <div class="md:col-span-1 flex flex-col items-center justify-center">
                            <div id="photoPreviewPlaceholder" class="photo-preview-placeholder"><i class="fas fa-user text-5xl text-gray-400"></i></div>
                            <img id="photoPreview" src="#" alt="Employee Photo" class="photo-preview hidden"/>
                            <label for="photo" class="mt-4 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg cursor-pointer hover:bg-gray-300 transition-colors"><i class="fas fa-upload mr-2"></i>Upload Photo</label>
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
                                <label for="directorate" class="block text-sm font-medium text-slate-700 mb-2">Department / Directorate *</label>
                                <select name="directorate" id="directorate" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 modern-input transition-all bg-white">
                                    <option value="" disabled selected>Select a Department</option>
                                    <?php foreach ($budget_owners as $owner): ?>
                                        <option value="<?php echo htmlspecialchars($owner['name']); ?>"><?php echo htmlspecialchars($owner['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end pt-4"><button type="submit" class="px-6 py-3 gradient-button text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium shadow-lg hover:shadow-xl transform hover:scale-105"><i class="fas fa-save mr-2"></i>Register Employee</button></div>
                </form>
            </div>

            <!-- Employee List Table -->
            <div class="bg-white rounded-2xl p-6 shadow-xl border border-gray-100">
                <h2 class="text-2xl font-bold text-slate-800 mb-6"><i class="fas fa-users mr-3 text-blue-500"></i>Registered Employees</h2>
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200">
                    <thead class="gradient-header"><tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Photo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Position</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Department</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Salary</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-white uppercase tracking-wider">Actions</th>
                    </tr></thead>
                    <tbody id="employeeTableBody" class="bg-white divide-y divide-gray-200">
                        <tr><td colspan="6" class="p-8 text-center text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading employees...</td></tr>
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>

    <!-- Edit Employee Modal -->
    <div id="editEmployeeModal" class="modal-backdrop"><div class="modal-content"><div class="p-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-slate-800">Edit Employee Details</h2>
            <button id="closeEditModal" class="text-gray-500 hover:text-gray-800 text-3xl font-bold">&times;</button>
        </div>
        <form id="editEmployeeForm" class="space-y-6">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
            <input type="hidden" id="edit_id" name="edit_id">
            <input type="hidden" id="current_photo" name="current_photo">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
                 <div class="md:col-span-1 flex flex-col items-center justify-center">
                    <img id="edit_photoPreview" src="#" alt="Employee Photo" class="photo-preview"/>
                    <label for="edit_photo" class="mt-4 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg cursor-pointer hover:bg-gray-300 transition-colors"><i class="fas fa-upload mr-2"></i>Change Photo</label>
                    <input type="file" name="edit_photo" id="edit_photo" class="hidden" accept="image/*">
                </div>
                <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div><label for="edit_name" class="block text-sm font-medium text-slate-700 mb-2">Full Name (English) *</label><input type="text" name="edit_name" id="edit_name" required class="w-full modern-input"></div>
                    <div><label for="edit_name_am" class="block text-sm font-medium text-slate-700 mb-2 ethio-font">ሙሉ ስም (አማርኛ)</label><input type="text" name="edit_name_am" id="edit_name_am" class="w-full modern-input ethio-font"></div>
                    <div><label for="edit_salary" class="block text-sm font-medium text-slate-700 mb-2">Salary (ETB) *</label><input type="number" step="0.01" name="edit_salary" id="edit_salary" required class="w-full modern-input"></div>
                    <div><label for="edit_taamagoli" class="block text-sm font-medium text-slate-700 mb-2">Position / Title *</label><input type="text" name="edit_taamagoli" id="edit_taamagoli" required class="w-full modern-input"></div>
                    <div class="md:col-span-2"><label for="edit_directorate" class="block text-sm font-medium text-slate-700 mb-2">Department *</label><select name="edit_directorate" id="edit_directorate" required class="w-full modern-input bg-white">
                        <option value="" disabled>Select a Department</option>
                        <?php foreach ($budget_owners as $owner): ?><option value="<?php echo htmlspecialchars($owner['name']); ?>"><?php echo htmlspecialchars($owner['name']); ?></option><?php endforeach; ?>
                    </select></div>
                </div>
            </div>
            <div class="flex justify-end space-x-4 pt-4">
                <button type="button" id="cancelEdit" class="px-6 py-3 bg-gray-300 text-gray-700 rounded-xl hover:bg-gray-400">Cancel</button>
                <button type="submit" class="px-6 py-3 gradient-button text-white rounded-xl">Save Changes</button>
            </div>
        </form>
    </div></div></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        const csrfToken = '<?php echo htmlspecialchars($_SESSION['csrf']); ?>';

        // Display flash messages from PHP using SweetAlert
        <?php if ($flash_message): ?>
        Swal.fire({
            icon: <?php echo json_encode($flash_type); ?>,
            title: <?php echo json_encode(ucfirst($flash_type) . '!'); ?>,
            text: <?php echo json_encode($flash_message); ?>,
            toast: true, position: 'top-end', showConfirmButton: false, timer: 4000, timerProgressBar: true
        });
        <?php endif; ?>

        // Photo Preview Logic for main form
        $('#photo').on('change', function(event) {
            const [file] = event.target.files;
            if (file) {
                $('#photoPreview').attr('src', URL.createObjectURL(file)).removeClass('hidden');
                $('#photoPreviewPlaceholder').addClass('hidden');
            }
        });

        // Function to load employees into the table
        function loadEmployees() {
            $.ajax({
                url: 'ajax_get_employees.php', // This file fetches the list of all employees
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    const tbody = $('#employeeTableBody');
                    tbody.empty();
                    if (response.success && response.employees.length > 0) {
                        response.employees.forEach(emp => {
                            const photoHtml = emp.photo ? `<img src="${emp.photo}" alt="${emp.name}" class="h-10 w-10 rounded-full object-cover">` : `<div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center"><i class="fas fa-user text-gray-500"></i></div>`;
                            const salaryFormatted = parseFloat(emp.salary).toLocaleString('en-US', { style: 'currency', currency: 'ETB' });
                            const row = `
                                <tr data-id="${emp.id}" class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3 whitespace-nowrap">${photoHtml}</td>
                                    <td class="px-4 py-3 whitespace-nowrap"><div class="font-medium text-gray-900">${emp.name || ''}</div><div class="text-sm text-gray-500 ethio-font">${emp.name_am || ''}</div></td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">${emp.taamagoli || ''}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">${emp.directorate || ''}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-800">${salaryFormatted}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-center">
                                        <button class="text-blue-600 hover:text-blue-900 edit-btn" title="Edit"><i class="fas fa-edit"></i></button>
                                        <button class="text-red-600 hover:text-red-900 ml-2 delete-btn" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                        <button class="text-green-600 hover:text-green-900 ml-2 print-btn" title="Print"><i class="fas fa-print"></i></button>
                                    </td>
                                </tr>`;
                            tbody.append(row);
                        });
                    } else {
                        tbody.html('<tr><td colspan="6" class="p-8 text-center text-gray-500">No employees found. Register one above.</td></tr>');
                    }
                },
                error: function() {
                    $('#employeeTableBody').html('<tr><td colspan="6" class="p-8 text-center text-red-500">Error: Could not load employee data.</td></tr>');
                }
            });
        }
        loadEmployees(); // Initial load of employee list

        // --- Modal Control ---
        const modal = $('#editEmployeeModal');
        function showModal() { modal.css('display', 'flex'); setTimeout(() => modal.find('.modal-content').css({ 'transform': 'scale(1)', 'opacity': '1' }), 10); }
        function hideModal() { modal.find('.modal-content').css({ 'transform': 'scale(0.95)', 'opacity': '0' }); setTimeout(() => modal.hide(), 200); }
        $('#closeEditModal, #cancelEdit').on('click', hideModal);

        // --- Event Delegation for Dynamic Content ---
        // This is the key to making clicks work on content loaded by AJAX
        $('#employeeTableBody').on('click', 'tr', function(e) {
            if ($(e.target).closest('button').length) return; // Ignore clicks on buttons within the row
            const empId = $(this).data('id');
            if (empId) openEditModalFor(empId);
        });
        $('#employeeTableBody').on('click', '.edit-btn', function(e) { e.stopPropagation(); openEditModalFor($(this).closest('tr').data('id')); });
        $('#employeeTableBody').on('click', '.delete-btn', function(e) { e.stopPropagation(); deleteEmployee($(this).closest('tr').data('id')); });
        $('#employeeTableBody').on('click', '.print-btn', function(e) { e.stopPropagation(); printEmployee($(this).closest('tr').data('id')); });

        // Function to open the Edit Modal with employee data
        function openEditModalFor(empId) {
             $.ajax({
                url: 'ajax_get_employee_details.php', // This file fetches details for ONE employee
                type: 'GET', data: { id: empId }, dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const emp = response.employee;
                        $('#edit_id').val(emp.id);
                        $('#edit_name').val(emp.name);
                        $('#edit_name_am').val(emp.name_am);
                        $('#edit_salary').val(emp.salary);
                        $('#edit_taamagoli').val(emp.taamagoli);
                        $('#edit_directorate').val(emp.directorate);
                        $('#current_photo').val(emp.photo);
                        $('#edit_photoPreview').attr('src', emp.photo || 'https://placehold.co/128x128/EFEFEF/AAAAAA&text=No+Photo');
                        showModal();
                    } else { Swal.fire('Error', response.error || 'Could not fetch employee details.', 'error'); }
                },
                error: function(jqXHR, textStatus, errorThrown) { Swal.fire('AJAX Error!', `Failed to fetch details. Status: ${textStatus}. Error: ${errorThrown}`, 'error'); }
            });
        }

        // Function to handle employee deletion
        function deleteEmployee(empId) {
            Swal.fire({
                title: 'Are you sure?', text: "This action cannot be undone!", icon: 'warning',
                showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'ajax_delete_employee.php',
                        type: 'POST', data: { id: empId, csrf: csrfToken }, dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire('Deleted!', 'The employee has been removed.', 'success');
                                loadEmployees();
                            } else { Swal.fire('Error!', response.error || 'Could not delete employee.', 'error'); }
                        },
                        error: function() { Swal.fire('AJAX Error!', 'Could not contact server to delete.', 'error'); }
                    });
                }
            });
        }
        
        // Function to handle printing
        function printEmployee(empId) {
            $.ajax({
                url: 'ajax_get_employee_details.php', type: 'GET', data: { id: empId }, dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const emp = response.employee;
                        const salaryFormatted = parseFloat(emp.salary).toLocaleString('en-US', { style: 'currency', currency: 'ETB' });
                        const printWindow = window.open('', '_blank');
                        printWindow.document.write(`<html><head><title>Print: ${emp.name}</title><script src="https://cdn.tailwindcss.com"><\/script><style>.ethio-font{font-family:'Nyala',sans-serif;} body{-webkit-print-color-adjust:exact;}</style></head><body class="bg-gray-100 p-8"><div class="max-w-2xl mx-auto bg-white p-10 rounded-lg shadow-xl"><div class="text-center mb-8"><img src="${emp.photo || 'https://placehold.co/128x128'}" class="w-32 h-32 rounded-full mx-auto object-cover border-4 border-gray-200 shadow-md"><h1 class="text-3xl font-bold text-gray-800 mt-4">${emp.name}</h1><p class="text-xl text-gray-600">${emp.taamagoli}</p></div><div class="border-t pt-6"><dl class="grid grid-cols-2 gap-x-4 gap-y-8"><div class="sm:col-span-1"><dt class="text-sm font-medium text-gray-500">Amharic Name</dt><dd class="mt-1 text-lg ethio-font">${emp.name_am||'N/A'}</dd></div><div class="sm:col-span-1"><dt class="text-sm font-medium text-gray-500">Department</dt><dd class="mt-1 text-lg">${emp.directorate}</dd></div><div class="sm:col-span-2"><dt class="text-sm font-medium text-gray-500">Salary</dt><dd class="mt-1 text-2xl font-semibold">${salaryFormatted}</dd></div></dl></div></div><script>window.onload=function(){window.print();setTimeout(function(){window.close();},100);}<\/script></body></html>`);
                        printWindow.document.close();
                    } else { Swal.fire('Error', 'Could not fetch data for printing.', 'error'); }
                },
                error: function() { Swal.fire('AJAX Error!', 'Could not contact server for printing.', 'error'); }
            });
        }

        // Handle Edit Form Submission
         $('#editEmployeeForm').on('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            $.ajax({
                url: 'ajax_update_employee.php',
                type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        hideModal();
                        Swal.fire({ icon: 'success', title: 'Updated!', text: 'Employee details saved.', timer: 2000, showConfirmButton: false });
                        loadEmployees();
                    } else { Swal.fire('Error!', response.error || 'Could not update employee.', 'error'); }
                },
                error: function() { Swal.fire('AJAX Error!', 'Could not contact server to update.', 'error'); }
            });
        });
        $('#edit_photo').on('change', function(event) {
            const [file] = event.target.files;
            if (file) $('#edit_photoPreview').attr('src', URL.createObjectURL(file));
        });
    });
    </script>
</body>
</html>