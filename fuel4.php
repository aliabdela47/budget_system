```php
<?php
include 'includes/db.php';
include 'includes/functions.php';
if (!isset($_SESSION['user_id'])) header('Location: index.php');

$owners = $pdo->query("SELECT * FROM budget_owners")->fetchAll();
$vehicles = $pdo->query("SELECT * FROM vehicles")->fetchAll(); // Fetch vehicles for dropdown
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

$et_info = getEtMonthAndQuarter(date('Y-m-d'));

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $owner_id = $_POST['owner_id'];
    $driver_name = $_POST['driver_name'];
    $plate_number = $_POST['plate_number'];
    $et_month = $_POST['et_month'];
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
    if ($last_new_gauge && $current_gauge < $last_new_gauge) {
        $message = 'Gauge error: Current gauge less than expected new gauge';
    } else {
        $pdo->beginTransaction();
        try {
            // Deduct from budget code ID 5 (Sansii kee Sukutih 6217)
            $fuel_code_id = 5; // Fixed ID for fuel budget code
            $year = date('Y') - 8; // Ethiopian year
            $quarterMap = [
                'ሐምሌ' => 1, 'ነሐሴ' => 1, 'መስከረም' => 1,
                'ጥቅምት' => 2, 'ህዳር' => 2, 'ታኅሳስ' => 2,
                'ጥር' => 3, 'የካቲቷ' => 3, 'መጋቢቷ' => 3,
                'ሚያዝያ' => 4, 'ግንቦቷ' => 4, 'ሰኔ' => 4,
            ];
            $quarter = $quarterMap[$et_month] ?? 0;

            // Fetch monthly budget for fuel
            $stmt = $pdo->prepare("SELECT * FROM budgets WHERE owner_id = ? AND code_id = ? AND year = ? AND month = ?");
            $stmt->execute([$owner_id, $fuel_code_id, $year, $et_month]);
            $budget = $stmt->fetch();

            if ($budget) {
                $new_remaining_monthly = $budget['remaining_monthly'] - $total_amount;
                if ($new_remaining_monthly < 0) {
                    throw new Exception('Insufficient remaining monthly budget for fuel.');
                }
                $stmt = $pdo->prepare("UPDATE budgets SET remaining_monthly = ? WHERE id = ?");
                $stmt->execute([$new_remaining_monthly, $budget['id']]);
            } else {
                // Fetch yearly budget if monthly not found
                $stmt = $pdo->prepare("SELECT * FROM budgets WHERE owner_id = ? AND code_id = ? AND year = ? AND monthly_amount = 0");
                $stmt->execute([$owner_id, $fuel_code_id, $year]);
                $budget_yearly = $stmt->fetch();

                if ($budget_yearly) {
                    $new_remaining_yearly = $budget_yearly['remaining_yearly'] - $total_amount;
                    if ($new_remaining_yearly < 0) {
                        throw new Exception('Insufficient remaining yearly budget for fuel.');
                    }
                    $stmt = $pdo->prepare("UPDATE budgets SET remaining_yearly = ? WHERE id = ?");
                    $stmt->execute([$new_remaining_yearly, $budget_yearly['id']]);
                } else {
                    throw new Exception('No fuel budget allocated for the selected month.');
                }
            }

            // Insert or update fuel transaction
            if (isset($_POST['id']) && $_POST['action'] == 'update') {
                $stmt = $pdo->prepare("UPDATE fuel_transactions SET owner_id = ?, driver_name = ?, plate_number = ?, et_month = ?, current_gauge = ?, journey_distance = ?, fuel_price = ?, refuelable_amount = ?, total_amount = ?, new_gauge = ?, gauge_gap = ? WHERE id = ?");
                $stmt->execute([$owner_id, $driver_name, $plate_number, $et_month, $current_gauge, $journey_distance, $fuel_price, $refuelable_amount, $total_amount, $new_gauge, $gauge_gap, $_POST['id']]);
                $message = 'Fuel transaction updated';
            } else {
                $stmt = $pdo->prepare("INSERT INTO fuel_transactions (owner_id, driver_name, plate_number, et_month, previous_gauge, current_gauge, journey_distance, fuel_price, refuelable_amount, total_amount, new_gauge, gauge_gap) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$owner_id, $driver_name, $plate_number, $et_month, $last_new_gauge, $current_gauge, $journey_distance, $fuel_price, $refuelable_amount, $total_amount, $new_gauge, $gauge_gap]);
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
    <link rel="stylesheet" href="css/styles.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="js/scripts.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <!-- Same navbar as dashboard.php -->
    </nav>
    <div class="container">
        <div class="card mt-4">
            <div class="card-body">
                <h2 class="card-title">Fuel Management Form</h2>
                <?php if (isset($message)): ?>
                    <div class="alert alert-info"><?php echo $message; ?></div>
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
                                    <?php echo $o['code'] . ' - ' . $o['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ethiopian Month</label>
                        <select name="et_month" id="month" class="form-control" onchange="updateQuarter()" required>
                            <?php foreach ($etMonths as $month): ?>
                                <option value="<?php echo $month; ?>" <?php echo ($fuel && $fuel['et_month'] == $month) || (!$fuel && $et_info['etMonth'] == $month) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($month); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label class="form-label mt-2">Quarter: <span id="quarter_label"><?php echo $fuel ? $fuel['quarter'] : $et_info['quarter']; ?></span></label>
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
                        <input type="text" name="driver_name" class="form-control" value="<?php echo $fuel ? $fuel['driver_name'] : ''; ?>" required>
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
                            <th>Month</th>
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
                                <td><?php echo htmlspecialchars($f['et_month']); ?></td>
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
                    calculateFuel(); // Recalculate after setting last gauge
                });
            }
        }

        function calculateFuel() {
            const current = parseFloat(document.getElementById('current').value) || 0;
            const journey = parseFloat(document.getElementById('journey').value) || 0;
            const price = parseFloat(document.getElementById('price').value) || 0;

            const refuelable = journey / 5; // Assuming 5 km per liter as an example
            const total = refuelable * price;
            //const newGauge = current + refuelable;
            const newGauge = current + journey;
            const gap = newGauge - current;

            document.getElementById('refuelable').value = refuelable.toFixed(2);
            document.getElementById('total').value = total.toFixed(2);
            document.getElementById('new_gauge').value = newGauge.toFixed(2);
            document.getElementById('gap').value = gap.toFixed(2);
        }

        function updateQuarter() {
            const month = document.getElementById('month').value;
            const quarterMap = {
                'ሐምሌ': 1, 'ነሐሴ': 1, 'መስከረም': 1,
                'ጥቅምት': 2, 'ህዳር': 2, 'ታኅሳስ': 2,
                'ጥር': 3, 'የካቲቷ': 3, 'መጋቢቷ': 3,
                'ሚያዝያ': 4, 'ግንቦቷ': 4, 'ሰኔ': 4
            };
            document.getElementById('quarter_label').textContent = quarterMap[month] || 0;
        }
    </script>
</body>
</html>
```