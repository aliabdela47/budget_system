<?php
// reports/fuel_filtered_report.php

// Go up one level to find the 'includes' directory
include '../includes/db.php';

// Get search and filter parameters from URL
$search_query = $_GET['search_query'] ?? '';
$filter_plate = $_GET['filter_plate'] ?? '';

// Build the base query to fetch all details for matching transactions
$sql = "SELECT 
            ft.*,
            bo.name AS owner_name,
            bo.code AS owner_code,
            v.model AS vehicle_model
        FROM fuel_transactions ft
        LEFT JOIN budget_owners bo ON ft.owner_id = bo.id
        LEFT JOIN vehicles v ON ft.plate_number = v.plate_no";

$conditions = [];
$params = [];

// Add conditions based on filters
if (!empty($search_query)) {
    $conditions[] = "(ft.driver_name LIKE ? OR ft.plate_number LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}
if (!empty($filter_plate)) {
    $conditions[] = "ft.plate_number = ?";
    $params[] = $filter_plate;
}
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}
$sql .= " ORDER BY ft.date DESC";

// Execute the query to get all matching transactions
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$transactions) {
    die("No transactions found matching your filter criteria.");
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filtered Fuel Transactions Report</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;700&display=swap');

        body {
            font-family: 'Noto Sans Ethiopic', 'Nyala', serif;
            background-color: #f4f4f4;
        }

        .report-container {
            background-color: #fff;
            border: 1px solid #ccc;
            padding: 25px;
            width: 850px;
            margin: 20px auto;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .report-header {
            display: flex; justify-content: space-between; align-items: flex-start;
            border-bottom: 1.5px solid #000; padding-bottom: 10px; margin-bottom: 5px;
        }

        .header-left p, .header-right p { margin: 0; line-height: 1.6; }
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
            border-bottom: 1px solid #ccc; padding-bottom: 10px; line-height: 1.7;
        }
        
        .transaction-details { margin: 25px 0; }
        
        .details-grid {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 15px 25px; line-height: 1.8;
        }
        
        .details-grid p {
            margin: 0; padding: 8px; border-bottom: 1px dotted #ccc;
            display: flex; justify-content: space-between; align-items: baseline;
        }
        
        .details-grid p strong { color: #555; padding-right: 10px; }

        .gauge-details { margin-top: 30px; }
        .gauge-table { width: 100%; border-collapse: collapse; text-align: center; }
        .gauge-table th, .gauge-table td { border: 1.5px solid #000; padding: 8px; font-size: 1.0em; }
        .gauge-table thead th { background-color: #f2f2f2; font-weight: bold; }
        .gauge-table td p { margin: 0; }

        .signature-section { margin-top: 40px; font-size: 1.05em; }
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
        
        .page-break {
            page-break-after: always;
        }
        
        @media print {
            body { background-color: #fff; padding: 0; margin: 0; }
            .report-container { box-shadow: none; border: none; margin: 0; }
            @page { margin: 0.75in; }
        }
    </style>
</head>
<body onload="window.print()">

    <?php foreach ($transactions as $transaction): ?>
        <div class="report-container">
            <header class="report-header">
                <div class="header-left">
                    <p>የአፋር ብሔራዊ ክልላዊ መንግሥት</p>
                    <p>ጤና ቢሮ</p>
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
                <p style="font-size: 0.8rem; font-style: normal; font-weight: bold;">Biiro Makaayinih Suktay Zeytiy Kee Gersi Gacamgac Uwwaytih damiyyi Essero Cibu</p>
                <p style="font-size: 0.8rem; font-style: normal; font-weight: bold;">የመስሪያ ቤታችን የተሽከርካሪ ቅባትና ዘይት የተለያዩ የመለዋወጫ እቃዎች ግዥ መጠየቂያ ቅጽ።</p>
            </section>

            <section class="transaction-details">
                <div class="details-grid">
                    <p><strong>የበጀት ባለቤት (Owner):</strong> <span><?php echo htmlspecialchars($transaction['owner_code'] . ' - ' . $transaction['owner_name']); ?></span></p>
                    <p><strong>የሹፌር ስም (Driver):</strong> <span style="font-size: 1rem; font-weight: bold;"> <?php echo htmlspecialchars($transaction['driver_name']); ?></span></p>
                    <p><strong>የተሽከርካሪው ታርጋ:</strong> <span style="font-size: 1rem; font-weight: bold;"><?php echo htmlspecialchars($transaction['plate_number']); ?></span></p>
                    <p><strong>የተሽከርካሪ አይነት (Model):</strong> <span><?php echo htmlspecialchars($transaction['vehicle_model']); ?></span></p>
                    <p><strong>የኢትዮጵያ ወር (Month):</strong> <span><?php echo htmlspecialchars($transaction['et_month']); ?></span></p>
                    <p><strong>የሚጓዘው ርቀት (Distance):</strong> <span style="font-size: 1rem; font-weight: bold;"><?php echo number_format($transaction['journey_distance'], 2); ?> km</span></p>
                    <p><strong>የነዳጅ ዋጋ (Price/Liter):</strong> <span style="font-size: 1rem; font-weight: bold;"><?php echo number_format($transaction['fuel_price'], 2); ?> Birr</span></p>
                    <p><strong>የተሞላ ነዳጅ (በሊትር):</strong> <span style="font-size: 1rem; font-weight: bold;"><?php echo number_format($transaction['refuelable_amount'], 2); ?> L</span></p>
                    <p><strong> የሚያስፈልገው ገንዘብ(Total Cost):</strong> <span style="font-size: 1rem; font-weight: bold;"><?php echo number_format($transaction['total_amount'], 2); ?> Birr</span></p>
                </div>
            </section>

            <section class="gauge-details">
                <table class="gauge-table">
                    <thead>
                      <tr>
                        <th>K.L</th>
                        <th>Uwwayti Qaynat / ADAB KESA</th>
                        <th>Giyaase <br>(መለኪያ)</th>
                        <th>Manga <br>(ብዛት)</th>
                        <th>Inkitti Limo <br>(የአንዱ ዋጋ)</th>
                        <th>Sittat <br>(ጠቅ/ድምር)</th>
                        <th>Kusaq <br>(ምርመራ)</th>
                      </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td>Sansi/ናፍታ</td>
                            <td>Liter / ሊትር</td>
                            <td><p><strong><?php echo number_format($transaction['refuelable_amount'], 2); ?></strong></p></td>
                            <td><p><strong><?php echo number_format($transaction['fuel_price'], 2); ?></strong></p></td>
                            <td><p><strong><?php echo number_format($transaction['total_amount'], 2); ?></strong></p></td>
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
                        <tr><td>10</td><td>Kalat Tanim / ሌሎች</td><td></td><td></td><td></td><td></td><td></td></tr>
                        <tr style="font-weight: bold;">
                            <td colspan="5" style="text-align: right;">Sittat (ድምር)</td>
                            <td><p><strong><?php echo number_format($transaction['total_amount'], 2); ?></strong></p></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </section>
            
            <section class="signature-section">
                <div class="signature-line">
                    <span class="label">Taama gesenum Migaaqa:</span>
                    <span class="firma">Firma:__________________________________</span>
                </div>
                <div class="signature-line">
                    <span class="label">Caatab Massosee Migaaqa:</span>
                    <span class="firma">Firma:__________________________________</span>
                </div>
                <div class="signature-line">
                    <span class="label">Diggosse Migaaqa:</span>
                    <span class="firma">Firma:__________________________________</span>
                </div>
                <div class="signature-line">
                    <span class="label">Fasisse Migaaqa:</span>
                    <span class="firma">Firma:__________________________________</span>
                </div>
            </section>

            <footer class="report-footer">
                <span>TEL: 033-666-00-22</span>
                <span>Email: afarhealthbureau@gmail.com</span>
                <span>Printed on: <?php echo date('Y-m-d H:i:s'); ?></span>
            </footer>
        </div>
        
    <?php if (next($transactions)): // Add a page break if this is not the last item ?>
            <div class="page-break"></div>
        <?php endif; ?>
    <?php endforeach; ?>

</body>
</html>