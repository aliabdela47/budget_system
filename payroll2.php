<?php
require_once 'includes/init.php';

// User Authentication Check
$userid = $SESSION['user_id'] ?? null;
if (!$user_id) {
header('Location: login.php');
exit;
}

// Fetch user data for the header
$stmt = $pdo->prepare("SELECT name, profile_picture FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$userdata = $stmt->fetch(PDO::FETCHASSOC);
$username = $userdata['name'] ?? 'User';
$profilepicture = $userdata['profile_picture'] ?? '';

// Get current Ethiopian month and year for the report title
// Placeholder until you integrate a proper Ethiopian date library
$currentmonthname = "Tikimt";
$current_year       = "2017";

// Fetch all employees and their salary info
$payroll_stmt = $pdo->prepare("
SELECT id, name, name_am, salary, taamagoli, directorate
FROM emp_list
ORDER BY name ASC
");
$payroll_stmt->execute();
$employees = $payrollstmt->fetchAll(PDO::FETCHASSOC);


function calculateethiopianpayroll($basic_salary) {
$desertallowancerate = 0.30;
$pensionemployeerate = 0.07;
$pensioncompanyrate  = 0.11;
$other_deductions      = 462.2;

// Gross salary includes desert allowance
$grosssalary = $basicsalary + ($basicsalary * $desertallowance_rate);

// Income Tax on basic salary only
if ($basic_salary < 2000) {
$income_tax = 0;
} elseif ($basic_salary <= 4000) {
$incometax = ($basicsalary * 0.15) - 300;
} elseif ($basic_salary <= 7000) {
$incometax = ($basicsalary * 0.20) - 500;
} elseif ($basic_salary <= 10000) {
$incometax = ($basicsalary * 0.25) - 850;
} elseif ($basic_salary <= 14000) {
$incometax = ($basicsalary * 0.30) - 1350;
} else {
$incometax = ($basicsalary * 0.35) - 2150;
}

// Pension contributions on basic salary
$pensionemployee = $basicsalary * $pensionemployeerate;
$pensioncompany  = $basicsalary * $pensioncompanyrate;

// Sum of all deductions
$totaldeductions = $incometax + $pensionemployee + $otherdeductions;

// Net pay from gross salary
$netpay = $grosssalary - $total_deductions;

return [
'grosssalary'     => $grosssalary,
'incometax'       => $incometax,
'pensionemployee' => $pensionemployee,
'pensioncompany'  => $pensioncompany,
'otherdeductions' => $otherdeductions,
'totaldeductions' => $totaldeductions,
'netpay'          => $netpay,
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
.table-action-btn {
transition: all 0.2s ease-in-out;
}
.table-action-btn:hover {
transform: scale(1.1);
}
@media print {
body * { visibility: hidden; }
#print-area, #print-area * { visibility: visible; }
#print-area {
position: absolute;
left: 0; top: 0;
width: 100%;
}
.no-print { display: none; }
}
</style>
</head>
<body class="text-slate-700 flex bg-gray-50 min-h-screen">
<?php require_once 'includes/sidebar-new.php'; ?>

<div class="main-content flex-1 min-h-screen" id="mainContent">
<div class="p-6">
<?php require_once 'includes/header.php'; ?>

<div class="bg-white rounded-2xl p-6 md:p-8 shadow-xl border border-gray-100">
<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
<div>
<h1 class="text-2xl md:text-3xl font-bold text-slate-800">Monthly Payroll</h1>
<p class="text-slate-500 mt-1">
Payroll summary for <?php echo htmlspecialchars("$currentmonthname, $current_year"); ?>
</p>
</div>
<div class="flex items-center space-x-2 md:space-x-4 no-print">
<a href="generatepayrollreport.php?format=excel"
class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center shadow-md">
<i class="fas fa-file-excel mr-2"></i>Export to Excel
</a>
<a href="generatepayrollreport.php?format=pdf" target="_blank"
class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 flex items-center shadow-md">
<i class="fas fa-file-pdf mr-2"></i>Export to PDF
</a>
</div>
</div>

<!-- Payroll Table -->
<div id="print-area" class="overflow-x-auto">
<table class="min-w-full divide-y divide-gray-200">
<thead class="gradient-header">
<tr>
<th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">
Employee Name
</th>
<th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">
Position
</th>
<th class="px-4 py-3 text-right text-xs font-medium text-white uppercase tracking-wider">
Gross Salary
</th>
<th class="px-4 py-3 text-right text-xs font-medium text-white uppercase tracking-wider">
Income Tax
</th>
<th class="px-4 py-3 text-right text-xs font-medium text-white uppercase tracking-wider">
Pension (7%)
</th>
<th class="px-4 py-3 text-right text-xs font-medium text-white uppercase tracking-wider">
Total Deductions
</th>
<th class="px-4 py-3 text-right text-xs font-medium text-white uppercase tracking-wider">
Net Pay
</th>
</tr>
</thead>
<tbody class="bg-white divide-y divide-gray-200">
<?php
$totalgross = $totaltax = $totalpension = $totaldeductions = $total_net = 0;
foreach ($employees as $emp):
$payroll = calculateethiopianpayroll($emp['salary']);

// Accumulate totals
$totalgross      += $payroll['grosssalary'];
$totaltax        += $payroll['incometax'];
$totalpension    += $payroll['pensionemployee'];
$totaldeductions += $payroll['totaldeductions'];
$totalnet        += $payroll['netpay'];
?>
<tr class="hover:bg-gray-50">
<td class="px-4 py-3 whitespace-nowrap">
<div class="font-medium text-gray-900">
<?php echo htmlspecialchars($emp['name']); ?>
</div>
<div class="text-sm text-gray-500 ethio-font">
<?php echo htmlspecialchars($emp['name_am']); ?>
</div>
</td>
<td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
<?php echo htmlspecialchars($emp['taamagoli']); ?>
</td>
<td class="px-4 py-3 whitespace-nowrap text-sm text-gray-800 text-right">
<?php echo numberformat($payroll['grosssalary'], 2); ?>
</td>
<td class="px-4 py-3 whitespace-nowrap text-sm text-red-600 text-right">
<?php echo numberformat($payroll['incometax'], 2); ?>
</td>
<td class="px-4 py-3 whitespace-nowrap text-sm text-red-600 text-right">
<?php echo numberformat($payroll['pensionemployee'], 2); ?>
</td>
<td class="px-4 py-3 whitespace-nowrap text-sm text-red-700 font-semibold text-right">
<?php echo numberformat($payroll['totaldeductions'], 2); ?>
</td>
<td class="px-4 py-3 whitespace-nowrap text-sm text-green-700 font-bold text-right">
<?php echo numberformat($payroll['netpay'], 2); ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
<tfoot class="bg-gray-100 font-bold">
<tr>
<td colspan="2" class="px-4 py-3 text-right text-sm text-gray-800 uppercase">
Totals
</td>
<td class="px-4 py-3 text-right text-sm text-gray-800">
<?php echo numberformat($totalgross, 2); ?>
</td>
<td class="px-4 py-3 text-right text-sm text-gray-800">
<?php echo numberformat($totaltax, 2); ?>
</td>
<td class="px-4 py-3 text-right text-sm text-gray-800">
<?php echo numberformat($totalpension, 2); ?>
</td>
<td class="px-4 py-3 text-right text-sm text-gray-800">
<?php echo numberformat($totaldeductions, 2); ?>
</td>
<td class="px-4 py-3 text-right text-sm text-gray-800">
<?php echo numberformat($totalnet, 2); ?>
</td>
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
$('.user-profile').on('click', function(e) {
e.stopPropagation();
$('.user-dropdown').toggleClass('show');
});
$(document).on('click', function() {
$('.user-dropdown').removeClass('show');
});
</script>
</body>
</html>