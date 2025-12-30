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
        
        .transaction-details {
            margin: 25px 0; padding: 20px;
            border: 1px solid #eee; background-color: #fdfdfd; border-radius: 8px;
        }

        .details-heading {
            font-size: 1.3em; font-weight: bold; color: #333;
            border-bottom: 1px solid #ddd; padding-bottom: 10px;
            margin-top: 0; margin-bottom: 20px;
        }
        
        .details-grid {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 15px 25px;
        }
        
        .details-grid p {
            margin: 0; padding: 8px; border-bottom: 1px dotted #ccc;
            display: flex; justify-content: space-between;
        }
        
        .details-grid p strong { color: #555; padding-right: 10px; }

        /* Styles for the new gauge table */
        .gauge-details {
            margin-top: 30px;
        }

        .gauge-table {
            width: 100%; border-collapse: collapse; text-align: center;
        }

        .gauge-table th, .gauge-table td {
            border: 1px solid #ccc; padding: 12px; font-size: 1.05em;
        }

        .gauge-table th {
            background-color: #f2f2f2; font-weight: bold;
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
                <p>የአፋር ብሔራዊ ክልላዊ መንግሥት</p>
                <p>ጤና ቢሮ>
                <p style="font-size: 0.9em; font-style: italic;">The Afar National Regional State</p>
                <p style="font-size: 0.9em; font-style: italic;">Bureau of Health</p>
            </div>
           
        <!-- Bureau Logo -->
        
      
            <div class="header-center">
                <div class="logo"></div>
            </div>
            <div class="header-right">
                <p>Ref. No. <span style="font-weight:bold;"><?php echo 'FT-' . str_pad($transaction['id'], 5, '0', STR_PAD_LEFT); ?></span></p>
                <p>Date: <span style="font-weight:bold;"><?php echo date('d-m-Y', strtotime($transaction['date'])); ?></span></p>
            </div>
        </header>

        <section class="sub-header">
          <p style="font-size: 1.25rem; font-style: normal; font-weight: bold;">Biiro Makaayinih Suktay Zeytiy Kee Gersi Gacamgac Uwwaytih damiyyi Essero Cibu</p>
            <p>የመስሪያ ቤታችን የተሽከርካሪ ቅባትና ዘይት የተለያዩ የመለዋወጫ እቃዎች ግዥ መጠየቂያ ቅጽ።</p>
        </section>

        <section class="transaction-details">
            
            <div class="details-grid">
                <p><strong>የበጀት ባለቤት (Owner):</strong> <span><?php echo htmlspecialchars($transaction['owner_code'] . ' - ' . $transaction['owner_name']); ?></span></p>
                <p><strong>የሹፌር ስም (Driver):</strong> <span style="font-size: 1.25rem; font-style: normal; font-weight: bold;"> <?php echo htmlspecialchars($transaction['driver_name']); ?></span></p>
<p><strong>የተሽከርካሪው ታርጋ:</strong> <span style="font-size: 1.25rem; font-style: normal; font-weight: bold;"><?php echo htmlspecialchars($transaction['plate_number']); ?></span></p>
                <p><strong>የተሽከርካሪ አይነት (Model):</strong> <span><?php echo htmlspecialchars($transaction['vehicle_model']); ?></span></p>
                <p><strong>የኢትዮጵያ ወር (Month):</strong> <span><?php echo htmlspecialchars($transaction['et_month']); ?></span></p>
                <p><strong>የሚጓዘው ርቀት (Distance):</strong> <span style="font-size: 1.25rem; font-style: normal; font-weight: bold;"><?php echo number_format($transaction['journey_distance'], 2); ?> km</span></p>
                <p><strong>የነዳጅ ዋጋ (Price/Liter):</strong> <span style="font-size: 1.25rem; font-style: normal; font-weight: bold;"><?php echo number_format($transaction['fuel_price'], 2); ?> Birr</span></p>
                <p><strong>የተሞላ ነዳጅ (በሊትር):</strong> <span style="font-size: 1.25rem; font-style: normal; font-weight: bold;"><?php echo number_format($transaction['refuelable_amount'], 2); ?> L</span></p>
<p><strong> የሚያስፈልገው ገንዘብ(Total Cost):</strong> <span style="font-size: 1.25rem; font-style: normal; font-weight: bold;"><?php echo number_format($transaction['total_amount'], 2); ?> Birr</span></p>

            </div>
        </section>

        <!-- New Gauge Information Table -->
        <section class="gauge-details">
                    <table class="gauge-table">
                <thead>
                  <tr>
                    <th>K.L</th>
                    <th>Uwwayti Qaynat /ADAB KESA</th>
                    <th>Giyaase <br >  መለኪያ </br></th>
                    <th>Manga <br >  ብዛት </br></th>
                    <th>Inkitti Limo <br >  የአንዱ ዋጋ </br></th>
                    <th>Sittat <br >  ጠቅ/ድምር </br></th>
                    <th>Kusaq <br >  ምርመራ </br></th>
                </tr>
                    <tr>
                        <th>1 <br></th>
<th>Sansi/ናፍታ <br></th>
<th><p></p>Liter / ብዛት <span style="font-weight: bold;"</span><br></p></th>
<td><p><strong> <span><?php echo number_format($transaction['refuelable_amount'], 2); ?> L</span></p></strong></td>
<td><p><strong> <span style="font-weight: bold;"></span><?php echo number_format($transaction['fuel_price'], 2); ?> Birr</span></p></strong></td>
<td> <p> <span style="font-weight: bold;"><?php echo number_format($transaction['total_amount'], 2); ?> Birr</span></p></td>
<th><br></th>

                    </tr>
                    
                    <tr>
                        <th> 2<br></th>
<th>Benzin/ቤንዚን<br></th>
<th><br></th>
<th><br></th>
<th><br></th>
<th><br></th>
<th><br></th>
                    </tr>
                    <th> 3<br></th>
<th>Matoor Sukat/ የሞተር ዘይት<br></th>
<th><br></th>
<th><br></th>
<th><br></th>
<th><br></th>
<th><br></th>
                    </tr>
                    <th> 4<br></th>
<th>Kanbiyo Sukat/የካንቢዮ ዘይት<br></th>
<th><br></th>
<th><br></th>
<th><br></th>
<th><br></th>
<th><br></th>
                    </tr>
                    <th> 5<br></th>
<th>Mari Sukat/የመሪ ዘይት<br></th>
<th><br></th>
<th><br></th>
<th><br></th>
<th><br></th>
<th><br></th>
                    </tr>
                    <th> 6<br></th>
<th>Gerse/ግሪስ<br></th>
<th><br></th>
<th><br></th>
<th><br></th>
<th><br></th>
<th><br></th>
                    </tr>
                    <th>7 <br></th>
<th>Fereen Sukat/የፍሬን ዘይት<br></th>
<th><br></th>
<th><br></th>
<th><br></th>
<th><br></th>
<th><br></th>
                    </tr>
                    <th> 8<br></th>
<th>Batri Lee/የባትሪ ውሀ<br></th>
<th><br></th>
<th><br></th>
<th><br></th>
<th><br></th>
<th><br></th>
                    </tr>
                    <th>9 <br></th>
<th>Gommi Bicsa/ለጎማ<br></th>
<th><br></th>
<th><br></th>
<th><br></th>
<th><br></th>
<th><br></th>
                    </tr>
                    <th>10 <br></th>
<th>Kalat Tanim / ሌሎች<br></th>
<th><br></th>
<th><br></th>
<th><br></th>
<th><br></th>
<th><br></th>
                    </tr>
                    <th><br></th>
                    <th> Sittat </th>
                    <th><br></th>
<th><br></th>
<th><br></th>

<td> <p> <span style="font-weight: bold;"><?php echo number_format($transaction['total_amount'], 2); ?> Birr</span></p></td>
                        <td></td>
                       </tr>
                </thead>
                <tbody>
                   

                    </tr>
                </tbody>
            </table>
        </section>
        <section class="signature-section">
            <div class="signature-line">
                <span class="label">Taama gesenum Migaaqa:</span>
                <span class="line"></span>
                <span class="firma">Firma:__________________________________</span>
            </div>
            <div class="signature-line">
                <span class="label">Caatab Massosee Migaaqa:</span>
                <span class="line"></span>
                <span class="firma">Firma:__________________________________</span>
            </div>
            <div class="signature-line">
                <span class="label">Diggosse Migaaqa:</span>
                 <span class="line"></span>
                <span class="firma">Firma:__________________________________</span>
            </div>
            <div class="signature-line">
                <span class="label">Fasisse Migaaqa:</span>
                 <span class="line"></span>
                <span class="firma">Firma:__________________________________</span>
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