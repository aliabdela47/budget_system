<?php
session_start();
include 'includes/db.php';
include 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Fetch dropdown data
$owners      = $pdo->query("SELECT * FROM budget_owners")->fetchAll();
$vehicles    = $pdo->query("SELECT * FROM vehicles")->fetchAll();
$fuel_trans  = $pdo->query("
    SELECT f.*, o.code AS owner_code
      FROM fuel_transactions f
      JOIN budget_owners o
        ON f.owner_id = o.id
")->fetchAll();

// Last fuel price to prefill the form
$last_price = $pdo
    ->query("SELECT fuel_price FROM fuel_transactions ORDER BY date DESC LIMIT 1")
    ->fetchColumn() ?: 0;

// Editing existing record?
$fuel = null;
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM fuel_transactions WHERE id = ?");
    $stmt->execute([ $_GET['id'] ]);
    $fuel = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $owner_id         = $_POST['owner_id'];
    $driver_name      = $_POST['driver_name'];
    $plate_number     = $_POST['plate_number'];
    $et_month         = $_POST['et_month'];
    $current_gauge    = (float)($_POST['current_gauge']    ?? 0);
    $journey_distance = (float)($_POST['journey_distance'] ?? 0);
    $fuel_price       = (float)($_POST['fuel_price']       ?? 0);
    $refuelable_amt   = (float)($_POST['refuelable_amount']?? 0);
    $total_amount     = (float)($_POST['total_amount']     ?? 0);
    $new_gauge        = (float)($_POST['new_gauge']        ?? 0);
    $gauge_gap        = (float)($_POST['gauge_gap']        ?? 0);

    // Validate gauge progression
    $stmt = $pdo->prepare("
        SELECT new_gauge
          FROM fuel_transactions
         WHERE plate_number = ?
         ORDER BY date DESC
         LIMIT 1
    ");
    $stmt->execute([ $plate_number ]);
    $last_new_gauge = (float)($stmt->fetchColumn() ?: 0);

    if ($last_new_gauge && $current_gauge < $last_new_gauge) {
        $message = 'Gauge error: Current gauge less than expected new gauge.';
    } else {
        $pdo->beginTransaction();
        try {
            // 1) Determine Ethiopian year and quarter from selected month
            $year        = date('Y') - 8;
            $quarterMap = [
                'ሐምሌ'=>1,'ነሐሴ'=>1,'መስከረም'=>1,
                'ጥቅምት'=>2,'ህዳር'=>2,'ታኅሳስ'=>2,
                'ጥር'=>3,'የካቲቷ'=>3,'መጋቢቷ'=>3,
                'ሚያዝያ'=>4,'ግንቦቷ'=>4,'ሰኔ'=>4
            ];
            $quarter = $quarterMap[$et_month] ?? 0;

            // 2) Deduct from monthly budget if exists, else from yearly
            $fuel_code_id = 5; // Sansii kee Sukutih
            // Fetch monthly budget row
            $stmt = $pdo->prepare("
                SELECT * 
                  FROM budgets
                 WHERE owner_id   = ?
                   AND code_id    = ?
                   AND year       = ?
                   AND month      = ?
            ");
            $stmt->execute([ $owner_id, $fuel_code_id, $year, $et_month ]);
            $budget = $stmt->fetch();

            if ($budget) {
                // Use remaining_monthly field for fuel
                $new_rem_month = $budget['remaining_monthly'] - $total_amount;
                if ($new_rem_month < 0) {
                    throw new Exception('Insufficient remaining monthly budget for fuel.');
                }
                $stmt = $pdo->prepare("
                    UPDATE budgets
                       SET remaining_monthly = ?
                     WHERE id = ?
                ");
                $stmt->execute([ $new_rem_month, $budget['id'] ]);
            } else {
                // Fallback to yearly
                $stmt = $pdo->prepare("
                    SELECT *
                      FROM budgets
                     WHERE owner_id     = ?
                       AND code_id      = ?
                       AND year         = ?
                       AND monthly_amount = 0
                ");
                $stmt->execute([ $owner_id, $fuel_code_id, $year ]);
                $budget_yearly = $stmt->fetch();
                if (! $budget_yearly) {
                    throw new Exception('No fuel budget allocated.');
                }
                $new_rem_year = $budget_yearly['remaining_yearly'] - $total_amount;
                if ($new_rem_year < 0) {
                    throw new Exception('Insufficient remaining yearly budget for fuel.');
                }
                $stmt = $pdo->prepare("
                    UPDATE budgets
                       SET remaining_yearly = ?
                     WHERE id = ?
                ");
                $stmt->execute([ $new_rem_year, $budget_yearly['id'] ]);
            }

            // 3) Insert or update fuel transaction
            if ($fuel) {
                $stmt = $pdo->prepare("
                    UPDATE fuel_transactions
                       SET owner_id           = ?,
                           driver_name        = ?,
                           plate_number       = ?,
                           et_month           = ?,
                           previous_gauge     = ?,
                           current_gauge      = ?,
                           journey_distance   = ?,
                           fuel_price         = ?,
                           refuelable_amount  = ?,
                           total_amount       = ?,
                           new_gauge          = ?,
                           gauge_gap          = ?
                     WHERE id = ?
                ");
                $stmt->execute([
                    $owner_id, $driver_name, $plate_number, $et_month,
                    $last_new_gauge, $current_gauge, $journey_distance,
                    $fuel_price, $refuelable_amt, $total_amount,
                    $new_gauge, $gauge_gap, $fuel['id']
                ]);
                $message = 'Fuel transaction updated';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO fuel_transactions (
                      owner_id,
                      driver_name,
                      plate_number,
                      et_month,
                      previous_gauge,
                      current_gauge,
                      journey_distance,
                      fuel_price,
                      refuelable_amount,
                      total_amount,
                      new_gauge,
                      gauge_gap
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $owner_id, $driver_name, $plate_number, $et_month,
                    $last_new_gauge, $current_gauge, $journey_distance,
                    $fuel_price, $refuelable_amt, $total_amount,
                    $new_gauge, $gauge_gap
                ]);
                $message = 'Fuel transaction added';
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Error: ' . $e->getMessage();
        }
    }
}

// Handle delete
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
    $stmt = $pdo->prepare("DELETE FROM fuel_transactions WHERE id = ?");
    $stmt->execute([ $_GET['id'] ]);
    header('Location: fuel_management.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Fuel Management – Budget System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
  <link href="css/styles.css" rel="stylesheet">
  <script src="js/scripts.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</head>
<body class="dashboard-body">
  <?php include 'includes/sidebar.php'; ?>

  <div class="main-content">
    <div class="container-fluid mt-4">
      <div class="card">
        <div class="card-body">
          <h2 class="card-title">Fuel Management Form</h2>
          <?php if (isset($message)): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
          <?php endif; ?>

          <form method="post" class="needs-validation" novalidate>
            <?php if ($fuel): ?>
              <input type="hidden" name="id" value="<?php echo $fuel['id']; ?>">
              <input type="hidden" name="action" value="update">
            <?php endif; ?>

            <div class="mb-3">
              <label class="form-label">Budget Owner</label>
              <select name="owner_id" id="owner_id" class="form-control" onchange="loadFuelRemaining()" required>
                <option value="">Select owner…</option>
                <?php foreach ($owners as $o): ?>
                  <option 
                    value="<?php echo $o['id']; ?>" 
                    <?php echo ($fuel && $fuel['owner_id']==$o['id'])?'selected':''; ?>>
                    <?php echo htmlspecialchars($o['code'] . ' – ' . $o['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label">Ethiopian Month</label>
              <select name="et_month" id="et_month" class="form-control" onchange="loadFuelRemaining()" required>
                <option value="">Select month…</option>
                <?php
                  $etMonths = ['መስከረም','ጥቅምት','ህዳር','ታኅሳስ','ጥር',
                               'የካቲቷ','መጋቢቷ','ሚያዝያ','ግንቦቷ','ሰኔ',
                               'ሐምሌ','ነሐሴ'];
                  foreach ($etMonths as $m): 
                    $sel = ($fuel && $fuel['et_month']==$m) ? 'selected' : '';
                ?>
                  <option value="<?php echo $m;?>" <?php echo $sel;?>><?php echo $m;?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="row g-3">
              <div class="col-md-6 mb-3">
                <label class="form-label">Plate Number</label>
                <select 
                  name="plate_number" 
                  id="plate_number" 
                  class="form-control select2" 
                  onchange="fetchLastGauge(this.value)" 
                  required>
                  <option value="">Select vehicle…</option>
                  <?php foreach ($vehicles as $v): ?>
                    <option 
                      value="<?php echo htmlspecialchars($v['plate_no']); ?>" 
                      <?php echo ($fuel && $fuel['plate_number']==$v['plate_no'])?'selected':''; ?>>
                      <?php echo htmlspecialchars($v['plate_no']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-6 mb-3">
                <label class="form-label">Driver Name</label>
                <input 
                  type="text" 
                  name="driver_name" 
                  class="form-control" 
                  value="<?php echo $fuel['driver_name'] ?? ''; ?>" 
                  required>
              </div>
            </div>

            <div class="row g-3">
              <div class="col-md-4 mb-3">
                <label class="form-label">Current Gauge</label>
                <input 
                  type="number" 
                  step="0.01" 
                  id="current" 
                  name="current_gauge" 
                  class="form-control" 
                  value="<?php echo $fuel['current_gauge'] ?? ''; ?>" 
                  onchange="calculateFuel()" 
                  required>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Journey Distance (Km)</label>
                <input 
                  type="number" 
                  step="0.01" 
                  id="journey" 
                  name="journey_distance" 
                  class="form-control" 
                  value="<?php echo $fuel['journey_distance'] ?? ''; ?>" 
                  onchange="calculateFuel()" 
                  required>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Fuel Price /L</label>
                <input 
                  type="number" 
                  step="0.01" 
                  id="price" 
                  name="fuel_price" 
                  class="form-control" 
                  value="<?php echo $fuel['fuel_price'] ?? $last_price; ?>" 
                  onchange="calculateFuel()" 
                  required>
              </div>
            </div>

            <div class="row g-3">
              <div class="col-md-3 mb-3">
                <label class="form-label">Refuelable (L)</label>
                <input type="number" step="0.01" id="refuelable" name="refuelable_amount" class="form-control" readonly>
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">Total Amount</label>
                <input type="number" step="0.01" id="total" name="total_amount" class="form-control" readonly>
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">New Gauge</label>
                <input type="number" step="0.01" id="new_gauge" name="new_gauge" class="form-control" readonly>
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">Gauge Gap</label>
                <input type="number" step="0.01" id="gap" name="gauge_gap" class="form-control" readonly>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Remaining Monthly Fuel Budget</label>
              <div>
                <span id="rem_monthly">0.00</span> birr
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Remaining Quarterly Fuel Budget</label>
              <div>
                <span id="rem_quarterly">0.00</span> birr
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Remaining Yearly Fuel Budget</label>
              <div>
                <span id="rem_yearly">0.00</span> birr
              </div>
            </div>

            <button type="submit" class="btn btn-primary">
              <?php echo $fuel ? 'Update' : 'Save'; ?>
            </button>
            <button type="button" class="btn btn-info" onclick="window.print()">
              Print
            </button>
          </form>
        </div>
      </div>

      <h3 class="mt-4">Existing Fuel Transactions</h3>
      <table class="table table-striped">
        <thead>
          <tr>
            <th>ID</th>
            <th>Owner</th>
            <th>Driver</th>
            <th>Plate</th>
            <th>Month</th>
            <th>Total Amount</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($fuel_trans as $f): ?>
            <tr>
              <td><?php echo $f['id']; ?></td>
              <td><?php echo htmlspecialchars($f['owner_code']); ?></td>
              <td><?php echo htmlspecialchars($f['driver_name']); ?></td>
              <td><?php echo htmlspecialchars($f['plate_number']); ?></td>
              <td><?php echo htmlspecialchars($f['et_month']); ?></td>
              <td><?php echo number_format($f['total_amount'], 2); ?></td>
              <td>
                <a href="?action=edit&id=<?php echo $f['id']; ?>" class="btn btn-secondary btn-sm">Edit</a>
                <a href="?action=delete&id=<?php echo $f['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">Delete</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <script>
    // Sidebar toggle
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('.select2').forEach(el => {
        $(el).select2({ placeholder: 'Select…', allowClear: true });
      });
      document.getElementById('sidebarToggle')?.addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('active');
      });
    });

    // Fetch last gauge for selected plate
    function fetchLastGauge(plate) {
      if (!plate) return;
      $.get('get_last_gauge.php', { plate_number: plate }, data => {
        const resp = JSON.parse(data);
        document.getElementById('current').value = resp.last_gauge || 0;
        calculateFuel();
      });
    }

    // Calculate liters & cost & gauges
    function calculateFuel() {
      const current = parseFloat(document.getElementById('current').value) || 0;
      const journey = parseFloat(document.getElementById('journey').value)   || 0;
      const price   = parseFloat(document.getElementById('price').value)     || 0;

      // your business logic (e.g. 5 km per liter)
      const refuel = journey / 5;
      const total  = refuel * price;
      const newG   = current + journey;
      const gap    = newG - current;

      document.getElementById('refuelable').value = refuel.toFixed(2);
      document.getElementById('total').value      = total.toFixed(2);
      document.getElementById('new_gauge').value  = newG.toFixed(2);
      document.getElementById('gap').value        = gap.toFixed(2);
    }

    // Load remaining budgets via AJAX
    function loadFuelRemaining() {
      const ownerId = document.getElementById('owner_id').value;
      const etMonth = document.getElementById('et_month').value;
      const codeId  = 5; // fuel code

      if (!ownerId || !etMonth) return;

      fetch(`get_remaining.php?owner_id=${ownerId}&code_id=${codeId}&month=${encodeURIComponent(etMonth)}`)
        .then(res => res.json())
        .then(data => {
          document.getElementById('rem_monthly').textContent   = parseFloat(data.remaining_month).toFixed(2);
          document.getElementById('rem_quarterly').textContent = parseFloat(data.remaining_quarter).toFixed(2);
          document.getElementById('rem_yearly').textContent    = parseFloat(data.remaining_year).toFixed(2);
        })
        .catch(console.error);
    }
    
    
    
    
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>