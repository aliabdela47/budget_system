<?php
if (!isset($_SESSION)) {
    session_start();
}

$username = $_SESSION['username'] ?? 'unknown';
$role = $_SESSION['role'] ?? 'guest';

echo '<div class="mb-4 p-4 bg-blue-50 dark:bg-blue-900 rounded-lg shadow text-sm text-gray-700 dark:text-gray-200">';
echo "<strong>Logged in as:</strong> " . htmlspecialchars($role) . " (" . htmlspecialchars($username) . ")<br>";

if ($role === 'admin') {
    echo "<strong>Budget Scope:</strong> Full access to all Government and Programs Budgets.";
} elseif ($username === 'lemlem') {
    echo "<strong>Budget Scope:</strong> Government Budget – all budget owners.";
} else {
    echo "<strong>Budget Scope:</strong> Programs Budget – assigned programs only.";
}

echo '</div>';
?>