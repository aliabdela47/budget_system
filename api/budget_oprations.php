<?php
session_start();
include '../includes/db.php';
include '../includes/functions.php';
include '../config/ethiopian_calendar.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get request data
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

switch ($action) {
    case 'allocate_monthly':
        allocateMonthlyBudget($data);
        break;
    case 'add_extra_budget':
        addExtraBudget($data);
        break;
    case 'get_budget_status':
        getBudgetStatus($data);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function allocateMonthlyBudget($data) {
    global $pdo, $ethiopianConfig;
    
    $owner_id = $data['owner_id'];
    $code_id = $data['code_id'];
    $month = $data['month'];
    $amount = (float) $data['amount'];
    $year = date('Y') - $ethiopianConfig['year_offset'];
    
    // Get yearly budget
    $stmt = $pdo->prepare("SELECT * FROM budgets WHERE owner_id = ? AND code_id = ? AND year = ? AND is_yearly = TRUE");
    $stmt->execute([$owner_id, $code_id, $year]);
    $yearly_budget = $stmt->fetch();
    
    if (!$yearly_budget) {
        echo json_encode(['success' => false, 'message' => 'Yearly budget not found']);
        return;
    }
    
    // Calculate available amount
    $available = $yearly_budget['yearly_amount'] - $yearly_budget['allocated_amount'];
    
    if ($amount > $available) {
        echo json_encode(['success' => false, 'message' => "Insufficient yearly budget. Available: " . number_format($available, 2)]);
        return;
    }
    
    // Create monthly budget
    $quarter = $ethiopianConfig['quarter_map'][$month] ?? 0;
    $stmt = $pdo->prepare("INSERT INTO budgets (owner_id, code_id, adding_date, year, month, monthly_amount, quarter, parent_id, allocated_amount, spent_amount, is_yearly) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, 0, FALSE)");
    $stmt->execute([$owner_id, $code_id, $year, $month, $amount, $quarter, $yearly_budget['id'], $amount]);
    
    // Update yearly allocated amount
    $new_allocated = $yearly_budget['allocated_amount'] + $amount;
    $stmt = $pdo->prepare("UPDATE budgets SET allocated_amount = ? WHERE id = ?");
    $stmt->execute([$new_allocated, $yearly_budget['id']]);
    
    echo json_encode(['success' => true, 'message' => 'Monthly budget allocated successfully']);
}

function addExtraBudget($data) {
    global $pdo;
    
    $monthly_budget_id = $data['monthly_budget_id'];
    $additional_amount = (float) $data['amount'];
    $reason = $data['reason'];
    
    // Get monthly budget
    $stmt = $pdo->prepare("SELECT * FROM budgets WHERE id = ?");
    $stmt->execute([$monthly_budget_id]);
    $monthly_budget = $stmt->fetch();
    
    if (!$monthly_budget) {
        echo json_encode(['success' => false, 'message' => 'Monthly budget not found']);
        return;
    }
    
    // Get yearly budget
    $stmt = $pdo->prepare("SELECT * FROM budgets WHERE id = ?");
    $stmt->execute([$monthly_budget['parent_id']]);
    $yearly_budget = $stmt->fetch();
    
    // Check available yearly budget
    $yearly_available = $yearly_budget['yearly_amount'] - $yearly_budget['allocated_amount'];
    if ($additional_amount > $yearly_available) {
        echo json_encode(['success' => false, 'message' => "Insufficient yearly budget. Available: " . number_format($yearly_available, 2)]);
        return;
    }
    
    // Update monthly budget
    $new_monthly_amount = $monthly_budget['monthly_amount'] + $additional_amount;
    $new_remaining = $monthly_budget['remaining_monthly'] + $additional_amount;
    
    $stmt = $pdo->prepare("UPDATE budgets SET monthly_amount = ?, remaining_monthly = ? WHERE id = ?");
    $stmt->execute([$new_monthly_amount, $new_remaining, $monthly_budget_id]);
    
    // Update yearly allocated amount
    $new_allocated = $yearly_budget['allocated_amount'] + $additional_amount;
    $stmt = $pdo->prepare("UPDATE budgets SET allocated_amount = ? WHERE id = ?");
    $stmt->execute([$new_allocated, $yearly_budget['id']]);
    
    // Record revision
    $stmt = $pdo->prepare("INSERT INTO budget_revisions (budget_id, previous_amount, new_amount, reason, revised_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$monthly_budget_id, $monthly_budget['monthly_amount'], $new_monthly_amount, $reason, $_SESSION['user_id']]);
    
    echo json_encode(['success' => true, 'message' => 'Extra budget added successfully']);
}

function getBudgetStatus($data) {
    global $pdo;
    
    $owner_id = $data['owner_id'];
    $code_id = $data['code_id'];
    $year = $data['year'];
    
    // Get yearly budget
    $stmt = $pdo->prepare("SELECT * FROM budgets WHERE owner_id = ? AND code_id = ? AND year = ? AND is_yearly = TRUE");
    $stmt->execute([$owner_id, $code_id, $year]);
    $yearly_budget = $stmt->fetch();
    
    if (!$yearly_budget) {
        echo json_encode(['success' => false, 'message' => 'Yearly budget not found']);
        return;
    }
    
    // Get monthly budgets
    $stmt = $pdo->prepare("SELECT * FROM budgets WHERE parent_id = ? ORDER BY FIELD(month, 'መስከረም', 'ጥቅምት', 'ኅዳር', 'ታኅሣሥ', 'ጥር', 'የካቲት', 'መጋቢት', 'ሚያዝያ', 'ግንቦት', 'ሰኔ', 'ሐምሌ', 'ነሐሴ')");
    $stmt->execute([$yearly_budget['id']]);
    $monthly_budgets = $stmt->fetchAll();
    
    // Calculate quarterly summaries
    $quarterly_data = [];
    foreach ($monthly_budgets as $budget) {
        $quarter = $budget['quarter'];
        if (!isset($quarterly_data[$quarter])) {
            $quarterly_data[$quarter] = [
                'allocated' => 0,
                'spent' => 0,
                'remaining' => 0
            ];
        }
        
        $quarterly_data[$quarter]['allocated'] += $budget['monthly_amount'];
        $quarterly_data[$quarter]['spent'] += $budget['spent_amount'];
        $quarterly_data[$quarter]['remaining'] += ($budget['monthly_amount'] - $budget['spent_amount']);
    }
    
    echo json_encode([
        'success' => true,
        'yearly' => $yearly_budget,
        'monthly' => $monthly_budgets,
        'quarterly' => $quarterly_data
    ]);
}