<?php
require_once 'includes/init.php';
header('Content-Type: application/json');

try {
    $budget_type = $_GET['budget_type'] ?? 'governmental';
    $owner_id = isset($_GET['owner_id']) && $_GET['owner_id'] !== '' ? (int)$_GET['owner_id'] : null;
    $year = (int)($_GET['year'] ?? (date('Y') - 8));

    $response = ['ok' => true];

    if ($budget_type === 'program') {
        // Optional: owner-specific remaining (not used by current UI but useful)
        if ($owner_id) {
            // Try p_budgets first
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(remaining_yearly),0) FROM p_budgets WHERE year=? AND owner_id=?");
            $stmt->execute([$year, $owner_id]);
            $ownerRem = (float)$stmt->fetchColumn();

            // Fallback to budgets (legacy program yearly rows)
            if ($ownerRem <= 0) {
                $stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(remaining_yearly),0)
                    FROM budgets
                    WHERE budget_type='program' AND monthly_amount=0 AND year=? AND owner_id=?
                ");
                $stmt->execute([$year, $owner_id]);
                $ownerRem = (float)$stmt->fetchColumn();
            }
            $response['programOwnerRemainingYearly'] = $ownerRem;
        }

        // Bureau total remaining
        // Try p_budgets first
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(remaining_yearly), 0) FROM p_budgets WHERE year=?");
        $stmt->execute([$year]);
        $total = (float)$stmt->fetchColumn();

        // Fallback to budgets (legacy program yearly rows)
        if ($total <= 0) {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(remaining_yearly), 0)
                FROM budgets
                WHERE budget_type='program' AND monthly_amount=0 AND year=?
            ");
            $stmt->execute([$year]);
            $total = (float)$stmt->fetchColumn();
        }

        $response['programsTotalYearly'] = $total;

    } else {
        // Governmental
        if ($owner_id) {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(remaining_yearly), 0)
                FROM budgets
                WHERE budget_type='governmental' AND monthly_amount=0 AND year=? AND owner_id=?
            ");
            $stmt->execute([$year, $owner_id]);
            $response['govtOwnerYearlyRemaining'] = (float)$stmt->fetchColumn();
        } else {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(remaining_yearly), 0)
                FROM budgets
                WHERE budget_type='governmental' AND monthly_amount=0 AND year=?
            ");
            $stmt->execute([$year]);
            $response['govtBureauRemainingYearly'] = (float)$stmt->fetchColumn();
        }
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}