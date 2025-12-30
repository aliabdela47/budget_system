<?php
require_once 'includes/init.php';
header('Content-Type: application/json');

$plate_number = $_GET['plate_number'] ?? '';

if ($plate_number !== '') {
    $stmt = $pdo->prepare("SELECT new_gauge FROM fuel_transactions WHERE plate_number = ? ORDER BY date DESC, id DESC LIMIT 1");
    $stmt->execute([$plate_number]);
    $last_gauge = $stmt->fetchColumn();
    echo json_encode(['last_gauge' => $last_gauge ? (float)$last_gauge : 0]);
} else {
    echo json_encode(['last_gauge' => 0]);
}