<?php
// employee_list_report.php
// Standalone employee list report with minimal dependencies

// Database connection (adjust these settings according to your environment)
$host = 'localhost';
$dbname = 'budget_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Start session for user info (if needed)
session_start();

// Helper function to safely encode strings for HTML
function safe_html($value) {
    if ($value === null) {
        return '';
    }
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// Fetch all employees
$stmt = $pdo->query("
    SELECT e.*, 
           COALESCE(e.name_am, '') as name_am,
           COALESCE(e.taamagoli, '') as position,
           COALESCE(e.directorate, '') as department,
           COALESCE(e.salary, 0) as salary,
           COALESCE(e.photo, '') as photo,
           DATE_FORMAT(e.created_at, '%Y-%m-%d') as join_date
    FROM emp_list e
    ORDER BY e.directorate, e.name
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group employees by department
$employees_by_department = [];
$total_salary = 0;
$employee_count = 0;

foreach ($employees as $employee) {
    $dept = !empty($employee['department']) ? $employee['department'] : 'Unassigned';
    if (!isset($employees_by_department[$dept])) {
        $employees_by_department[$dept] = [];
    }
    $employees_by_department[$dept][] = $employee;
    $total_salary += $employee['salary'];
    $employee_count++;
}

// Calculate average salary
$average_salary = $employee_count > 0 ? $total_salary / $employee_count : 0;

// Get current date for report
$current_date = date('F j, Y');
$current_time = date('h:i A');

// Get username from session or use default
$current_user = $_SESSION['username'] ?? 'System Administrator';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee List Report</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            color: #334155;
        }
        
        .ethio-font {
            font-family: 'Nyala', 'Abyssinica SIL', 'GF Zemen', sans-serif;
        }
        
        /* Print Styles */
        @media print {
            body {
                background: white !important;
                color: black !important;
                font-size: 12pt;
            }
            
            .no-print {
                display: none !important;
            }
            
            .print-break {
                page-break-after: always;
            }
            
            .print-break-before {
                page-break-before: always;
            }
            
            .print-shadow {
                box-shadow: none !important;
            }
            
            .print-border {
                border: 1px solid #e5e7eb !important;
            }
            
            .gradient-bg {
                background: #667eea !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            
            .summary-card {
                background: #f8fafc !important;
                border: 1px solid #e5e7eb !important;
            }
            
            .department-header {
                background: #3b82f6 !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            
            .report-table th {
                background: #667eea !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
        }
        
        /* Report Header Styles */
        .report-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px 16px 0 0;
        }
        
        .summary-card {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .department-header {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            border-radius: 8px;
        }
        
        .employee-card {
            border-left: 4px solid #3b82f6;
            transition: all 0.3s ease;
        }
        
        .employee-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .photo-placeholder {
            background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
        }
        
        /* Table Styles */
        .report-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .report-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            text-align: left;
            padding: 12px 16px;
        }
        
        .report-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .report-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        
        .report-table tr:hover {
            background-color: #f1f5f9;
        }
    </style>
</head>
<body class="p-4 bg-gray-50">
    <!-- Print Control Buttons -->
    <div class="no-print mb-6 flex justify-between items-center bg-white p-4 rounded-xl shadow-lg">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center">
                <i class="fas fa-users mr-3 text-blue-500"></i>Employee List Report
            </h1>
            <p class="text-gray-600 mt-1">Generated on <?php echo $current_date; ?> at <?php echo $current_time; ?></p>
        </div>
        <div class="flex space-x-3">
            <button onclick="window.print()" class="px-5 py-2.5 bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-xl hover:from-blue-600 hover:to-indigo-700 transition-all duration-200 font-medium shadow-lg hover:shadow-xl flex items-center">
                <i class="fas fa-print mr-2"></i> Print Report
            </button>
            <button onclick="window.close()" class="px-5 py-2.5 bg-gradient-to-r from-gray-500 to-gray-600 text-white rounded-xl hover:from-gray-600 hover:to-gray-700 transition-all duration-200 font-medium shadow-lg hover:shadow-xl flex items-center">
                <i class="fas fa-times mr-2"></i> Close
            </button>
            <button onclick="exportToPDF()" class="px-5 py-2.5 bg-gradient-to-r from-red-500 to-pink-600 text-white rounded-xl hover:from-red-600 hover:to-pink-700 transition-all duration-200 font-medium shadow-lg hover:shadow-xl flex items-center">
                <i class="fas fa-file-pdf mr-2"></i> Export PDF
            </button>
        </div>
    </div>

    <!-- Report Content -->
    <div class="bg-white rounded-2xl shadow-xl print-shadow overflow-hidden mb-6">
        <!-- Report Header -->
        <div class="report-header p-6 text-white">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-3xl font-bold mb-2">Employee Directory</h1>
                    <p class="text-blue-100 text-lg">Comprehensive Employee List Report</p>
                    <div class="flex items-center mt-4 space-x-6 text-blue-100">
                        <div class="flex items-center">
                            <i class="fas fa-calendar-alt mr-2"></i>
                            <span><?php echo $current_date; ?></span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-clock mr-2"></i>
                            <span><?php echo $current_time; ?></span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-user-check mr-2"></i>
                            <span><?php echo $employee_count; ?> Employees</span>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center backdrop-blur-sm">
                        <i class="fas fa-building text-2xl text-white"></i>
                    </div>
                    <p class="mt-2 text-blue-100">Official Report</p>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold text-slate-800 mb-4 flex items-center">
                <i class="fas fa-chart-pie mr-2 text-blue-500"></i> Report Summary
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="summary-card p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-700">Total Employees</p>
                            <p class="text-2xl font-bold text-slate-900"><?php echo $employee_count; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-users text-blue-600"></i>
                        </div>
                    </div>
                </div>
                
                <div class="summary-card p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-700">Departments</p>
                            <p class="text-2xl font-bold text-slate-900"><?php echo count($employees_by_department); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-sitemap text-green-600"></i>
                        </div>
                    </div>
                </div>
                
                <div class="summary-card p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-700">Total Salary</p>
                            <p class="text-2xl font-bold text-slate-900"><?php echo number_format($total_salary, 2); ?> ETB</p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-money-bill-wave text-purple-600"></i>
                        </div>
                    </div>
                </div>
                
                <div class="summary-card p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-700">Avg. Salary</p>
                            <p class="text-2xl font-bold text-slate-900"><?php echo number_format($average_salary, 2); ?> ETB</p>
                        </div>
                        <div class="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-chart-line text-amber-600"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Employee List -->
        <div class="p-6">
            <h2 class="text-xl font-bold text-slate-800 mb-6 flex items-center">
                <i class="fas fa-list-alt mr-2 text-blue-500"></i> Employee Details
            </h2>
            
            <!-- Table View (for printing) -->
            <div class="mb-8 overflow-x-auto">
                <table class="report-table min-w-full divide-y divide-gray-200 print-border">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Employee</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Position</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Department</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Salary (ETB)</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Join Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($employees as $index => $employee): ?>
                        <tr class="<?php echo $index % 2 === 0 ? 'bg-white' : 'bg-gray-50'; ?>">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center">
                                    <?php if (!empty($employee['photo']) && file_exists($employee['photo'])): ?>
                                        <img class="h-10 w-10 rounded-full object-cover" src="<?php echo safe_html($employee['photo']); ?>" alt="<?php echo safe_html($employee['name']); ?>">
                                    <?php else: ?>
                                        <div class="h-10 w-10 rounded-full photo-placeholder flex items-center justify-center">
                                            <i class="fas fa-user text-gray-500"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo safe_html($employee['name']); ?></div>
                                        <div class="text-sm text-gray-500 ethio-font"><?php echo safe_html($employee['name_am']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600"><?php echo safe_html($employee['position']); ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600"><?php echo safe_html($employee['department']); ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-800"><?php echo number_format($employee['salary'], 2); ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600"><?php echo safe_html($employee['join_date']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Department-wise Cards (for screen view) -->
            <div class="no-print">
                <?php foreach ($employees_by_department as $department => $dept_employees): ?>
                <div class="mb-8 print-break">
                    <div class="department-header p-4 mb-4">
                        <h3 class="text-lg font-bold flex items-center">
                            <i class="fas fa-building mr-2"></i> <?php echo safe_html($department); ?>
                            <span class="ml-2 text-blue-100 font-normal">(<?php echo count($dept_employees); ?> employees)</span>
                        </h3>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($dept_employees as $employee): ?>
                        <div class="employee-card bg-white rounded-xl p-4 shadow-md border border-gray-100">
                            <div class="flex items-start space-x-4">
                                <?php if (!empty($employee['photo']) && file_exists($employee['photo'])): ?>
                                    <img class="h-16 w-16 rounded-full object-cover" src="<?php echo safe_html($employee['photo']); ?>" alt="<?php echo safe_html($employee['name']); ?>">
                                <?php else: ?>
                                    <div class="h-16 w-16 rounded-full photo-placeholder flex items-center justify-center">
                                        <i class="fas fa-user text-gray-500 text-xl"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-1">
                                    <h4 class="font-bold text-slate-800"><?php echo safe_html($employee['name']); ?></h4>
                                    <p class="text-sm text-slate-600 ethio-font mb-1"><?php echo safe_html($employee['name_am']); ?></p>
                                    <p class="text-sm text-slate-600 mb-1">
                                        <i class="fas fa-briefcase mr-1 text-blue-500"></i> <?php echo safe_html($employee['position']); ?>
                                    </p>
                                    <p class="text-sm text-slate-600 mb-1">
                                        <i class="fas fa-calendar-alt mr-1 text-green-500"></i> Joined: <?php echo safe_html($employee['join_date']); ?>
                                    </p>
                                    <p class="text-sm font-semibold text-slate-800">
                                        <i class="fas fa-money-bill-wave mr-1 text-purple-500"></i> <?php echo number_format($employee['salary'], 2); ?> ETB
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Report Footer -->
        <div class="border-t border-gray-200 p-6 bg-gray-50">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <p class="text-sm text-gray-600">
                        <i class="fas fa-shield-alt mr-1 text-blue-500"></i> 
                        Confidential Employee Report - For Internal Use Only
                    </p>
                </div>
                <div class="text-sm text-gray-600">
                    <p>Generated by: <?php echo safe_html($current_user); ?></p>
                    <p>Page generated on: <?php echo $current_date . ' at ' . $current_time; ?></p>
                </div>
            </div>
            
            <!-- Signature Area -->
            <div class="mt-6 pt-6 border-t border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <p class="text-sm font-medium text-gray-700 mb-1">Prepared By:</p>
                        <div class="h-0.5 w-32 bg-gray-300 mb-1"></div>
                        <p class="text-xs text-gray-600">Name and Signature</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-700 mb-1">Approved By:</p>
                        <div class="h-0.5 w-32 bg-gray-300 mb-1"></div>
                        <p class="text-xs text-gray-600">Name and Signature</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Optimization Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add page breaks for printing if needed
            const departmentSections = document.querySelectorAll('.print-break');
            departmentSections.forEach((section, index) => {
                if (index > 0 && index % 2 === 0) {
                    section.classList.add('print-break-before');
                }
            });
            
            // Optimize for printing
            window.addEventListener('beforeprint', function() {
                document.body.classList.add('printing');
            });
            
            window.addEventListener('afterprint', function() {
                document.body.classList.remove('printing');
            });
        });

        // PDF Export function (placeholder - would need a proper PDF library)
        function exportToPDF() {
            alert('PDF export functionality would be implemented with a library like jsPDF or by generating a server-side PDF.');
            // In a real implementation, you would use:
            // 1. jsPDF + html2canvas for client-side PDF generation
            // 2. Or make an AJAX request to a server-side PDF generator
        }
    </script>
</body>
</html>