<?php
header('Content-Type: application/json');
require_once 'includes/init.php';

try {
    // Fetches all employees, newest first, for the main list view.
    $stmt = $pdo->query("SELECT id, name, name_am, salary, taamagoli, directorate, photo FROM emp_list ORDER BY id DESC");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'employees' => $employees]);

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'error' => 'Database query failed: ' . $e->getMessage()]);
}