<?php
// reports/fuel_transaction_report.php

// Go up one level to find the 'includes' directory
include '../includes/db.php';

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
?>

<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fuel Transaction Report - <?php echo $transaction['id']; ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;700&display=swap');
        
        /* Base styles */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Noto Sans Ethiopic', sans-serif;
            line-height: 1.3;
            color: #000;
            background: #fff;
            padding: 5mm;
            width: 100%;
            height: 100%;
        }
        
        .report-container {
            max-width: 100%;
            margin: 0 auto;
        }
        
        .sub-header {
            text-align: center;
            margin-bottom: 8px;
            padding-bottom: 5px;
            border-bottom: 1px solid #000;
        }
        
        .sub-header p {
            margin-bottom: 4px;
        }
        
        .details-grid.compact {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 5px;
            margin-bottom: 8px;
        }
        
        .details-grid.compact p {
            margin: 0;
            padding: 3px 0;
            display: flex;
            justify-content: space-between;
            border-bottom: 1px dotted #ddd;
        }
        
        .details-grid.compact p strong {
            color: #555;
            font-weight: 500;
            flex-shrink: 0;
            margin-right: 5px;
        }
        
        .details-grid.compact p span {
            text-align: right;
            flex-grow: 1;
        }
        
        .details-grid.compact .highlight {
            font-weight: bold;
            color: #2c5282;
        }
        
        .gauge-table.compact {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75em;
            border: 2px solid #000;
            margin-bottom: 8px;
        }
        
        .gauge-table.compact th,
        .gauge-table.compact td {
            padding: 3px 4px;
            border: 1px solid #ccc;
            text-align: center;
            height: 18px;
        }
        
        .gauge-table.compact th {
            background-color: #f5f5f5;
            font-weight: bold;
            border-bottom: 2px solid #000;
        }
        
        .gauge-table.compact .total-row {
            font-weight: bold;
            background-color: #f0f0f0;
            border-top: 2px solid #000;
        }
        
        .signature-section.compact {
            margin-top: 10px;
        }
        
        .signature-section.compact .signature-line {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            font-size: 0.8em;
        }
        
        .signature-section.compact .label {
            flex: 1;
        }
        
        .signature-section.compact .firma {
            flex: 1;
            text-align: right;
        }
        
        /* Print-specific styles */
        @media print {
            @page {
                margin: 5mm;
                size: portrait;
            }
            
            * {
                margin: 0;
                padding: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            html, body {
                width: 100%;
                height: auto;
                margin: 0;
                padding: 0;
                background: #FFF;
                overflow: visible;
            }
            
            body {
                padding: 0;
                font-size: 9pt;
            }
            
            .report-container {
                width: 100%;
                max-width: 100%;
                margin: 0;
                padding: 0;
            }
            
            .sub-header {
                page-break-after: avoid;
            }
            
            .transaction-details {
                page-break-inside: avoid;
            }
            
            .gauge-details {
                page-break-inside: avoid;
            }
            
            .signature-section {
                page-break-before: avoid;
                page-break-inside: avoid;
            }
            
            /* Ensure table rows don't break across pages */
            table tr {
                page-break-inside: avoid;
            }
            
            /* Adjust font sizes for print */
            .sub-header p {
                font-size: 9pt;
            }
            
            .details-grid.compact {
                font-size: 9pt;
            }
            
            .gauge-table.compact {
                font-size: 8pt;
            }
            
            .signature-section.compact {
                font-size: 8pt;
            }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="report-container">
        <section class="sub-header">
            <p style="font-weight: semi-bold;">Biiro Makaayinih Suktay Zeytiy Kee Gersi Gacamgac Uwwaytih damiyyi Essero Cibu</p>
            <p style="font-weight: bold;">የመስሪያ ቤታችን የተሽከርካሪ ቅባትና ዘይት የተለያዩ የመለዋወጫ እቃዎች ግዥ መጠየቂያ ቅጽ።</p>
            <p style="font-weight: normal;"><strong>Projecti Migaq kee Kooxu:</strong> <span class="highlight"><?php echo htmlspecialchars($transaction['owner_name']); ?></span></p>
        </section>

        <section class="transaction-details">
            <div class="details-grid compact">
                <p><strong>Kokobise Migaq:</strong><span class="highlight"><?php echo htmlspecialchars($transaction['driver_name']); ?></span></p>
                <p><strong>Plate No:</strong><span class="highlight"><?php echo htmlspecialchars($transaction['plate_number']); ?></span></p>
                <p><strong>Model:</strong><span><?php echo htmlspecialchars($transaction['vehicle_model']); ?></span></p>
                <p><strong>Month:</strong><span><?php echo htmlspecialchars($transaction['et_month']); ?></span></p>
                <p><strong>Distance:</strong><span class="highlight"><?php echo number_format($transaction['journey_distance'], 2); ?> km</span></p>
                <p><strong>Price/L:</strong><span class="highlight"><?php echo number_format($transaction['fuel_price'], 2); ?> Birr</span></p>
                <p><strong>Fuel (L):</strong><span class="highlight"><?php echo number_format($transaction['refuelable_amount'], 2); ?> L</span></p>
                <p><strong>Total Cost:</strong><span class="highlight"><?php echo number_format($transaction['total_amount'], 2); ?> Birr</span></p>
            </div>
        </section>

        <section class="gauge-details">
            <table class="gauge-table compact">
                <thead>
                    <tr>
                        <th>K.L</th>
                        <th>Uwwayti Qaynat<br>የእቃው አይነት</th>
                        <th>Giyaase<br>መለኪያ</th>
                        <th>Manga<br>ብዛት</th>
                        <th>Inkitti Limo<br>የአንዱ ዋጋ</th>
                        <th>Sittat<br>ጠቅ/ድምር</th>
                        <th>Kusaq<br>ምርመራ</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>Sansi/ናፍታ</td>
                        <td>Liter/ሊትር</td>
                        <td><strong><?php echo number_format($transaction['refuelable_amount'], 2); ?></strong></td>
                        <td><strong><?php echo number_format($transaction['fuel_price'], 2); ?></strong></td>
                        <td><strong><?php echo number_format($transaction['total_amount'], 2); ?></strong></td>
                        <td></td>
                    </tr>
                    <tr><td>2</td><td>Benzin/ቤንዚን</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>3</td><td>Matoor Sukat/የሞተር ዘይት</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>4</td><td>Kanbiyo Sukat/የካንቢዮ ዘይት</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>5</td><td>Mari Sukat/የመሪ ዘይት</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>6</td><td>Gerse/ግሪስ</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>7</td><td>Fereen Sukat/የፍሬን ዘይት</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>8</td><td>Batri Lee/የባትሪ ውሀ</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>9</td><td>Gommi Bicsa/ለጎማ</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>10</td><td>Kalat Tanim/ሌሎች</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr class="total-row">
                        <td colspan="5">Sittat (ድምር)</td>
                        <td><strong><?php echo number_format($transaction['total_amount'], 2); ?></strong></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="signature-section compact">
            <div class="signature-line">
                <span class="label">Taama gexenum Migaaqa:__________________________</span>
                <span class="firma">Firma: ___________________________</span>
            </div>
            <div class="signature-line">
                <span class="label">Cisaab Massosee Migaaqa:_________________________</span>
                <span class="firma">Firma: ___________________________</span>
            </div>
            <div class="signature-line">
                <span class="label">Diggosse Migaaqa:_________________________________</span>
                <span class="firma">Firma: ___________________________</span>
            </div>
            <div class="signature-line">
                <span class="label">Faticisee Migaaqa:__________________________________</span>
                <span class="firma">Firma: ___________________________</span>
            </div>
        </section>
    </div>
</body>
</html>