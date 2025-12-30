<?php
require_once '../includes/init.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT f.*, o.name AS owner_name, o.code AS owner_code, o.p_koox
                       FROM fuel_transactions f
                       LEFT JOIN budget_owners o ON o.id = f.owner_id
                       WHERE f.id = ? AND f.budget_type='program'");
$stmt->execute([$id]);
$tx = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$tx) { die('Transaction not found'); }

function birr($n){ return number_format((float)$n,2).' ብር'; }
$today = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="am">
<head>
  <meta charset="UTF-8">
  <title>የነዳጅ ውል መጠየቂያ (Programs) - #<?php echo $id; ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    @page { size: A4; margin: 18mm; }
    body { font-family: 'Noto Sans Ethiopic', 'Arial', sans-serif; color:#000; }
    h1,h2,h3 { margin:0; padding:0; }
    .title { text-align:center; font-weight:700; font-size:18px; text-decoration:underline; margin-bottom:8px; }
    .subtitle { text-align:center; font-weight:600; font-size:14px; margin-bottom:16px; }
    .row { display:flex; gap:12px; margin-bottom:6px; }
    .col { flex:1; }
    .label { font-weight:600; }
    .box { border:1px solid #000; padding:8px; margin-top:10px; }
    table { width:100%; border-collapse:collapse; margin-top:6px; }
    th,td { border:1px solid #000; padding:6px 8px; font-size:13px; }
    th { background:#f2f2f2; text-align:center; }
    .sign-row { display:flex; gap:20px; margin-top:22px; }
    .sign { flex:1; }
    .sign .line { border-bottom:1px solid #000; height:24px; }
    .muted { color:#333; font-size:12px; }
    .right { text-align:right; }
    .center { text-align:center; }
    .mt-8 { margin-top:8px; }
    .mt-16 { margin-top:16px; }
    .mb-8 { margin-bottom:8px; }
    .small { font-size:12px; }
    .bold { font-weight:700; }
    .print-btn { position: fixed; right: 20px; top: 20px; }
    @media print { .print-btn { display:none; } }
  </style>
</head>
<body>
  <button class="print-btn" onclick="window.print()">Print</button>

  <div class="title">የነዳጅ ውል መጠየቂያ (Programs)</div>
  <div class="subtitle">አፋር ክልል ጤና ቢሮ — የፕሮግራም በጀት ክፍያ ሪፖርት</div>

  <div class="row small">
    <div class="col"><span class="label">ቢሮ/ዳይሬክቶሬት:</span> <?php echo htmlspecialchars(($tx['owner_code']??'').' - '.($tx['owner_name']??'')); ?></div>
    <div class="col"><span class="label">ፕሮጀክት (Koox):</span> <?php echo htmlspecialchars($tx['p_koox'] ?? '-'); ?></div>
  </div>

  <div class="row small">
    <div class="col"><span class="label">ሞተር ቁጥር:</span> <?php echo htmlspecialchars($tx['plate_number']); ?></div>
    <div class="col"><span class="label">ሹፌር:</span> <?php echo htmlspecialchars($tx['driver_name']); ?></div>
  </div>

  <div class="row small">
    <div class="col"><span class="label">ቀን:</span> <?php echo htmlspecialchars(substr($tx['date'],0,10)); ?></div>
    <div class="col"><span class="label">ሊትር:</span> <?php echo number_format((float)$tx['refuelable_amount'],2); ?></div>
  </div>

  <div class="row small">
    <div class="col"><span class="label">ዋጋ/ሊትር:</span> <?php echo birr($tx['fuel_price']); ?></div>
    <div class="col"><span class="label">ጠቅላላ መጠን:</span> <span class="bold"><?php echo birr($tx['total_amount']); ?></span></div>
  </div>

  <div class="row small">
    <div class="col"><span class="label">የቀድሞ ጌጅ:</span> <?php echo number_format((float)$tx['previous_gauge'],2); ?></div>
    <div class="col"><span class="label">አሁን ጌጅ:</span> <?php echo number_format((float)$tx['current_gauge'],2); ?></div>
  </div>

  <div class="row small">
    <div class="col"><span class="label">የጉዞ ርቀት (ኪ.ሜ):</span> <?php echo number_format((float)$tx['journey_distance'],2); ?></div>
    <div class="col"><span class="label">አዲስ ጌጅ:</span> <?php echo number_format((float)$tx['new_gauge'],2); ?> (ልዩነት: <?php echo number_format((float)$tx['gauge_gap'],2); ?>)</div>
  </div>

  <div class="box">
    <div class="bold mb-8 center">የነዳጅ ክፍያ ዝርዝር መረጃ</div>
    <table>
      <thead>
        <tr>
          <th>ተ.ቁ</th>
          <th>መኪና ሰሌዳ</th>
          <th>ሾፌር</th>
          <th>ኪ.ሜ</th>
          <th>ሊትር</th>
          <th>ዋጋ/ሊትር</th>
          <th>ጠቅላላ</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="center">1</td>
          <td class="center"><?php echo htmlspecialchars($tx['plate_number']); ?></td>
          <td class="center"><?php echo htmlspecialchars($tx['driver_name']); ?></td>
          <td class="right"><?php echo number_format((float)$tx['journey_distance'],2); ?></td>
          <td class="right"><?php echo number_format((float)$tx['refuelable_amount'],2); ?></td>
          <td class="right"><?php echo birr($tx['fuel_price']); ?></td>
          <td class="right bold"><?php echo birr($tx['total_amount']); ?></td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="sign-row">
    <div class="sign">
      <div class="small">የጠየቀ</div>
      <div class="line"></div>
    </div>
    <div class="sign">
      <div class="small">የፀደቀ</div>
      <div class="line"></div>
    </div>
    <div class="sign">
      <div class="small">የከፈለ</div>
      <div class="line"></div>
    </div>
  </div>

  <div class="mt-16 small muted">
    ማብራሪያ: የነዳጅ ክፍያ ሪፖርት (Programs) በላይ ተዘርዝሯል። የሪፖርቱ ቁጥር: #<?php echo $id; ?> — ተዘጋጀበት: <?php echo htmlspecialchars($today); ?>
  </div>
</body>
</html>