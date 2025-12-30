<?php
// reports/fuel_transaction_report.php
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
            padding: 10px 20px; /* Reduced top padding */
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
            padding: 5px 0; /* Reduced top padding */
            border-bottom: 2px solid #000;
        }

        .header .flags-left, .header .flags-right {
            flex: 1;
        }
        
        .header .flags-left img, .header .flags-right img {
            height: 60px;
            width: auto;
        }

        .header .flags-left img {
            display: block;
        }

        .header .flags-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            font-size: 0.9em;
        }
        
        .header .flags-right p {
            margin: 2px 0;
        }

        .header-center {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            flex: 2;
        }
        
        .header-center .logo img {
            height: 90px;
            width: auto;
            margin-bottom: 5px;
        }
        
        .header-center .titles p {
            margin: 0;
            font-weight: bold;
        }

        .report-title {
            text-align: center;
            font-size: 1.2em;
            font-weight: bold;
            margin: 10px 0; /* Reduced margin */
        }

        .report-title p {
            margin: 5px 0;
        }

        .details-section {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px 30px;
            margin: 15px 0; /* Reduced margin */
        }
        
        .details-item {
            display: flex;
            align-items: baseline;
            gap: 15px; /* Increased space */
        }

        .details-item label {
            font-weight: bold;
            color: #444;
            white-space: nowrap;
            line-height: 1.2;
        }

        .details-item span {
            border-bottom: 1px dotted #000;
            flex-grow: 1;
            padding: 0 2px;
            font-weight: bold; /* Make the variable text bold */
        }

        .table-container {
            margin: 15px 0; /* Reduced margin */
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

        .main-table tbody td {
            font-weight: bold; /* Make the variable text bold */
        }

        .main-table .total-row {
            font-weight: bold;
            background-color: #e8e8e8;
        }

        .signature-section {
            margin-top: 15px; /* Reduced space */
        }

        .signature-line {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 10px; /* Reduced space */
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
            margin-top: 15px; /* Reduced space */
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
                <p><strong>Ayro:</strong> <?php echo date('d-m-Y', strtotime($transaction['date'])); ?></p>
            </div>
        </header>

        <section class="report-title">
            <p>Biiro Makaayinih Suktay Zeytiy Kee Gersi Gacamgac Uwwaytih damiyyi Essero Cibu</p>
            <p>የመስሪያ ቤታችን የተሽከርካሪ ቅባትና ዘይት የተለያዩ የመለዋወጫ እቃዎች ግዥ መጠየቂያ ቅጽ።</p>
     <br>
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
                <span><?php echo number_format($transaction['refuelable_amount'], 2); ?></span>
            </div>
            <div class="details-item">
                <label>5. Sansi Kibteway Suge Geej:<br>ነዳጅ ሲሞላ የነበረው የኪሎሜትር ንባብ</label>
                <span><?php echo number_format($transaction['previous_gauge'], 2); ?> KM</span>
            </div>
            <div class="details-item">
                <label>6. Away yan Geej Lowwo:<br>አሁን ያለው የጌጅ ንባብ </label>
                <span><?php echo number_format($transaction['current_gauge'], 2); ?> KM</span>
            </div>
            
             <div class="details-item">
                <label>7. Geej Lowwo Baxsi:<br>የጌጅ ንባብ ልዩነት </label>
                <span><?php echo number_format($transaction['gauge_gap'], 2); ?> KM</span>
            </div>
            
            <div class="details-item">
                <label>8. Duyyek Faxximtam:<br>የሚያስፈልገው የገንዘብ መጠን </label>
                <span><?php echo number_format($transaction['total_amount'], 2); ?> ብር</span>
            </div>
            <br>
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
        <br>

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
