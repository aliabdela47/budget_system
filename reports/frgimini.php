<?php
// reports/fuel_transaction_report.php

// Go up one level to find the 'includes' directory
include '../includes/db.php'; 

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Error: No valid transaction ID provided.");
}

$transaction_id = $_GET['id'];

// Fetch the specific transaction details, joining with other tables to get names
$stmt = $pdo->prepare("
    SELECT 
        ft.*,
        bo.name AS owner_name,
        bo.code AS owner_code,
        v.model AS vehicle_model
    FROM fuel_transactions ft
    LEFT JOIN budget_owners bo ON ft.owner_id = bo.id
    LEFT JOIN vehicles v ON ft.plate_number = v.plate_no
    WHERE ft.id = ?
");
$stmt->execute([$transaction_id]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    die("Error: Transaction with ID {$transaction_id} not found.");
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fuel Transaction Report - ID: <?php echo $transaction_id; ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;700&display=swap');

        body {
            font-family: 'Noto Sans Ethiopic', 'Nyala', serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 20px;
            line-height: 1.6;
        }

        .report-container {
            background-color: #fff;
            border: 1px solid #ccc;
            padding: 25px;
            width: 850px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1.5px solid #000;
            padding-bottom: 10px;
            margin-bottom: 5px;
        }

        .header-left p, .header-right p { margin: 0; }
        .header-left, .header-right { text-align: left; }

        .header-center .logo {
            width: 70px; height: 70px; border: 1.5px solid #555;
            display: flex; justify-content: center; align-items: center;
            font-size: 0.8em; color: #666; margin: 0 15px;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path d="M20 50 L80 50 M50 20 L50 80" stroke="%23333" stroke-width="5" transform="rotate(45 50 50)"/><path d="M20 50 L80 50 M50 20 L50 80" stroke="%23333" stroke-width="5" transform="rotate(-45 50 50)"/></svg>');
            background-repeat: no-repeat; background-position: center; background-size: 60%;
        }

        .sub-header {
            text-align: center; font-weight: bold; margin: 15px 0;
            font-size: 1.2em; border-bottom: 1px solid #ccc; padding-bottom: 10px;
        }
        
        /* This section replaces the original table for a better fit */
        .transaction-details {
            margin: 25px 0;
            padding: 20px;
            border: 1px solid #eee;
            background-color: #fdfdfd;
            border-radius: 8px;
        }

        .transaction-details h2 {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
            margin-top: 0;
            margin-bottom: 20px;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px 25px;
        }
        
        .details-grid p {
            margin: 0;
            padding: 8px;
            border-bottom: 1px dotted #ccc;
            display: flex;
            justify-content: space-between;
        }
        
        .details-grid p strong {
            color: #555;
            padding-right: 10px;
        }

        .signature-section {
            margin-top: 40px; font-size: 1.05em;
        }
        
        .signature-line {
            display: flex; justify-content: space-between;
            align-items: flex-end; margin-top: 35px;
        }
        
        .signature-line .label { white-space: nowrap; padding-right: 15px; }
        .signature-line .line { border-bottom: 1px solid #000; width: 60%; }
        .signature-line .firma { padding-left: 20px; white-space: nowrap; }

        .report-footer {
            display: flex; justify-content: space-between; align-items: center;
            border-top: 1.5px solid #000; padding-top: 10px; margin-top: 35px;
            font-size: 0.9em;
        }
        
        @media print {
            body { background-color: #fff; padding: 0; margin: 0; }
            .report-container { box-shadow: none; border: none; }
            @page { margin: 0.75in; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="report-container">
        <header class="report-header">
            <div class="header-left">
                <p>በቤንሻንጉል ጉሙዝ ብሔራዊ ክልላዊ መንግሥት</p>
                <p>ጤና ጥበቃ ቢሮ</p>
                <p style="font-size: 0.9em; font-style: italic;">The Afar National Regional State</p>
                <p style="font-size: 0.9em; font-style: italic;">Bureau of Health</p>
            </div>
            <div class="header-center">
                <div class="logo"></div>
            </div>
            <div class="header-right">
                <p>Ref. No. <span style="font-weight:bold;"><?php echo 'FT-' . str_pad($transaction['id'], 5, '0', STR_PAD_LEFT); ?></span></p>
                <p>Date: <span style="font-weight:bold;"><?php echo date('d-m-Y', strtotime($transaction['date'])); ?></span></p>
            </div>
        </header>

        <section class="sub-header">
            <p>የነዳጅ አጠቃቀም እና ወጪ ማመልከቻ</p>
            <p style="font-size: 0.9em; font-style: italic; font-weight: normal;">Fuel Usage and Expense Voucher</p>
        </section>

        <!-- Replaced the original table with a details section -->
        <section class="transaction-details">
            <h2>Transaction Details (የግብይት ዝርዝሮች)</h2>
            <div class="details-grid">
                <p><strong>የበጀት ባለቤት (Owner):</strong> <span><?php echo htmlspecialchars($transaction['owner_code'] . ' - ' . $transaction['owner_name']); ?></span></p>
                <p><strong>የሹፌር ስም (Driver):</strong> <span><?php echo htmlspecialchars($transaction['driver_name']); ?></span></p>
                <p><strong>የተሽከርካሪ ታርጋ (Plate No):</strong> <span><?php echo htmlspecialchars($transaction['plate_number']); ?></span></p>
                <p><strong>የተሽከርካሪ አይነት (Model):</strong> <span><?php echo htmlspecialchars($transaction['vehicle_model']); ?></span></p>
                <p><strong>የኢትዮጵያ ወር (Month):</strong> <span><?php echo htmlspecialchars($transaction['et_month']); ?></span></p>
                <p><strong>የተጓዘው ርቀት (Distance):</strong> <span><?php echo number_format($transaction['journey_distance'], 2); ?> km</span></p>
                <p><strong>የነዳጅ ዋጋ (Price/Liter):</strong> <span><?php echo number_format($transaction['fuel_price'], 2); ?> Birr</span></p>
                <p><strong>የተሞላ ነዳጅ (Liters):</strong> <span><?php echo number_format($transaction['refuelable_amount'], 2); ?> L</span></p>
                <p><strong>ጠቅላላ ወጪ (Total Cost):</strong> <span><strong><?php echo number_format($transaction['total_amount'], 2); ?> Birr</strong></span></p>
            </div>
        </section>

        <section class="signature-section">
            <div class="signature-line">
                <span class="label">የጠየቀው ስምና ፊርማ (Requested By):</span>
                <span class="line"></span>
                <span class="firma">ፊርማ (Signature):</span>
            </div>
            <div class="signature-line">
                <span class="label">ያረጋገጠው ስምና ፊርማ (Verified By):</span>
                <span class="line"></span>
                <span class="firma">ፊርማ (Signature):</span>
            </div>
            <div class="signature-line">
                <span class="label">ያጸደቀው ስምና ፊርማ (Approved By):</span>
                 <span class="line"></span>
                <span class="firma">ፊርማ (Signature):</span>
            </div>
        </section>

        <footer class="report-footer">
            <span>TEL: 033-666-00-22</span>
            <span>Email: afarhealthbureau@gmail.com</span>
            <span>Printed on: <?php echo date('Y-m-d H:i:s'); ?></span>
        </footer>
    </div>

</body>
</html>