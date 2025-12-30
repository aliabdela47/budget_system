<?php
// report.php
// Printable report page (layout inspired by uploaded image).
// Customize the $header, $columns and $rows arrays below to change content.

$header = [
    'left_logo'  => 'logo-left.png',   // replace or set to '' if none
    'right_logo' => 'logo-right.png',  // replace or set to '' if none
    'org'        => 'ORGANIZATION / AGENCY NAME',
    'sub'        => 'SUB-DEPARTMENT OR ADDRESS LINE',
    'title'      => 'REPORT TITLE / FORM NAME',
    'subtitle'   => 'Secondary title or short description',
    'date_label' => 'Date:',           // label for date box
    'date'       => date('Y-m-d'),
    'ref_label'  => 'Ref No:',
    'ref'        => 'XXX/2025',
];

$checklist = [
    '1. Item A',
    '2. Item B',
    '3. Item C',
    '4. Item D',
    '5. Item E',
    '6. Item F',
    '7. Item G',
    '8. Item H',
];

// Table column headings (edit to match the picture)
$columns = ['S/NO', 'NAME', 'ID/REF', 'AGE', 'SEX', 'AREA/VILLAGE', 'QTY', 'REMARKS'];

// Sample rows (12 rows by default). Set to '' for blank lines.
$rows = [
    ['1', 'Sulaiman Mwita', 'A12345', '34', 'M', 'Kijiji A', '1', 'OK'],
    ['2', 'Asha Juma', 'B98765', '29', 'F', 'Kijiji B', '2', 'Pending'],
    ['3', '', '', '', '', '', '', ''],
    ['4', '', '', '', '', '', '', ''],
    ['5', '', '', '', '', '', '', ''],
    ['6', '', '', '', '', '', '', ''],
    ['7', '', '', '', '', '', '', ''],
    ['8', '', '', '', '', '', '', ''],
    ['9', '', '', '', '', '', '', ''],
    ['10', '', '', '', '', '', '', ''],
    ['11', '', '', '', '', '', '', ''],
    ['12', '', '', '', '', '', '', ''],
];

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo htmlspecialchars($header['title']); ?></title>
<style>
    /* A4-like printable paper */
    body { background:#eef2f4; font-family: Arial, Helvetica, sans-serif; padding:20px; }
    .paper {
        width:210mm; margin:0 auto; padding:12mm;
        background:#fff; color:#000;
        box-shadow: 0 0 6px rgba(0,0,0,0.15);
        box-sizing: border-box;
    }

    /* Header */
    .hdr { display:flex; align-items:flex-start; gap:12px; margin-bottom:8px; }
    .logo { width:78px; height:78px; border:1px solid #000; display:flex; align-items:center; justify-content:center; }
    .logo img { max-width:100%; max-height:100%; display:block; }
    .title-wrap { flex:1; text-align:center; }
    .title-wrap h1 { margin:0; font-size:16px; letter-spacing:0.6px; text-transform:uppercase; }
    .title-wrap p { margin:4px 0 0 0; font-size:12px; }

    .info-boxes { display:flex; flex-direction:column; gap:6px; }
    .info-small { border:1px solid #000; padding:6px 8px; width:150px; font-size:12px; text-align:left; background:#fff; }

    /* top two-column area */
    .top-area { display:flex; gap:12px; margin-top:6px; }
    .left-block { width:56%; border:1px solid #000; padding:8px; }
    .left-block ul { list-style:none; padding-left:10px; margin:0; }
    .left-block li { margin:4px 0; font-size:12px; }

    .right-block { width:44%; border:1px solid #000; padding:8px; font-size:12px; }
    .field { display:flex; gap:8px; margin-bottom:6px; }
    .field .label { width:30%; font-weight:600; }
    .field .value { flex:1; border-bottom:1px dotted #000; padding-left:4px; }

    /* main table */
    .main-table { width:100%; border-collapse: collapse; margin-top:10px; }
    .main-table th, .main-table td { border:1px solid #000; padding:6px 6px; font-size:12px; }
    .main-table th { background:#f5f5f5; text-align:center; }

    /* signature area */
    .sigs { display:flex; gap:18px; margin-top:10px; }
    .sig { flex:1; text-align:left; padding-top:26px; border-top:1px solid #000; font-size:12px; }

    /* footer contact */
    .footer { text-align:center; margin-top:8px; font-size:12px; border-top:1px dashed #000; padding-top:8px; }

    /* print-friendly */
    @media print {
        body { background:#fff; padding:0; }
        .paper { box-shadow:none; margin:0; width:auto; }
        .no-print { display:none !important; }
    }

    /* print button style */
    .controls { margin-bottom:10px; }
    .btn { display:inline-block; background:#0073e6; color:#fff; padding:8px 12px; border-radius:4px; text-decoration:none; font-size:13px; margin-right:6px; }
    .btn.secondary { background:#666; }
</style>
</head>
<body>
<div class="controls no-print">
    <a href="#" class="btn" onclick="window.print(); return false;">Print / Save as PDF</a>
    <a href="#" class="btn secondary" onclick="location.reload(); return false;">Reset</a>
    <small style="margin-left:8px; color:#333;">Tip: edit $header, $columns and $rows inside report.php to customize content.</small>
</div>

<div class="paper" role="document">
    <div class="hdr">
        <div class="logo">
            <?php if (!empty($header['left_logo']) && file_exists($header['left_logo'])): ?>
                <img src="<?php echo htmlspecialchars($header['left_logo']); ?>" alt="logo left">
            <?php else: ?>
                <div style="font-size:11px; text-align:center;">LOGO</div>
            <?php endif; ?>
        </div>

        <div class="title-wrap">
            <h1><?php echo htmlspecialchars($header['org']); ?></h1>
            <p><?php echo htmlspecialchars($header['sub']); ?></p>
            <p style="font-weight:700; margin-top:6px;"><?php echo htmlspecialchars($header['title']); ?></p>
            <?php if (!empty($header['subtitle'])): ?>
                <p style="margin-top:3px; font-size:12px;"><?php echo htmlspecialchars($header['subtitle']); ?></p>
            <?php endif; ?>
        </div>

        <div class="info-boxes" aria-hidden="true">
            <div class="info-small">
                <strong><?php echo htmlspecialchars($header['date_label']); ?></strong>
                <div><?php echo htmlspecialchars($header['date']); ?></div>
            </div>
            <div class="info-small">
                <strong><?php echo htmlspecialchars($header['ref_label']); ?></strong>
                <div><?php echo htmlspecialchars($header['ref']); ?></div>
            </div>

            <div style="width:78px; height:18px; border:1px solid #000; display:flex; align-items:center; justify-content:center; font-size:10px;">
                <?php if (!empty($header['right_logo']) && file_exists($header['right_logo'])): ?>
                    <img src="<?php echo htmlspecialchars($header['right_logo']); ?>" alt="logo right" style="max-height:16px;">
                <?php else: ?>
                    <span style="font-size:10px;">LOGO</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="top-area">
        <div class="left-block" aria-label="Left checklist">
            <strong style="display:block; margin-bottom:6px;">Checklist / Items</strong>
            <ul>
                <?php foreach ($checklist as $item): ?>
                    <li><?php echo htmlspecialchars($item); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="right-block" aria-label="Right fields">
            <div class="field"><div class="label">Region:</div><div class="value">____________________________</div></div>
            <div class="field"><div class="label">District:</div><div class="value">____________________________</div></div>
            <div class="field"><div class="label">Ward:</div><div class="value">____________________________</div></div>
            <div class="field"><div class="label">Village:</div><div class="value">____________________________</div></div>
            <div class="field"><div class="label">Officer:</div><div class="value">____________________________</div></div>
            <div class="field"><div class="label">Phone:</div><div class="value">____________________________</div></div>
        </div>
    </div>

    <table class="main-table" role="table" aria-label="Report table">
        <thead>
            <tr>
                <?php foreach ($columns as $col): ?>
                    <th><?php echo htmlspecialchars($col); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <?php
                        // ensure each row has the same number of cells as $columns
                        for ($i=0; $i < count($columns); $i++):
                            $cell = isset($r[$i]) ? $r[$i] : '';
                    ?>
                        <td><?php echo htmlspecialchars($cell); ?></td>
                    <?php endfor; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="sigs">
        <div class="sig">
            Name / Signature<br>
            <span style="font-size:11px; color:#444;">(Printed name and signature)</span>
        </div>
        <div class="sig">
            Designation / Role<br>
            <span style="font-size:11px; color:#444;">(Title)</span>
        </div>
        <div class="sig">
            Date<br>
            <span style="font-size:11px; color:#444;">(dd/mm/yyyy)</span>
        </div>
    </div>

    <div class="footer">
        Tel: 000-000-000 &nbsp; | &nbsp; Email: info@example.com
    </div>
</div>
</body>
</html>