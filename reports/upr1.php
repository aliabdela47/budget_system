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
            bo.p_koox As project_migaq_kee_koox,
            e.name AS emp_name,
            e.salary AS emp_salary,
            e.directorate AS employee_directorate,
            c.name_amharic AS city_name_amharic,
            c.name_english AS city_name_english,
            c.rate_low,
            c.rate_medium,
            c.rate_high,
            bc.name AS budget_code_name
        FROM perdium_transactions pt
        LEFT JOIN budget_owners bo ON pt.budget_owner_id = bo.id
        LEFT JOIN emp_list e ON pt.employee_id = e.id
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
$perdium_rate = $transaction['perdium_rate'] ?? 0;
$total_days = $transaction['total_days'] ?? 0;
$total_amount = $transaction['total_amount'] ?? 0;
$departure_date = $transaction['departure_date'] ?? '';
$arrival_date = $transaction['arrival_date'] ?? '';

$travel_rate = $perdium_rate * 0.1;
$food_rate = $perdium_rate * 0.25;
$lodging_rate = $perdium_rate * 0.25;

$daily_per_diem = $travel_rate + $food_rate + $lodging_rate;

// Correct stay days calculation: total days minus the first and last day
$stay_days = max(0, $total_days - 2);
$stay_amount = $stay_days * $daily_per_diem;

// Values for the table on page 2
$A = $daily_per_diem;
$stay_amount = $stay_days * $daily_per_diem;
$C = $travel_rate + $food_rate; // Sitat Meklenim

// Correctly calculate the total of the last column of the three rows
$table_total_last_col = $A + $stay_amount + $C;

// Calculate the travel allowance for the second table
$travel_allowance = $perdium_rate * 0.4;
$total_travel_allowance = max(0, $total_days - 1) * $travel_allowance;

// Calculate final totals for the signature section
$total_final_amount = $table_total_last_col + $total_travel_allowance;

// Helper function to safely output values
function safeOutput($value, $default = '') {
    if ($value === null) {
        return $default;
    }
    return htmlspecialchars($value);
}

