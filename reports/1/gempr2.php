<?php
// reports/perdium_report.php

// Go up one level to find the 'includes' directory
include '../includes/db.php';

// Get transaction ID from URL
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

// Calculate perdium breakdown
$perdium_rate = $transaction['perdium_rate'];
$total_days = $transaction['total_days'];

$A = ($perdium_rate * 0.1) + ($perdium_rate * 0.25) + ($perdium_rate * 0.25);
$B = $A * ($total_days - 2);
$C = $A;
$D = $perdium_rate * ($total_days - 1) * 0.4;
$total_amount = $A + $B + $C + $D;

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
    </style>
</head>
<body>
    <form class="editable-form" method="POST" action="/submit-perdium">
        <div class="report-container">
            <!-- ================================================================== -->
            <!--                             PAGE 1                                 -->
            <!-- ================================================================== -->
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
                                    <label for="ixxima">Ixxima:</label>
                                    <div class="input-wrapper" style="width: 250px;"><input type="text" id="ixxima" name="ixxima"></div>
                                </div>
                                <div class="field-group">
                                    <label for="ayro">Ayro:</label>
                                    <div class="input-wrapper" style="width: 250px;"><input type="date" id="ayro" name="ayro"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>

                <section class="sub-header-fields">
                    <div class="field-group">
                        <label for="taama_abeeni_kood">Taama Abeeni Kood/ID:</label>
                        <div class="input-wrapper" style="width: 300px;"><input type="text" id="taama_abeeni_kood" name="taama_abeeni_kood"></div>
                    </div>
                    <div class="field-group">
                        <label>Print Date:</label>
                        <span><?php echo date('m/d/Y'); ?></span>
                    </div>
                </section>

                <main>
                    <div class="field-group"><label for="biiro_migaq_1">1. Biiro Migaq:</label><div class="input-wrapper"><input type="text" id="biiro_migaq_1" name="biiro_migaq_1" value="Q.A.R.D Qaafiyat Biiro"></div></div>
                    <div class="field-group"><label for="taamagoli">2. Taamagoli:</label><div class="input-wrapper"><input type="text" id="taamagoli" name="taamagoli"></div></div>
                    <div class="field-group"><label for="project_migaq_kee_koox">3. Project Migaq Kee Koox:</label><div class="input-wrapper"><input type="text" id="project_migaq_kee_koox" name="project_migaq_kee_koox"></div></div>
                    <div class="field-group"><label for="taamabeyni_migaq_1">4. Taamabeyni Migaq:</label><div class="input-wrapper"><input type="text" id="taamabeyni_migaq_1" name="taamabeyni_migaq_1"></div></div>
                    <div class="field-group"><label for="qasbikinnihgide">5. Qasbikinnihgide:</label><div class="input-wrapper"><input type="text" id="qasbikinnihgide" name="qasbikinnihgide"></div></div>
                    <div class="field-group"><label for="safar_sababa">6. Safar Sababa:</label><div class="input-wrapper"><input type="text" id="safar_sababa" name="safar_sababa"></div></div>
                    
                    <div style="display: flex; gap: 40px;">
                        <div class="field-group" style="flex:1;"><label for="ugtuma_araca">7. Ugtuma Araca:</label><div class="input-wrapper"><input type="text" id="ugtuma_araca" name="ugtuma_araca"></div></div>
                        <div class="field-group" style="flex:1;"><label for="eddecaboh_araca">Eddecaboh Araca:</label><div class="input-wrapper"><input type="text" id="eddecaboh_araca" name="eddecaboh_araca"></div></div>
                    </div>
                    <div style="display: flex; gap: 40px;">
                        <div class="field-group" style="flex:1;"><label for="safarat_sugahgide">8. Safarat Sugahgide:</label><div class="input-wrapper"><input type="text" id="safarat_sugahgide" name="safarat_sugahgide"></div></div>
                        <div class="field-group" style="flex:1;"><label for="illa">Illa:</label><div class="input-wrapper"><input type="text" id="illa" name="illa"></div></div>
                    </div>
                    
                    <div style="margin-left: 20px; margin-top: 20px;">
                        <label style="font-weight: bold; font-size: 1.1em;">9. Aakamah Mekleenim:</label>
                        <div style="margin-left: 20px; margin-top: 10px;">
                            <div class="field-group"><label for="ayrohaassenta">B. Ayrohaassenta:</label><div class="input-wrapper"><input type="text" id="ayrohaassenta" name="ayrohaassenta"></div></div>
                            <div class="field-group"><label for="sirag_le_lee">T. Sirag le lee:</label><div class="input-wrapper"><input type="text" id="sirag_le_lee" name="sirag_le_lee"></div></div>
                            <div style="display: flex; gap: 40px;">
                               <div class="field-group" style="flex:1;"><label for="foxomigi_1">S. Foxomigi:</label><div class="input-wrapper"><input type="text" id="foxomigi_1" name="foxomigi_1"></div></div>
                               <div class="field-group" style="flex:1;"><label for="sittatmekleenim">Sittatmekleenim:</label><div class="input-wrapper"><input type="text" id="sittatmekleenim" name="sittatmekleenim"></div></div>
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

            <!-- ================================================================== -->
            <!--                             PAGE 2                                 -->
            <!-- ================================================================== -->
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
                        <div class="input-wrapper" style="width: 300px;"><input type="text" id="kood" name="kood"></div>
                    </div>
                    <div class="field-group">
                        <label>Print Date:</label>
                        <span><?php echo date('m/d/Y'); ?></span>
                    </div>
                </section>
                
                <main>
                    <div class="field-group"><label for="biiro_migaq_2">Biiro Migaq:</label><div class="input-wrapper"><input type="text" id="biiro_migaq_2" name="biiro_migaq_2" value="Q.A.R.D Qaafiyat Biiro"></div></div>
                    <div class="field-group"><label for="taamagoli_2">Taamagoli:</label><div class="input-wrapper"><input type="text" id="taamagoli_2" name="taamagoli_2"></div></div>
                    <div class="field-group"><label for="taamabeyni_migaq_2">Taamabeyni Migaq:</label><div class="input-wrapper"><input type="text" id="taamabeyni_migaq_2" name="taamabeyni_migaq_2"></div></div>
                    <div class="field-group"><label for="qasbi_kinnihgide_2">Qasbi kinnihgide:</label><div class="input-wrapper"><input type="text" id="qasbi_kinnihgide_2" name="qasbi_kinnihgide_2"></div></div>
                    <div class="field-group"><label for="merraytu">Merraytu:</label><div class="input-wrapper"><input type="text" id="merraytu" name="merraytu"></div></div>
                    
                    <p style="font-weight: bold; margin-top: 20px;">Assenti Mekla:</p>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th rowspan="2">Lowwo</th><th colspan="3">Ugtuma Araca</th><th colspan="3">Wadba Araca</th><th colspan="2">Cisabgolikibima</th><th rowspan="2">Ayro Assenta Ximmo</th>
                            </tr>
                            <tr>
                                <th>Arac</th><th>Ayro</th><th>Saaqat</th><th>Kuraq 10%</th><th>Kada 25%</th><th>Diraar 25%</th><th>Ayro Manga</th><th>Ayro Assenta</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Example for row 1 -->
                            <tr><td>1.</td><td><input type="text" name="mekla[0][arac]"></td><td><input type="date" name="mekla[0][ayro]"></td><td><input type="text" name="mekla[0][saaqat]"></td><td><input type="number" name="mekla[0][kuraq]"></td><td><input type="number" name="mekla[0][kada]"></td><td><input type="number" name="mekla[0][diraar]"></td><td><input type="number" name="mekla[0][ayro_manga]"></td><td><input type="number" name="mekla[0][ayro_assenta]"></td><td><input type="number" name="mekla[0][ximmo]" readonly></td></tr>
                            <tr><td>2.</td><td><input type="text" name="mekla[1][arac]"></td><td><input type="date" name="mekla[1][ayro]"></td><td><input type="text" name="mekla[1][saaqat]"></td><td><input type="number" name="mekla[1][kuraq]"></td><td><input type="number" name="mekla[1][kada]"></td><td><input type="number" name="mekla[1][diraar]"></td><td><input type="number" name="mekla[1][ayro_manga]"></td><td><input type="number" name="mekla[1][ayro_assenta]"></td><td><input type="number" name="mekla[1][ximmo]" readonly></td></tr>
                            <tr><td>3.</td><td><input type="text" name="mekla[2][arac]"></td><td><input type="date" name="mekla[2][ayro]"></td><td><input type="text" name="mekla[2][saaqat]"></td><td><input type="number" name="mekla[2][kuraq]"></td><td><input type="number" name="mekla[2][kada]"></td><td><input type="number" name="mekla[2][diraar]"></td><td><input type="number" name="mekla[2][ayro_manga]"></td><td><input type="number" name="mekla[2][ayro_assenta]"></td><td><input type="number" name="mekla[2][ximmo]" readonly></td></tr>
                            <tr class="row-total"><td colspan="9" style="text-align: right; padding-right: 10px;">Sittat</td><td><input type="number" name="mekla_total_ximmo" readonly></td></tr>
                        </tbody>
                    </table>

                    <p style="font-weight: bold; margin-top: 20px;">B. Taamabeyni Safaral Sugahgide Kee Ayroca (Kaqaada) Qadoosa Qarwali</p>
                     <table class="data-table">
                        <thead>
                            <tr><th colspan="2">Ayro</th><th>Taamaelle Xiina Araca</th><th colspan="3">Cisabgolikibima 40%</th></tr>
                            <tr><th>Qinboh Ayro</th><th>Ellecabo Ayro</th><th>Araca</th><th>Ayro Manga</th><th>Ayro Assenta</th><th>Ayro Assenta Ximmo</th></tr>
                        </thead>
                         <tbody>
                            <tr><td><input type="date" name="qarwali[0][qinboh_ayro]"></td><td><input type="date" name="qarwali[0][ellecabo_ayro]"></td><td><input type="text" name="qarwali[0][araca]"></td><td><input type="number" name="qarwali[0][ayro_manga]"></td><td><input type="number" name="qarwali[0][ayro_assenta]"></td><td><input type="number" name="qarwali[0][ximmo]" readonly></td></tr>
                         </tbody>
                    </table>
                    
                    <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
                        <div style="width: 40%;">
                            <div class="field-group"><label for="a_kee_b_sittat">A Kee B Sittat</label><div class="input-wrapper"><input type="number" id="a_kee_b_sittat" name="a_kee_b_sittat" readonly></div></div>
                            <div class="field-group"><label for="silaglelee">Silaglelee</label><div class="input-wrapper"><input type="number" id="silaglelee" name="silaglelee" readonly></div></div>
                            <div class="field-group"><label for="foxomigi_2">Foxomigi</label><div class="input-wrapper"><input type="number" id="foxomigi_2" name="foxomigi_2" readonly></div></div>
                            <div class="field-group"><label for="sittat_final">Sittat:</label><div class="input-wrapper"><input type="number" id="sittat_final" name="sittat_final" readonly></div></div>
                        </div>
                    </div>

                    <div class="signature-section">
                        <div class="field-group"><label for="taama_gexanum_migaqa">Taama Gexanum Migaqa:</label><div class="input-wrapper"><input type="text" id="taama_gexanum_migaqa" name="taama_gexanum_migaqa"></div><label for="taama_gexanum_firma">Firma:</label><div class="input-wrapper"><input type="text" id="taama_gexanum_firma" name="taama_gexanum_firma"></div></div>
                        <div class="field-group"><label for="cisaab_massose_migaqa">Cisaab Massose Migaqa:</label><div class="input-wrapper"><input type="text" id="cisaab_massose_migaqa" name="cisaab_massose_migaqa"></div><label for="cisaab_massose_firma">Firma:</label><div class="input-wrapper"><input type="text" id="cisaab_massose_firma" name="cisaab_massose_firma"></div></div>
                        <div class="field-group"><label for="diggosse_migaqa">Diggosse Migaqa:</label><div class="input-wrapper"><input type="text" id="diggosse_migaqa" name="diggosse_migaqa"></div><label for="diggosse_firma">Firma:</label><div class="input-wrapper"><input type="text" id="diggosse_firma" name="diggosse_firma"></div></div>
                        <div class="field-group"><label for="fatiicisee_migaqa">Fatiicisee Migaqa:</label><div class="input-wrapper"><input type="text" id="fatiicisee_migaqa" name="fatiicisee_migaqa"></div><label for="fatiicisee_firma">Firma:</label><div class="input-wrapper"><input type="text" id="fatiicisee_firma" name="fatiicisee_firma"></div></div>
                    </div>
                </main>
            </div>
            
            <div class="form-footer">
                <button type="submit">Submit Per Diem Form</button>
            </div>
        </div>
    </form>
</body>
</html>