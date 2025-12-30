<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Per Diem Report - Doolat Taamabeynih Assenti Esserih Cibta</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;700&display=swap');
        
        /* Page size and print settings */
        @page {
            size: A4 portrait;
            margin: 0.5cm;
        }

        body {
            font-family: 'Noto Sans Ethiopic', 'Nyala', serif;
            background-color: #f0f0f0;
            display: flex;
            justify-content: center;
            padding: 20px 0;
            line-height: 1.6;
            width: 21cm; /* A4 width */
            margin: 0 auto;
        }

        .report-container {
            display: flex;
            flex-direction: column;
            gap: 30px;
            width: 100%;
        }

        .page {
            background-color: #fff;
            border: 1px solid #ccc;
            padding: 15px;
            width: 100%;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            box-sizing: border-box;
            min-height: 29.7cm; /* A4 height */
        }
        
        .page-break {
            page-break-after: always;
        }
        
        /* --- Reusable Header --- */
        .main-header-box {
            border: 2px solid #000;
            padding: 10px;
            margin-bottom: 15px;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-top img {
            height: 40px;
        }

        .header-titles {
            text-align: center;
            flex-grow: 1;
            margin: 0 10px;
        }
        .header-titles p {
            margin: 2px 0;
            font-weight: bold;
            font-size: 14px;
        }

        .header-main-title {
            text-align: center;
            font-weight: bold;
            font-size: 16px;
            margin: 10px 0;
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
            margin-bottom: 10px;
        }
        .field-group label {
            white-space: nowrap;
            font-weight: bold;
            padding-right: 8px;
            font-size: 12px;
        }
        .field-group .value {
            border-bottom: 1px solid #000;
            flex-grow: 1;
            font-weight: bold;
            padding: 0 5px;
            min-height: 18px;
        }
        
        .sub-header-fields {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            margin-bottom: 10px;
        }

        /* --- Table Styles --- */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            table-layout: fixed;
            margin: 10px 0;
        }
        .data-table th, .data-table td {
            border: 1px solid #000;
            padding: 4px;
            text-align: center;
            vertical-align: middle;
            height: 25px;
            overflow: hidden;
        }
        .data-table th {
            font-weight: bold;
            background-color: #f0f0f0;
        }
        .data-table .row-header {
            font-weight: bold;
            text-align: left;
        }
        
        /* Make specific columns narrower */
        .narrow-col {
            width: 5%;
        }
        .medium-col {
            width: 7%;
        }
        .wide-col {
            width: 12%;
        }

        /* --- Signature Styles --- */
        .signature-section {
            margin-top: 15px;
        }
        .signature-section .field-group {
            margin-bottom: 15px;
        }
        
        .signature-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 15px;
        }
        
        .signature-box {
            border-top: 1px solid #000;
            padding-top: 5px;
            text-align: center;
            font-weight: bold;
            font-size: 11px;
            height: 60px;
        }
        
        .two-column-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
        }
        
        .summary-fields {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-top: 15px;
        }

        @media print {
            body { 
                background-color: #fff; 
                padding: 0; 
                width: 100%;
            }
            .report-container { 
                gap: 0; 
            }
            .page {
                box-shadow: none;
                border: none;
                margin: 0;
                padding: 0.5cm;
                width: 100%;
                height: auto;
                min-height: auto;
            }
            .page-break {
                page-break-after: always;
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
                                <div class="value" style="width: 250px;">&nbsp;</div>
                            </div>
                            <div class="field-group">
                                <label>Ayro:</label>
                                <div class="value" style="width: 250px;">&nbsp;</div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <section class="sub-header-fields">
                <div class="field-group">
                    <label>Taama Abeeni Kood/ID:</label>
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
                                <label>Ayro:</label>
                                <div class="value" style="width: 250px;">&nbsp;</div>
                            </div>
                            <div class="field-group">
                                <label>Ixxima:</label>
                                <div class="value" style="width: 250px;">&nbsp;</div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <section class="sub-header-fields">
                <div class="field-group">
                    <label>Fatiicisee Migaqa:</label>
                    <div class="value" style="width: 300px;"></div>
                </div>
                <div class="field-group">
                    <label>Print Date:</label>
                    <div class="value" style="border:none;"><?php echo date('m/d/Y'); ?></div>
                </div>
            </section>
            
            <main>
                <!-- Per Diem Calculation Table -->
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="wide-col">B. Taamabeyni Safaral Sugahgide Kee Ayroca (Kaqaada) Qadoosa Qarwali</th>
                            <th colspan="2" class="medium-col">Araca</th>
                            <th class="narrow-col">Cisabgolikibima</th>
                            <th class="narrow-col">Sittat</th>
                            <th class="narrow-col">Ayro Assenta</th>
                            <th class="narrow-col">Ayro Manga</th>
                            <th colspan="3" class="medium-col">Kada</th>
                            <th class="narrow-col">Saaqat</th>
                            <th class="narrow-col">Ayro</th>
                            <th class="narrow-col">Arac</th>
                            <th class="narrow-col">Ayro Assenta</th>
                            <th colspan="2" class="medium-col">Ximmo</th>
                        </tr>
                        <tr>
                            <th></th>
                            <th class="narrow-col">Ugtuma</th>
                            <th class="narrow-col">Wadba</th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th class="narrow-col">Diraar 25%</th>
                            <th class="narrow-col">Kada 25%</th>
                            <th class="narrow-col">Kuraq 10%</th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th class="narrow-col"></th>
                            <th class="narrow-col">Lowwo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Sample data row - you would loop through your PHP data here -->
                        <tr>
                            <td class="row-header">Travel Purpose Description</td>
                            <td>Start Date</td>
                            <td>End Date</td>
                            <td>Basis</td>
                            <td>Amount</td>
                            <td>Daily Rate</td>
                            <td>Days</td>
                            <td>25%</td>
                            <td>25%</td>
                            <td>10%</td>
                            <td>Deductions</td>
                            <td>Per Diem</td>
                            <td>Date</td>
                            <td>Rate</td>
                            <td>Sig 1</td>
                            <td>Sig 2</td>
                        </tr>
                        <!-- Add more rows as needed -->
                    </tbody>
                </table>
                
                <!-- Additional Information Section -->
                <div class="two-column-layout">
                    <div>
                        <div class="field-group"><label>Kood:</label><div class="value"></div></div>
                        <div class="field-group"><label>Biiro Migaq:</label><div class="value">Q.A.R.D Qaafiyat Biiro</div></div>
                        <div class="field-group"><label>Taamagoli:</label><div class="value"></div></div>
                        <div class="field-group"><label>Taamabeyni Migaq:</label><div class="value"></div></div>
                        <div class="field-group"><label>Qasbi kinnihgide:</label><div class="value"></div></div>
                        <div class="field-group"><label>Merraytu:</label><div class="value"></div></div>
                        <div class="field-group"><label>Assenti Mekla:</label><div class="value"></div></div>
                    </div>
                    
                    <div>
                        <div class="field-group"><label>Diggosse Migaqa:</label><div class="value" style="height: 40px;"></div></div>
                        <div class="field-group"><label>Cisaab Massose Migaqa:</label><div class="value" style="height: 40px;"></div></div>
                        <div class="field-group"><label>Taama Gexanum Migaqa:</label><div class="value" style="height: 40px;"></div></div>
                        <div class="field-group"><label>Firma:</label><div class="value" style="height: 40px;"></div></div>
                    </div>
                </div>
                
                <!-- Summary Section -->
                <div class="summary-fields">
                    <div class="field-group"><label>A Kee B Sittat:</label><div class="value"></div></div>
                    <div class="field-group"><label>Silaglelee:</label><div class="value"></div></div>
                    <div class="field-group"><label>Foxomigi:</label><div class="value"></div></div>
                    <div class="field-group"><label>Sittat:</label><div class="value"></div></div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>