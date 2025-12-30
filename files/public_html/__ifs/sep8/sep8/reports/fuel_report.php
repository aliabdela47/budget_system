<?php
session_start();
include '../includes/db.php';
include '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Get report parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$owner_id = $_GET['owner_id'] ?? null;
$format = $_GET['format'] ?? 'html';

// Build query
$sql = "SELECT f.*, o.code AS owner_code, o.name AS owner_name
        FROM fuel_transactions f 
        JOIN budget_owners o ON f.owner_id = o.id 
        WHERE f.date BETWEEN ? AND ?";
$params = [$start_date, $end_date];

if ($owner_id) {
    $sql .= " AND f.owner_id = ?";
    $params[] = $owner_id;
}

$sql .= " ORDER BY f.date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Calculate totals
$total_amount = 0;
$total_fuel = 0;
foreach ($transactions as $t) {
    $total_amount += $t['total_amount'];
    $total_fuel += $t['refuelable_amount'];
}

// For PDF format, use TCPDF
if ($format === 'pdf') {
    // Check if TCPDF is available
    $tcpdf_path = '../vendor/tecnickcom/tcpdf/tcpdf.php';
    if (file_exists($tcpdf_path)) {
        require_once($tcpdf_path);
        
        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('Budget System');
        $pdf->SetAuthor('Budget System');
        $pdf->SetTitle('Fuel Transactions Report');
        $pdf->SetSubject('Fuel Transactions Report');
        
        // Set default header data
        $pdf->SetHeaderData('', 0, 'Fuel Transactions Report', 'Generated on: ' . date('F j, Y'));
        
        // Set header and footer fonts
        $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
        
        // Set default monospaced font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        
        // Set margins
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        
        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        
        // Add a page
        $pdf->AddPage();
        
        // Add Bureau Logo
        $logo = '../images/bureau-logo.png';
        if (file_exists($logo)) {
            $pdf->Image($logo, 15, 15, 30, 0, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
        
        // Set font
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'Fuel Transactions Report', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'Period: ' . date('M j, Y', strtotime($start_date)) . ' to ' . date('M j, Y', strtotime($end_date)), 0, 1, 'C');
        $pdf->Ln(10);
        
        // Create table header
        $pdf->SetFont('helvetica', 'B', 10);
        $header = array('Date', 'Owner', 'Driver Name', 'Plate_No', 'Month', 'Fuel (Ltr)', 'Amount (ETB)');
        $w = array(25, 35, 30, 25, 20, 25, 30);
        for ($i = 0; $i < count($header); $i++) {
            $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C');
        }
        $pdf->Ln();
        
        // Table data
        $pdf->SetFont('helvetica', '', 9);
        
        foreach ($transactions as $row) {
            $pdf->Cell($w[0], 6, date('M j, Y', strtotime($row['date'])), 'LR', 0, 'L');
            $pdf->Cell($w[1], 6, $row['owner_code'], 'LR', 0, 'L');
            $pdf->Cell($w[2], 6, $row['driver_name'], 'LR', 0, 'L');
            $pdf->Cell($w[3], 6, $row['plate_number'], 'LR', 0, 'C');
            $pdf->Cell($w[4], 6, $row['et_month'], 'LR', 0, 'C');
            $pdf->Cell($w[5], 6, number_format($row['refuelable_amount'], 2), 'LR', 0, 'R');
            $pdf->Cell($w[6], 6, number_format($row['total_amount'], 2), 'LR', 0, 'R');
            $pdf->Ln();
        }
        
        // Closing line
        $pdf->Cell(array_sum($w), 0, '', 'T');
        $pdf->Ln(10);
        
        // Totals
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(150, 6, 'Total Fuel:', 0, 0, 'R');
        $pdf->Cell(30, 6, number_format($total_fuel, 2) . ' Ltr', 0, 1, 'R');
        $pdf->Cell(150, 6, 'Total Amount:', 0, 0, 'R');
        $pdf->Cell(30, 6, number_format($total_amount, 2) . ' ETB', 0, 1, 'R');
        
        // Footer
        $pdf->Ln(15);
        $pdf->Cell(0, 10, 'Prepared by: ' . $_SESSION['username'], 0, 1, 'L');
        $pdf->Cell(0, 10, 'Taama gexenumih Migaq:-__________________', 0, 1, 'L');
        $pdf->Cell(0, 10, 'Cisaab Massosee Migaq:-__________________', 0, 1, 'L');
        $pdf->Cell(0, 10, 'Diggosse  Migaq:-________________________', 0, 1, 'L');
        $pdf->Cell(0, 10, 'Fatiicissee Migaq:-_______________________', 0, 1, 'L');
        $pdf->Cell(0, 10, 'Date: ' . date('Y-m-d'), 0, 1, 'L');
        
        // Close and output PDF document
        $pdf->Output('fuel_report_' . date('Y-m-d') . '.pdf', 'D');
        exit;
    } else {
        // Fallback to HTML if TCPDF not available
        $format = 'html';
    }
}

// HTML Report (default or fallback)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fuel Transactions Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print {
                display: none;
            }
            body {
                font-size: 12pt;
            }
            .report-header {
                border-bottom: 2px solid #000;
                margin-bottom: 20px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                border: 1px solid #ddd;
                padding: 6px;
                text-align: left;
            }
            th {
                background-color: #f3f4f6;
            }
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-6 bg-white shadow-md">
        <!-- Report Header with Bureau Logo -->
        <div class="report-header flex justify-between items-center mb-8">
            <div>
                <img src="../images/bureau-logo.png" alt="Bureau Logo" class="h-16" 
                     onerror="this.src='https://via.placeholder.com/150x80?text=Bureau+Logo'">
            </div>
            <div class="text-right">
                <h1 class="text-2xl font-bold">Fuel Transactions Report</h1>
                <p class="text-gray-600">Generated on: <?php echo date('F j, Y'); ?></p>
                <p class="text-gray-600">Period: <?php echo date('M j, Y', strtotime($start_date)); ?> to <?php echo date('M j, Y', strtotime($end_date)); ?></p>
            </div>
        </div>

        <!-- Report Filters Summary -->
        <div class="mb-6 p-4 bg-gray-50 rounded-lg">
            <h2 class="text-lg font-semibold mb-2">Report Parameters</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="font-medium">Date Range:</span> 
                    <?php echo date('M j, Y', strtotime($start_date)); ?> to <?php echo date('M j, Y', strtotime($end_date)); ?>
                </div>
                <div>
                    <span class="font-medium">Owner:</span> 
                    <?php 
                    if ($owner_id) {
                        $owner_name = $pdo->prepare("SELECT name FROM budget_owners WHERE id = ?");
                        $owner_name->execute([$owner_id]);
                        echo htmlspecialchars($owner_name->fetchColumn());
                    } else {
                        echo "All Owners";
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <table class="w-full mb-8">
            <thead>
                <tr class="bg-gray-200">
                    <th class="p-2">Date</th>
                    <th class="p-2">Owner</th>
                    <th class="p-2">Driver</th>
                    <th class="p-2">Plate Number</th>
                    <th class="p-2">Ethiopian Month</th>
                    <th class="p-2">Fuel (Ltr)</th>
                    <th class="p-2">Amount (ETB)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($transactions) > 0): ?>
                    <?php foreach ($transactions as $t): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="p-2"><?php echo date('M j, Y', strtotime($t['date'])); ?></td>
                        <td class="p-2"><?php echo htmlspecialchars($t['owner_code'] . ' - ' . $t['owner_name']); ?></td>
                        <td class="p-2"><?php echo htmlspecialchars($t['driver_name']); ?></td>
                        <td class="p-2"><?php echo htmlspecialchars($t['plate_number']); ?></td>
                        <td class="p-2"><?php echo htmlspecialchars($t['et_month']); ?></td>
                        <td class="p-2 text-right"><?php echo number_format($t['refuelable_amount'], 2); ?></td>
                        <td class="p-2 text-right"><?php echo number_format($t['total_amount'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="bg-gray-100 font-bold">
                        <td class="p-2 text-right" colspan="5">Total:</td>
                        <td class="p-2 text-right"><?php echo number_format($total_fuel, 2); ?> Ltr</td>
                        <td class="p-2 text-right"><?php echo number_format($total_amount, 2); ?> ETB</td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="p-4 text-center">No transactions found for the selected criteria</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Report Footer -->
        <div class="mt-8 border-t pt-4">
            <div class="grid grid-cols-2 gap-8">
                <div>
                    <p class="font-semibold">Prepared by:</p>
                    <p><?php echo htmlspecialchars($_SESSION['username']); ?></p>
                </div>
                <div class="text-right">
                    <p class="font-semibold">Signature:</p>
                    <p class="mt-8">_________________________</p>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mt-8 no-print flex justify-center space-x-4">
            <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded flex items-center">
                <i class="fas fa-print mr-2"></i> Print Report
            </button>
            <button onclick="window.close()" class="bg-gray-600 text-white px-4 py-2 rounded flex items-center">
                <i class="fas fa-times mr-2"></i> Close
            </button>
        </div>
    </div>
</body>
</html>