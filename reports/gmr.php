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
            margin-top: 20px;
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
                <p>የአፋር ብ/ክ/መ ጤና ቢሮ. </p>
                <p>Q.A.R.D Qaafiyat Biiro </p>
                <p style="font-size: 0.9em; font-style: normal;">The Afar National Regional State</p>
                <p style="font-size: 0.9em; font-style: normal;">Health Bureau</p>
            </div>
            <div class="header-center">
                <div class="logo">LOGO</div>
            </div>
            <div class="header-right">
                <p>Ixxima</p>
                <p>ቁጥር_____________________________</p>
               <p> Ref No. </p>
                <p>Ayro</p>
                <p>ቀን_______________________________</p>
               <p> Date</p>
            </div>
        </header>

        <section class="sub-header">
          <p style="font-size: 1rem; font-style: normal; font-weight: bold;">Biiro Makaayinih Suktay Zeytiy Kee Gersi Gacamgac Uwwaytih damiyyi Essero Cibu</p>
                <p style="font-size: 1rem; font-style: normal; font-weight: bold;">የመስሪያ ቤታችን የተሽከርካሪ ቅባትና ዘይት የተለያዩ የመለዋወጫ እቃዎች ግዥ መጠየቂያ ቅጽ።</p>
<br>
        </section>
        <section class="details-section">
            <p style="font-size: 0.8rem; font-style: normal; font-weight: normal;">1. Kokobise Migaq:________________ <strong></strong></p><br>
            <p style="font-size: 0.8rem; font-style: normal; font-weight: normal;"> የአሽከርካሪው ስም </p>
            <p style="font-size: 0.8rem; font-style: normal; font-weight: normal;">5. Sansi Kibteway Suge:________________ <strong></strong></p><br>
            <p style="font-size: 0.8rem; font-style: normal; font-weight: normal;"> Geej Loowo</p>
            <p style="font-size: 0.8rem; font-style: normal; font-weight: normal;">2. Sansi Kibteway Suge:________________ <strong></strong></p><br>
            <p style="font-size: 0.8rem; font-style: normal; font-weight: normal;"> Geej Loowo</p>
            <p style="font-size: 0.8rem; font-style: normal; font-weight: normal;">6. Sansi Kibteway Suge:________________ <strong></strong></p>
            <p style="font-size: 0.8rem; font-style: normal; font-weight: normal;"> Geej Loowo</p>
            <p style="font-size: 0.8rem; font-style: normal; font-weight: normal;">3. Sansi Kibteway Suge:________________ <strong></strong></p>
            <p style="font-size: 0.8rem; font-style: normal; font-weight: normal;"> Geej Loowo</p>
            <p style="font-size: 0.8rem; font-style: normal; font-weight: normal;">7. Sansi Kibteway Suge:________________ <strong></strong></p>
            <p style="font-size: 0.8rem; font-style: normal; font-weight: normal;"> Geej Loowo</p>
            <p style="font-size: 0.8rem; font-style: normal; font-weight: normal;">4. Sansi Kibteway Suge:________________ <strong></strong></p>
            <p style="font-size: 0.8rem; font-style: normal; font-weight: normal;"> Geej Loowo</p>
           <p style="font-size: 0.8rem; font-style: normal; font-weight: normal;">8. Sansi Kibteway Suge:________________ <strong></strong></p>
            <p style="font-size: 0.8rem; font-style: normal; font-weight: normal;"> Geej Loowo</p>
        </section>

        <table class="main-table">
            <thead>
                <tr>
                    <th> R.L </th>
                    <th> Uwwaytih Qaynat <br> የእቃው አይነት </th>
                    <th> Giyaase <br> መለኪያ </th>
                    <th> Manga<br>ብዛት</th>
                    <th> Inkitti Limo <br>የአንዱ ዋጋ</th>
                    <th> Sittat <br>ጠቅላላ ድምር</th>
                    <th> Kusaq <br> ምርመራ  </th>
                    
                    </tr>
            </thead>
            <tbody>
                <tr><td>1</td><td>Sansi/ናፍታ</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td>2</td><td>Benzin/ቤንዚን</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td>3</td><td>Matoor Sukat/ የሞተር ዘይት/td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td>4</td><td>Kanbiyo Sukat/የካንቢዮ ዘይት</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td>5</td><td>Mari Sukat/የመሪ ዘይት</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td>6</td><td>Gerse/ግሪስ</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td>7</td><td>Fereen Sukat/የፍሬን ዘይት</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td>8</td><td>Batri Lee/የባትሪ ውሀ</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td>9</td><td>Gommi Bicsa/ለጎማ</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td>10</td><td>Kalah Tanim/ሌሎች</td><td></td><td></td><td></td><td></td><td></td></tr>
                <tr><td></td><td>Sittat</td><td></td><td></td><td></td><td></td><td></td>
                </tr>
            </tbody>
        </table>
        
        <section class="signature-section">
                <div class="signature-line">
                    <span class="label">Taama gesenum Migaaqa:_________________________________</span>
                    <span class="firma">Firma:_____________________________________</span>
                </div>
                <div class="signature-line">
                    <span class="label">Caatab Massosee Migaaqa:_________________________________</span>
                    <span class="firma">Firma:_____________________________________</span>
                </div>
                <div class="signature-line">
                    <span class="label">Diggosse Migaaqa:_________________________________________</span>
                    <span class="firma">Firma:_____________________________________</span>
                </div>
                <div class="signature-line">
                    <span class="label">Fasisse Migaaqa:___________________________________________</span>
                    <span class="firma">Firma:_____________________________________</span>
                </div>
            </section>

      

        <footer class="report-footer">
          <span>Addres: Semera, Ethiopia</span>
            <span>TEL: 033-666-00-22</span>
            <span>033-666-00-..</span>
            <span>Email: info@afarrhb.et</span>
         <p> Form Designed by: ICT Directorate</br> </p><br>
          </div>

</body>
</html>