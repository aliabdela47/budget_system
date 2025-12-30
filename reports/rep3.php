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
$travel_allowance = $perdium_rate * 0.4;
$total_travel_allowance = max(0, $total_days - 1) * $travel_allowance;
$total_per_diem_amount = ($daily_per_diem * $total_days) + $total_travel_allowance;

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
            padding: 20px 0;
            line-height: 1.6;
        }

        .report-container {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .page {
            background-color: #fff;
            border: 1px solid #ccc;
            padding: 30px;
            width: 850px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            box-sizing: border-box;
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
            padding: 2px 5px;
            width: 100%;
            box-sizing: border-box;
        }
        .editable-form input:focus {
            outline: none;
            background-color: #fdf5e6;
        }
        
        .field-group {
            display: flex;
            align-items: baseline;
            margin-bottom: 12px;
            gap: 8px;
        }
        .field-group label {
            white-space: nowrap;
            font-weight: bold;
        }
        .field-group .input-wrapper {
            width: 100%;
        }

        /* --- Reusable Header --- */
        .main-header-box {
            border: 2px solid #000;
            padding: 15px;
        }
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header-top img { height: 45px; }
        .header-titles { text-align: center; }
        .header-titles p { margin: 2px 0; font-weight: bold; }
        .header-main-title {
            text-align: center;
            font-weight: bold;
            font-size: 1.3em;
            margin-top: 15px;
            text-decoration: underline;
        }
        .header-bottom {
            display: flex;
            justify-content: flex-end;
            margin-top: 10px;
        }

        /* --- Sub-header --- */
        .sub-header-fields {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
        }

        /* --- Table Styles --- */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em;
        }
        .data-table th, .data-table td {
            border: 1.5px solid #000;
            padding: 2px;
            text-align: center;
            vertical-align: middle;
        }
        .data-table th { font-weight: bold; padding: 6px; }
        .data-table input {
            border: none;
            width: 100%;
            height: 100%;
            padding: 6px;
            text-align: center;
        }
        .data-table .row-total td { font-weight: bold; }

        /* --- Signature Styles --- */
        .signature-section { margin-top: 25px; }
        
        /* --- Footer/Submit --- */
        .form-footer {
            margin-top: 40px;
            text-align: center;
        }
        .form-footer button {
            background-color: #4f46e5;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
        }
        .form-footer button:hover {
            background-color: #4338ca;
        }

        @media print {
            .no-print {
                display: none;
            }
            .page {
                box-shadow: none;
                border: 1px solid #000;
                margin: 0;
            }
            .report-container {
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: right; width: 850px; margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background-color: #4f46e5; color: white; border: none; border-radius: 5px; cursor: pointer;">
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
                                <p>Qafar Agatih Rakaakayih Doolatak Qafiyaat Biiro</p>
                                <p>የአፋር ብሔራዊ ክልላዊ መንግስት ጤና ቢሮ</p>
                                <p>Afar National Regional State Health Bureau</p>
                            </div>
                            <img src="../images/ethiopia-flag.png" alt="Ethiopia Flag">
                        </div>
                        <p class="header-main-title">Doolat Taamabeynih Assenti Esserih Cibta</p>
                        <div class="header-bottom">
                            <div>
                               <div class="field-group">
                                <label>Ixxima:</label>
                                <div class="value" style="width: 250px;">
                                    <?php echo htmlspecialchars($transaction['et_month']); ?>
                                </div>
                            </div>
                            <div class="field-group">
                                <label>Ayro:</label>
                                <div class="value" style="width: 250px;">
                                    <?php echo htmlspecialchars($formattedDate); ?>
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
                            <input type="text" id="taama_abeeni_kood" name="taama_abeeni_kood" value="<?php echo htmlspecialchars($transaction['employee_id']); ?>">
                        </div>
                    </div>
                    <div class="field-group">
                        <label>Printed Date :</label>
                        <span><?php echo date('m/d/Y'); ?></span>
                    </div>
                </section>

                <main>
                  <div class="field-group">
                        <label for="Biiro Migaq">1. Biiro Migaq:</label>
                        <div class="input-wrapper">
                            <input type="text" id="Biiro Migaq" name="Biiro Migaq" value="Q.A.R.D Qaafiyat Biiro">
                        </div>
                    </div>
                  
                  <div class="field-group">
                    <label>2. Taamagoli:</label>
                      <div class="value">
                          <span><?php echo htmlspecialchars($transaction['owner_name']); ?> - <?php echo htmlspecialchars($transaction['owner_code']); ?></span>
                      </div>
                  </div>
                   
                    <div class="field-group">
                        <label for="project_migaq_kee_koox">3. Project Migaq Kee Koox:</label>
                        <div class="input-wrapper">
                            <input type="text" id="project_migaq_kee_koox" name="project_migaq_kee_koox" value="<?php echo htmlspecialchars($transaction['budget_code_name']); ?>">
                        </div>
                    </div>
                    
                    <div class="field-group">
                        <label for="taamabeyni_migaq_1">4. Taamabeyni Migaq:</label>
                        <div class="input-wrapper">
                            <input type="text" id="taamabeyni_migaq_1" name="taamabeyni_migaq_1" value="<?php echo htmlspecialchars($transaction['employee_name']); ?>">
                        </div>
                    </div>
                     
                    <div class="field-group">
                        <label for="qasbikinnihgide">5. Qasbikinnihgide:</label>
                        <div class="input-wrapper">
                            <input type="text" id="qasbikinnihgide" name="qasbikinnihgide" value="<?php echo htmlspecialchars($transaction['employee_salary']); ?>">
                        </div>
                    </div>
                    
                    <div class="field-group">
                        <label for="safar_sababa">6. Safar Sababa:</label>
                        <div class="input-wrapper">
                            <input type="text" id="safar_sababa" name="safar_sababa" value="Madab Taama">
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 40px;">
                        <div class="field-group" style="flex:1;">
                            <label for="ugtuma_araca">7. Ugtuma Araca:</label>
                            <div class="input-wrapper">
                                <input type="text" id="ugtuma_araca" name="ugtuma_araca" value="ሰመራ">
                            </div>
                        </div>
                        <div class="field-group" style="flex:1;">
                            <label for="eddecaboh_araca">Eddecaboh Araca:</label>
                            <div class="input-wrapper">
                                <input type="text" id="eddecaboh_araca" name="eddecaboh_araca" value="<?php echo htmlspecialchars($transaction['city_name_amharic']); ?>">
                            </div>
                        </div>
                    </div>
                     
                    <div style="display: flex; gap: 40px;">
                        <div class="field-group" style="flex:1;">
                            <label for="safarat_sugahgide">8. Safarat Sugahgide:</label>
                            <div class="input-wrapper">
                                <input type="text" id="safarat_sugahgide" name="safarat_sugahgide" value="<?php echo htmlspecialchars($transaction['total_days']); ?>">
                            </div>
                        </div>
                        <div class="field-group" style="flex:1;">
                            <label for="illa">Illa:</label>
                            <div class="input-wrapper">
                                <input type="text" id="illa" name="illa">
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-left: 20px; margin-top: 20px;">
                        <label style="font-weight: bold; font-size: 1.1em;">9. Aakamah Mekleenim:</label>
                        <div style="margin-left: 20px; margin-top: 10px;">
                            <div class="field-group">
                                <label for="ayrohaassenta">B. Ayrohaassenta:</label>
                                <div class="input-wrapper">
                                    <input type="text" id="ayrohaassenta" name="ayrohaassenta" value="<?php echo number_format($perdium_rate, 2); ?>">
                                </div>
                            </div>
                               
                            <div class="field-group">
                                <label for="sirag_le_lee">T. Sirag le lee:</label>
                                <div class="input-wrapper">
                                    <input type="text" id="sirag_le_lee" name="sirag_le_lee" value="0.00">
                                </div>
                            </div>
                                 
                            <div style="display: flex; gap: 40px;">
                               <div class="field-group" style="flex:1;">
                                   <label for="foxomigi_1">S. Foxomigi:</label>
                                   <div class="input-wrapper">
                                       <input type="text" id="foxomigi_1" name="foxomigi_1" value="0.00">
                                   </div>
                               </div>
                               <div class="field-group" style="flex:1;">
                                   <label for="sittatmekleenim">Sittatmekleenim:</label>
                                   <div class="input-wrapper">
                                       <input type="text" id="sittatmekleenim" name="sittatmekleenim" value="<?php echo number_format($total_amount, 2); ?>">
                                   </div>
                               </div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display:flex; justify-content: space-between; align-items: flex-end; margin-top: 40px; border-top: 1px solid #ccc; padding-top: 20px;">
                        <div>
                            <p style="font-weight: bold; margin-bottom: 10px;">Koraq:</p>
                            <ul style="list-style-type: none; padding-left: 10px; margin:0;">
                                <li>* Asq/um/gab/as/ko/ra/ma/ku/buxa</li>
                                <li>* Add/oxi/officer</li>
                                <li>* xa/iko/xi/officer</li>
                            </ul>
                            <p style="border: 1px solid #000; padding: 5px 10px; margin-top: 10px; display: inline-block;"><strong>Xin/abb/Ku/Buxa</strong></p>
                        </div>
                        <p style="font-weight: bold;">Ninni Qaafiyatah Sittallih Taamitnay!</p>
                    </div>
                </main>
            </div>

            <div class="page" id="page2">
                <header>
                     <div class="main-header-box">
                        <div class="header-top">
                            <img src="../images/afar-flag.png" alt="Afar Flag">
                            <div class="header-titles">
                                <p>Qafar Agatih Rakaakayih Doolatak Qafiyaat Biiro</p>
                                <p>የአፋር ብሔራዊ ክልላዊ መንግስት ጤና ቢሮ</p>
                                <p>Afar National Regional State Health Bureau</p>
                            </div>
                            <img src="../images/ethiopia-flag.png" alt="Ethiopia Flag">
                        </div>
                        <p class="header-main-title">Doolat Taamabeynih Assenti Esserih Cibta</p>
                    </div>
                </header>
                 <section class="sub-header-fields">
                    <div class="field-group">
                        <label for="kood">Kood:</label>
                        <div class="input-wrapper">
                            <input type="text" id="kood" name="kood" value="<?php echo htmlspecialchars($transaction['employee_id']); ?>">
                        </div>
                    </div>
                    <div class="field-group">
                        <label>Print Date:</label>
                        <span><?php echo htmlspecialchars($formattedDate); ?></span>
                    </div>
                </section>
                
                <main>
                    <div class="field-group">
                        <label for="biiro_migaq_2">Biiro Migaq:</label>
                        <div class="input-wrapper">
                            <input type="text" id="biiro_migaq_2" name="biiro_migaq_2" value="<?php echo htmlspecialchars($transaction['owner_name']); ?>">
                        </div>
                    </div>
                    <div class="field-group">
                        <label for="taamagoli_2">Taamagoli:</label>
                        <div class="input-wrapper">
                            <input type="text" id="taamagoli_2" name="taamagoli_2" value="<?php echo htmlspecialchars($transaction['employee_directorate']); ?>">
                        </div>
                    </div>
                    <div class="field-group">
                        <label for="taamabeyni_migaq_2">Taamabeyni Migaq:</label>
                        <div class="input-wrapper">
                            <input type="text" id="taamabeyni_migaq_2" name="taamabeyni_migaq_2" value="<?php echo htmlspecialchars($transaction['employee_name']); ?>">
                        </div>
                    </div>
                    <div class="field-group">
                        <label for="qasbi_kinnihgide_2">Qasbi kinnihgide:</label>
                        <div class="input-wrapper">
                            <input type="text" id="qasbi_kinnihgide_2" name="qasbi_kinnihgide_2" value="<?php echo htmlspecialchars($transaction['employee_salary']); ?>">
                        </div>
                    </div>
                    <div class="field-group">
                        <label for="merraytu">Merraytu:</label>
                        <div class="input-wrapper">
                            <input type="text" id="merraytu" name="merraytu" value="">
                        </div>
                    </div>
                    
                    <p style="font-weight: bold; margin-top: 20px;">Assenti Mekla:</p>
                    <table class="data-table">
                        <thead>
                            <tr>
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
                                <td>1.</td>
                                <td><input type="text" value="ሰመራ"></td>
                                <td><input type="date" value="<?php echo htmlspecialchars($departure_date); ?>"></td>
                                <td><input type="text" value="2:00 AM"></td>
                                <td><input type="text" value="<?php echo number_format($perdium_rate * 0.1, 2); ?>"></td>
                                <td><input type="text" value="<?php echo number_format($perdium_rate * 0.25, 2); ?>"></td>
                                <td><input type="text" value="<?php echo number_format($perdium_rate * 0.25, 2); ?>"></td>
                                <td><input type="text" value="1"></td>
                                <td><input type="text" value="<?php echo number_format($daily_per_diem, 2); ?>"></td>
                                <td><input type="text" value="<?php echo number_format($daily_per_diem, 2); ?>"></td>
                            </tr>
                            <tr>
                                <td>2.</td>
                                <td><input type="text"></td>
                                <td><input type="date"></td>
                                <td><input type="text"></td>
                                <td><input type="text"></td>
                                <td><input type="text"></td>
                                <td><input type="text"></td>
                                <td><input type="text" value="<?php echo max(0, $total_days - 2); ?>"></td>
                                <td><input type="text" value="<?php echo number_format($daily_per_diem, 2); ?>"></td>
                                <td><input type="text" value="<?php echo number_format($stay_amount, 2); ?>"></td>
                            </tr>
                            <tr>
                                <td>3.</td>
                                <td><input type="text"></td>
                                <td><input type="date"></td>
                                <td><input type="text" value="6:00 PM"></td>
                                <td><input type="text" value="<?php echo number_format($perdium_rate * 0.1, 2); ?>"></td>
                                <td><input type="text" value="<?php echo number_format($perdium_rate * 0.25, 2); ?>"></td>
                                <td><input type="text" value="<?php echo number_format($perdium_rate * 0.25, 2); ?>"></td>
                                <td><input type="text" value="1"></td>
                                <td><input type="text" value="<?php echo number_format($daily_per_diem, 2); ?>"></td>
                                <td><input type="text" value="<?php echo number_format($daily_per_diem, 2); ?>"></td>
                            </tr>
                            <tr class="row-total">
                                <td colspan="8" style="text-align: right; padding-right: 10px;">Sittat</td>
                                <td><input type="text" value="<?php echo number_format($A + $B + $C, 2); ?>"></td>
                            </tr>
                        </tbody>
                    </table>

                    <p style="font-weight: bold; margin-top: 20px;">B. Taamabeyni Safaral Sugahgide Kee Ayroca (Kaqaada) Qadoosa Qarwali</p>
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
                                <td><input type="date" value="<?php echo htmlspecialchars($departure_date); ?>"></td>
                                <td><input type="date" value="<?php echo htmlspecialchars($arrival_date); ?>"></td>
                                <td><input type="text" value="<?php echo htmlspecialchars($transaction['city_name_amharic']); ?>"></td>
                                <td><input type="number" value="<?php echo max(0, $total_days - 1); ?>"></td>
                                <td><input type="number" value="<?php echo number_format($perdium_rate * 0.4, 2); ?>"></td>
                                <td><input type="number" readonly value="<?php echo number_format($total_travel_allowance, 2); ?>"></td>
                            </tr>
                         </tbody>
                    </table>
                    
                    <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
                        <div style="width: 40%;">
                            <div class="field-group">
                                <label for="a_kee_b_sittat">A Kee B Sittat</label>
                                <div class="input-wrapper">
                                    <input type="number" id="a_kee_b_sittat" name="a_kee_b_sittat" readonly value="<?php echo number_format($total_per_diem_amount, 2); ?>">
                                </div>
                            </div>
                            <div class="field-group">
                                <label for="silaglelee">Silaglelee</label>
                                <div class="input-wrapper">
                                    <input type="number" id="silaglelee" name="silaglelee" readonly value="0.00">
                                </div>
                            </div>
                            <div class="field-group">
                                <label for="foxomigi_2">Foxomigi</label>
                                <div class="input-wrapper">
                                    <input type="number" id="foxomigi_2" name="foxomigi_2" readonly value="0.00">
                                </div>
                            </div>
                            <div class="field-group">
                                <label for="sittat_final">Sittat:</label>
                                <div class="input-wrapper">
                                    <input type="number" id="sittat_final" name="sittat_final" readonly value="<?php echo number_format($total_per_diem_amount, 2); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="signature-section">
                        <div class="field-group"><label for="taama_gexanum_migaqa">Taama Gexanum Migaqa:</label><div class="input-wrapper"><input type="text" id="taama_gexanum_migaqa" name="taama_gexanum_migaqa" value="<?php echo htmlspecialchars($transaction['employee_name']); ?>"></div><label for="taama_gexanum_firma">Firma:</label><div class="input-wrapper"><input type="text" id="taama_gexanum_firma" name="taama_gexanum_firma"></div></div>
                        <div class="field-group"><label for="cisaab_massose_migaqa">Cisaab Massose Migaqa:</label><div class="input-wrapper"><input type="text" id="cisaab_massose_migaqa" name="cisaab_massose_migaqa"></div><label for="cisaab_massose_firma">Firma:</label><div class="input-wrapper"><input type="text" id="cisaab_massose_firma" name="cisaab_massose_firma"></div></div>
                        <div class="field-group"><label for="diggosse_migaqa">Diggosse Migaqa:</label><div class="input-wrapper"><input type="text" id="diggosse_migaqa" name="diggosse_migaqa"></div><label for="diggosse_firma">Firma:</label><div class="input-wrapper"><input type="text" id="diggosse_firma" name="diggosse_firma"></div></div>
                        <div class="field-group"><label for="fatiicisee_migaqa">Fatiicisee Migaqa:</label><div class="input-wrapper"><input type="text" id="fatiicisee_migaqa" name="fatiicisee_migaqa"></div><label for="fatiicisee_firma">Firma:</label><div class="input-wrapper"><input type="text" id="fatiicisee_firma" name="fatiicisee_firma"></div></div>
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
