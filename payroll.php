<?php
require_once 'includes/init.php';

// User Authentication Check
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: login.php');
    exit;
}

// Fetch user data for the header
$stmt = $pdo->prepare("SELECT name, profile_picture FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$user_name = $user_data['name'] ?? 'User';
$profile_picture = $user_data['profile_picture'] ?? '';

// Ethiopian Months for the dropdown
$ethiopian_months = [
    1 => 'Meskerem', 2 => 'Tikimt', 3 => 'Hidar', 4 => 'Tahsas', 5 => 'Tir',
    6 => 'Yekatit', 7 => 'Megabit', 8 => 'Miyazya', 9 => 'Ginbot', 10 => 'Sene',
    11 => 'Hamle', 12 => 'Nehase', 13 => 'Paguemen'
];

// Get selected month, default to Tikimt (2)
$selected_month_num = $_GET['month'] ?? 2;
$current_month_name = $ethiopian_months[$selected_month_num] ?? 'Tikimt';
$current_year = "2017"; // Placeholder for the year

// Fetch all employees and their salary info
$payroll_stmt = $pdo->prepare("SELECT id, name, name_am, salary, taamagoli, directorate FROM emp_list ORDER BY name ASC");
$payroll_stmt->execute();
$employees = $payroll_stmt->fetchAll(PDO::FETCH_ASSOC);

// UPDATED PAYROLL CALCULATION LOGIC
function calculate_ethiopian_payroll($basic_salary) {
    // 1. Desert Allowance and Gross Salary
    $desert_allowance = $basic_salary * 0.30;
    $gross_salary = $basic_salary + $desert_allowance;

    // 2. New Income Tax Calculation based on Basic Salary
    $income_tax = 0;
    if ($basic_salary > 14000) { $income_tax = ($basic_salary * 0.35) - 2150; }
    elseif ($basic_salary > 10000) { $income_tax = ($basic_salary * 0.30) - 1350; }
    elseif ($basic_salary > 7000) { $income_tax = ($basic_salary * 0.25) - 850; }
    elseif ($basic_salary > 4000) { $income_tax = ($basic_salary * 0.20) - 500; }
    elseif ($basic_salary > 2000) { $income_tax = ($basic_salary * 0.15) - 300; }
    // Salary < 2000 has 0 tax

    // 3. Pension and Other Deductions
    $pension_employee = $basic_salary * 0.07;
    $other_deductions = 462.20;

    // 4. Total Deductions and Net Pay
    $total_deductions = $income_tax + $pension_employee + $other_deductions;
    $net_pay = $gross_salary - $total_deductions;

    return [
        'desert_allowance' => $desert_allowance,
        'gross_salary' => $gross_salary,
        'income_tax' => $income_tax,
        'pension_employee' => $pension_employee,
        'other_deductions' => $other_deductions,
        'total_deductions' => $total_deductions,
        'net_pay' => $net_pay
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php
    $pageTitle = 'Payroll Management';
    require_once 'includes/head.php';
    ?>
    <style>
        .gradient-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
        }
        .ethio-font {
            font-family: 'Nyala', 'Abyssinica SIL', serif;
        }
        .table-action-btn:hover { transform: scale(1.1); }
         @media print {
            body * { visibility: hidden; }
            #print-area, #print-area * { visibility: visible; }
            #print-area { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none; }
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

<?php require_once 'includes/sidebar-component.php'; ?>
	
    <div class="main-content flex-1 min-h-screen" id="mainContent">
    	
    	<?php require_once 'includes/header.php'; ?>
    
        <div class="p-6">          

            <div class="bg-white rounded-2xl p-6 md:p-8 shadow-xl border border-gray-100">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold text-slate-800">ወርሀዊ ደመወዝ</h1>
                       <p class="text-slate-500 mt-1">የ አፋር/ብ/ክ/መ ጤና ቢሮ የ <?php echo htmlspecialchars($current_month_name . ", " . $current_year); ?> ወርሀዊ ደመወዝ</p>
                    </div>

                    <div class="flex items-center space-x-2 md:space-x-4 no-print">
                        <form method="GET" action="payroll.php" class="flex items-center space-x-2">
                            <select name="month" id="month" onchange="this.form.submit()" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                <?php foreach ($ethiopian_months as $num => $name): ?>
                                    <option value="<?php echo $num; ?>" <?php echo ($num == $selected_month_num) ? 'selected' : ''; ?>>
                                        <?php echo $name; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                         <a href="generate_payroll_report.php?format=excel&month=<?php echo $selected_month_num; ?>" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center shadow-md">
                            <i class="fas fa-file-excel mr-2"></i>Excel
                        </a>
                        <a href="generate_payroll_report.php?format=pdf&month=<?php echo $selected_month_num; ?>" target="_blank" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center shadow-md">
                            <i class="fas fa-file-pdf mr-2"></i>PDF
                        </a>
                      
                    </div>
                </div>

                <!-- Payroll Table -->
                <div id="print-area" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="gradient-header">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Employee Name</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-white uppercase tracking-wider">Basic Salary</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-white uppercase tracking-wider">Desert Allow.</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-white uppercase tracking-wider">Gross Salary</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-white uppercase tracking-wider">Income Tax</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-white uppercase tracking-wider">Pension (7%)</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-white uppercase tracking-wider">Other Ded.</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-white uppercase tracking-wider">Total Ded.</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-white uppercase tracking-wider">Net Pay</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php 
                            $totals = array_fill_keys(['basic', 'allowance', 'gross', 'tax', 'pension', 'other', 'deductions', 'net'], 0);
                            foreach ($employees as $emp): 
                                $payroll = calculate_ethiopian_payroll($emp['salary']);
                                // Accumulate totals
                                $totals['basic'] += $emp['salary'];
                                $totals['allowance'] += $payroll['desert_allowance'];
                                $totals['gross'] += $payroll['gross_salary'];
                                $totals['tax'] += $payroll['income_tax'];
                                $totals['pension'] += $payroll['pension_employee'];
                                $totals['other'] += $payroll['other_deductions'];
                                $totals['deductions'] += $payroll['total_deductions'];
                                $totals['net'] += $payroll['net_pay'];
                            ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 whitespace-nowrap"><div class="font-medium text-gray-900"><?php echo htmlspecialchars($emp['name']); ?></div></td>
                                    <td class="px-3 py-2 text-right"><?php echo number_format($emp['salary'], 2); ?></td>
                                    <td class="px-3 py-2 text-right"><?php echo number_format($payroll['desert_allowance'], 2); ?></td>
                                    <td class="px-3 py-2 text-right font-semibold"><?php echo number_format($payroll['gross_salary'], 2); ?></td>
                                    <td class="px-3 py-2 text-right text-red-600"><?php echo number_format($payroll['income_tax'], 2); ?></td>
                                    <td class="px-3 py-2 text-right text-red-600"><?php echo number_format($payroll['pension_employee'], 2); ?></td>
                                    <td class="px-3 py-2 text-right text-red-600"><?php echo number_format($payroll['other_deductions'], 2); ?></td>
                                    <td class="px-3 py-2 text-right text-red-700 font-semibold"><?php echo number_format($payroll['total_deductions'], 2); ?></td>
                                    <td class="px-3 py-2 text-right text-green-700 font-bold"><?php echo number_format($payroll['net_pay'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-gray-100 font-bold">
                            <tr>
                                <td class="px-3 py-3 text-right uppercase">Totals</td>
                                <?php foreach ($totals as $total): ?>
                                <td class="px-3 py-3 text-right"><?php echo number_format($total, 2); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    
    
    
    
    
    
        document.getElementById('sidebarToggle')?.addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('mainContent').classList.toggle('expanded');
        });
        $('.user-profile').on('click', function(e) { e.stopPropagation(); $('.user-dropdown').toggleClass('show'); });
        $(document).on('click', function() { $('.user-dropdown').removeClass('show'); });
    </script>
</body>
</html>