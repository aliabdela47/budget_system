<?php
session_start();
include 'includes/db.php';
include 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Check if an ID is provided in the URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Perdium transaction ID not provided.");
}

$perdium_id = (int)$_GET['id'];

try {
$stmt = $pdo->prepare("
        SELECT 
            p.*, 
            e.name AS employee_name,
            e.directorate AS employee_directorate,
            e.salary AS employee_salary,
            o.code AS owner_code,
            c.name_amharic AS city_name
        FROM perdium_transactions p
        JOIN employees e ON p.employee_id = e.id
        JOIN budget_owners o ON p.budget_owner_id = o.id
        JOIN cities c ON p.city_id = c.id
        WHERE p.id = ?
    ");
    $stmt->execute([$perdium_id]);
    $perdium_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // If the transaction is not found, show an error
    if (!$perdium_data) {
        die("Perdium transaction not found.");
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perdium Transaction Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap');
        
        body { font-family: 'Inter', sans-serif; }
        .ethio-font { font-family: 'Noto Sans Ethiopic', sans-serif; }

        @media print {
            body {
                background-color: #fff;
            }
            .no-print {
                display: none !important;
            }
            .print-container {
                box-shadow: none;
                border: 1px solid #000;
            }
        }
    </style>
</head>
<body class="bg-gray-100 p-6 flex flex-col items-center justify-center min-h-screen">

    <div class="no-print w-full max-w-4xl flex justify-end mb-4">
        <button onclick="window.print()" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition shadow-md">
            <i class="fas fa-print mr-2"></i> Print Report
        </button>
    </div>

    <div class="w-full max-w-4xl bg-white rounded-xl shadow-lg p-8 print-container">
        <div class="flex justify-between items-center mb-6 border-b pb-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">Perdium Payment Report</h1>
                <p class="text-slate-600 mt-1">Transaction #<?php echo htmlspecialchars($perdium_data['id']); ?></p>
            </div>
            <div>
                <img src="images/bureau-logo.png" alt="Bureau Logo" class="w-24 h-auto"
                     onerror="this.src='https://via.placeholder.com/100x40?text=Logo'">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-y-6 gap-x-8 mb-8">
            <div>
                <h2 class="text-lg font-semibold text-blue-700 mb-2">Employee Details</h2>
                <div class="space-y-1 text-gray-700 text-sm">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($perdium_data['employee_name']); ?></p>
                    <p><strong>Directorate:</strong> <?php echo htmlspecialchars($perdium_data['employee_directorate']); ?></p>
                    <p><strong>Salary:</strong> <?php echo number_format($perdium_data['employee_salary'], 2); ?> Birr</p>
                </div>
            </div>

            <div>
                <h2 class="text-lg font-semibold text-blue-700 mb-2">Transaction Details</h2>
                <div class="space-y-1 text-gray-700 text-sm">
                    <p><strong>Budget Owner:</strong> <?php echo htmlspecialchars($perdium_data['owner_code']); ?></p>
                    <p><strong>Destination City:</strong> <span class="ethio-font"><?php echo htmlspecialchars($perdium_data['city_name']); ?></span></p>
                    <p><strong>Ethiopian Month:</strong> <span class="ethio-font"><?php echo htmlspecialchars($perdium_data['et_month']); ?></span></p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-y-6 gap-x-8 mb-8">
            <div>
                <h2 class="text-lg font-semibold text-blue-700 mb-2">Trip & Duration</h2>
                <div class="space-y-1 text-gray-700 text-sm">
                    <p><strong>Departure Date:</strong> <?php echo htmlspecialchars($perdium_data['departure_date']); ?></p>
                    <p><strong>Arrival Date:</strong> <?php echo htmlspecialchars($perdium_data['arrival_date']); ?></p>
                    <p><strong>Total Days:</strong> <?php echo htmlspecialchars($perdium_data['total_days']); ?></p>
                </div>
            </div>

            <div>
                <h2 class="text-lg font-semibold text-blue-700 mb-2">Financial Breakdown</h2>
                <div class="space-y-1 text-gray-700 text-sm">
                    <p><strong>Perdium Rate:</strong> <?php echo number_format($perdium_data['perdium_rate'], 2); ?> Birr/day</p>
                    <p><strong>Calculated Amount:</strong> <?php echo number_format($perdium_data['total_amount'], 2); ?> Birr</p>
                    <p><strong>Total Payment:</strong> <span class="text-xl font-bold text-green-700"><?php echo number_format($perdium_data['total_amount'], 2); ?> Birr</span></p>
                </div>
            </div>
        </div>

        <div class="border-t border-gray-300 pt-6 mt-6">
            <h2 class="text-lg font-semibold text-blue-700 mb-2">Notes</h2>
            <p class="text-sm text-gray-600">This report is for internal records and financial auditing. All values are calculated based on the official perdium rates and the provided trip details.</p>
        </div>
    </div>
</body>
</html>