// Helper function to format currency
function formatCurrency($value, $default = '0.00') {
    if ($value === null) {
        return $default;
    }
    return number_format((float)$value, 2);
}
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
        
        .page-break {
            page-break-after: always;
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
        
        .header-top img {
            height: 45px;
        }

        .header-titles {
            text-align: center;
        }
        .header-titles p {
            margin: 2px 0;
            font-weight: bold;
        }

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

        /* --- Shared Styles --- */
        .field-group {
            display: flex;
            align-items: baseline;
            margin-bottom: 12px;
        }
        .field-group label {
            white-space: nowrap;
            font-weight: bold;
            padding-right: 8px;
        }
        .field-group .value {
            border-bottom: 1px solid #000;
            width: 100%;
            font-weight: bold;
            padding: 0 5px;
        }
        
        .input-wrapper {
            border-bottom: 1px solid #000;
            width: 100%;
            padding: 0 5px;
        }
        
        .input-wrapper input {
            border: none;
            outline: none;
            width: 100%;
            background: transparent;
        }
        
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
            margin: 15px 0;
        }
        .data-table th, .data-table td {
            border: 1.5px solid #000;
            padding: 6px;
            text-align: center;
            vertical-align: middle;
        }
        .data-table th {
            font-weight: bold;
            background-color: #f0f0f0;
        }
        .data-table .row-total td {
            font-weight: bold;
        }

        @media print {
            body { background-color: #fff; padding: 0; }
            .report-container { gap: 0; }
            .page {
                box-shadow: none;
                border: none;
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: right; width: 850px; margin-bottom: 10px;">
        <button onclick="window.print()" style="padding: 8px 16px; background-color: #4f46e5; color: white; border: none; border-radius: 4px; cursor: pointer;">
            Print Report
        </button>
    </div>

    <div class="report-container">
        <div class="page page-break" id="page1">
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
                    <div class="header-bottom">
                        <div>
                            <div class="field-group">
                                <label>Ixxima:</label>
                                <div class="value" style="width: 250px;">
                                    <strong></strong>
                                </div>
                            </div>
                            <div class="field-group">
                                <label>Ayro:</label>
                                <div class="value" style="width: 250px;">
                                    <strong><?php echo safeOutput($formattedDate); ?> ዓ.ም</strong>
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
                        <input type="text" id="taama_abeeni_kood" name="taama_abeeni_kood" value="<?php echo safeOutput($transaction['employee_id']); ?>" style="font-weight: bold;">
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
                        <span style="font-weight: bold; border-bottom: 1.5px solid #000; display: block; padding: 1px 3px;"><?php echo safeOutput($transaction['owner_name']); ?></span>
                    </div>
                </div>
                <div class="field-group">
                    <label for="project_migaq_kee_koox">3. Project Migaq Kee Koox:</label>
                    <div class="input-wrapper">
                        <input type="text" id="project_migaq_kee_koox" name="project_migaq_kee_koox" value="<?php echo safeOutput($transaction['project_migaq_kee_koox']); ?>" style="font-weight: bold;">
                    </div>
                </div>
                <div class="field-group">
                    <label for="taamabeyni_migaq_1">4. Taamabeyni Migaq:</label>
                    <div class="input-wrapper">
                        <input type="text" id="taamabeyni_migaq_1" name="taamabeyni_migaq_1" value="<?php echo safeOutput($transaction['emp_name']); ?>" style="font-weight: bold;">
                    </div>
                </div>
                <div class="field-group">
                    <label for="qasbikinnihgide">5. Qasbikinnihgide:</label>
                    <div class="input-wrapper">
                        <input type="text" id="qasbikinnihgide" name="qasbikinnihgide" value="<?php echo formatCurrency($transaction['emp_salary']); ?>" style="font-weight: bold;">
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
                            <input type="text" id="eddecaboh_araca" name="eddecaboh_araca" value="<?php echo safeOutput($transaction['city_name_amharic']); ?>" style="font-weight: bold;">
                        </div>
                    </div>
                </div>
                <div style="display: flex; gap: 40px;">
                    <div class="field-group" style="flex:1;">
                        <label for="safarat_sugahgide">8. Safarat Sugahgide:</label>
                        <div class="input-wrapper">
                            <input type="text" id="safarat_sugahgide" name="safarat_sugahgide" value="<?php echo safeOutput($departure_date); ?>" style="font-weight: bold;">
                        </div>
                    </div>
                    <div class="field-group" style="flex:1;">
                        <label for="illa">Illa:</label>
                        <div class="input-wrapper">
                            <input type="text" id="illa" name="illa" value="<?php echo safeOutput($arrival_date); ?>" style="font-weight: bold;">
                        </div>
                    </div>
                </div>
                <div style="margin-left: 20px; margin-top: 10px;">
                    <label style="font-weight: bold; font-size: 1.1em;">9. Aakamah Mekleenim:</label>
                    <div style="margin-left: 20px; margin-top: 5px;">
                        <div class="field-group">
                            <label for="ayrohaassenta">B. Ayrohaassenta:</label>
                            <div class="input-wrapper">
                                <input type="text" id="ayrohaassenta" name="ayrohaassenta" value="<?php echo formatCurrency($perdium_rate); ?>" style="font-weight: bold;">
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
                                    <input type="text" id="sittatmekleenim" name="sittatmekleenim" value="<?php echo formatCurrency($total_amount); ?> ብር" style="font-weight: bold;">
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
                        <input type="text" id="kood" name="kood" value="<?php echo safeOutput($transaction['employee_id']); ?>" style="font-weight: bold;">
                    </div>
                </div>
                <div class="field-group">
                    <label>Print Date:</label>
                    <span><strong><?php echo safeOutput($formattedDate); ?> ዓ.ም</strong></span>
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
                        <input type="text" id="taamagoli_2" name="taamagoli_2" value="<?php echo safeOutput($transaction['owner_name']); ?>" style="font-weight: bold;">
                    </div>
                </div>
                <div class="field-group">
                    <label for="taamabeyni_migaq_2">Taamabeyni Migaq:</label>
                    <div class="input-wrapper">
                        <input type="text" id="taamabeyni_migaq_2" name="taamabeyni_migaq_2" value="<?php echo safeOutput($transaction['emp_name']); ?>" style="font-weight: bold;">
                    </div>
                </div>
                <div class="field-group">
                    <label for="qasbi_kinnihgide_2">Qasbi kinnihgide:</label>
                    <div class="input-wrapper">
                        <input type="text" id="qasbi_kinnihgide_2" name="qasbi_kinnihgide_2" value="<?php echo formatCurrency($transaction['emp_salary']); ?>" style="font-weight: bold;">
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
                            <td><strong>1.</strong></td>
                            <td><input type="text" value="ሰመራ" style="font-weight: bold;"></td>
                            <td><input type="date" value="<?php echo safeOutput($departure_date); ?>" style="font-weight: bold;"></td>
                            <td><input type="text" value="12:00 AM" style="font-weight: bold;"></td>
                            <td><input type="text" value="<?php echo formatCurrency($perdium_rate * 0.1); ?>" style="font-weight: bold;"></td>
                            <td><input type="text" value="<?php echo formatCurrency($perdium_rate * 0.25); ?>" style="font-weight: bold;"></td>
                            <td><input type="text" value="<?php echo formatCurrency($perdium_rate * 0.25); ?>" style="font-weight: bold;"></td>
                            <td><input type="text" value="1" style="font-weight: bold;"></td>
                            <td><input type="text" value="<?php echo formatCurrency($A); ?>" style="font-weight: bold;"></td>
                            <td><input type="text" value="<?php echo formatCurrency($A); ?>" style="font-weight: bold;"></td>
                        </tr>
                        <tr>
                            <td><strong>2.</strong></td>
                            <td><input type="text" value="<?php echo safeOutput($transaction['city_name_amharic']); ?>" style="font-weight: bold;"></td>
                            <td><input type="date" value="" style="font-weight: bold;"></td>
                            <td><input type="text" value="" style="font-weight: bold;"></td>
                            <td><input type="text" value="" style="font-weight: bold;"></td>
                            <td><input type="text" value="" style="font-weight: bold;"></td>
                            <td><input type="text" value="" style="font-weight: bold;"></td>
                            <td><input type="text" value="<?php echo max(0, $total_days - 2); ?>" style="font-weight: bold;"></td>
                            <td><input type="text" value="<?php echo formatCurrency($daily_per_diem); ?>" style="font-weight: bold;"></td>
                            <td><input type="text" value="<?php echo formatCurrency($stay_amount); ?>" style="font-weight: bold;"></td>
                        </tr>
                        <tr>
                            <td><strong>3.</strong></td>
                            <td><input type="text" value="<?php echo safeOutput($transaction['city_name_amharic']); ?>" style="font-weight: bold;"></td>
                            <td><input type="date" value="<?php echo safeOutput($arrival_date); ?>" style="font-weight: bold;"></td>
                            <td><input type="text" value="6:00 PM" style="font-weight: bold;"></td>
                            <td><input type="text" value="<?php echo formatCurrency($perdium_rate * 0.1); ?>" style="font-weight: bold;"></td>
                            <td><input type="text" value="<?php echo formatCurrency($perdium_rate * 0.25); ?>" style="font-weight: bold;"></td>
                            <td><input type="text" value="0.00" style="font-weight: bold;"></td>
                            <td><input type="text" value="1" style="font-weight: bold;"></td>
                            <td><input type="text" value="<?php echo formatCurrency($perdium_rate * 0.1 + $perdium_rate * 0.25); ?>" style="font-weight: bold;"></td>
                            <td><input type="text" value="<?php echo formatCurrency($perdium_rate * 0.1 + $perdium_rate * 0.25); ?>" style="font-weight: bold;"></td>
                        </tr>
                        <tr class="row-total">
                            <td colspan="9" style="text-align: right; padding-right: 10px;"><strong>Sittat</strong></td>
                            <td><input type="text" value="<?php echo formatCurrency($table_total_last_col); ?>" style="font-weight: bold;"></td>
                        </tr>
                    </tbody>
                </table>
                <div class="field-group">
                    <label for="sittat_final">Sittat:</label>
                    <div class="input-wrapper">
                        <span id="sittat_final" style="font-weight: bold; border-bottom: 1.5px solid #000; display: block; padding: 1px 3px;">
                            <?php echo formatCurrency($table_total_last_col + $total_travel_allowance); ?>
                        </span>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>