<?php
// reports/fuel_transaction_report.php
require '../vendor/autoload.php';
include '../includes/db.php';

// Start session to get current user data
session_start();

use Andegna\DateTimeFactory;

// Get transaction ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Transaction ID is required.");
}
$transaction_id = $_GET['id'];

// Fetch the specific transaction with all details
$sql = "SELECT 
            ft.*,
            bo.name AS owner_name,
            bo.code AS owner_code,
            v.model AS vehicle_model
        FROM fuel_transactions ft
        LEFT JOIN budget_owners bo ON ft.owner_id = bo.id
        LEFT JOIN vehicles v ON ft.plate_number = v.plate_no
        WHERE ft.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$transaction_id]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$transaction) {
    die("Transaction not found.");
}

// Get current logged in user's name
$current_user_name = $_SESSION['username'] ?? 'Unknown User';

// If we have user_id in session, try to get the actual name from database
if (isset($_SESSION['user_id'])) {
    $user_stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $user_stmt->execute([$_SESSION['user_id']]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    if ($user_data && !empty($user_data['name'])) {
        $current_user_name = $user_data['name'];
    }
}

// Convert transaction date to Ethiopian calendar
try {
    $gregorianTransactionDate = new DateTime($transaction['date']);
    $ethiopicTransactionDate = DateTimeFactory::fromDateTime($gregorianTransactionDate);
    $formattedTransactionDate = $ethiopicTransactionDate->format('d/m/Y');
} catch (Exception $e) {
    // Fallback to Gregorian if Ethiopian calendar library fails
    $formattedTransactionDate = date('d-m-Y', strtotime($transaction['date']));
}

// Convert current date to Ethiopian calendar
try {
    $ethiopicPrintDate = DateTimeFactory::now();
    $formattedPrintDate = $ethiopicPrintDate->format('d/m/Y');
} catch (Exception $e) {
    // Fallback to Gregorian if Ethiopian calendar library fails
    $formattedPrintDate = date('d-m-Y');
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fuel Transaction Report - <?php echo $transaction['id']; ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;700&display=swap');

        body {
            font-family: 'Noto Sans Ethiopic', 'Nyala', serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            font-size: 14px; /* Increased from 12px to 14px (20% increase) */
        }
        .report-container {
            background-color: #fff;
            padding: 3mm 5mm; /* Reduced padding */
            width: 21cm;
            height: 29.7cm;
            margin: 0 auto;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1mm 0; /* Reduced padding */
            border-bottom: 2px solid #000;
            flex-shrink: 0;
        }

        .header .flags-left, .header .flags-right {
            flex: 1;
        }
        
        .header .flags-left img, .header .flags-right img {
            height: 35px; /* Reduced from 45px */
            width: auto;
        }

        .header .flags-left img {
            display: block;
        }

        .header .flags-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            font-size: 0.8em; /* Increased from 0.75em */
        }
        
        .header .flags-right p {
            margin: 1px 0;
        }

        .header-center {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            flex: 2;
        }
        
        .header-center .logo img {
            height: 45px; /* Reduced from 60px */
            width: auto;
            margin-bottom: 1px; /* Reduced margin */
        }
        
        .header-center .titles p {
            margin: 0;
            font-weight: bold;
            font-size: 0.85em; /* Increased from 0.8em */
        }

        .report-title {
            text-align: center;
            font-size: 1.15em; /* Increased from 1.0em */
            font-weight: bold;
            margin: 3mm 0; /* Reduced margin */
            flex-shrink: 0;
        }

        .report-title p {
            margin: 1px 0; /* Reduced margin */
        }

        .details-section {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 5px 10px; /* Reduced gap */
            margin: 5px 0; /* Reduced margin */
            flex-shrink: 0;
        }
        
        .details-item {
            display: flex;
            align-items: baseline;
            gap: 5px; /* Reduced gap */
        }

        .details-item label {
            font-weight: bold;
            color: #444;
            white-space: nowrap;
            line-height: 1.2;
            font-size: 0.9em; /* Increased from 0.85em */
        }

        .details-item span {
            border-bottom: 1px dotted #000;
            flex-grow: 1;
            padding: 0 2px;
            font-weight: bold;
            font-size: 0.9em; /* Increased from 0.85em */
        }

        .table-container {
            margin: 5px 0; /* Reduced margin */
            flex: 1;
            overflow: hidden;
        }

        .main-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em; /* Increased from 0.85em */
            table-layout: fixed;
        }

        .main-table th, .main-table td {
            border: 1px solid #000;
            padding: 3px; /* Reduced padding */
            text-align: center;
        }
        
        .main-table thead th {
            background-color: #f2f2f2;
            font-weight: bold;
            font-size: 0.85em; /* Increased from 0.8em */
        }

        .main-table tbody td {
            font-weight: bold;
        }
        
        .main-table .total-row {
            font-weight: bold;
            background-color: #e8e8e8;
        }

        .signature-section {
            margin-top: 5px; /* Reduced margin */
            flex-shrink: 0;
        }

        .signature-line {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 4px; /* Reduced margin */
            font-size: 0.9em; /* Increased from 0.85em */
        }
        
        .signature-line .label {
            white-space: nowrap;
            margin-right: 3px;
        }
        
        .signature-line .firma {
            text-align: right;
            padding-left: 8px; /* Reduced padding */
        }

        .signature-name {
            display: inline-block;
            border-bottom: 1px solid #000;
            min-width: 100px; /* Reduced width */
            padding: 0 2px; /* Reduced padding */
            margin-left: 2px; /* Reduced margin */
            text-align: center;
            font-weight: bold;
        }

        .signature-combined {
            display: flex;
            align-items: baseline;
            flex: 1;
        }

        .footer {
            margin-top: 5px; /* Reduced margin */
            border-top: 2px solid #000;
            padding-top: 4px; /* Reduced padding */
            color: #555;
            flex-shrink: 0;
        }

        .footer-top {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px; /* Reduced margin */
            font-size: 1.08em; /* 20% increase from 0.9em */
        }

        .footer-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: bold;
            margin-top: 2px; /* Reduced margin */
            font-size: 1.08em; /* 20% increase from 0.9em */
        }

        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                background-color: #fff;
                margin: 0;
                padding: 0;
            }
            .report-container {
                box-shadow: none;
                padding: 2mm 3mm; /* Reduced padding */
                width: 100%;
                height: 100%;
            }
            .no-print {
                display: none;
            }
            @page {
                size: A4 portrait;
                margin: 2mm; /* Reduced margin */
            }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="report-container">
        <header class="header">
            <div class="flags-left">
                <img src="../images/afar-flag.png" alt="Afar Flag">
            </div>
            <div class="header-center">
                <div class="logo">
                    <img src="../images/bureau-logo.png" alt="Bureau Logo">
                </div>
                <div class="titles">
                    <p>የአፋር ብሔራዊ ክልላዊ መንግስት ጤና ቢሮ</p>
                    <p>Afar National Regional State Health Bureau</p>
                </div>
            </div>
            <div class="flags-right">
                <img src="../images/ethiopia-flag.png" alt="Ethiopian Flag">
                <p><strong>Ixxima:</strong> FT-<?php echo str_pad($transaction['id'], 5, '0', STR_PAD_LEFT); ?></p>
                <p><strong>Ayro:</strong> <?php echo $formattedTransactionDate; ?> ዓ.ም</p>
            </div>
        </header>
