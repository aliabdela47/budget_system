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
            font-size: 14px;
        }
        .report-container {
            background-color: #fff;
            padding: 20px;
            width: 21cm; /* A4 width */
            min-height: 29.7cm; /* A4 height */
            margin: 0 auto;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            box-sizing: border-box;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 2px solid #000;
        }

        .header .logo {
            flex: 0 0 auto;
        }

        .header .logo img {
            height: 90px;
            width: auto;
            display: block;
        }

        .header .flags {
            flex: 1;
            display: flex;
            justify-content: flex-start;
            align-items: center;
            gap: 10px;
        }

        .header .flags img {
            height: 60px;
            width: auto;
        }

        .header-info {
            flex: 2;
            text-align: center;
            line-height: 1.5;
            font-weight: bold;
        }

        .header-info p {
            margin: 0;
            font-size: 1.1em;
        }

        .header-right {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            font-size: 0.9em;
        }

        .header-right p {
            margin: 2px 0;
            text-align: right;
            line-height: 1.2;
        }

        .report-title {
            text-align: center;
            font-size: 1.2em;
            font-weight: bold;
            margin: 15px 0;
        }

        .report-title p {
            margin: 5px 0;
        }

        .details-section {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px 30px;
            margin: 20px 0;
        }

        .details-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px dotted #ccc;
            padding: 5px 0;
        }

        .details-item label {
            font-weight: bold;
            color: #444;
            white-space: nowrap;
        }

        .details-item span {
            text-align: right;
            flex-grow: 1;
        }

        .table-container {
            margin: 20px 0;
        }

        .main-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 1.0em;
        }

        .main-table th, .main-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
        }
        
        .main-table thead th {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        .main-table .total-row {
            font-weight: bold;
            background-color: #e8e8e8;
        }

        .signature-section {
            margin-top: 40px;
        }

        .signature-line {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 30px;
            font-size: 0.95em;
        }
        
        .signature-line .label {
            flex: 1;
            white-space: nowrap;
            margin-right: 15px;
        }
        
        .signature-line .firma {
            flex: 1;
            text-align: right;
            padding-left: 20px;
        }

        .footer {
            margin-top: 40px;
            border-top: 2px solid #000;
            padding-top: 10px;
            display: flex;
            justify-content: space-between;
            font-size: 0.8em;
            color: #555;
        }

        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                background-color: #fff;
            }
            .report-container {
                box-shadow: none;
            }
            .no-print {
                display: none;
            }
            @page {
                size: A4 portrait;
                margin: 0.5in;
            }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="report-container">
        <header class="header">
            <div class="flags">
                <img src="../images/afar-flag.png" alt="Afar Flag">
                <img src="../images/ethiopia-flag.png" alt="Ethiopian Flag">
            </div>
            <div class="header-info">
                <p>የአፋር ብሔራዊ ክልላዊ መንግስት ጤና ቢሮ</p>
                <p>Afar National Regional State Health Bureau</p>
            </div>
            <div class="logo">
                <img src="../images/bureau-logo.png" alt="Bureau Logo">
            </div>
            <div class="header-right">
                <p><strong>Ixxima:</strong> FT-<?php echo str_pad($transaction['id'], 5, '0', STR_PAD_LEFT); ?></p>
<!-- convert date to current Ethiopian Date-->               
                <p><strong>Ayro:</strong> <?php echo date('d-m-Y', strtotime($transaction['date'])); ?></p>
            </div>
        </header>

        <section class="report-title">
            <p>Biiro Makaayinih Suktay Zeytiy Kee Gersi Gacamgac Uwwaytih damiyyi Essero Cibu</p>
            <p>የመስሪያ ቤታችን የተሽከርካሪ ቅባትና ዘይት የተለያዩ የመለዋወጫ እቃዎች ግዥ መጠየቂያ ቅጽ።</p>
        </section>

        <section class="details-section">
            <div class="details-item">
                <label>1. Driver Name:</label>
                <span><?php echo htmlspecialchars($transaction['driver_name']); ?></span>
                <br> <label>የአሽከርካሪው ስም :</label> </br>
                
            </div>
            <div class="details-item">
                <label><p>5. Sansi Kibteway Suge :</lable></p><lable>Geej Loowo:-<p></label>
                <span><?php echo htmlspecialchars($transaction['current_gauge']); ?></span>
            </div>
            <div class="details-item">
                <label>2. Makina Lucatih Koox:-</label>
                <span><?php echo htmlspecialchars($transaction['plate_no']); ?></span>
            </div>
            <div class="details-item">
                <label>6. Awaay Yan Gej Loowo:</label>
                <span><?php echo htmlspecialchars($transaction['current_gauge']); ?></span>
            </div>
            <div class="details-item">
                <label>3. Inki Litir "5" Gexxa Km:</label>
                <span><?php echo number_format($transaction['5'], 2); ?> KM</span>
            </div>
            <div class="details-item">
                <label>Geej Loowo Baxsi:</label>
                <span><?php echo number_format($transaction[''], 2); ?> KM</span>
            </div>
        </section>

        <div class="table-container">
            <table class="main-table">
                <thead>
                    <tr>
                        <th>K.L</th>
                        <th>Uwwayti Qaynat<br>የእቃው አይነት</th>
                        <th>Giyaase<br>መለኪያ</th>
                        <th>Manga<br>ብዛት</th>
                        <th>Inkitti Limo<br>የአንዱ ዋጋ</th>
                        <th>Sittat<br>ጠቅ / ድምር</th>
                        <th>Kusaq<br>ምርመራ</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>Sansi/ ናፍታ</td>
                        <td>Liter/ ሊትር</td>
                        <td><?php echo number_format($transaction['refuelable_amount'], 2); ?></td>
                        <td><?php echo number_format($transaction['fuel_price'], 2); ?></td>
                        <td><?php echo number_format($transaction['total_amount'], 2); ?></td>
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
                        <td><?php echo number_format($transaction['total_amount'], 2); ?></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <section class="signature-section">
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

        <footer class="footer">
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
