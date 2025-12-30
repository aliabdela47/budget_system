<?php
include 'includes/db.php';
include 'includes/functions.php';
if (!isset($_SESSION['user_id'])) header('Location: index.php');

$owners = $pdo->query("SELECT * FROM budget_owners")->fetchAll();
$vehicles = $pdo->query("SELECT * FROM vehicles")->fetchAll();
$fuel_trans = $pdo->query("SELECT f.*, o.code AS owner_code 
                           FROM fuel_transactions f 
                           JOIN budget_owners o ON f.owner_id = o.id")->fetchAll();
$last_price = $pdo->query("SELECT fuel_price FROM fuel_transactions ORDER BY date DESC LIMIT 1")->fetchColumn() ?: 0;

$fuel = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM fuel_transactions WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $fuel = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $owner_id = $_POST['owner_id'];
    $driver_name = $_POST['driver_name'];
    $plate_number = $_POST['plate_number'];
    $current_gauge = (float)$_POST['current_gauge'];
    $journey_distance = (float)$_POST['journey_distance'];
    $fuel_price = (float)$_POST['fuel_price'];
    $refuelable_amount = (float)$_POST['refuelable_amount'];
    $total_amount = (float)$_POST['total_amount'];
    $new_gauge = (float)$_POST['new_gauge'];
    $gauge_gap = (float)$_POST['gauge_gap'];

    $stmt = $pdo->prepare("SELECT new_gauge FROM fuel_transactions WHERE plate_number = ? ORDER BY date DESC LIMIT 1");
    $stmt->execute([$plate_number]);
    $last_new_gauge = $stmt->fetchColumn() ?: 0;
    
    // Get current Ethiopian month
   // $et_info = getEtMonthAndQuarter(date('Y-m-d'));
  //  $et_month = $et_info['etMonth'];
    
    if ($last_new_gauge && $current_gauge < $last_new_gauge) {
        $message = 'Gauge error: Current gauge less than expected new gauge';
    } else {
        $pdo->beginTransaction();
        try {
            $fuel_code_id = 5; // Fixed ID for Sansii kee Sukutih 6217
            $year = date('Y') - 8;

            // Check if we have a monthly budget
            $stmt = $pdo->prepare("SELECT * FROM budgets WHERE owner_id = ? AND code_id = ? AND year = ? AND month = ?");
            $stmt->execute([$owner_id, $fuel_code_id, $year, $et_month]);
            $budget = $stmt->fetch();

            if ($budget) {
                // Check if we have the right field names in budgets table
                $remaining_field = isset($budget['remaining_monthly']) ? 'remaining_monthly' : 
                                  (isset($budget['monthly_amount']) ? 'monthly_amount' : 'amount');
                
                $new_remaining = $budget[$remaining_field] - $total_amount;
                if ($new_remaining < 0) {
                    throw new Exception('Insufficient remaining monthly budget for fuel.');
                }
                
                $stmt = $pdo->prepare("UPDATE budgets SET $remaining_field = ? WHERE id = ?");
                $stmt->execute([$new_remaining, $budget['id']]);
            } else {
                // Check yearly budget
                $stmt = $pdo->prepare("SELECT * FROM budgets WHERE owner_id = ? AND code_id = ? AND year = ? AND (monthly_amount = 0 OR month IS NULL OR month = '')");
                $stmt->execute([$owner_id, $fuel_code_id, $year]);
                $budget_yearly = $stmt->fetch();

                if ($budget_yearly) {
                    $remaining_field = isset($budget_yearly['remaining_yearly']) ? 'remaining_yearly' : 
                                      (isset($budget_yearly['yearly_amount']) ? 'yearly_amount' : 'amount');
                    
                    $new_remaining = $budget_yearly[$remaining_field] - $total_amount;
                    if ($new_remaining < 0) {
                        throw new Exception('Insufficient remaining yearly budget for fuel.');
                    }
                    
                    $stmt = $pdo->prepare("UPDATE budgets SET $remaining_field = ? WHERE id = ?");
                    $stmt->execute([$new_remaining, $budget_yearly['id']]);
                } else {
                    throw new Exception('No fuel budget allocated.');
                }
            }

            if (isset($_POST['id']) && $_POST['action'] == 'update') {
                $stmt = $pdo->prepare("UPDATE fuel_transactions SET owner_id = ?, driver_name = ?, plate_number = ?, current_gauge = ?, journey_distance = ?, fuel_price = ?, refuelable_amount = ?, total_amount = ?, new_gauge = ?, gauge_gap = ?, et_month = ? WHERE id = ?");
                $stmt->execute([$owner_id, $driver_name, $plate_number, $current_gauge, $journey_distance, $fuel_price, $refuelable_amount, $total_amount, $new_gauge, $gauge_gap, $et_month, $_POST['id']]);
                $message = 'Fuel transaction updated';
            } else {
                $stmt = $pdo->prepare("INSERT INTO fuel_transactions (owner_id, driver_name, plate_number, previous_gauge, current_gauge, journey_distance, fuel_price, refuelable_amount, total_amount, new_gauge, gauge_gap, et_month) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$owner_id, $driver_name, $plate_number, $last_new_gauge, $current_gauge, $journey_distance, $fuel_price, $refuelable_amount, $total_amount, $new_gauge, $gauge_gap, $et_month]);
                $message = 'Fuel transaction added';
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Error: ' . $e->getMessage();
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM fuel_transactions WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $message = 'Fuel transaction deleted';
    header('Location: fuel_management.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fuel Management - Budget System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="js/scripts.js"></script>
</head>
<body class="dashboard-body">
    <?php include 'includes/sidebar.php'; ?>

    <div class="container-fluid">
        <div class="card dashboard-card mt-4">
            <div class="card-body">
                <h2 class="card-title">Fuel Management Form</h2>
                <?php if (isset($message)): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <form method="post">
                    <?php if ($fuel): ?>
                        <input type="hidden" name="id" value="<?php echo $fuel['id']; ?>">
                        <input type="hidden" name="action" value="update">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Budget Owners Code</label>
                        <select name="owner_id" class="form-control" required>
                            <?php foreach ($owners as $o): ?>
                                <option value="<?php echo $o['id']; ?>" <?php echo $fuel && $fuel['owner_id'] == $o['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($o['code'] . ' - ' . $o['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                      <label class="form-label">Ethiopian Month</label>
                      <select name="et_month" class="form-control" required>
                      <?php
                      $etMonths = ['መስከረም','ጥቅምት','ህዳር','ታኅሳስ','ጥር','የካቲቷ','መጋቢቷ','ሚያዝያ','ግንቦቷ','ሰኔ','ሐምሌ','ነሐሴ'];
                      foreach ($etMonths as $month) {
                        $selected = ($fuel && $fuel['et_month'] == $month) ?
                        'selected' : '';
                        echo "<option value=\"$month\" $selected>$month</option>";
}
                      ?>
                      </select>
                      </div>
                    <div class="mb-3">
                        <label class="form-label">Plate Number</label>
                        <select name="plate_number" id="plate_number" class="form-control select2" onchange="fetchLastGauge(this.value)" required>
                            <?php foreach ($vehicles as $v): ?>
                                <option value="<?php echo htmlspecialchars($v['plate_no']); ?>" <?php echo $fuel && $fuel['plate_number'] == $v['plate_no'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($v['plate_no']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Driver Name</label>
                        <input type="text" name="driver_name" class="form-control" value="<?php echo $fuel ? htmlspecialchars($fuel['driver_name']) : ''; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Current Gauge</label>
                        <input type="number" step="0.01" id="current" name="current_gauge" class="form-control" value="<?php echo $fuel ? $fuel['current_gauge'] : ''; ?>" onchange="calculateFuel()" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Journey Distance (Km)</label>
                        <input type="number" step="0.01" id="journey" name="journey_distance" class="form-control" value="<?php echo $fuel ? $fuel['journey_distance'] : ''; ?>" onchange="calculateFuel()" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fuel Price /L</label>
                        <input type="number" step="0.01" id="price" name="fuel_price" class="form-control" value="<?php echo $fuel ? $fuel['fuel_price'] : $last_price; ?>" onchange="calculateFuel()" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Refuelable Amount (Ltr)</label>
                        <input type="number" step="0.01" id="refuelable" name="refuelable_amount" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Total Amount</label>
                        <input type="number" step="0.01" id="total" name="total_amount" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Gauge</label>
                        <input type="number" step="0.01" id="new_gauge" name="new_gauge" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Gauge Gap</label>
                        <input type="number" step="0.01" id="gap" name="gauge_gap" class="form-control" readonly>
                    </div>
                    <button type="submit" class="btn btn-primary"><?php echo $fuel ? 'Update' : 'Save'; ?></button>
                    <button type="button" class="btn btn-info" onclick="window.print()">Print</button>
                </form>
                <h3 class="mt-4">Existing Fuel Transactions</h3>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Owner</th>
                            <th>Driver</th>
                            <th>Plate</th>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2').select2({
                placeholder: "Select a plate number",
                allowClear: true
            });
        });

        function fetchLastGauge(plateNumber) {
            if (plateNumber) {
                $.get('get_last_gauge.php', { plate_number: plateNumber }, function(data) {
                    const response = JSON.parse(data);
                    $('#current').val(response.last_gauge);
                    calculateFuel();
                });
            }
        }

        function calculateFuel() {
            const current = parseFloat(document.getElementById('current').value) || 0;
            const journey = parseFloat(document.getElementById('journey').value) || 0;
            const price = parseFloat(document.getElementById('price').value) || 0;

            const refuelable = journey / 5; // Placeholder fuel efficiency
            const total = refuelable * price;
            const newGauge = current + journey;
            const gap = newGauge - current;

            document.getElementById('refuelable').value = refuelable.toFixed(2);
            document.getElementById('total').value = total.toFixed(2);
            document.getElementById('new_gauge').value = newGauge.toFixed(2);
            document.getElementById('gap').value = gap.toFixed(2);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('sidebarToggle');
            if (toggleBtn && sidebar) {
                toggleBtn.addEventListener('click', () => {
                    sidebar.classList.toggle('active');
                });
            }
        });
    </script>
</body>
</html>