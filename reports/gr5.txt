<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Bureau Report</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;700&display=swap');

        body {
            font-family: 'Noto Sans Ethiopic', 'Nyala', serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
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
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 5px;
        }

        .header-left p, .header-right p {
            margin: 0;
            line-height: 1.5;
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
        }

        .header-right {
            text-align: left;
        }

        .sub-header {
            text-align: center;
            font-weight: bold;
            margin-bottom: 15px;
            font-size: 1.1em;
        }

        .details-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 5px 20px;
            margin-bottom: 20px;
        }
        
        .details-section p {
            margin: 4px 0;
        }

        .main-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .main-table th, .main-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
            font-size: 0.9em;
        }

        .main-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        
        .main-table td {
             height: 20px; /* To make empty cells visible */
        }
        
        .main-table tr:last-child {
            font-weight: bold;
        }

        .signature-section {
            margin-top: 30px;
        }

        .signature-line {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 25px;
        }
        
        .signature-line .label {
            white-space: nowrap;
        }

        .signature-line .line {
            border-bottom: 1px dotted #000;
            width: 70%;
            text-align: right;
            padding-right: 5px;
        }

        .report-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #000;
            padding-top: 10px;
            margin-top: 30px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>

    <div class="report-container">
        <header class="report-header">
            <div class="header-left">
                <p>የቤንሻንጉል ጉሙዝ ብሔራዊ ክልላዊ መንግሥት</p>
                <p>ጤና ጥበቃ ቢሮ</p>
                <p style="font-size: 0.9em; font-style: italic;">The Afar National Regional State</p>
                <p style="font-size: 0.9em; font-style: italic;">Bureau of Health</p>
            </div>
            <div class="header-center">
                <div class="logo">LOGO</div>
            </div>
            <div class="header-right">
                <p>ተ.ቁ. _______________</p>
                <p>ቀን _______________</p>
                <p>Apre _______________</p>
                <p>....... ቀበሌ</p>
            </div>
        </header>

        <section class="sub-header">
            <p>በኮማሺ ዞን መካከለኛ ሱኳር በሽታ ያለባቸው ህሙማን ...</p>
        </section>

        <section class="details-section">
            <p>1. ዞን: <strong>ኮማሺ</strong></p>
            <p>5. የጤና ጣቢያ: ____________________</p>
            <p>2. ወረዳ: <strong>መካነ ሰላም</strong></p>
            <p>6. ዓይነት: ____________________</p>
            <p>3. ቀበሌ: ____________________</p>
            <p>7. የቡድን መሪ: ____________________</p>
            <p>4. የጤና ተቋም ስም: _______________</p>
            <p>8. የተገልጋዮች ብዛት: ________________</p>
        </section>

        <table class="main-table">
            <thead>
                <tr>
                    <th>ተ.ቁ.</th>
                    <th>የህሙማን ስም</th>
                    <th>ፆታ</th>
                    <th>ዕድሜ</th>
                    <th>የስኳር መጠን</th>
                    <th>ክብደት</th>
                    <th>ማስታወሻ</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>1</td><td>ሳኖ/Sano</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td>2</td><td>በርሲሳ/Bersisa</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td>3</td><td>ማሎር ሱኳር/Maloor Sukat</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td>4</td><td>ካንቢዮ ሱኳር/Kanbiyo Sukat</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td>5</td><td>ማሪ ሱኳር/Mari Sukat</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td>6</td><td>ገሪሳ/Gerisa</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td>7</td><td>ፈሪን ሱኳር/Fereen Sukat</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td>8</td><td>ባተሪ ሊ/Batri Lee</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td>9</td><td>ጎሚ ቢቸሳ/Gommi Bicesa</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td>10</td><td>ካላት ታኒም/Kalat Tanim</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td></td><td>ድምር / Siltet</td><td></td><td></td><td></td><td></td><td></td></tr>
            </tbody>
        </table>

        <section class="signature-section">
            <div class="signature-line">
                <span class="label">የጤና ባለሙያ ስምና ፊርማ:</span>
                <span class="line">ፊርማ:</span>
            </div>
            <div class="signature-line">
                <span class="label">የጤና ጣቢያ ኃላፊ ስምና ፊርማ:</span>
                <span class="line">ፊርማ:</span>
            </div>
            <div class="signature-line">
                <span class="label">የወረዳ ጤ/ፅ/ቤት ኃላፊ ስምና ፊርማ:</span>
                <span class="line">ፊርማ:</span>
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