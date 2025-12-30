<?php
session_start();
include 'includes/db.php';

$fuel_trans = $pdo->query("
    SELECT f.*, o.code AS owner_code
      FROM fuel_transactions f
      JOIN budget_owners o
        ON f.owner_id = o.id
    ORDER BY f.date DESC
")->fetchAll();

if (empty($fuel_trans)) {
    echo '<tr><td colspan="7" class="px-4 py-4 text-center text-sm text-gray-500">No fuel transactions found.</td></tr>';
} else {
    foreach ($fuel_trans as $f) {
        echo '<tr>
            <td class="px-4 py-4 text-sm text-gray-900">' . date('M j, Y', strtotime($f['date'])) . '</td>
            <td class="px-4 py-4 text-sm text-gray-900">' . htmlspecialchars($f['owner_code']) . '</td>
            <td class="px-4 py-4 text-sm text-gray-900">' . htmlspecialchars($f['driver_name']) . '</td>
            <td class="px-4 py-4 text-sm text-gray-900">' . htmlspecialchars($f['plate_number']) . '</td>
            <td class="px-4 py-4 text-sm text-gray-900">' . htmlspecialchars($f['et_month']) . '</td>
            <td class="px-4 py-4 text-sm text-gray-900">' . number_format($f['total_amount'], 2) . '</td>
            <td class="px-4 py-4 text-sm">
                <div class="flex space-x-2">
                    <a href="?action=edit&id=' . $f['id'] . '" class="px-3 py-1 bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200">
                        <i class="fas fa-edit mr-1"></i> Edit
                    </a>
                    <a href="reports/fuel_transaction_report.php?id=' . $f['id'] . '" class="px-3 py-1 bg-green-100 text-green-700 rounded-md hover:bg-green-200" target="_blank">
                        <i class="fas fa-print mr-1"></i> Print
                    </a>
                    <a href="?action=delete&id=' . $f['id'] . '" class="px-3 py-1 bg-red-100 text-red-700 rounded-md hover:bg-red-200" onclick="return confirm(\'Are you sure you want to delete this transaction?\')">
                        <i class="fas fa-trash mr-1"></i> Delete
                    </a>
                </div>
            </td>
        </tr>';
    }
}
?>