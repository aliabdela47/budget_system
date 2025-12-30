<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'vendor/autoload.php'; // Path to Composer autoload

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Get transaction ID from URL
$transaction_id = $_GET['id'] ?? null;

if (!$transaction_id) {
    die('Transaction ID is required');
}

// Fetch the specific transaction
$stmt = $pdo->prepare("
    SELECT f.*, o.code AS owner_code, o.name AS owner_name, 
           v.model AS vehicle_model, v.type AS vehicle_type
    FROM fuel_transactions f 
    JOIN budget_owners o ON f.owner_id = o.id 
    LEFT JOIN vehicles v ON f.plate_number = v.plate_no
    WHERE f.id = ?
");
$stmt->execute([$transaction_id]);
$transaction = $stmt->fetch();

if (!$transaction) {
    die('Transaction not found');
}

// Create new PDF document
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Budget System');
$pdf->SetAuthor('Budget System');
$pdf->SetTitle('Fuel Transaction Report #' . $transaction_id);
$pdf->SetSubject('Fuel Transaction Details');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(TRUE, 15);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 10);

// Add Bureau Logo
$logo_path = 'images/bureau-logo.png';
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, 15, 10, 30, 0, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
}

// Report Title
$pdf->SetFont('helvetica', 'B', 16);
$pdf->SetY(15);
$pdf->Cell(0, 10, 'FUEL TRANSACTION REPORT', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 5, 'Transaction #' . $transaction_id, 0, 1, 'C');
$pdf->Ln(5);

// Transaction Details
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'TRANSACTION DETAILS', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 10);

// Create a table for transaction details
$html = '
<table border="0.5" cellpadding="4" cellspacing="0">
    <tr>
        <td width="30%" bgcolor="#f2f2f2"><strong>Field</strong></td>
        <td width="70%"><strong>Value</strong></td>
    </tr>
    <tr>
        <td bgcolor="#f2f2f2">Transaction Date</td>
        <td>' . date('F j, Y', strtotime($transaction['date'])) . '</td>
    </tr>
    <tr>
        <td bgcolor="#f2f2f2">Budget Owner</td>
        <td>' . htmlspecialchars($transaction['owner_code'] . ' - ' . $transaction['owner_name']) . '</td>
    </tr>
    <tr>
        <td bgcolor="#f2f2f2">Driver Name</td>
        <td>' . htmlspecialchars($transaction['driver_name']) . '</td>
    </tr>
    <tr>
        <td bgcolor="#f2f2f2">Vehicle Plate Number</td>
        <td>' . htmlspecialchars($transaction['plate_number']) . '</td>
    </tr>
    <tr>
        <td bgcolor="#f2f2f2">Vehicle Model/Type</td>
        <td>' . htmlspecialchars(($transaction['vehicle_model'] ?? 'N/A') . ' / ' . ($transaction['vehicle_type'] ?? 'N/A')) . '</td>
    </tr>
    <tr>
        <td bgcolor="#f2f2f2">Ethiopian Month</td>
        <td>' . htmlspecialchars($transaction['et_month']) . '</td>
    </tr>
    <tr>
        <td bgcolor="#f2f2f2">Previous Gauge</td>
        <td>' . number_format($transaction['previous_gauge'], 2) . '</td>
    </tr>
    <tr>
        <td bgcolor="#f2f2f2">Current Gauge</td>
        <td>' . number_format($transaction['current_gauge'], 2) . '</td>
    </tr>
    <tr>
        <td bgcolor="#f2f2f2">Journey Distance (Km)</td>
        <td>' . number_format($transaction['journey_distance'], 2) . ' Km</td>
    </tr>
    <tr>
        <td bgcolor="#f2f2f2">Fuel Price per Liter</td>
        <td>' . number_format($transaction['fuel_price'], 2) . ' ETB</td>
    </tr>
    <tr>
        <td bgcolor="#f2f2f2">Refuelable Amount</td>
        <td>' . number_format($transaction['refuelable_amount'], 2) . ' Liters</td>
    </tr>
    <tr>
        <td bgcolor="#f2f2f2">Total Amount</td>
        <td>' . number_format($transaction['total_amount'], 2) . ' ETB</td>
    </tr>
    <tr>
        <td bgcolor="#f2f2f2">New Gauge</td>
        <td>' . number_format($transaction['new_gauge'], 2) . '</td>
    </tr>
    <tr>
        <td bgcolor="#f2f2f2">Gauge Gap</td>
        <td>' . number_format($transaction['gauge_gap'], 2) . '</td>
    </tr>
</table>
';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Ln(10);

// Add approval sections
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'APPROVALS', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 10);

$pdf->Cell(90, 5, 'Taama gexenumih Migaq: _________________________', 0, 0, 'L');
$pdf->Cell(90, 5, 'Cisaab Missosee Migaq: _________________________', 0, 0, 'L');
$pdf->Cell(90, 5, 'Diggossee Migaq: _________________________', 0, 0, 'L');
$pdf->Cell(90, 5, 'Fatiicisee Migaq: _________________________', 0, 0, 'L');

$pdf->Cell(90, 5, 'Date: ' . date('Y-m-d'), 0, 1, 'R');
$pdf->Ln(10);

// Add a footer
$pdf->SetY(-20);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 5, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 0, 'L');
$pdf->Cell(0, 5, 'Page ' . $pdf->getAliasNumPage() . ' of ' . $pdf->getAliasNbPages(), 0, 0, 'R');

// Output the PDF
$pdf->Output('fuel_transaction_' . $transaction_id . '.pdf', 'I');