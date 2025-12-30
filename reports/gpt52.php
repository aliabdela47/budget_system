<?php
// report.php
// Printable report page using your uploaded photo as the exact visual form
// and an editable typed transcription below (best-effort).
//
// Usage:
// 1) Save your uploaded image in the same folder as "form-bg.jpg" (or change $bgPath).
// 2) Open this file in a browser. To force typed-only mode use ?mode=text

$bgPath = 'form-bg.jpg';     // Put your uploaded photo here (rename if needed)
$mode = isset($_GET['mode']) && $_GET['mode'] === 'text' ? 'text' : 'image';

// Best-effort transcription of the visible labels/text in the photo.
// I may have been uncertain about some words; uncertain pieces are marked with [??].
// If you want me to correct any, tell me which exact words to change.
$transcription = [
    'header_lines' => [
        'THE AFRICAN ... [unclear header line 1]',
        'REGIONAL HEALTH [??]',
        'The Annual Regional ... [??]',
    ],
    'title' => 'EMBELE YA KITCHI / KABLA KUTOKA (sample title from photo) [??]',
    'small_labels' => [
        'Tarehe' => date('d/m/Y'),
        'Kumb. Namba' => '[REF NO]',
        'Sehemu' => '[PLACE]',
    ],
    'left_checklist' => [
        '1. Kizuizi/Majeruhi' ,
        '2. Kubadilisha Kitambaa',
        '3. Kulegea kwa Kifaa',
        '4. Kuondoa Madoa',
        '5. Kupima Damu',
        '6. Kuteleza kwa Mguu',
        '7. Kupima Sumu [??]',
        '8. Kuongezea Dawa [??]'
    ],
    // Table headers - approximate; edit whichever you want to be exact
    'table_headers' => [
        'S/NO', 'JINA / NAME', 'UMRI', 'JINSIA', 'KITAMBULISHO', 'KITAIFA/AREA', 'KIASI', 'REMARKS'
    ],
    // Sample blank rows
    'rows' => array_fill(0, 12, ['', '', '', '', '', '', '', '']),
    'footer' => 'TEL: 033-666-00-22  |  033-666-00-??   Email: stafealthbureau@gmail.com [??]'
];

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Printable Report (from photo)</title>
<style>
    body { font-family: Arial, Helvetica, sans-serif; background:#f0f2f5; padding:18px; color:#111; }
    .controls { margin-bottom:10px; }
    .btn { display:inline-block; padding:8px 12px; background:#0b74da; color:#fff; border-radius:4px; text-decoration:none; margin-right:8px; }
    .paper { width:210mm; margin:0 auto; background:#fff; padding:12mm; box-shadow:0 0 8px rgba(0,0,0,.12); box-sizing:border-box; }
    img.form-photo { width:100%; height:auto; display:block; border:1px solid #ccc; margin-bottom:12px; }
    .recreate { margin-top:8px; }
    .hdr { display:flex; gap:12px; align-items:flex-start; margin-bottom:6px; }
    .title { text-align:center; flex:1; font-weight:700; }
    .small-box { border:1px solid #000; padding:6px; width:150px; font-size:12px; text-align:left; }
    .top-area { display:flex; gap:12px; }
    .left-block { width:58%; border:1px solid #000; padding:8px; }
    .right-block{ width:42%; border:1px solid #000; padding:8px; }
    .left-block ul { margin:0; padding-left:18px; }
    .left-block li { margin:6px 0; font-size:13px; }
    .field { display:flex; margin-bottom:6px; font-size:13px; }
    .field .label { width:30%; font-weight:600; }
    .field .value { flex:1; border-bottom:1px dotted #000; padding-left:6px; }
    table.main { width:100%; border-collapse:collapse; margin-top:10px; font-size:13px; }
    table.main th, table.main td { border:1px solid #000; padding:6px; text-align:left; }
    table.main th { background:#f6f6f6; text-align:center; }
    .sigs { display:flex; gap:16px; margin-top:12px; }
    .sig { flex:1; border-top:1px solid #000; padding-top:10px; text-align:left; }
    .footer { text-align:center; margin-top:10px; border-top:1px dashed #000; padding-top:8px; font-size:13px; }
    @media print { .no-print { display:none; } .paper { box-shadow:none; } }
    /* typed-transcription note */
    .trans-note { font-size:12px; color:#666; margin-bottom:6px; }
    .uncert { color:#b00; font-weight:700; }
</style>
</head>
<body>
<div class="controls no-print">
    <a class="btn" href="#" onclick="window.print();return false;">Print / Save PDF</a>
    <a class="btn" href="?mode=text">Show typed transcription</a>
    <a class="btn" href="?">Show photo + typed</a>
    <span style="margin-left:10px;color:#333;">If text in the typed form is wrong I will correct it — tell me the exact corrections.</span>
</div>

<div class="paper" role="document">
    <?php if ($mode === 'image' && file_exists($bgPath)): ?>
        <!-- Exact visual match: show uploaded photo (this preserves the exact text/graphics) -->
        <img src="<?php echo htmlspecialchars($bgPath); ?>" alt="Form photo" class="form-photo">
        <div style="font-size:12px; color:#333; margin-bottom:8px;">
            The image above is your uploaded photo and will print with the exact text/graphics from your picture.
            Below is a typed recreation (best-effort).
        </div>
    <?php elseif ($mode === 'image'): ?>
        <div style="color:#900; font-weight:700; margin-bottom:8px;">No image found at "<?php echo htmlspecialchars($bgPath); ?>". Put your uploaded photo in the same folder and name it <?php echo htmlspecialchars($bgPath); ?> to print the exact original form.</div>
    <?php endif; ?>

    <!-- Typed recreation (editable in code) -->
    <div class="recreate" aria-label="Recreated form (typed)">
        <div class="hdr">
            <div style="width:78px; height:78px; border:1px solid #000; display:flex; align-items:center; justify-content:center;">LOGO</div>
            <div class="title">
                <div style="font-size:14px; font-weight:800;"><?php echo htmlspecialchars($transcription['title']); ?></div>
                <div style="font-size:12px; margin-top:6px;"><?php echo htmlspecialchars(implode(' / ', $transcription['header_lines'])); ?></div>
            </div>
            <div style="display:flex; flex-direction:column; gap:6px; align-items:flex-end;">
                <div class="small-box"><strong>Tarehe:</strong><div><?php echo htmlspecialchars($transcription['small_labels']['Tarehe']); ?></div></div>
                <div class="small-box"><strong>Ref:</strong><div><?php echo htmlspecialchars($transcription['small_labels']['Kumb. Namba']); ?></div></div>
            </div>
        </div>

        <div class="top-area">
            <div class="left-block" aria-label="Check list (from photo)">
                <strong>Vidokezo / Vitu</strong>
                <ul>
                    <?php foreach ($transcription['left_checklist'] as $li): ?>
                        <li><?php echo htmlspecialchars($li); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="right-block">
                <div class="field"><div class="label">Mkoa:</div><div class="value"></div></div>
                <div class="field"><div class="label">Wilaya:</div><div class="value"></div></div>
                <div class="field"><div class="label">Kata:</div><div class="value"></div></div>
                <div class="field"><div class="label">Kijiji/Mtaa:</div><div class="value"></div></div>
                <div class="field"><div class="label">Afisa:</div><div class="value"></div></div>
                <div class="field"><div class="label">Simu:</div><div class="value"></div></div>
            </div>
        </div>

        <table class="main" role="table" aria-label="Data table">
            <thead>
                <tr>
                    <?php foreach ($transcription['table_headers'] as $h): ?>
                        <th><?php echo htmlspecialchars($h); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transcription['rows'] as $r): ?>
                    echo "<tr>";
                    <?php for ($c=0;$c<count($transcription['table_headers']);$c++): ?>
                        <td style="min-width:60px;"></td>
                    <?php endfor; ?>
                    echo "</tr>";
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="sigs">
            <div class="sig">Mkutubi / Signature</div>
            <div class="sig">Msimamizi / Designation</div>
            <div class="sig">Tarehe / Date</div>
        </div>

        <div class="footer">
            <?php echo htmlspecialchars($transcription['footer']); ?>
        </div>

        <div class="trans-note">
            Typed transcription: uncertain words are marked with <span class="uncert">[??]</span>. If you want an exact typed copy I will update the strings to precisely match — tell me corrections and I'll paste an updated file.
        </div>
    </div>
</div>
</body>
</html>