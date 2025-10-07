<?php
function logUserActivity($pdo, $userId, $actionType, $actionDetail) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $device = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $stmt = $pdo->prepare("INSERT INTO user_activity_log (user_id, action_type, action_detail, timestamp, ip_address, device_info)
                           VALUES (?, ?, ?, NOW(), ?, ?)");
    $stmt->execute([$userId, $actionType, $actionDetail, $ip, $device]);
}
?>