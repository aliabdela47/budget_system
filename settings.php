<?php
require_once 'includes/init.php';
require_once 'includes/sidebar.php';
if (($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['flash_error'] = 'You are not authorized to access that page.';
    header('Location: dashboard.php');
    exit;
}


// Get current tab or default to 'owners'
$tab = $_GET['tab'] ?? 'owners';

// Owners Logic
$owners = $pdo->query("SELECT * FROM budget_owners")->fetchAll();
$owner = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id']) && $tab == 'owners') {
    $stmt = $pdo->prepare("SELECT * FROM budget_owners WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $owner = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tab']) && $_POST['tab'] == 'owners') {
    $code = $_POST['code'];
    $name = $_POST['name'];
    $p_koox = $_POST['p_koox'] ?? '';
    
    if (isset($_POST['id']) && $_POST['action'] == 'update') {
        $stmt = $pdo->prepare("UPDATE budget_owners SET code = ?, name = ?, p_koox = ? WHERE id = ?");
        $stmt->execute([$code, $name, $p_koox, $_POST['id']]);
        $message = 'Owner updated';
    } else {
        $stmt = $pdo->prepare("INSERT INTO budget_owners (code, name, p_koox) VALUES (?, ?, ?)");
        $stmt->execute([$code, $name, $p_koox]);
        $message = 'Owner added';
    }
    header('Location: settings.php?tab=owners');
    exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id']) && $tab == 'owners') {
    $stmt = $pdo->prepare("DELETE FROM budget_owners WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $message = 'Owner deleted';
    header('Location: settings.php?tab=owners');
    exit;
}

// Codes Logic
$codes = $pdo->query("SELECT * FROM budget_codes")->fetchAll();
$code = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id']) && $tab == 'codes') {
    $stmt = $pdo->prepare("SELECT * FROM budget_codes WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $code = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tab']) && $_POST['tab'] == 'codes') {
    $code_val = $_POST['code'];
    $name = $_POST['name'];
    if (isset($_POST['id']) && $_POST['action'] == 'update') {
        $stmt = $pdo->prepare("UPDATE budget_codes SET code = ?, name = ? WHERE id = ?");
        $stmt->execute([$code_val, $name, $_POST['id']]);
        $message = 'Code updated';
    } else {
        $stmt = $pdo->prepare("INSERT INTO budget_codes (code, name) VALUES (?, ?)");
        $stmt->execute([$code_val, $name]);
        $message = 'Code added';
    }
    header('Location: settings.php?tab=codes');
    exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id']) && $tab == 'codes') {
    $stmt = $pdo->prepare("DELETE FROM budget_codes WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $message = 'Code deleted';
    header('Location: settings.php?tab=codes');
    exit;
}

// Vehicles Logic
$vehicles = $pdo->query("SELECT * FROM vehicles")->fetchAll();
$vehicle = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id']) && $tab == 'vehicles') {
    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $vehicle = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tab']) && $_POST['tab'] == 'vehicles') {
    $model = $_POST['model'];
    $plate_no = $_POST['plate_no'];
    $chassis_no = $_POST['chassis_no'] ?? null;

    // Validate uniqueness of plate_no
    if (isset($_POST['id'])) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM vehicles WHERE plate_no = ? AND id != ?");
        $stmt->execute([$plate_no, $_POST['id']]);
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM vehicles WHERE plate_no = ?");
        $stmt->execute([$plate_no]);
    }
    if ($stmt->fetchColumn() > 0) {
        $message = 'Plate number must be unique.';
    } else {
        if (isset($_POST['id']) && $_POST['action'] == 'update') {
            $stmt = $pdo->prepare("UPDATE vehicles SET model = ?, plate_no = ?, chassis_no = ? WHERE id = ?");
            $stmt->execute([$model, $plate_no, $chassis_no, $_POST['id']]);
            $message = 'Vehicle updated';
        } else {
            $stmt = $pdo->prepare("INSERT INTO vehicles (model, plate_no, chassis_no) VALUES (?, ?, ?)");
            $stmt->execute([$model, $plate_no, $chassis_no]);
            $message = 'Vehicle added';
        }
    }
    header('Location: settings.php?tab=vehicles');
    exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id']) && $tab == 'vehicles') {
    $stmt = $pdo->prepare("DELETE FROM vehicles WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $message = 'Vehicle deleted';
    header('Location: settings.php?tab=vehicles');
    exit;
}

// Get current message
$message = $message ?? null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Settings - Budget System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap');
        
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
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .btn-primary {
            background-color: #4f46e5;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #4338ca;
        }
        
        .btn-secondary {
            background-color: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #4b5563;
        }
        
        .btn-danger {
            background-color: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #dc2626;
        }
        
        .btn-info {
            background-color: #06b6d4;
            color: white;
        }
        
        .btn-info:hover {
            background-color: #0891b2;
        }
        
        /* Tabs Styling */
        .tab-group {
            display: flex;
            background: white;
            border-radius: 0.5rem 0.5rem 0 0;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .tab-button {
            flex: 1;
            padding: 1rem 1.5rem;
            text-align: center;
            font-weight: 600;
            font-size: 0.95rem;
            color: #6b7280;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .tab-button.active {
            color: #4f46e5;
            border-bottom: 2px solid #4f46e5;
        }
        
        .tab-button:hover {
            color: #4f46e5;
            background: rgba(79, 70, 229, 0.05);
        }
        
        .tab-button::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, #4f46e5, #7c3aed);
            transform: scaleX(0);
            transition: transform 0.3s ease;
            transform-origin: center;
        }
        
        .tab-button.active::after, .tab-button:hover::after {
            transform: scaleX(1);
        }
        
        .tab-content {
            background: white;
            border-radius: 0 0 0.5rem 0.5rem;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .tab-panel {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        .tab-panel.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Table styling improvements */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .data-table th {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 0.75rem 1rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .data-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        .data-table tr {
            transition: background-color 0.2s ease;
        }
        
        .data-table tr:hover {
            background-color: #f9fafb;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }
        
        /* Form improvements */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
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
                        System Settings
                    </h1>
                    <p class="text-slate-600 mt-2">Manage budget owners, codes, and vehicles</p>
                </div>
                <div class="flex items-center space-x-4 mt-4 md:mt-0">
                    <button class="bg-slate-200 hover:bg-slate-300 text-slate-700 p-2 rounded-lg md:hidden shadow-sm" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="tab-group">
                <button onclick="openTab('owners')" class="tab-button <?php echo $tab == 'owners' ? 'active' : ''; ?>">
                    <i class="fas fa-building mr-2"></i> Owners
                </button>
                <button onclick="openTab('codes')" class="tab-button <?php echo $tab == 'codes' ? 'active' : ''; ?>">
                    <i class="fas fa-code mr-2"></i> Codes
                </button>
                <button onclick="openTab('vehicles')" class="tab-button <?php echo $tab == 'vehicles' ? 'active' : ''; ?>">
                    <i class="fas fa-car mr-2"></i> Vehicles
                </button>
            </div>

            <!-- Tab Content -->
            <div class="tab-content">
                <!-- Owners Tab -->
                <div id="owners" class="tab-panel <?php echo $tab == 'owners' ? 'active' : ''; ?>">
                    <h2 class="text-xl font-bold text-slate-800 mb-6">Budget Owners</h2>
                    <form method="post" class="space-y-6">
                        <input type="hidden" name="tab" value="owners">
                        <?php if ($owner): ?>
                            <input type="hidden" name="id" value="<?php echo $owner['id']; ?>">
                            <input type="hidden" name="action" value="update">
                        <?php endif; ?>
                        
                        <div class="form-grid">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Code</label>
                                <div class="input-group">
                                    <input type="text" name="code" value="<?php echo $owner ? $owner['code'] : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                                <div class="input-group">
                                    <input type="text" name="name" value="<?php echo $owner ? $owner['name'] : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">P Koox</label>
                                <div class="input-group">
                                    <input type="text" name="p_koox" value="<?php echo $owner ? $owner['p_koox'] : ''; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas <?php echo $owner ? 'fa-sync' : 'fa-plus'; ?> mr-2"></i>
                                <?php echo $owner ? 'Update Owner' : 'Add Owner'; ?>
                            </button>
                            <button type="button" class="btn btn-info" onclick="window.print()">
                                <i class="fas fa-print mr-2"></i> Print
                            </button>
                        </div>
                    </form>
                    
                    <h3 class="text-lg font-bold text-slate-800 mt-8 mb-4">Existing Owners</h3>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>P Koox</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($owners as $o): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="font-medium"><?php echo htmlspecialchars($o['code']); ?></td>
                                        <td><?php echo htmlspecialchars($o['name']); ?></td>
                                        <td><?php echo htmlspecialchars($o['p_koox']); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="?tab=owners&action=edit&id=<?php echo $o['id']; ?>" class="btn btn-secondary">
                                                    <i class="fas fa-edit mr-1"></i> Edit
                                                </a>
                                                <a href="?tab=owners&action=delete&id=<?php echo $o['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure?')">
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

                <!-- Codes Tab -->
                <div id="codes" class="tab-panel <?php echo $tab == 'codes' ? 'active' : ''; ?>">
                    <h2 class="text-xl font-bold text-slate-800 mb-6">Budget Codes</h2>
                    <form method="post" class="space-y-6">
                        <input type="hidden" name="tab" value="codes">
                        <?php if ($code): ?>
                            <input type="hidden" name="id" value="<?php echo $code['id']; ?>">
                            <input type="hidden" name="action" value="update">
                        <?php endif; ?>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Code</label>
                                <div class="input-group">
                                    <input type="text" name="code" value="<?php echo $code ? $code['code'] : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                                <div class="input-group">
                                    <input type="text" name="name" value="<?php echo $code ? $code['name'] : ''; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex space-x-4 mt-6">
                            <button type="submit" class="btn-primary">
                                <i class="fas <?php echo $code ? 'fa-sync' : 'fa-plus'; ?> mr-2"></i>
                                <?php echo $code ? 'Update Code' : 'Add Code'; ?>
                            </button>
                            <button type="button" class="btn-info" onclick="window.print()">
                                <i class="fas fa-print mr-2"></i> Print
                            </button>
                        </div>
                    </form>
                    
                    <h3 class="text-lg font-bold text-slate-800 mt-8 mb-4">Existing Codes</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-slate-600">
                            <thead class="text-xs uppercase bg-slate-100 text-slate-700">
                                <tr>
                                    <th class="px-4 py-3">Code</th>
                                    <th class="px-4 py-3">Name</th>
                                    <th class="px-4 py-3 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($codes as $c): ?>
                                    <tr class="border-b border-slate-200 hover:bg-slate-50">
                                        <td class="px-4 py-2"><?php echo htmlspecialchars($c['code']); ?></td>
                                        <td class="px-4 py-2"><?php echo htmlspecialchars($c['name']); ?></td>
                                        <td class="px-4 py-2 text-center">
                                            <a href="?tab=codes&action=edit&id=<?php echo $c['id']; ?>" class="btn-secondary btn-sm">
                                                <i class="fas fa-edit mr-1"></i> Edit
                                            </a>
                                            <a href="?tab=codes&action=delete&id=<?php echo $c['id']; ?>" class="btn-danger btn-sm" onclick="return confirm('Are you sure?')">
                                                <i class="fas fa-trash mr-1"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Vehicles Tab -->
                <div id="vehicles" class="tab-panel <?php echo $tab == 'vehicles' ? 'active' : ''; ?>">
                    <h2 class="text-xl font-bold text-slate-800 mb-6">Vehicles</h2>
                    <form method="post" class="space-y-6">
                        <input type="hidden" name="tab" value="vehicles">
                        <?php if ($vehicle): ?>
                            <input type="hidden" name="id" value="<?php echo $vehicle['id']; ?>">
                            <input type="hidden" name="action" value="update">
                        <?php endif; ?>
                        
                        <div class="form-grid">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Model</label>
                                <div class="input-group">
                                    <input type="text" name="model" value="<?php echo $vehicle ? htmlspecialchars($vehicle['model']) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Plate No</label>
                                <div class="input-group">
                                    <input type="text" name="plate_no" value="<?php echo $vehicle ? htmlspecialchars($vehicle['plate_no']) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Chassis No</label>
                                <div class="input-group">
                                    <input type="text" name="chassis_no" value="<?php echo $vehicle ? htmlspecialchars($vehicle['chassis_no']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas <?php echo $vehicle ? 'fa-sync' : 'fa-plus'; ?> mr-2"></i>
                                <?php echo $vehicle ? 'Update Vehicle' : 'Add Vehicle'; ?>
                            </button>
                            <button type="button" class="btn btn-info" onclick="window.print()">
                                <i class="fas fa-print mr-2"></i> Print
                            </button>
                        </div>
                    </form>
                    
                    <h3 class="text-lg font-bold text-slate-800 mt-8 mb-4">Existing Vehicles</h3>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Model</th>
                                    <th>Plate No</th>
                                    <th>Chassis No</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vehicles as $v): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td><?php echo htmlspecialchars($v['model']); ?></td>
                                        <td class="font-medium"><?php echo htmlspecialchars($v['plate_no']); ?></td>
                                        <td><?php echo htmlspecialchars($v['chassis_no'] ?? 'N/A'); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="?tab=vehicles&action=edit&id=<?php echo $v['id']; ?>" class="btn btn-secondary">
                                                    <i class="fas fa-edit mr-1"></i> Edit
                                                </a>
                                                <a href="?tab=vehicles&action=delete&id=<?php echo $v['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure?')">
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

            // Set initial tab
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab') || 'owners';
            openTab(tab);
        });

        function openTab(tabName) {
            const buttons = document.querySelectorAll('.tab-button');
            buttons.forEach(btn => btn.classList.remove('active'));
            document.querySelector(`[onclick="openTab('${tabName}')"]`).classList.add('active');
            
            const panels = document.querySelectorAll('.tab-panel');
            panels.forEach(panel => panel.classList.remove('active'));
            document.getElementById(tabName).classList.add('active');
            
            // Update URL without reload
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            history.pushState(null, '', url);
        }

        // Simple confirmation for delete actions
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButtons = document.querySelectorAll('a[href*="action=delete"]');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to delete this item?')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>