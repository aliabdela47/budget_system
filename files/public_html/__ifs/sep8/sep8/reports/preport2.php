<?php
// reports/perdium_report.php
require '../vendor/autoload.php';

// Go up one level to find the 'includes' directory
include '../includes/db.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Transaction ID is required.");
}

$transaction_id = $_GET['id'];

// Fetch the specific perdium transaction with all details
$sql = "SELECT 
            pt.*,
            bo.name AS owner_name,
            bo.code AS owner_code,
            e.name AS employee_name,
            e.salary AS employee_salary,
            e.directorate AS employee_directorate,
            c.name_amharic AS city_name_amharic,
            c.name_english AS city_name_english,
            c.rate_low,
            c.rate_medium,
            c.rate_high,
            bc.name AS budget_code_name
        FROM perdium_transactions pt
        LEFT JOIN budget_owners bo ON pt.budget_owner_id = bo.id
        LEFT JOIN employees e ON pt.employee_id = e.id
        LEFT JOIN cities c ON pt.city_id = c.id
        LEFT JOIN budget_codes bc ON pt.budget_code_id = bc.id
        WHERE pt.id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$transaction_id]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    die("Transaction not found.");
}
use Andegna\DateTimeFactory;

// Get current date in Ethiopian calendar
try {
    $ethiopianDate = DateTimeFactory::now();
    $formattedDate = $ethiopianDate->format('d/m/Y');
} catch (Exception $e) {
    // Fallback to Gregorian if Ethiopian calendar library fails
    $formattedDate = date('d/m/Y');
}

// Calculate perdium breakdown
$perdium_rate = $transaction['perdium_rate'];
$total_days = $transaction['total_days'];
$total_amount = $transaction['total_amount'];
$departure_date = $transaction['departure_date'];
$arrival_date = $transaction['arrival_date'];

$travel_rate = $perdium_rate * 0.1;
$food_rate = $perdium_rate * 0.25;
$lodging_rate = $perdium_rate * 0.25;




$daily_per_diem = $travel_rate + $food_rate + $lodging_rate;
$stay_days = max(0, $total_days - 2);
$stay_amount = $stay_days * $daily_per_diem;

$A = $daily_per_diem;
$B = $stay_amount;
$C = $daily_per_diem;
$sittat = $travel_rate + $food_rate;
$D = $sittat;
$E = $A + $B + $D;

$travel_allowance = $perdium_rate * 0.4;
$total_travel_allowance = max(0, $total_days - 1) * $travel_allowance;
$total_per_diem_amount = $A + $B + $C;


// Calculate the values for the last column of the table
$row1_last_col = $A;
$row2_last_col = $stay_amount;
$row3_last_col = $perdium_rate * 0.1 + $perdium_rate * 0.25;

// Correctly calculate the total of the last column of the three rows
$table_total_last_col = $row1_last_col + $row2_last_col + $row3_last_col;
// ...
// Fetch the values for A, B, and C from your database query


// Now you can use them in your HTML




?>

