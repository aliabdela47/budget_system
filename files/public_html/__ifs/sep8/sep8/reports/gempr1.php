<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Per Diem Report - Doolat Taamabeynih Assenti Esserih Cibta</title>
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
            padding: 6px;
            text-align: center;
            vertical-align: middle;
        }
        .data-table th {
            font-weight: bold;
        }
        .data-table .row-total td {
            font-weight: bold;
        }

        /* --- Signature Styles --- */
        .signature-section {
             margin-top: 25px;
        }
        .signature-section .field-group {
            margin-bottom: 20px;
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
        }
    </style>
</head>
<body onload="window.print()">

    <div class="report-container">
        <!-- ================================================================== -->
        <!--                             PAGE 1                                 -->
        <!-- ================================================================== -->
        <div class="page page-break" id="page1">
            <header>
                <div class="main-header-box">
                    <div class="header-top">
                        <!-- NOTE: Replace '#' with the correct path to your flag images -->
                        <img src="../images/afar-flag.png" alt="Afar Flag">
                        <div class="header-titles">
                            <p>Qafar Agatih Rakaakayih Doolatak Qafiyaat Biiro</p>
                            <p>የአፋር ብሔራዊ ክልላዊ መንግስት ጤና ቢሮ</p>
                            <p>Afar National Regional State Health Bureau</p>
                        </div>
                        <!-- NOTE: Replace '#' with the correct path to your flag images -->
                        <img src="../images/ethiopia-flag.png" alt="Ethiopia Flag">
                    </div>
                    <p class="header-main-title">Doolat Taamabeynih Assenti Esserih Cibta</p>
                    <div class="header-bottom">
                        <div>
                            <div class="field-group">
                                <label>Ixxima:</label>
                                <!-- PHP Placeholder -->
                                <div class="value" style="width: 250px;">&nbsp;</div>
                            </div>
                            <div class="field-group">
                                <label>Ayro:</label>
                                <!-- PHP Placeholder -->
                                <div class="value" style="width: 250px;">&nbsp;</div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <section class="sub-header-fields">
                <div class="field-group">
                    <label>Taama Abeeni Kood/ID:</label>
                    <!-- PHP Placeholder -->
                    <div class="value" style="width: 300px;"></div>
                </div>
                <div class="field-group">
                    <label>Print Date:</label>
                    <div class="value" style="border:none;"><?php echo date('m/d/Y'); ?></div>
                </div>
            </section>

            <main>
                <div class="field-group"><label>1. Biiro Migaq:</label><div class="value">Q.A.R.D Qaafiyat Biiro</div></div>
                <div class="field-group"><label>2. Taamagoli:</label><div class="value"></div></div>
                <div class="field-group"><label>3. Project Migaq Kee Koox:</label><div class="value"></div></div>
                <div class="field-group"><label>4. Taamabeyni Migaq:</label><div class="value"></div></div>
                <div class="field-group"><label>5. Qasbikinnihgide:</label><div class="value"></div></div>
                <div class="field-group"><label>6. Safar Sababa:</label><div class="value"></div></div>
                
                <div style="display: flex; gap: 40px;">
                    <div class="field-group" style="flex:1;"><label>7. Ugtuma Araca:</label><div class="value"></div></div>
                    <div class="field-group" style="flex:1;"><label>Eddecaboh Araca:</label><div class="value"></div></div>
                </div>
                <div style="display: flex; gap: 40px;">
                    <div class="field-group" style="flex:1;"><label>8. Safarat Sugahgide:</label><div class="value"></div></div>
                    <div class="field-group" style="flex:1;"><label>Illa:</label><div class="value"></div></div>
                </div>
                
                <div style="margin-left: 20px; margin-top: 20px;">
                    <label style="font-weight: bold; font-size: 1.1em;">9. Aakamah Mekleenim:</label>
                    <div style="margin-left: 20px; margin-top: 10px;">
                        <div class="field-group"><label>B. Ayrohaassenta:</label><div class="value"></div></div>
                        <div class="field-group"><label>T. Sirag le lee:</label><div class="value"></div></div>
                        <div style="display: flex; gap: 40px;">
                           <div class="field-group" style="flex:1;"><label>S. Foxomigi:</label><div class="value"></div></div>
                           <div class="field-group" style="flex:1;"><label>Sittatmekleenim:</label><div class="value"></div></div>
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
                         <!-- NOTE: Replace '#' with the correct path to your flag images -->
                        <img src="../images/afar-flag.png" alt="Afar Flag">
                        <div class="header-titles">
                            <p>Qafar Agatih Rakaakayih Doolatak Qafiyaat Biiro</p>
                            <p>የአፋር ብሔራዊ ክልላዊ መንግስት ጤና ቢሮ</p>
                            <p>Afar National Regional State Health Bureau</p>
                        </div>
                         <!-- NOTE: Replace '#' with the correct path to your flag images -->
                        <img src="../images/ethiopia-flag.png" alt="Ethiopia Flag">
                    </div>
                    <p class="header-main-title">Doolat Taamabeynih Assenti Esserih Cibta</p>
                </div>
            </header>
             <section class="sub-header-fields">
                <div class="field-group">
                    <label>Kood:</label>
                    <!-- PHP Placeholder -->
                    <div class="value" style="width: 300px;"></div>
      