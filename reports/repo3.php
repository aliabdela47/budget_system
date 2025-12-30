<?php
include '../includes/db.php';

// Get search and filter parameters from URL
$search_query = $_GET['search_query'] ?? '';
$filter_plate = $_GET['filter_plate'] ?? '';

// Build the base query
$sql = "SELECT f.*, o.name as owner_name, o.code as owner_code
        FROM fuel_transactions f
        JOIN budget_owners o ON f.owner_id = o.id";

$conditions = [];
$params = [];

// Add conditions based on filters
if (!empty($search_query)) {
    $conditions[] = "(f.driver_name LIKE ? OR f.plate_number LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

if (!empty($filter_plate)) {
    $conditions[] = "f.plate_number = ?";
    $params[] = $filter_plate;
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}

// Add ordering
$sql .= " ORDER BY f.date DESC";

// Execute the final query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Filtered Fuel Transactions Report</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 12px; }
        .report-container { width: 95%; margin: auto; }
        h1 { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .footer { text-align: center; margin-top: 20px; font-size: 10px; color: #777; }
        @media print {
            body { -webkit-print-color-adjust: exact; }
            @page { size: A4 landscape; margin: 0.5in; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="report-container">
        <h1>Filtered Fuel Transactions Report</h1>
        <p>
            <strong>Filter Criteria:</strong>
            <?php
            $criteria = [];
            if (!empty($search_query)) $criteria[] = "Search for '<strong>" . htmlspecialchars($search_query) . "</strong>'";
            if (!empty($filter_plate)) $criteria[] = "Plate Number: <strong>" . htmlspecialchars($filter_plate) . "</strong>";
            echo empty($criteria) ? 'None' : implode(', ', $criteria);
            ?>
        </p>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Owner</th>
                    <th>Driver</th>
                    <th>Plate No</th>
                    <th>Month</th>
                    <th>Distance (km)</th>
                    <th>Liters</th>
                    <th>Price/Liter</th>
                    <th>Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center;">No transactions found matching criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php
                    $total_liters = 0;
                    $total_cost = 0;
                    ?>
                    <?php foreach ($transactions as $t): ?>
                        <tr>
                            <td><?php echo date('Y-m-d', strtotime($t['date'])); ?></td>
                            <td><?php echo htmlspecialchars($t['owner_code']); ?></td>
                            <td><?php echo htmlspecialchars($t['driver_name']); ?></td>
                            <td><?php echo htmlspecialchars($t['plate_number']); ?></td>
                            <td><?php echo htmlspecialchars($t['et_month']); ?></td>
                            <td><?php echo number_format($t['journey_distance'], 2); ?></td>
                            <td><?php echo number_format($t['refuelable_amount'], 2); ?></td>
                            <td><?php echo number_format($t['fuel_price'], 2); ?></td>
                            <td><?php echo number_format($t['total_amount'], 2); ?></td>
                        </tr>
                        <?php
                        $total_liters += $t['refuelable_amount'];
                        $total_cost += $t['total_amount'];
                        ?>
                    <?php endforeach; ?>
                    <tr style="font-weight: bold; background-color: #f2f2f2;">
                        <td colspan="6" style="text-align: right;">Total:</td>
                        <td><?php echo number_format($total_liters, 2); ?></td>
                        <td>-</td>
                        <td><?php echo number_format($total_cost, 2); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="footer">
            Printed on: <?php echo date('Y-m-d H:i:s'); ?>
        </div>
    </div>
</body>
</html>