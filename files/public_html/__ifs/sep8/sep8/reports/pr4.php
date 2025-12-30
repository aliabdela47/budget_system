<?php
// reports/perdium_report.php

// Go up one level to find the 'includes' directory
include '../includes/db.php';

// Get transaction ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Transaction ID is required.");
}

$transaction_id = $_GET['id'];

// Fetch the specific perdium transaction with all details
$sql = "SELECT 
            pt.*,
            bo.name AS owner_name,
            bo.code AS owner_code,
            e.name AS employee_name,
            e.salary AS employee_salary,
            e.directorate AS employee_directorate,
            c.name_amharic AS city_name_amharic,
            c.name_english AS city_name_english,
            c.rate_low,
            c.rate_medium,
            c.rate_high,
            bc.name AS budget_code_name
        FROM perdium_transactions pt
        LEFT JOIN budget_owners bo ON pt.budget_owner_id = bo.id
        LEFT JOIN employees e ON pt.employee_id = e.id
        LEFT JOIN cities c ON pt.city_id = c.id
        LEFT JOIN budget_codes bc ON pt.budget_code_id = bc.id
        WHERE pt.id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$transaction_id]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    die("Transaction not found.");
}

// Calculate perdium breakdown
$perdium_rate = $transaction['perdium_rate'];
$total_days = $transaction['total_days'];

$A = ($perdium_rate * 0.1) + ($perdium_rate * 0.25) + ($perdium_rate * 0.25);
$B = $A * ($total_days - 2);
$C = $A;
$D = $perdium_rate * ($total_days - 1) * 0.4;
$total_amount = $A + $B + $C + $D;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Perdium Transaction Report - Budget System</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap');
    
    body { font-family: 'Inter', sans-serif; }
    
    .ethio-font {
      font-family: 'Noto Sans Ethiopic', sans-serif;
    }
    
    @media print {
      body { 
        background-color: #fff; 
        padding: 0; 
        width: 100%;
      }
      .no-print {
        display: none;
      }
      .print-section {
        width: 100%;
        box-shadow: none;
        border: none;
        margin: 0;
        padding: 0;
      }
    }
  </style>