<br>
        <section class="report-title">
            <p>Biiro Makaayinih Suktay Zeytiy Kee Gersi Gacamgac Uwwaytih damiyyi Essero Cibu</p>
            <p>የመስሪያ ቤታችን የተሽከርካሪ ቅባትና ዘይት የተለያዩ የመለዋወጫ እቃዎች ግዥ መጠየቂያ ቅጽ።</p>
        </section>

        <section class="details-section">
            <div class="details-item">
                <label>1. Kokobise Migaq:<br>የአሽከርካሪው ስም</label>
                <span><?php echo htmlspecialchars($transaction['driver_name']); ?></span>
            </div>
            <div class="details-item">
                <label>2. Number Plate:<br>የሰሌዳ ቁጥር</label>
                <span><?php echo htmlspecialchars($transaction['plate_number']); ?></span>
            </div>
            <div class="details-item">
                <label>3. Inki Litir 5 Gexxa km:<br>በአንድ ሊትር 5 ኪሜ ይጓዛል</label>
                <span>5</span>
            </div>
            <div class="details-item">
                <label>4. Kibbime Sansi:<br>የተሞላው የነዳጅ መጠን በሊትር</label>
                <span><?php echo number_format($transaction['refuelable_amount'] ?? 0, 2); ?></span>
            </div>
            <div class="details-item">
                <label>5. Sansi Kibteway Suge Geej:<br>ነዳጅ ሲሞላ የነበረው የኪሎሜትር ንባብ</label>
                <span><?php echo number_format($transaction['previous_gauge'] ?? 0, 2); ?> KM</span>
            </div>
            <div class="details-item">
                <label>6. Away yan Geej Lowwo:<br>አሁን ያለው የጌጅ ንባብ </label>
                <span><?php echo number_format($transaction['current_gauge'] ?? 0, 2); ?> KM</span>
            </div>
            
             <div class="details-item">
                <label>7. Geej Lowwo Baxsi:<br>የጌጅ ንባብ ልዩነት </label>
                <span><?php echo number_format($transaction['gauge_gap'] ?? 0, 2); ?> KM</span>
            </div>
            
            <div class="details-item">
                <label>8. Duyyek Faxximtam:<br>የሚያስፈልገው የገንዘብ መጠን </label>
                <span><?php echo number_format($transaction['total_amount'] ?? 0, 2); ?> ብር</span>
            </div>
        </section>
