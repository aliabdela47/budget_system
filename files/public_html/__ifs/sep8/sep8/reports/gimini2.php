<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Bureau Report</title>
    <style>
        /* This uses a font that supports Ethiopic script to ensure correct rendering. */
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;700&display=swap');

        body {
            font-family: 'Noto Sans Ethiopic', 'Nyala', serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
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

        .header-left p, .header-right p {
            margin: 0;
        }
        
        .header-left {
            text-align: left;
        }

        .header-center .logo {
            width: 70px;
            height: 70px;
            border: 1.5px solid #555;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 0.8em;
            color: #666;
            margin: 0 15px;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path d="M20 50 L80 50 M50 20 L50 80" stroke="%23333" stroke-width="5" transform="rotate(45 50 50)"/><path d="M20 50 L80 50 M50 20 L50 80" stroke="%23333" stroke-width="5" transform="rotate(-45 50 50)"/></svg>');
            background-repeat: no-repeat;
            background-position: center;
            background-size: 60%;
        }


        .header-right {
            text-align: left;
        }

        .sub-header {
            text-align: center;
            font-weight: bold;
            margin: 15px 0;
            font-size: 1.1em;
        }
        
        .sub-header p {
            margin: 0;
        }

        .details-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 5px 20px;
            margin-bottom: 20px;
            font-size: 1.05em;
        }
        
        .details-section p {
            margin: 4px 0;
            border-bottom: 1px dotted #555;
            padding-bottom: 2px;
        }
        
        .details-section .value {
            font-weight: normal;
            font-family: sans-serif; /* For Latin text */
            padding-left: 8px;
        }

        .main-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .main-table th, .main-table td {
            border: 1.5px solid #000;
            padding: 8px;
            text-align: left;
            font-size: 0.95em;
        }

        .main-table th {
            font-weight: bold;
        }
        
        .main-table td {
             height: 22px;
        }
        
        .main-table tr:last-child {
            font-weight: bold;
        }

        .signature-section {
            margin-top: 30px;
            font-size: 1.05em;
        }

        .signature-line {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 30px;
        }
        
        .signature-line .label {
            white-space: nowrap;
            padding-right: 15px;
        }

        .signature-line .line {
            border-bottom: 1px solid #000;
            width: 60%;
            text-align: right;
            padding-right: 5px;
        }
        
        .signature-line .firma {
            padding-left: 20px;
            white-space: nowrap;
        }

        .report-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1.5px solid #000;
            padding-top: 10px;
            margin-top: 35px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>

    <div class="report-container">
        <header class="report-header">
            <div class="header-left">
                <p>በአፋር ብሔራዊ ክልላዊ መንግሥት</p>
                <p>ጤና ቢሮ/p>
                <p style="font-size: 0.9em; font-style: italic;">The Afar National Regional State</p>
                <p style="font-size: 0.9em; font-style: italic;">Health Bureau</p>
            </div>
            <div class="header-center">
                <div class="logo"></div>
            </div>
            <div class="header-right">
                <p>Nawnawa</p>
                <p>Ref. No/ቁጥር _______________</p>
                <p>Ayro/ቀን _______________</p>
                <p style="text-align: right;">"Kebele Belen"</p>
            </div>
        </header>

        <section class="sub-header">
            <p>Buna makkeyinih Sukutay Zeytih kee Gersi gacamgas Unteeyih daniytih Ensaro Cibu</p>
            <p>Tumalih APPli FFfAbew BAFLI KEy FEAnE FIMAFIMA APPII YK musee KE-</p>
        </section>

        <section class="details-section">
            <p>1. Kokobisse Migaa:<br> የአሽከርካሪው ስም <span class="value"></span></p>
            <p>5. Sahu Kibtimay Suge: <span class="value"></span></p>
            <p>2. Makina Losatih Kook: <span class="value"></span></p>
            <p>6. Anay Yan Geej Loowo: <span class="value"></span></p>
            <p>3. Diki Lih / Geeka Kin: <span class="value"></span></p>
            <p>7. Geej Loowo Bakir: <span class="value"></span></p>
            <p>4. Kibbtime Sami: <span class="value"></span></p>
            <p>8. Duyyek Faxximatam: <span class="value"></span></p>
        </section>

        <table class="main-table">
            <thead>
                <tr>
                    <th>K.L</th>
                    <th>Unteyt Qeynat /ADAB KESA</th>
                    <th>Qeynat /PNNL</th>
                    <th>Manga /Off</th>
                    <th>Piktis Liho /PIRA YJ</th>
                    <th>Sitat /AKEPC</th>
                    <th>Insaq /IPCRY</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>1</td><td>Sano/የአፍል</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td>2</td><td>Bersisa/በገና</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td>3</td><td>Maloor Sukut/የዋግዛ</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td>4</td><td>Kanbiyo Sukat/የኮንሶ</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td>5</td><td>Mari Sukat/የከፋ</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td>6</td><td>Gerisa/ጌታ</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td>7</td><td>Fereen Sukut/የዮረ</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td>8</td><td>Batri Lee/የሃገር ቤት</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td>9</td><td>Gommi Bicesa/ልዩ ልዩ</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td>10</td><td>Kalat Tanim/ሌሎች</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td></td><td>Siltet</td><td></td><td></td><td></td><td></td><td></td></tr>
            </tbody>
        </table>

        <section class="signature-section">
            <div class="signature-line">
                <span class="label">Taama gesenum Migaaqa:</span>
                <span class="line"></span>
                <span class="firma">Firma:</span>
            </div>
            <div class="signature-line">
                <span class="label">Caatab Massosee Migaaqa:</span>
                <span class="line"></span>
                <span class="firma">Firma:</span>
            </div>
            <div class="signature-line">
                <span class="label">Diggosse Migaaqa:</span>
                 <span class="line"></span>
                <span class="firma">Firma:</span>
            </div>
            <div class="signature-line">
                <span class="label">Fasisse Migaaqa:</span>
                 <span class="line"></span>
                <span class="firma">Firma:</span>
            </div>
        </section>

        <footer class="report-footer">
            <span>TEL: 033-666-00-22</span>
            <span>033-666-00-..</span>
            <span>Email: afarhealthbureau@gmail.com</span>
        </footer>

    </div>

</body>
</html>