<?php
include 'includes/db.php';

$plate_number = $_GET['plate_number'] ?? '';

if ($plate_number) {
    $stmt = $pdo->prepare("SELECT new_gauge FROM fuel_transactions WHERE plate_number = ? ORDER BY date DESC LIMIT 1");
    $stmt->execute([$plate_number]);
    $last_gauge = $stmt->fetchColumn() ?: 0;
    echo json_encode(['last_gauge' => $last_gauge]);
} else {
    echo json_encode(['last_gauge' => 0]);
}