<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Per Diem Form - Doolat Taamabeynih Assenti Esserih Cibta</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;700&display=swap');

        body {
            font-family: 'Noto Sans Ethiopic', 'Nyala', serif;
            background-color: #f0f0f0;
            display: flex;
            justify-content: center;
            padding: 5px 0;
            line-height: 1.4;
        }

        .report-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .page {
            background-color: #fff;
            border: 1px solid #ccc;
            padding: 10px;
            width: 800px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            box-sizing: border-box;
            font-size: 0.85em;
            position: relative;
        }
        
        /* --- Form & Input Styles --- */
        .editable-form input[type="text"],
        .editable-form input[type="date"],
        .editable-form input[type="number"] {
            border: none;
            border-bottom: 1.5px solid #000;
            background-color: transparent;
            font-family: 'Noto Sans Ethiopic', 'Nyala', serif;
            font-size: 1em;
            padding: 1px 3px;
            width: 100%;
            box-sizing: border-box;
            font-weight: bold;
        }
        .editable-form input:focus {
            outline: none;
            background-color: #fdf5e6;
        }
        
        .field-group {
            display: flex;
            align-items: baseline;
            margin-bottom: 3px;
            gap: 5px;
            font-weight: bold;
        }
        .field-group label {
            white-space: nowrap;
        }
        .field-group .input-wrapper {
            width: 100%;
        }

        /* --- Reusable Header --- */
        .main-header-box {
            border: 2px solid #000;
            padding: 5px;
        }
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header-top img { height: 30px; }
        .header-titles {
            text-align: center;
            flex-grow: 1; /* Allows the title section to take up available space */
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .header-titles p { margin: 1px 0; font-weight: bold; }
        .header-main-title {
            text-align: center;
            font-weight: bold;
            font-size: 1em;
            margin-top: 5px;
            text-decoration: underline;
        }
        .header-bottom {
            display: flex;
            justify-content: flex-end;
            margin-top: 3px;
        }

        /* --- Sub-header --- */
        .sub-header-fields {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-weight: bold;
        }

        /* --- Table Styles --- */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8em;
            margin-top: 8px;
        }
        .data-table th, .data-table td {
            border: 1.5px solid #000;
            padding: 2px;
            text-align: center;
            vertical-align: middle;
            font-weight: bold;
        }
        .data-table th { padding: 4px; }
        .data-table input {
            border: none;
            width: 100%;
            height: 100%;
            padding: 2px;
            text-align: center;
        }
        .data-table .row-total td { font-weight: bold; }

        /* --- Signature Styles --- */
        .signature-section { margin-top: 10px; }
        .signature-section .field-group { margin-bottom: 2px; }
        
        /* --- Footer/Submit --- */
        .form-footer {
            margin-top: 20px;
            text-align: center;
        }
        .form-footer button {
            background-color: #4f46e5;
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 6px;
            font-size: 1em;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
        }
        .form-footer button:hover {
            background-color: #4338ca;
        }

        /* --- New Footer Styles --- */
        .page-footer {
            position: absolute;
            bottom: 20px;
            left: 10px;
            right: 10px;
            text-align: center;
            font-size: 0.8em;
            font-weight: bold;
            line-height: 1.2;
        }
        .page-footer p {
            margin: 2px 0;
        }
        
        /* Center bureau logo */
        .header-titles img {
            height: 50px; /* Adjust as needed */
            margin-bottom: 5px;
        }


        @media print {
            .no-print {
                display: none;
            }
            .page {
                box-shadow: none;
                border: 1px solid #000;
                margin: 0;
                padding: 8mm 12mm;
                width: 190mm;
                height: 277mm;
            }
            .report-container {
                gap: 0;
            }
            @page {
                size: A4;
                margin: 6mm;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: right; width: 850px; margin-bottom: 10px;">
        <button onclick="window.print()" style="padding: 8px 16px; background-color: #4f46e5; color: white; border: none; border-radius: 4px; cursor: pointer;">
            <i class="fas fa-print"></i> Print Report
        </button>
    </div>
    
    <form class="editable-form" method="POST" action="/submit-perdium">
        <div class="report-container">
            <div class="page" id="page1">
                <header>
                    <div class="main-header-box">
                        <div class="header-top">
                            <img src="../images/afar-flag.png" alt="Afar Flag">
                            <div class="header-titles">
                                <img src="../images/bureau-logo.png" alt="Bureau Logo">
                                <p><strong>Qafar Agatih Rakaakayih Doolatak Qafiyaat Biiro</strong></p>
                                <p><strong>የአፋር ብሔራዊ ክልላዊ መንግስት ጤና ቢሮ</strong></p>
                                <p><strong>Afar National Regional State Health Bureau</strong></p>
                            </div>
                            <img src="../images/ethiopia-flag.png" alt="Ethiopia Flag">
                        </div>
                        <p class="header-main-title"><strong>Doolat Taamabeynih Assenti Esserih Cibta</strong></p>
<div class="header-bottom">
    <div style="text-align: right;">
       <div class="field-group">
        <label>Ixxima:</label>
        <div class="value" style="width: 250px;">
            <strong></strong>
        </div>
    </div>
    <div class="field-group">
        <label>Ayro:</label>
        <div class="value" style="width: 250px;">
            <strong><?php echo htmlspecialchars($formattedDate); ?> ዓ.ም</strong>
        </div>
    </div>
                            </div>
                        </div>
                    </div>
                </header>
                <section class="sub-header-fields">
                    <div class="field-group">
                        <label for="taama_abeeni_kood">Taama Abeeni Kood/ID:</label>
                        <div class="input-wrapper" style="width: 300px;">
                            <input type="text" id="taama_abeeni_kood" name="taama_abeeni_kood" value="<?php echo htmlspecialchars($transaction['employee_id']); ?>" style="font-weight: bold;">
                        </div>
                    </div>
                    <div class="field-group">
                        <label>Printed Date :</label>
                        <span><strong><?php echo date('m/d/Y'); ?></strong></span>
                    </div>
                </section>
                <main>
                  <div class="field-group">
                        <label for="Biiro Migaq">1. Biiro Migaq:</label>
                        <div class="input-wrapper">
                            <input type="text" id="Biiro Migaq" name="Biiro Migaq" value="Q.A.R.D Qaafiyat Biiro" style="font-weight: bold;">
                        </div>
                    </div>
                  <div class="field-group">
                    <label>2. Taamagoli:</label>
                      <div class="value">
                          <span><strong><?php echo htmlspecialchars($transaction['budget_owner_id']); ?></strong></span>
                          </div>
                          </div>
                          <div class="field-group">
    <label for="project_migaq_kee_koox">3. Project Migaq Kee Koox:</label>
    <div class="input-wrapper">
        <input type="text" id="project_migaq_kee_koox" name="project_migaq_kee_koox" value="<?php echo htmlspecialchars($transaction['budget_owner_id']); ?>" style="font-weight: bold;">
        </div>
</div>
<div class="field-group">
  <label for="taamabeyni_migaq_1">4. Taamabeyni Migaq:</label>
                        <div class="input-wrapper">
                            <input type="text" id="taamabeyni_migaq_1" name="taamabeyni_migaq_1" value="<?php echo htmlspecialchars($transaction['employee_name']); ?>" style="font-weight: bold;"></div></div>
                     <div class="field-group">
                        <label for="qasbikinnihgide">5. Qasbikinnihgide:</label>
                        <div class="input-wrapper">
                            <input type="text" id="qasbikinnihgide" name="qasbikinnihgide" value="<?php echo htmlspecialchars($transaction['employee_salary']); ?>" style="font-weight: bold;">
                        </div>
                    </div>
                    <div class="field-group">
                        <label for="safar_sababa">6. Safar Sababa:</label>
                        <div class="input-wrapper">
                            <input type="text" id="safar_sababa" name="safar_sababa" value="Madab Taama" style="font-weight: bold;">
                        </div>
                    </div>
                    <div style="display: flex; gap: 40px;">
                        <div class="field-group" style="flex:1;">
                            <label for="ugtuma_araca">7. Ugtuma Araca:</label>
                            <div class="input-wrapper">
                                <input type="text" id="ugtuma_araca" name="ugtuma_araca" value="ሰመራ" style="font-weight: bold;">
                            </div>
                        </div>
                        <div class="field-group" style="flex:1;">
                            <label for="eddecaboh_araca">Eddecaboh Araca:</label>
                            <div class="input-wrapper">
                                <input type="text" id="eddecaboh_araca" name="eddecaboh_araca" value="<?php echo htmlspecialchars($transaction['city_name_amharic']); ?>" style="font-weight: bold;">
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; gap: 40px;">
                        <div class="field-group" style="flex:1;">
                            <label for="safarat_sugahgide">8. Safarat Sugahgide:</label>
                            <div class="input-wrapper">
                                <input type="text" id="safarat_sugahgide" name="safarat_sugahgide" value="<?php echo htmlspecialchars($departure_date); ?>" style="font-weight: bold;">
                            </div>
                        </div>
                        <div class="field-group" style="flex:1;">
                            <label for="illa">Illa:</label>
                            <div class="input-wrapper">
                                <input type="text" id="illa" name="illa" value="<?php echo htmlspecialchars($arrival_date); ?>" style="font-weight: bold;">
                            </div>
                        </div>
                    </div>
                    <div style="margin-left: 20px; margin-top: 10px;">
                        <label style="font-weight: bold; font-size: 1.1em;">9. Aakamah Mekleenim:</label>
                        <div style="margin-left: 20px; margin-top: 5px;">
                            <div class="field-group">
                                <label for="ayrohaassenta">B. Ayrohaassenta:</label>
                                <div class="input-wrapper">
                                    <input type="text" id="ayrohaassenta" name="ayrohaassenta" value="<?php echo number_format($perdium_rate, 2); ?>" style="font-weight: bold;">
                                </div>
                            </div>
                            <div class="field-group">
                                <label for="sirag_le_lee">T. Sirag le lee:</label>
                                <div class="input-wrapper">
                                    <input type="text" id="sirag_le_lee" name="sirag_le_lee" value="0.00" style="font-weight: bold;">
                                </div>
                                </div>
                            <div style="display: flex; gap: 40px;">
                               <div class="field-group" style="flex:1;">
                                   <label for="foxomigi_1">S. Foxomigi:</label>
                                   <div class="input-wrapper">
                                       <input type="text" id="foxomigi_1" name="foxomigi_1" value="0.00" style="font-weight: bold;">
                                   </div>
                               </div>
                               <div class="field-group" style="flex:1;">
                                   <label for="sittatmekleenim">Sittatmekleenim:</label>
                                   <div class="input-wrapper">
                                       <input type="text" id="sittatmekleenim" name="sittatmekleenim" value="<?php echo number_format($total_amount, 2); ?> ብር" style="font-weight: bold;">
                                   </div>
                               </div>
                            </div>
                        </div>
                    </div>
                    <div style="display:flex; justify-content: space-between; align-items: flex-end; margin-top: 15px; border-top: 1px solid #ccc; padding-top: 8px;">
                        <div>
                            <p style="font-weight: bold; margin-bottom: 3px;">Koraq:</p>
                            <ul style="list-style-type: none; padding-left: 8px; margin:0;">
                                <li><strong>* Asq/um/gab/as/ko/ra/ma/ku/buxa</strong></li>
                                <li><strong>* Add/oxi/officer</strong></li>
                                <li><strong>* xa/iko/xi/officer</strong></li>
                            </ul>
                            <p style="border: 1px solid #000; padding: 4px 8px; margin-top: 6px; display: inline-block;"><strong>Xin/abb/Ku/Buxa</strong></p>
                        </div>
                        <p style="font-weight: bold;"><strong>Ninni Qaafiyatah Sittallih Taamitnay!</strong></p>
                    </div>
                </main>
            </div>
            <div class="page" id="page2">
                <header>
                     <div class="main-header-box">
                        <div class="header-top">
                            <img src="../images/afar-flag.png" alt="Afar Flag">
                            <div class="header-titles">
                                <img src="../images/bureau-logo.png" alt="Bureau Logo">
                                <p><strong>Qafar Agatih Rakaakayih Doolatak Qaafiyat Biiro</strong></p>
                                <p><strong>የአፋር ብሔራዊ ክልላዊ መንግስት ጤና ቢሮ</strong></p>
                                <p><strong>Afar National Regional State Health Bureau</strong></p>
                            </div>
                            <img src="../images/ethiopia-flag.png" alt="Ethiopia Flag">
                        </div>
                        <p class="header-main-title"><strong>Doolat Taamabeynih Assenti Esserih Cibta</strong></p>
                    </div>
                </header>
                 <section class="sub-header-fields">
                    <div class="field-group">
                        <label for="kood">Kood:</label>
                        <div class="input-wrapper">
                            <input type="text" id="kood" name="kood" value="<?php echo htmlspecialchars($transaction['employee_id']); ?>" style="font-weight: bold;">
                        </div>
                    </div>
                    <div class="field-group">
                        <label>Print Date:</label>
                        <span><strong><?php echo htmlspecialchars($formattedDate); ?> ዓ.ም</strong></span>
                    </div>
                </section>
                <main>
                    <div class="field-group">
                        <label for="biiro_migaq_2">Biiro Migaq:</label>
                        <div class="input-wrapper">
                            <input type="text" id="biiro_migaq_2" name="biiro_migaq_2" value="Q.A.R.D Qaafiyat Biiro" style="font-weight: bold;">
                        </div>
                    </div>
                    <div class="field-group">
    <label for="taamagoli_2">Taamagoli:</label>
    <div class="input-wrapper">
        <input type="text" id="taamagoli_2" name="taamagoli_2" value="<?php echo htmlspecialchars($transaction['employee_directorate']); ?>" style="font-weight: bold;">
    </div>
    </div>
    <div class="field-group">
                        <label for="taamabeyni_migaq_2">Taamabeyni Migaq:</label>
                        <div class="input-wrapper">
                            <input type="text" id="taamabeyni_migaq_2" name="taamabeyni_migaq_2" value="<?php echo htmlspecialchars($transaction['employee_name']); ?>" style="font-weight: bold;">
                        </div>
                    </div>
                    <div class="field-group">
                        <label for="qasbi_kinnihgide_2">Qasbi kinnihgide:</label>
                        <div class="input-wrapper">
                            <input type="text" id="qasbi_kinnihgide_2" name="qasbi_kinnihgide_2" value="<?php echo htmlspecialchars($transaction['employee_salary']); ?>" style="font-weight: bold;">
                        </div>
                    </div>
                    <div class="field-group">
                        <label for="merraytu">Merraytu:</label>
                        <div class="input-wrapper">
                            <input type="text" id="merraytu" name="merraytu" value="" style="font-weight: bold;">
                        </div>
                    </div>
                    <p style="font-weight: bold; margin-top: 8px;">Assenti Mekla:</p>
                    <table class="data-table">
                        <thead>   <tr>
                                <th rowspan="2">Lowwo</th>
                                <th colspan="3">Ugtuma Araca</th>
                                <th colspan="3">Wadba Araca</th>
                                <th colspan="2">Cisabgolikibima</th>
                                <th rowspan="2">Ayro Assenta Ximmo</th>
                            </tr>
                            <tr>
                                <th>Arac</th>
                                <th>Ayro</th>
                                <th>Saaqat</th>
                                <th>Kuraq 10%</th>
                                <th>Kada 25%</th>
                                <th>Diraar 25%</th>
                                <th>Ayro Manga</th>
                                <th>Ayro Assenta</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>1.</strong></td>
                                <td><input type="text" value="ሰመራ" style="font-weight: bold;"></td>
                                <td><input type="date" value="<?php echo htmlspecialchars($departure_date); ?>" style="font-weight: bold;"></td>
                                <td><input type="text" value="12:00 AM" style="font-weight: bold;"></td>
                                <td><input type="text" value="<?php echo number_format($perdium_rate * 0.1, 2); ?>" style="font-weight: bold;"></td>
                                <td><input type="text" value="<?php echo number_format($perdium_rate * 0.25, 2); ?>" style="font-weight: bold;"></td>
                                <td><input type="text" value="<?php echo number_format($perdium_rate * 0.25, 2); ?>" style="font-weight: bold;"></td>
                                <td><input type="text" value="1" style="font-weight: bold;"></td>
                                <td><input type="text" value="<?php echo number_format($A, 2); ?>" style="font-weight: bold;"></td>
                                <td><input type="text" value="<?php echo number_format($A, 2); ?>" style="font-weight: bold;"></td>
                            </tr>
                            <tr>
                                <td><strong>2.</strong></td>
                                <td><input type="text" value="<?php echo htmlspecialchars($transaction['city_name_amharic']); ?>" style="font-weight: bold;"></td>
                                <td><input type="date" value="" style="font-weight: bold;"></td>
                                <td><input type="text" value="" style="font-weight: bold;"></td>
                                <td><input type="text" value="" style="font-weight: bold;"></td>
                                <td><input type="text" value="" style="font-weight: bold;"></td>
                                <td><input type="text" value="" style="font-weight: bold;"></td>
                                <td><input type="text" value="<?php echo max(0, $total_days - 2); ?>" style="font-weight: bold;"></td>
                                <td><input type="text" value="<?php echo number_format($daily_per_diem, 2); ?>" style="font-weight: bold;"></td>
                                <td><input type="text" value="<?php echo number_format($stay_amount, 2); ?>" style="font-weight: bold;"></td>
                            </tr>
                            <tr>
                                <td><strong>3.</strong></td>
                                <td><input type="text" value="<?php echo htmlspecialchars($transaction['city_name_amharic']); ?>" style="font-weight: bold;"></td>
                                <td><input type="date" value="<?php echo htmlspecialchars($arrival_date); ?>" style="font-weight: bold;"></td>
                                <td><input type="text" value="6:00 PM" style="font-weight: bold;"></td>
                                <td><input type="text" value="<?php echo number_format($perdium_rate * 0.1, 2); ?>" style="font-weight: bold;"></td>
                                <td><input type="text" value="<?php echo number_format($perdium_rate * 0.25, 2); ?>" style="font-weight: bold;"></td>
                                <td><input type="text" value="0.00" style="font-weight: bold;"></td>
                                <td><input type="text" value="1" style="font-weight: bold;"></td>
                                <td><input type="text" value="<?php echo number_format($perdium_rate * 0.1 + $perdium_rate * 0.25, 2); ?>" style="font-weight: bold;"></td>
                                <td><input type="text" value="<?php echo number_format($perdium_rate * 0.1 + $perdium_rate * 0.25, 2); ?>" style="font-weight: bold;"></td>
                            </tr>
                            <tr class="row-total">
    <td colspan="9" style="text-align: right; padding-right: 10px;"><strong>Sittat</strong></td>
    <td><input type="text" value="<?php echo number_format($table_total_last_col, 2); ?>" style="font-weight: bold;"></td>
</tr>
                           
                        </tbody>
                    </table>

                    <p style="font-weight: bold; margin-top: 8px;">B. Taamabeyni Safaral Sugahgide Kee Ayroca (Kaqaada) Qadoosa Qarwali</p>
                     <table class="data-table">
                        <thead>
                            <tr>
                                <th colspan="2">Ayro</th>
                                <th>Taamaelle Xiina Araca</th>
                                <th colspan="3">Cisabgolikibima 40%</th>
                            </tr>
                            <tr>
                                <th>Qinboh Ayro</th>
                                <th>Ellecabo Ayro</th>
                                <th>Araca</th>
                                <th>Ayro Manga</th>
                                <th>Ayro Assenta</th>
                                <th>Ayro Assenta Ximmo</th>
                            </tr>
                        </thead>
                         <tbody>
                            <tr>
                                <td><input type="date" value="<?php echo htmlspecialchars($departure_date); ?>" style="font-weight: bold;"></td>
                                <td><input type="date" value="<?php echo htmlspecialchars($arrival_date); ?>" style="font-weight: bold;"></td>
                                <td><input type="text" value="<?php echo htmlspecialchars($transaction['city_name_amharic']); ?>" style="font-weight: bold;"></td>
                                <td><input type="number" value="<?php echo max(0, $total_days - 1); ?>" style="font-weight: bold;"></td>
                                 <td><input type="text" value="<?php echo number_format($perdium_rate * 0.4, 2); ?>" style="font-weight: bold;"></td>
                                 <td>
                                   <input type="text" value="<?php echo number_format(($perdium_rate * 0.4) * ($total_days - 1), 2); ?>" style="font-weight: bold;">
                                   </td>
                                   </tr>
                         </tbody>
                    </table>
                    
                    <div style="display: flex; justify-content: flex-end; margin-top: 8px;">
                        <div style="width: 40%;">
                            <div class="field-group">
                                <label for="a_kee_b_sittat">A Kee B Sittat</label>
                                <div class="input-wrapper">
                                    <input type="number" id="a_kee_b_sittat" name="a_kee_b_sittat" readonly value="<?php echo number_format($total_per_diem_amount + $total_travel_allowance, 2); ?>" style="font-weight: bold;">
                                </div>
                            </div>
                            <div class="field-group">
                                <label for="silaglelee">Silaglelee</label>
                                <div class="input-wrapper">
                                    <input type="number" id="silaglelee" name="silaglelee" readonly value="0.00" style="font-weight: bold;">
                                </div>
                            </div>
                            <div class="field-group">
                                <label for="foxomigi_2">Foxomigi</label>
                                <div class="input-wrapper">
                                    <input type="number" id="foxomigi_2" name="foxomigi_2" readonly value="0.00" style="font-weight: bold;">
                                </div>
                            </div>
<div class="field-group">
                                <label for="sittat_final">Sittat:</label>
                                <div class="input-wrapper">
                                    <input type="number" id="sittat_final" name="sittat_final" readonly value="<?php echo number_format($E - $food_rate, 2); ?>" style="font-weight: bold;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="signature-section" style="display: grid; grid-template-columns: 1fr 1fr; gap: 5px 15px;">
                        <div class="field-group"><label>Taama Gexanum Migaqa:</label><div class="input-wrapper"><input type="text" value="<?php echo htmlspecialchars($transaction['employee_name']); ?>"></div></div>
                        <div class="field-group"><label>Firma:</label><div class="input-wrapper"><input type="text"></div></div>
                        <div class="field-group"><label>Cisaab Massose Migaqa:</label><div class="input-wrapper"><input type="text"></div></div>
                        <div class="field-group"><label>Firma:</label><div class="input-wrapper"><input type="text"></div></div>
                        <div class="field-group"><label>Diggosse Migaqa:</label><div class="input-wrapper"><input type="text"></div></div>
                        <div class="field-group"><label>Firma:</label><div class="input-wrapper"><input type="text"></div></div>
                        <div class="field-group"><label>Fatiicisee Migaqa:</label><div class="input-wrapper"><input type="text"></div></div>
                        <div class="field-group"><label>Firma:</label><div class="input-wrapper"><input type="text"></div></div>
                    </div>

                    <div class="page-footer">
                        <p>Address: Semera, Ethiopia Tel: 033-666-00-22 Email: info@afarrhb.et</p>
                        <p>Developed by: ICT Directorate</p>
                    </div>
                </main>
            </div>
            
            <div class="form-footer no-print">
                <button type="submit">Submit Per Diem Form</button>
            </div>
        </div>
    </form>
</body>
</html>
