<?php
include 'includes/db.php';

if (isset($_GET['query'])) {
    $query = '%' . $_GET['query'] . '%';
    $stmt = $pdo->prepare("SELECT id, name, salary, directorate FROM employees WHERE name LIKE ? LIMIT 10");
    $stmt->execute([$query]);
    $employees = $stmt->fetchAll();
    
    if (count($employees) > 0) {
        foreach ($employees as $employee) {
            echo '<div class="employee-search-result" onclick="selectEmployee(' . 
                 $employee['id'] . ', \'' . addslashes($employee['name']) . '\', ' . 
                 $employee['salary'] . ', \'' . addslashes($employee['directorate']) . '\')">' .
                 htmlspecialchars($employee['name']) . ' (' . htmlspecialchars($employee['directorate']) . ')' .
                 '</div>';
        }
    } else {
        echo '<div class="employee-search-result">No employees found</div>';
    }
}
?>