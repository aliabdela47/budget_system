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

        body {
            font-family: 'Noto Sans Ethiopic', 'Nyala', serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        .report-container {
            background-color: #fff;
            padding: 25px;
            width: 21cm; /* A4 width */
            min-height: 29.7cm; /* A4 height */
            margin: 0 auto;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            box-sizing: border-box;
        }

        .report-header {
            display: flex; 
            justify-content: space-between; 
            align-items: flex-start;
            border-bottom: 1.5px solid #000; 
            padding-bottom: 10px; 
            margin-bottom: 5px;
        }

        .header-left p, .header-right p { 
            margin: 0; 
            line-height: 1.6; 
        }
        .header-left, .header-right { 
            text-align: left; 
        }

        .header-center .logo {
            width: 70px; 
            height: 70px; 
            border: 0px solid #555;
            display: flex; 
            justify-content: center; 
            align-items: center;
            font-size: 0.8em; 
            color: #666; 
            margin: 0 15px;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path d="M20 50 L80 50 M50 20 L50 80" stroke="%23333" stroke-width="5" transform="rotate(45 50 50)"/><path d="M20 50 L80 50 M50 20 L50 80" stroke="%23333" stroke-width="5" transform="rotate(-45 50 50)"/></svg>');
            background-repeat: no-repeat; 
            background-position: center; 
            background-size: 60%;
        }

        .sub-header {
            text-align: center; 
            font-weight: bold; 
            margin: 15px 0;
            border-bottom: 1px solid #ccc; 
            padding-bottom: 10px; 
            line-height: 1.7;
        }
        
        .transaction-details { 
            margin: 25px 0; 
        }
        
        .details-grid {
            display: grid; 
            grid-template-columns: 1fr 1fr;
            gap: 15px 25px; 
            line-height: 1.8;
        }
        
        .details-grid p {
            margin: 0; 
            padding: 8px; 
            border-bottom: 1px dotted #ccc;
            display: flex; 
            justify-content: space-between; 
            align-items: baseline;
        }
        
        .details-grid p strong { 
            color: #555; 
            padding-right: 10px; 
        }

        .gauge-details { 
            margin-top: 30px; 
        }
        
        .gauge-table { 
            width: 100%; 
            border-collapse: collapse; 
            text-align: center; 
        }
        
        .gauge-table th, .gauge-table td { 
            border: 1.5px solid #000; 
            padding: 8px; 
            font-size: 1.0em; 
        }
        
        .gauge-table thead th { 
            background-color: #f2f2f2; 
            font-weight: bold; 
        }
        
        .gauge-table td p { 
            margin: 0; 
        }

        .signature-section { 
            margin-top: 40px; 
            font-size: 1.05em; 
        }
        
        .signature-line {
            display: flex; 
            justify-content: space-between;
            align-items: flex-end; 
            margin-top: 35px;
        }
        
        .signature-line .label { 
            white-space: nowrap; 
            padding-right: 15px; 
        }
        
        .signature-line .line { 
            border-bottom: 1px solid #000; 
            width: 60%; 
        }
        
        .signature-line .firma { 
            padding-left: 20px; 
            white-space: nowrap; 
        }

        .report-footer {
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            border-top: 1.5px solid #000; 
            padding-top: 10px; 
            margin-top: 35px;
            font-size: 0.9em;
        }
        
        @media print {
            body { 
                background-color: #fff; 
                padding: 0; 
                margin: 0; 
            }
            
            .report-container { 
                box-shadow: none; 
                border: none; 
                margin: 0; 
                width: 100%;
                height: 100%;
            }
            
            @page { 
                margin: 0.5in; 
                size: A4 portrait;
            }
            
            .no-print {
                display: none;
            }
        }
  content://com.android.externalstorage.documents/tree/primary%3Ahtdocs::primary:htdocs/budget_system/reports/fuel_transaction_report.phpcontent://com.android.externalstorage.documents/tree/primary%3Ahtdocs::primary:htdocs/budget_system/reports/fuel_transaction_report.php
    
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
      
      <header class="report-header">
    <div class="header-left">
        <p>የአፋር ብሔራዊ ክልላዊ መንግሥት ጤና ቢሮ </p>        <p style="font-size: 0.9em; font-style: normal;">The Afar National Regional State</p>
        <p style="font-size: 0.9em; font-style: normal;">Bureau of Health</p>
    </div>
    <div class="header-center">
        <div class="logo">
            <img src="../images/bureau-logo.png" alt="Bureau Logo" onerror="this.style.display='none'">
        </div>
    </div>
    <div class="header-right">
        <p>Ixxima | Ref. No. <span style="font-weight:bold;">FT-<?php echo str_pad($transaction['id'], 5, '0', STR_PAD_LEFT); ?></span></p>
        <p>Ayro | Date: <span style="font-weight:bold;"><?php echo date('d-m-Y', strtotime($transaction['date'])); ?></span></p>
    </div>
</header>
<section class="sub-header">
            <p style="font-size: 0.8rem; font-style: normal; font-weight: semi-bold;">Biiro Makaayinih Suktay Zeytiy Kee Gersi Gacamgac Uwwaytih damiyyi Essero Cibu</p>
            <p style="font-size: 0.8rem; font-style: normal; font-weight: bold;">የመስሪያ ቤታችን የተሽከርካሪ ቅባትና ዘይት የተለያዩ የመለዋወጫ እቃዎች ግዥ መጠየቂያ ቅጽ።</p>
<p style="font-size: 0.8rem; font-style: normal; font-weight: normal;">Projecti Migaq kee Kooxu:</strong><span class="highlight"><?php echo htmlspecialchars($transaction['owner_name']); ?></span></p>
</section>
        
  <section class="transaction-details">
    <div class="details-grid compact">
      <p><strong>1. Kokobise Migaq:</strong><span class="highlight"><?php echo
      htmlspecialchars($transaction['driver_name']); ?></span></p>
      <p><strong>2. Plate No:</strong><span class="highlight"><?php echo htmlspecialchars($transaction['plate_number'],); ?></span></p>
        <p><strong>3. Model:</strong><span><?php echo htmlspecialchars($transaction['vehicle_model']); ?></span></p>
       <p><strong>4. Month:</strong><span class="highlight"><?php echo
      htmlspecialchars($transaction['et_month']); ?></span></p>
        <p><strong>5. Distance:</strong><span class="highlight"><?php echo number_format($transaction['journey_distance'],); ?> km</span></p>
        <p><strong>6. Price/L:</strong><span class="highlight"><?php echo number_format($transaction['fuel_price'], 2); ?> Birr</span></p>
        <p><strong>7. Fuel (L):</strong><span class="highlight"><?php echo number_format($transaction['refuelable_amount'],); ?> L</span></p>
        <p><strong>8. Total Cost:</strong><span class="highlight"><?php echo number_format($transaction['total_amount'], 2); ?> Birr</span></p>
    </div>
</section>

<style>
.transaction-details {
    margin: 15px 0;
}

.details-grid.compact {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 8px 15px;
    line-height: 1;
}

.details-grid.compact p {
    margin: 0;
    padding: 5px 0;
    display: flex;
    justify-content: space-between;
    border-bottom: 1px dotted #ddd;
}

.details-grid.compact p strong {
    color: #555;
    font-weight: 500;
    flex-shrink: 0;
    margin-right: 8px;
}

.details-grid.compact p span {
    text-align: right;
    flex-grow: 1;
}

.details-grid.compact .highlight {
    font-weight: bold;
    color: #2c5282;
}
</style>
<br>
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
<br>
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

<style>
.gauge-details {
    margin: 10px 0;
}

.gauge-table.compact {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85em;
    border: 3px solid #000; /* Thick outer border */
}

.gauge-table.compact th,
.gauge-table.compact td {
    padding: 4px 6px;
    border: 1px solid #ccc;
    text-align: center;
}

.gauge-table.compact th {
    background-color: #f5f5f5;
    font-weight: bold;
    border-bottom: 2px solid #000; /* Thicker bottom border for header */
}

.gauge-table.compact .total-row {
    font-weight: bold;
    background-color: #f0f0f0;
    border-top: 2px solid #000; /* Thicker top border for total row */
}

.signature-section.compact {
    margin-top: 20px;
}

.signature-section.compact .signature-line {
    display: flex;
    justify-content: space-between;
    margin: 8px 0;
    font-size: 0.9em;
}

.signature-section.compact .label {
    flex: 0em;
}

.signature-section.compact .firma {
    flex: 0em;
    text-align: right;
}

/* Header with logo */
.report-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1.5px solid #000;
    padding-bottom: 10px;
    margin-bottom: 5px;
}

.header-left {
    text-align: left;
    flex: 1;
}

.header-left p {
    margin: 0;
    line-height: 1.6;
}

.header-center {
    text-align: center;
    flex: 0 0 auto;
}

.header-center .logo {
    width: 80px;
    height: 80px;
    display: flex;
    justify-content: center;
    align-items: center;
}

.header-center .logo img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.header-right {
    text-align: right;
    flex: 1;
}

.header-right p {
    margin: 0;
    line-height: 1.6;
}
</style>
<footer class="report-footer">
            <span>TEL: 033-666-00-22</span>
            <span>Email: info@afarrhb.et</span>
            <span>Printed on: <?php echo date('d-m-Y'); ?></span>
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