<br>
        <div class="table-container">
            <table class="main-table">
                <thead>
                    <tr>
                        <th style="width: 5%">K.L</th>
                        <th style="width: 25%">Uwwayti Qaynat<br>የእቃው አይነት</th>
                        <th style="width: 10%">Giyaase<br>መለኪያ</th>
                        <th style="width: 10%">Manga<br>ብዛት</th>
                        <th style="width: 15%">Inkitti Limo<br>የአንዱ ዋጋ</th>
                        <th style="width: 15%">Sittat<br>ጠቅ / ድምር</th>
                        <th style="width: 10%">Kusaq<br>ምርመራ</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>Sansi/ ናፍታ</td>
                        <td>Liter/ ሊትር</td>
                        <td><?php echo number_format($transaction['refuelable_amount'] ?? 0, 2); ?></td>
                        <td><?php echo number_format($transaction['fuel_price'] ?? 0, 2); ?></td>
                        <td><?php echo number_format($transaction['total_amount'] ?? 0, 2); ?></td>
                        <td></td>
                    </tr>
                    <tr><td>2</td><td>Benzin/ ቤንዚን</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>3</td><td>Matoor Sukat/ የሞተር ዘይት</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>4</td><td>Kanbiyo Sukat/ የካንቢዮ ዘይት</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>5</td><td>Mari Sukat/ የመሪ ዘይት</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>6</td><td>Gerse/ ግሪስ</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>7</td><td>Fereen Sukat/ የፍሬን ዘይት</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>8</td><td>Batri Lee/ የባትሪ ውሀ</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>9</td><td>Gommi Bicsa/ ለጎማ</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>10</td><td>Kalat Tanim/ ሌሎች</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr class="total-row">
                        <td colspan="5">Sittat (ድምር)</td>
                        <td><?php echo number_format($transaction['total_amount'] ?? 0, 2); ?></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
<br>
        <section class="signature-section">
            <div class="signature-line">
                <div class="signature-combined">
                    <span class="label">Taama gexenum Migaaqa:</span>
                    <span class="signature-name"><?php echo htmlspecialchars($transaction['driver_name']); ?></span>
                </div>
                <span class="firma">Firma: ___________________________</span>
            </div>
            <div class="signature-line">
                <div class="signature-combined">
                    <span class="label">Cisaab Massosee Migaaqa:</span>
                    <span class="signature-name"><?php echo htmlspecialchars($current_user_name); ?></span>
                </div>
                <span class="firma">Firma: ___________________________</span>
            </div>
            <div class="signature-line">
                <div class="signature-combined">
                    <span class="label">Diggosse Migaaqa:</span>
                    <span class="signature-name">Abubeker Arba Oundie</span>
                </div>
                <span class="firma">Firma: ___________________________</span>
            </div>
            <div class="signature-line">
                <div class="signature-combined">
                    <span class="label">Faticisee Migaaqa:</span>
                    <span class="signature-name"></span>
                </div>
                <span class="firma">Firma: ___________________________</span>
            </div>
        </section>

        <footer class="footer">
            <div class="footer-top">
                <span>TEL: 033-666-00-22</span>
                <span>Email: info@afarrhb.et</span>
                <span>Printed on: <?php echo $formattedPrintDate; ?> ዓ.ም</span>
                <span><b>Developed by: ICT Directorate</b></span>
            </div>
            <div class="footer-bottom">
                <span>Printed by: <?php echo htmlspecialchars($current_user_name); ?></span>
                <span>Date: <?php echo $formattedPrintDate; ?> ዓ.ም</span>
            </div>
        </footer>

    </div>
    
    <div class="no-print" style="text-align: center; margin: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background-color: #4f46e5; color: white; border: none; border-radius: 5px; cursor: pointer;">
            Print Again
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; background-color: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
            Close Window
        </button>
    </div>
</body>
</html>