</head>
<body class="bg-gray-100 p-6">
  <div class="max-w-4xl mx-auto bg-white rounded-xl shadow-sm p-6 print-section">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8 border-b pb-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Perdium Transaction Report</h1>
        <p class="text-slate-600">Generated on: <?php echo date('F j, Y'); ?></p>
      </div>
      <div class="no-print">
        <button onclick="window.print()" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
          <i class="fas fa-print mr-2"></i> Print Report
        </button>
      </div>
    </div>

    <!-- Transaction Details -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
      <div class="bg-blue-50 p-4 rounded-lg">
        <h2 class="text-lg font-semibold text-blue-800 mb-3">Transaction Information</h2>
        <div class="space-y-2">
          <p><span class="font-medium">Reference ID:</span> <?php echo $transaction['id']; ?></p>
          <p><span class="font-medium">Created Date:</span> <?php echo date('F j, Y', strtotime($transaction['created_at'])); ?></p>
          <p><span class="font-medium">Ethiopian Month:</span> <?php echo $transaction['et_month']; ?></p>
          <p><span class="font-medium">Budget Code:</span> <?php echo $transaction['budget_code_name']; ?></p>
        </div>
      </div>

      <div class="bg-green-50 p-4 rounded-lg">
        <h2 class="text-lg font-semibold text-green-800 mb-3">Budget Information</h2>
        <div class="space-y-2">
          <p><span class="font-medium">Budget Owner:</span> <?php echo $transaction['owner_code'] . ' - ' . $transaction['owner_name']; ?></p>
          <p><span class="font-medium">Perdium Rate:</span> <?php echo number_format($transaction['perdium_rate'], 2); ?> Birr</p>
          <p><span class="font-medium">Total Amount:</span> <span class="font-bold"><?php echo number_format($transaction['total_amount'], 2); ?> Birr</span></p>
        </div>
      </div>
    </div>

    <!-- Employee & Travel Details -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
      <div class="bg-indigo-50 p-4 rounded-lg">
        <h2 class="text-lg font-semibold text-indigo-800 mb-3">Employee Details</h2>
        <div class="space-y-2">
          <p><span class="font-medium">Name:</span> <?php echo $transaction['employee_name']; ?></p>
          <p><span class="font-medium">Salary:</span> <?php echo number_format($transaction['employee_salary'], 2); ?> Birr</p>
          <p><span class="font-medium">Directorate:</span> <?php echo $transaction['employee_directorate']; ?></p>
        </div>
      </div>

      <div class="bg-purple-50 p-4 rounded-lg">
        <h2 class="text-lg font-semibold text-purple-800 mb-3">Travel Details</h2>
        <div class="space-y-2">
          <p><span class="font-medium">Destination:</span> <?php echo $transaction['city_name_amharic'] . ' (' . $transaction['city_name_english'] . ')'; ?></p>
          <p><span class="font-medium">Departure Date:</span> <?php echo date('F j, Y', strtotime($transaction['departure_date'])); ?></p>
          <p><span class="font-medium">Arrival Date:</span> <?php echo date('F j, Y', strtotime($transaction['arrival_date'])); ?></p>
          <p><span class="font-medium">Total Days:</span> <?php echo $transaction['total_days']; ?> days</p>
        </div>
      </div>
    </div>

    <!-- Perdium Calculation Breakdown -->
    <div class="bg-gray-50 p-6 rounded-lg mb-8">
      <h2 class="text-xl font-semibold text-gray-800 mb-4">Perdium Calculation Details</h2>
      
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow-sm">
          <p class="text-blue-700 font-medium">A (Departure):</p>
          <p class="text-2xl font-bold text-blue-800"><?php echo number_format($A, 2); ?></p>
          <p class="text-xs text-blue-600 mt-1">(PR × 10%) + (PR × 25%) + (PR × 25%)</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm">
          <p class="text-blue-700 font-medium">B (Field Days):</p>
          <p class="text-2xl font-bold text-blue-800"><?php echo number_format($B, 2); ?></p>
          <p class="text-xs text-blue-600 mt-1">A × (TPD - 2)</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm">
          <p class="text-blue-700 font-medium">C (Arrival):</p>
          <p class="text-2xl font-bold text-blue-800"><?php echo number_format($C, 2); ?></p>
          <p class="text-xs text-blue-600 mt-1">A</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm">
          <p class="text-blue-700 font-medium">D (Lodging):</p>
          <p class="text-2xl font-bold text-blue-800"><?php echo number_format($D, 2); ?></p>
          <p class="text-xs text-blue-600 mt-1">PR × (TPD - 1) × 40%</p>
        </div>
      </div>
      
      <div class="bg-blue-100 p-4 rounded-lg">
        <p class="text-xl font-bold text-blue-800 flex items-center">
          <i class="fas fa-calculator mr-2"></i> 
          Total Perdium Payment: <?php echo number_format($total_amount, 2); ?> Birr
        </p>
      </div>
    </div>

    <!-- Rate Information -->
    <div class="bg-yellow-50 p-4 rounded-lg mb-8">
      <h2 class="text-lg font-semibold text-yellow-800 mb-3">Rate Information</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white p-3 rounded-lg">
          <p class="text-sm font-medium text-yellow-700">Low Rate (Salary ≤ 3,933):</p>
          <p class="text-lg font-bold text-yellow-800"><?php echo number_format($transaction['rate_low'], 2); ?> Birr</p>
        </div>
        <div class="bg-white p-3 rounded-lg">
          <p class="text-sm font-medium text-yellow-700">Medium Rate (Salary ≤ 9,055):</p>
          <p class="text-lg font-bold text-yellow-800"><?php echo number_format($transaction['rate_medium'], 2); ?> Birr</p>
        </div>
        <div class="bg-white p-3 rounded-lg">
          <p class="text-sm font-medium text-yellow-700">High Rate (Salary > 9,055):</p>
          <p class="text-lg font-bold text-yellow-800"><?php echo number_format($transaction['rate_high'], 2); ?> Birr</p>
        </div>
      </div>
      <p class="text-sm text-yellow-700 mt-3">
        Applied Rate: <?php echo number_format($transaction['perdium_rate'], 2); ?> Birr 
        (Based on employee salary: <?php echo number_format($transaction['employee_salary'], 2); ?> Birr)
      </p>
    </div>

    <!-- Approval Section -->
    <div class="mt-8 border-t pt-6">
      <h2 class="text-lg font-semibold text-gray-800 mb-4">Approval</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="text-center">
          <p class="font-medium mb-2">Prepared By:</p>
          <div class="h-0.5 bg-gray-300 my-2"></div>
          <p class="text-sm text-gray-600">Name, Title & Signature</p>
        </div>
        <div class="text-center">
          <p class="font-medium mb-2">Approved By:</p>
          <div class="h-0.5 bg-gray-300 my-2"></div>
          <p class="text-sm text-gray-600">Name, Title & Signature</p>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="mt-8 text-center text-sm text-gray-500 border-t pt-4">
      <p>This is an official perdium transaction report from the Budget Management System</p>
      <p>Generated on: <?php echo date('F j, Y, g:i a'); ?></p>
    </div>
  </div>

  <div class="max-w-4xl mx-auto mt-4 no-print">
    <a href="../perdium.php" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
      <i class="fas fa-arrow-left mr-2"></i> Back to Perdium Management
    </a>
  </div>
</body>
</html>