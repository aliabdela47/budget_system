<?php
require_once 'includes/init.php';

// Enhanced flash messaging with SweetAlert2
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
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
        $_SESSION['flash_message'] = 'Owner updated successfully';
        $_SESSION['flash_type'] = 'success';
    } else {
        $stmt = $pdo->prepare("INSERT INTO budget_owners (code, name, p_koox) VALUES (?, ?, ?)");
        $stmt->execute([$code, $name, $p_koox]);
        $_SESSION['flash_message'] = 'Owner added successfully';
        $_SESSION['flash_type'] = 'success';
    }
    header('Location: settings.php?tab=owners');
    exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id']) && $tab == 'owners') {
    $stmt = $pdo->prepare("DELETE FROM budget_owners WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $_SESSION['flash_message'] = 'Owner deleted successfully';
    $_SESSION['flash_type'] = 'success';
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
        $_SESSION['flash_message'] = 'Code updated successfully';
        $_SESSION['flash_type'] = 'success';
    } else {
        $stmt = $pdo->prepare("INSERT INTO budget_codes (code, name) VALUES (?, ?)");
        $stmt->execute([$code_val, $name]);
        $_SESSION['flash_message'] = 'Code added successfully';
        $_SESSION['flash_type'] = 'success';
    }
    header('Location: settings.php?tab=codes');
    exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id']) && $tab == 'codes') {
    $stmt = $pdo->prepare("DELETE FROM budget_codes WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $_SESSION['flash_message'] = 'Code deleted successfully';
    $_SESSION['flash_type'] = 'success';
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
        $_SESSION['flash_message'] = 'Plate number must be unique.';
        $_SESSION['flash_type'] = 'error';
    } else {
        if (isset($_POST['id']) && $_POST['action'] == 'update') {
            $stmt = $pdo->prepare("UPDATE vehicles SET model = ?, plate_no = ?, chassis_no = ? WHERE id = ?");
            $stmt->execute([$model, $plate_no, $chassis_no, $_POST['id']]);
            $_SESSION['flash_message'] = 'Vehicle updated successfully';
            $_SESSION['flash_type'] = 'success';
        } else {
            $stmt = $pdo->prepare("INSERT INTO vehicles (model, plate_no, chassis_no) VALUES (?, ?, ?)");
            $stmt->execute([$model, $plate_no, $chassis_no]);
            $_SESSION['flash_message'] = 'Vehicle added successfully';
            $_SESSION['flash_type'] = 'success';
        }
    }
    header('Location: settings.php?tab=vehicles');
    exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id']) && $tab == 'vehicles') {
    $stmt = $pdo->prepare("DELETE FROM vehicles WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $_SESSION['flash_message'] = 'Vehicle deleted successfully';
    $_SESSION['flash_type'] = 'success';
    header('Location: settings.php?tab=vehicles');
    exit;
}
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
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        
        /* Modern Tab Styling */
        .tab-group {
            display: flex;
            background: white;
            border-radius: 1rem 1rem 0 0;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 0;
        }
        
        .tab-button {
            flex: 1;
            padding: 1.25rem 1.5rem;
            text-align: center;
            font-weight: 600;
            font-size: 0.95rem;
            color: #6b7280;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            position: relative;
            background: transparent;
            cursor: pointer;
        }
        
        .tab-button.active {
            color: #4f46e5;
            border-bottom: 3px solid #4f46e5;
            background: linear-gradient(to bottom, rgba(79, 70, 229, 0.05), transparent);
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
            height: 3px;
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
            border-radius: 0 0 1rem 1rem;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            margin-top: 0;
        }
        
        .tab-panel {
            display: none;
            animation: fadeIn 0.4s ease;
        }
        
        .tab-panel.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Modern Card Styling */
        .modern-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(229, 231, 235, 0.8);
            transition: all 0.3s ease;
        }
        
        .modern-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
        }
        
        /* Modern Input Styling */
        .modern-input {
            transition: all 0.3s ease;
            border: 1px solid #d1d5db;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            width: 100%;
            background: white;
            font-size: 0.95rem;
        }
        
        .modern-input:focus {
            transform: translateY(-2px);
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
            outline: none;
        }
        
        /* Modern Button Styling */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(79, 70, 229, 0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6b7280 0%, #9ca3af 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(107, 114, 128, 0.4);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(239, 68, 68, 0.4);
        }
        
        .btn-info {
            background: linear-gradient(135deg, #06b6d4 0%, #67e8f9 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3);
        }
        
        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(6, 182, 212, 0.4);
        }

        /* Modern Table Styling */
        .modern-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        .modern-table th {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 1rem 1.25rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .modern-table td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #f3f4f6;
            color: #4b5563;
        }
        
        .modern-table tr:last-child td {
            border-bottom: none;
        }
        
        .modern-table tr {
            transition: all 0.2s ease;
        }
        
        .modern-table tr:hover {
            background-color: #f9fafb;
            transform: scale(1.01);
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-start;
            flex-wrap: wrap;
        }
        
        /* Form improvements */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        /* Responsive improvements */
        @media (max-width: 768px) {
            .tab-group {
                flex-direction: column;
                border-radius: 1rem;
                margin-bottom: 1.5rem;
            }
            
            .tab-button {
                border-bottom: 1px solid #e5e7eb;
                border-right: none;
            }
            
            .tab-button.active {
                border-bottom: 1px solid #e5e7eb;
                border-right: 3px solid #4f46e5;
            }
            
            .tab-content {
                padding: 1.5rem;
                border-radius: 1rem;
            }
            
            .modern-card {
                padding: 1.5rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .action-buttons {
                justify-content: center;
            }
            
            .modern-table {
                font-size: 0.875rem;
            }
            
            .modern-table th,
            .modern-table td {
                padding: 0.75rem 0.5rem;
            }
        }

        /* Gradient text effect */
        .gradient-text {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Loading animation */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .loading {
            animation: pulse 1.5s infinite;
        }
    </style>
</head>
<body class="text-slate-700 flex">
    <!-- Sidebar -->
    <?php require_once  'includes/sidebar-component.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <?php require_once 'includes/header.php'; ?>
        
        <div class="p-6">
            <!-- Enhanced Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 p-8 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 rounded-2xl shadow-xl text-white">
                <div>
                    <h1 class="text-3xl md:text-4xl font-bold mb-3">
                        System Settings
                    </h1>
                    <p class="text-indigo-100 text-lg">Manage Directorates, Budget Codes, and Vehicles</p>
                </div>
                <div class="flex items-center space-x-4 mt-4 md:mt-0">
                    <button class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white p-3 rounded-xl md:hidden shadow-lg backdrop-blur-sm transition-all duration-200" id="sidebarToggle">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>

            <!-- Modern Tabs -->
            <div class="tab-group">
                <button onclick="openTab('owners')" class="tab-button <?php echo $tab == 'owners' ? 'active' : ''; ?>">
                    <i class="fas fa-building mr-3"></i> Budget Owners
                </button>
                <button onclick="openTab('codes')" class="tab-button <?php echo $tab == 'codes' ? 'active' : ''; ?>">
                    <i class="fas fa-code mr-3"></i> Budget Codes
                </button>
                <button onclick="openTab('vehicles')" class="tab-button <?php echo $tab == 'vehicles' ? 'active' : ''; ?>">
                    <i class="fas fa-car mr-3"></i> Vehicles
                </button>
            </div>

            <!-- Tab Content -->
            <div class="tab-content">
                <!-- Owners Tab -->
                <div id="owners" class="tab-panel <?php echo $tab == 'owners' ? 'active' : ''; ?>">
                    <div class="modern-card mb-8">
                        <h2 class="text-2xl font-bold text-slate-800 mb-6 flex items-center">
                            <i class="fas fa-building mr-3 text-indigo-500"></i>
                            <?php echo $owner ? 'Edit Budget Owner' : 'Add New Budget Owner'; ?>
                        </h2>
                        <form method="post" class="space-y-6">
                            <input type="hidden" name="tab" value="owners">
                            <?php if ($owner): ?>
                                <input type="hidden" name="id" value="<?php echo $owner['id']; ?>">
                                <input type="hidden" name="action" value="update">
                            <?php endif; ?>
                            
                            <div class="form-grid">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Code *</label>
                                    <input type="text" name="code" value="<?php echo $owner ? htmlspecialchars($owner['code']) : ''; ?>" required class="modern-input" placeholder="Enter owner code">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Name *</label>
                                    <input type="text" name="name" value="<?php echo $owner ? htmlspecialchars($owner['name']) : ''; ?>" required class="modern-input" placeholder="Enter owner name">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">P Koox</label>
                                    <input type="text" name="p_koox" value="<?php echo $owner ? htmlspecialchars($owner['p_koox']) : ''; ?>" class="modern-input" placeholder="Enter P Koox">
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas <?php echo $owner ? 'fa-sync' : 'fa-plus'; ?> mr-2"></i>
                                    <?php echo $owner ? 'Update Owner' : 'Add Owner'; ?>
                                </button>
                                <?php if ($owner): ?>
                                    <a href="settings.php?tab=owners" class="btn btn-secondary">
                                        <i class="fas fa-times mr-2"></i> Cancel
                                    </a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-info" onclick="window.print()">
                                    <i class="fas fa-print mr-2"></i> Print
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="modern-card">
                        <h3 class="text-xl font-bold text-slate-800 mb-6 flex items-center">
                            <i class="fas fa-list mr-3 text-indigo-500"></i>
                            Existing Budget Owners
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="modern-table">
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
                                        <tr>
                                            <td class="font-semibold text-indigo-600"><?php echo htmlspecialchars($o['code']); ?></td>
                                            <td><?php echo htmlspecialchars($o['name']); ?></td>
                                            <td><?php echo htmlspecialchars($o['p_koox']); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="?tab=owners&action=edit&id=<?php echo $o['id']; ?>" class="btn btn-secondary">
                                                        <i class="fas fa-edit mr-1"></i> Edit
                                                    </a>
                                                    <button onclick="confirmDelete('owner', <?php echo $o['id']; ?>)" class="btn btn-danger">
                                                        <i class="fas fa-trash mr-1"></i> Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Codes Tab -->
                <div id="codes" class="tab-panel <?php echo $tab == 'codes' ? 'active' : ''; ?>">
                    <div class="modern-card mb-8">
                        <h2 class="text-2xl font-bold text-slate-800 mb-6 flex items-center">
                            <i class="fas fa-code mr-3 text-indigo-500"></i>
                            <?php echo $code ? 'Edit Budget Code' : 'Add New Budget Code'; ?>
                        </h2>
                        <form method="post" class="space-y-6">
                            <input type="hidden" name="tab" value="codes">
                            <?php if ($code): ?>
                                <input type="hidden" name="id" value="<?php echo $code['id']; ?>">
                                <input type="hidden" name="action" value="update">
                            <?php endif; ?>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Code *</label>
                                    <input type="text" name="code" value="<?php echo $code ? htmlspecialchars($code['code']) : ''; ?>" required class="modern-input" placeholder="Enter budget code">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Name *</label>
                                    <input type="text" name="name" value="<?php echo $code ? htmlspecialchars($code['name']) : ''; ?>" required class="modern-input" placeholder="Enter code name">
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas <?php echo $code ? 'fa-sync' : 'fa-plus'; ?> mr-2"></i>
                                    <?php echo $code ? 'Update Code' : 'Add Code'; ?>
                                </button>
                                <?php if ($code): ?>
                                    <a href="settings.php?tab=codes" class="btn btn-secondary">
                                        <i class="fas fa-times mr-2"></i> Cancel
                                    </a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-info" onclick="window.print()">
                                    <i class="fas fa-print mr-2"></i> Print
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="modern-card">
                        <h3 class="text-xl font-bold text-slate-800 mb-6 flex items-center">
                            <i class="fas fa-list mr-3 text-indigo-500"></i>
                            Existing Budget Codes
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($codes as $c): ?>
                                        <tr>
                                            <td class="font-semibold text-indigo-600"><?php echo htmlspecialchars($c['code']); ?></td>
                                            <td><?php echo htmlspecialchars($c['name']); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="?tab=codes&action=edit&id=<?php echo $c['id']; ?>" class="btn btn-secondary">
                                                        <i class="fas fa-edit mr-1"></i> Edit
                                                    </a>
                                                    <button onclick="confirmDelete('code', <?php echo $c['id']; ?>)" class="btn btn-danger">
                                                        <i class="fas fa-trash mr-1"></i> Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Vehicles Tab -->
                <div id="vehicles" class="tab-panel <?php echo $tab == 'vehicles' ? 'active' : ''; ?>">
                    <div class="modern-card mb-8">
                        <h2 class="text-2xl font-bold text-slate-800 mb-6 flex items-center">
                            <i class="fas fa-car mr-3 text-indigo-500"></i>
                            <?php echo $vehicle ? 'Edit Vehicle' : 'Add New Vehicle'; ?>
                        </h2>
                        <form method="post" class="space-y-6">
                            <input type="hidden" name="tab" value="vehicles">
                            <?php if ($vehicle): ?>
                                <input type="hidden" name="id" value="<?php echo $vehicle['id']; ?>">
                                <input type="hidden" name="action" value="update">
                            <?php endif; ?>
                            
                            <div class="form-grid">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Model *</label>
                                    <input type="text" name="model" value="<?php echo $vehicle ? htmlspecialchars($vehicle['model']) : ''; ?>" required class="modern-input" placeholder="Enter vehicle model">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Plate No *</label>
                                    <input type="text" name="plate_no" value="<?php echo $vehicle ? htmlspecialchars($vehicle['plate_no']) : ''; ?>" required class="modern-input" placeholder="Enter plate number">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Chassis No</label>
                                    <input type="text" name="chassis_no" value="<?php echo $vehicle ? htmlspecialchars($vehicle['chassis_no']) : ''; ?>" class="modern-input" placeholder="Enter chassis number">
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas <?php echo $vehicle ? 'fa-sync' : 'fa-plus'; ?> mr-2"></i>
                                    <?php echo $vehicle ? 'Update Vehicle' : 'Add Vehicle'; ?>
                                </button>
                                <?php if ($vehicle): ?>
                                    <a href="settings.php?tab=vehicles" class="btn btn-secondary">
                                        <i class="fas fa-times mr-2"></i> Cancel
                                    </a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-info" onclick="window.print()">
                                    <i class="fas fa-print mr-2"></i> Print
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="modern-card">
                        <h3 class="text-xl font-bold text-slate-800 mb-6 flex items-center">
                            <i class="fas fa-list mr-3 text-indigo-500"></i>
                            Existing Vehicles
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="modern-table">
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
                                        <tr>
                                            <td><?php echo htmlspecialchars($v['model']); ?></td>
                                            <td class="font-semibold text-indigo-600"><?php echo htmlspecialchars($v['plate_no']); ?></td>
                                            <td><?php echo htmlspecialchars($v['chassis_no'] ?? 'N/A'); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="?tab=vehicles&action=edit&id=<?php echo $v['id']; ?>" class="btn btn-secondary">
                                                        <i class="fas fa-edit mr-1"></i> Edit
                                                    </a>
                                                    <button onclick="confirmDelete('vehicle', <?php echo $v['id']; ?>)" class="btn btn-danger">
                                                        <i class="fas fa-trash mr-1"></i> Delete
                                                    </button>
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
            
            // Show flash message if exists
            <?php if (isset($flash_message)): ?>
            showFlashMessage('<?php echo $flash_message; ?>', '<?php echo $flash_type; ?>');
            <?php endif; ?>
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

        function confirmDelete(type, id) {
            const typeNames = {
                'owner': 'Budget Owner',
                'code': 'Budget Code', 
                'vehicle': 'Vehicle'
            };
            
            const typeName = typeNames[type] || 'Item';
            
            Swal.fire({
                title: `<div class="flex items-center justify-center mb-4"><i class="fas fa-trash-alt text-4xl text-red-500 mr-3"></i><span class="text-2xl font-bold text-gray-800">Confirm Deletion</span></div>`,
                html: `
                <div class="text-center py-4">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-exclamation-triangle text-2xl text-red-600"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Are you sure you want to delete this ${typeName.toLowerCase()}?</h3>
                    <p class="text-gray-600 mb-4">This action cannot be undone and will permanently remove the ${typeName.toLowerCase()} from the system.</p>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-3 mt-4">
                        <p class="text-sm text-red-700 flex items-center justify-center">
                            <i class="fas fa-info-circle mr-2"></i>
                            All associated data with this ${typeName.toLowerCase()} will be lost.
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
                        title: '<div class="flex items-center justify-center"><i class="fas fa-spinner fa-spin text-2xl text-blue-500 mr-3"></i><span class="text-lg">Deleting...</span></div>',
                        text: 'Please wait while we remove the item',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        background: '#fff',
                        customClass: {
                            popup: 'rounded-2xl shadow-2xl'
                        }
                    });
                    
                    // Redirect to delete URL
                    window.location.href = `settings.php?tab=${type}s&action=delete&id=${id}`;
                }
            });
        }

        function showFlashMessage(message, type) {
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

        // Enhanced form submission with confirmation
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn && !this.classList.contains('no-confirm')) {
                        e.preventDefault();
                        
                        const isEdit = this.querySelector('input[name="action"]')?.value === 'update';
                        const itemType = this.querySelector('input[name="tab"]')?.value || 'item';
                        
                        Swal.fire({
                            title: `<div class="flex items-center justify-center mb-4">${isEdit ? '<i class="fas fa-sync text-4xl text-blue-500 mr-3"></i>' : '<i class="fas fa-plus-circle text-4xl text-green-500 mr-3"></i>'}<span class="text-2xl font-bold text-gray-800">${isEdit ? 'Confirm Update' : 'Confirm Creation'}</span></div>`,
                            html: `
                            <div class="text-center py-4">
                                <div class="w-16 h-16 ${isEdit ? 'bg-blue-100' : 'bg-green-100'} rounded-full flex items-center justify-center mx-auto mb-4">
                                    ${isEdit ? '<i class="fas fa-sync text-2xl text-blue-600"></i>' : '<i class="fas fa-plus text-2xl text-green-600"></i>'}
                                </div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-2">Are you sure you want to ${isEdit ? 'update' : 'create'} this ${itemType.slice(0, -1)}?</h3>
                                <p class="text-gray-600">${isEdit ? 'This will update the existing record with new information.' : 'This will create a new record in the system.'}</p>
                            </div>
                            `,
                            showCancelButton: true,
                            confirmButtonColor: isEdit ? '#3b82f6' : '#10b981',
                            cancelButtonColor: '#6b7280',
                            confirmButtonText: isEdit ? '<i class="fas fa-sync mr-2"></i>Yes, Update It' : '<i class="fas fa-plus mr-2"></i>Yes, Create It',
                            cancelButtonText: '<i class="fas fa-times mr-2"></i>Cancel',
                            background: '#fff',
                            customClass: {
                                popup: 'rounded-2xl shadow-2xl border border-gray-200',
                                confirmButton: 'px-6 py-3 rounded-lg font-semibold',
                                cancelButton: 'px-6 py-3 rounded-lg font-semibold'
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Show loading state
                                Swal.fire({
                                    title: `<div class="flex items-center justify-center"><i class="fas fa-spinner fa-spin text-2xl ${isEdit ? 'text-blue-500' : 'text-green-500'} mr-3"></i><span class="text-lg">${isEdit ? 'Updating...' : 'Creating...'}</span></div>`,
                                    text: `Please wait while we ${isEdit ? 'update' : 'create'} the ${itemType.slice(0, -1)}`,
                                    allowOutsideClick: false,
                                    showConfirmButton: false,
                                    background: '#fff',
                                    customClass: {
                                        popup: 'rounded-2xl shadow-2xl'
                                    }
                                });
                                
                                // Submit the form
                                form.submit();
                            }
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>