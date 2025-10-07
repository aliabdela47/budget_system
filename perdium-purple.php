<?php
require_once 'includes/init.php';

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function csrf_check($t) { return hash_equals($_SESSION['csrf'] ?? '', $t ?? ''); }

// Roles
$is_admin   = (($_SESSION['role'] ?? '') === 'admin');
$is_officer = (($_SESSION['role'] ?? '') === 'officer');

// Helpers
function ecYear(): int { return (int)date('Y') - 8; } // Consider a proper EC library for accuracy
function monthsEC(): array {
    return ['መስከረም','ጥቅምት','ህዳር','ታኅሳስ','ጥር','የካቲት','መጋቢት','ሚያዝያ','ግንቦት','ሰኔ','ሐምሌ','ነሃሴ'];
}
function calcPerdiem(float $rate, int $days): float {
    $days = max(1, $days);
    $A = $rate * (0.1 + 0.25 + 0.25);
    $mid = max(0, $days - 2);
    $C = $rate * (0.1 + 0.25);
    $nights = max(0, $days - 1);
    return $A + ($A * $mid) + $C + ($rate * 0.4 * $nights);
}

// Existence checks
function ownerExists(PDO $pdo, string $type, int $owner_id): bool {
    if ($type === 'program') {
        $s = $pdo->prepare("SELECT id FROM p_budget_owners WHERE id = ?");
        $s->execute([$owner_id]);
        return (bool)$s->fetchColumn();
    }
    $s = $pdo->prepare("SELECT id FROM budget_owners WHERE id = ?");
    $s->execute([$owner_id]);
    return (bool)$s->fetchColumn();
}
function employeeExists(PDO $pdo, int $employee_id): bool {
    $s = $pdo->prepare("SELECT id FROM emp_list WHERE id = ?");
    $s->execute([$employee_id]);
    return (bool)$s->fetchColumn();
}
function cityExists(PDO $pdo, int $city_id): bool {
    $s = $pdo->prepare("SELECT id FROM cities WHERE id = ?");
    $s->execute([$city_id]);
    return (bool)$s->fetchColumn();
}

// Overlap + active checks
function hasOverlap(PDO $pdo, int $employee_id, string $dep, string $arr, ?int $exclude_id = null): bool {
    $sql = "SELECT COUNT(*) FROM perdium_transactions
            WHERE employee_id = ?
              AND NOT (arrival_date < ? OR departure_date > ?)";
    $params = [$employee_id, $dep, $arr];
    if ($exclude_id) { $sql .= " AND id <> ?"; $params[] = $exclude_id; }
    $s = $pdo->prepare($sql);
    $s->execute($params);
    return ((int)$s->fetchColumn()) > 0;
}
function isEmployeeActive(PDO $pdo, int $employee_id, ?int $exclude_id = null): bool {
    $sql = "SELECT COUNT(*) FROM perdium_transactions
            WHERE employee_id = ?
              AND CURDATE() BETWEEN departure_date AND arrival_date";
    $params = [$employee_id];
    if ($exclude_id) { $sql .= " AND id <> ?"; $params[] = $exclude_id; }
    $s = $pdo->prepare($sql);
    $s->execute($params);
    return ((int)$s->fetchColumn()) > 0;
}

// Reverse allocations using ledger
function reverseAllocations(PDO $pdo, int $perdium_id): int {
    $sel = $pdo->prepare("SELECT * FROM perdium_budget_allocations WHERE perdium_id = ?");
    $sel->execute([$perdium_id]);
    $rows = $sel->fetchAll(PDO::FETCH_ASSOC);
    $count = 0;

    foreach ($rows as $al) {
        $amount = (float)$al['amount'];

        if ($al['budget_type'] === 'governmental' && $al['gov_budget_id']) {
            $r = $pdo->prepare("SELECT id, monthly_amount FROM budgets WHERE id = ? FOR UPDATE");
            $r->execute([(int)$al['gov_budget_id']]);
            $b = $r->fetch(PDO::FETCH_ASSOC);
            if ($b) {
                if ((float)$b['monthly_amount'] > 0) {
                    $pdo->prepare("UPDATE budgets SET remaining_monthly = remaining_monthly + ? WHERE id = ?")
                        ->execute([$amount, (int)$b['id']]);
                } else {
                    $pdo->prepare("UPDATE budgets SET remaining_yearly = remaining_yearly + ? WHERE id = ?")
                        ->execute([$amount, (int)$b['id']]);
                }
                $count++;
            }
        } elseif ($al['budget_type'] === 'program') {
            if (!empty($al['prog_budget_id'])) {
                // p_budgets model
                $r = $pdo->prepare("SELECT id FROM p_budgets WHERE id = ? FOR UPDATE");
                $r->execute([(int)$al['prog_budget_id']]);
                if ($r->fetch(PDO::FETCH_ASSOC)) {
                    $pdo->prepare("UPDATE p_budgets SET remaining_yearly = remaining_yearly + ? WHERE id = ?")
                        ->execute([$amount, (int)$al['prog_budget_id']]);
                    $count++;
                }
            } elseif (!empty($al['gov_budget_id'])) {
                // Legacy budgets model (program rows)
                $r = $pdo->prepare("SELECT id FROM budgets WHERE id = ? FOR UPDATE");
                $r->execute([(int)$al['gov_budget_id']]);
                if ($r->fetch(PDO::FETCH_ASSOC)) {
                    $pdo->prepare("UPDATE budgets SET remaining_yearly = remaining_yearly + ? WHERE id = ?")
                        ->execute([$amount, (int)$al['gov_budget_id']]);
                    $count++;
                }
            }
        }
    }
    if ($count > 0) {
        $pdo->prepare("DELETE FROM perdium_budget_allocations WHERE perdium_id = ?")->execute([$perdium_id]);
    }
    return $count;
}

// Legacy best-effort reversal (when no ledger rows exist)
function reverseLegacy(PDO $pdo, array $tx): void {
    $amount = (float)$tx['total_amount'];
    $year = (int)($tx['year'] ?? (date('Y') - 8));
    $owner_id = (int)$tx['budget_owner_id'];
    $code_id = (int)($tx['budget_code_id'] ?: 6);

    if ($tx['budget_type'] === 'program') {
        // Try p_budgets first
        $r = $pdo->prepare("SELECT id FROM p_budgets WHERE owner_id = ? AND year = ? FOR UPDATE");
        $r->execute([$owner_id, $year]);
        $row = $r->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $pdo->prepare("UPDATE p_budgets SET remaining_yearly = remaining_yearly + ? WHERE id = ?")
                ->execute([$amount, (int)$row['id']]);
            return;
        }
        // Fallback to budgets (program yearly rows)
        $r = $pdo->prepare("SELECT id FROM budgets WHERE budget_type='program' AND owner_id = ? AND year = ? AND monthly_amount = 0 FOR UPDATE");
        $r->execute([$owner_id, $year]);
        $b = $r->fetch(PDO::FETCH_ASSOC);
        if ($b) {
            $pdo->prepare("UPDATE budgets SET remaining_yearly = remaining_yearly + ? WHERE id = ?")
                ->execute([$amount, (int)$b['id']]);
        }
    } else {
        // Governmental monthly first then yearly
        $month = $tx['et_month'];
        if (!empty($month)) {
            $r = $pdo->prepare("SELECT id FROM budgets WHERE budget_type='governmental' AND owner_id = ? AND code_id = ? AND year = ? AND month = ? FOR UPDATE");
            $r->execute([$owner_id, $code_id, $year, $month]);
            $b = $r->fetch(PDO::FETCH_ASSOC);
            if ($b) {
                $pdo->prepare("UPDATE budgets SET remaining_monthly = remaining_monthly + ? WHERE id = ?")
                    ->execute([$amount, (int)$b['id']]);
                return;
            }
        }
        $r = $pdo->prepare("SELECT id FROM budgets WHERE budget_type='governmental' AND owner_id = ? AND code_id = ? AND year = ? AND monthly_amount = 0 FOR UPDATE");
        $r->execute([$owner_id, $code_id, $year]);
        $b = $r->fetch(PDO::FETCH_ASSOC);
        if ($b) {
            $pdo->prepare("UPDATE budgets SET remaining_yearly = remaining_yearly + ? WHERE id = ?")
                ->execute([$amount, (int)$b['id']]);
        }
    }
}

// Allocation helpers
function allocateProgram(PDO $pdo, int $owner_id, int $year, float $amount): array {
    // 1) Try new model (p_budgets)
    $s = $pdo->prepare("SELECT id, remaining_yearly FROM p_budgets WHERE owner_id = ? AND year = ? FOR UPDATE");
    $s->execute([$owner_id, $year]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $newRem = (float)$row['remaining_yearly'] - $amount;
        if ($newRem < 0) throw new Exception('Insufficient program yearly budget.');
        $pdo->prepare("UPDATE p_budgets SET remaining_yearly = ? WHERE id = ?")
            ->execute([$newRem, (int)$row['id']]);
        return [[
            'budget_type'    => 'program',
            'prog_budget_id' => (int)$row['id'],
            'gov_budget_id'  => null,
            'amount'         => $amount
        ]];
    }

    // 2) Fallback: legacy model in budgets (program yearly rows)
    $s = $pdo->prepare("
        SELECT id, remaining_yearly
        FROM budgets
        WHERE budget_type='program'
          AND owner_id = ?
          AND year = ?
          AND monthly_amount = 0
        ORDER BY id ASC
        FOR UPDATE
    ");
    $s->execute([$owner_id, $year]);
    $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) throw new Exception('No program budget allocated or registered for this owner/year.');

    $total_remaining = 0;
    foreach ($rows as $r) $total_remaining += (float)$r['remaining_yearly'];
    if ($amount > $total_remaining) throw new Exception('Insufficient program yearly budget.');

    $allocs = [];
    $left = $amount;
    foreach ($rows as $r) {
        if ($left <= 0) break;
        $avail = (float)$r['remaining_yearly'];
        $use = min($avail, $left);
        $pdo->prepare("UPDATE budgets SET remaining_yearly = ? WHERE id = ?")
            ->execute([$avail - $use, (int)$r['id']]);
        // Note: we store gov_budget_id to reference budgets table row even for program fallback
        $allocs[] = [
            'budget_type'    => 'program',
            'prog_budget_id' => null,
            'gov_budget_id'  => (int)$r['id'],
            'amount'         => $use
        ];
        $left -= $use;
    }
    return $allocs;
}
function allocateGovernment(PDO $pdo, int $owner_id, int $year, string $et_month, float $amount, int $code_id = 6): array {
    // Monthly first (lock row)
    $s = $pdo->prepare("SELECT id, remaining_monthly FROM budgets
                        WHERE budget_type='governmental' AND owner_id = ? AND code_id = ? AND year = ? AND month = ?
                        FOR UPDATE");
    $s->execute([$owner_id, $code_id, $year, $et_month]);
    $b = $s->fetch(PDO::FETCH_ASSOC);
    if ($b) {
        $newRem = (float)$b['remaining_monthly'] - $amount;
        if ($newRem < 0) throw new Exception('Insufficient remaining monthly budget for perdium.');
        $pdo->prepare("UPDATE budgets SET remaining_monthly = ? WHERE id = ?")
            ->execute([$newRem, (int)$b['id']]);
        return [[
            'budget_type'    => 'governmental',
            'gov_budget_id'  => (int)$b['id'],
            'prog_budget_id' => null,
            'amount'         => $amount
        ]];
    }
    // Yearly fallback
    $s = $pdo->prepare("SELECT id, remaining_yearly FROM budgets
                        WHERE budget_type='governmental' AND owner_id = ? AND code_id = ? AND year = ? AND monthly_amount = 0
                        FOR UPDATE");
    $s->execute([$owner_id, $code_id, $year]);
    $y = $s->fetch(PDO::FETCH_ASSOC);
    if (!$y) throw new Exception('No perdium budget allocated.');
    $newRemY = (float)$y['remaining_yearly'] - $amount;
    if ($newRemY < 0) throw new Exception('Insufficient remaining yearly budget for perdium.');
    $pdo->prepare("UPDATE budgets SET remaining_yearly = ? WHERE id = ?")
        ->execute([$newRemY, (int)$y['id']]);
    return [[
        'budget_type'    => 'governmental',
        'gov_budget_id'  => (int)$y['id'],
        'prog_budget_id' => null,
        'amount'         => $amount
    ]];
}
function insertAllocations(PDO $pdo, int $perdium_id, array $allocs): void {
    $ins = $pdo->prepare("INSERT INTO perdium_budget_allocations (perdium_id, budget_type, gov_budget_id, prog_budget_id, amount)
                          VALUES (?, ?, ?, ?, ?)");
    foreach ($allocs as $a) {
        $ins->execute([$perdium_id, $a['budget_type'], $a['gov_budget_id'] ?? null, $a['prog_budget_id'] ?? null, $a['amount']]);
    }
}

// User
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) { header('Location: login.php'); exit; }
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$user_name = $user_data['name'] ?? ($_SESSION['username'] ?? 'User');

// Get user's assigned budget types to determine default behavior
$user_assigned_budgets = getUserAssignedBudgets($pdo, $user_id);
$user_budget_types = [];
foreach ($user_assigned_budgets as $budget) {
    if (!in_array($budget['budget_type'], $user_budget_types)) {
        $user_budget_types[] = $budget['budget_type'];
    }
}

// Determine default budget type for this user
$default_budget_type = 'governmental'; // Default fallback
$budget_type_locked = false;

if ($is_admin) {
    // Admin can access both types, no locking
    $default_budget_type = 'governmental';
    $budget_type_locked = false;
} else {
    // Officer: determine based on assignments
    if (count($user_budget_types) === 1) {
        // User has only one budget type assigned
        $default_budget_type = $user_budget_types[0];
        $budget_type_locked = true;
    } elseif (count($user_budget_types) > 1) {
        // User has multiple budget types, default to first one but allow switching
        $default_budget_type = $user_budget_types[0];
        $budget_type_locked = false;
    } else {
        // User has no assignments, show governmental but locked
        $default_budget_type = 'governmental';
        $budget_type_locked = true;
    }
}

// Data for selects - Using filtered budget owners based on user role
$gov_owners = getFilteredBudgetOwners($pdo, $user_id, $_SESSION['role'], 'governmental');
$prog_owners = getFilteredBudgetOwners($pdo, $user_id, $_SESSION['role'], 'program');
$employees   = $pdo->query("SELECT * FROM emp_list ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$cities      = $pdo->query("SELECT * FROM cities ORDER BY name_english")->fetchAll(PDO::FETCH_ASSOC);
$months      = monthsEC();

// Edit mode
$perdium = null;
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
    if (!$is_admin) { http_response_code(403); exit('Forbidden'); }
    $stmt = $pdo->prepare("SELECT * FROM perdium_transactions WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $perdium = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Enhanced Flash system with multiple notification types
function set_flash($msg, $type='info', $options = []){ 
    $_SESSION['flash_message'] = $msg; 
    $_SESSION['flash_type'] = $type;
    $_SESSION['flash_options'] = $options;
}

// Handle POST (no output before redirects)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // DELETE via POST
    if ($action === 'delete') {
        if (!$is_admin) { http_response_code(403); exit('Forbidden'); }
        if (!csrf_check($_POST['csrf'] ?? '')) { http_response_code(400); exit('Bad CSRF'); }
        $del_id = (int)($_POST['id'] ?? 0);
        if ($del_id <= 0) { 
            set_flash('Invalid delete request', 'error', ['icon' => '🚫', 'duration' => 4000]); 
            header('Location: perdium.php'); exit; 
        }

        try {
            $pdo->beginTransaction();
            $s = $pdo->prepare("SELECT *, YEAR(created_at) AS year FROM perdium_transactions WHERE id = ? FOR UPDATE");
            $s->execute([$del_id]);
            $tx = $s->fetch(PDO::FETCH_ASSOC);
            if ($tx) {
                $revCount = reverseAllocations($pdo, $del_id);
                if ($revCount === 0) {
                    $tx['year'] = $tx['year'] ?: ecYear();
                    $tx['budget_code_id'] = $tx['budget_code_id'] ?: 6;
                    reverseLegacy($pdo, $tx);
                }
            }
            $pdo->prepare("DELETE FROM perdium_transactions WHERE id = ?")->execute([$del_id]);
            $pdo->commit();
            set_flash('Perdium transaction deleted successfully', 'success', [
                'icon' => '✅',
                'showUndo' => true,
                'undoId' => $del_id,
                'duration' => 6000
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('Error deleting transaction: ' . $e->getMessage(), 'error', [
                'icon' => '❌',
                'duration' => 7000
            ]);
        }
        header('Location: perdium.php'); exit;
    }

    // ADD/UPDATE
    if (!in_array($_SESSION['role'] ?? '', ['admin','officer'], true)) { http_response_code(403); exit('Forbidden'); }
    if (!csrf_check($_POST['csrf'] ?? '')) { http_response_code(400); exit('Bad CSRF'); }

    $id           = isset($_POST['id']) ? (int)$_POST['id'] : null;
    $is_update    = (($action === 'update') && $id);
    if ($is_update && !$is_admin) { http_response_code(403); exit('Forbidden'); }

    $budget_type  = (($_POST['budget_type'] ?? 'governmental') === 'program') ? 'program' : 'governmental';
    $employee_id  = (int)($_POST['employee_id'] ?? 0);
    $owner_id     = (int)($_POST['owner_id'] ?? 0);
    $city_id      = (int)($_POST['city_id'] ?? 0);
    $perdium_rate = (float)($_POST['perdium_rate'] ?? 0);
    $total_days   = (int)($_POST['total_days'] ?? 0);
    $departure_date = trim($_POST['departure_date'] ?? '');
    $arrival_date   = trim($_POST['arrival_date'] ?? '');
    $et_month     = $budget_type === 'program' ? '' : trim($_POST['et_month'] ?? '');

    // Validation
    if ($employee_id <= 0 || !employeeExists($pdo, $employee_id)) { 
        set_flash('Invalid employee selected', 'error', ['icon' => '👤', 'duration' => 5000]);
    }
    elseif ($owner_id <= 0 || !ownerExists($pdo, $budget_type, $owner_id)) { 
        set_flash('Invalid budget owner selected', 'error', ['icon' => '🏢', 'duration' => 5000]);
    }
    elseif ($city_id <= 0 || !cityExists($pdo, $city_id)) { 
        set_flash('Invalid destination city selected', 'error', ['icon' => '🏙️', 'duration' => 5000]);
    }
    elseif ($perdium_rate <= 0) { 
        set_flash('Perdium rate must be greater than 0', 'error', ['icon' => '💰', 'duration' => 5000]);
    }
    elseif ($total_days < 1) { 
        set_flash('Total days must be at least 1', 'error', ['icon' => '📅', 'duration' => 5000]);
    }
    elseif (!$departure_date || !$arrival_date) { 
        set_flash('Departure and arrival dates are required', 'error', ['icon' => '✈️', 'duration' => 5000]);
    }
    elseif (strtotime($arrival_date) < strtotime($departure_date)) { 
        set_flash('Arrival date cannot be before departure date', 'error', ['icon' => '⏰', 'duration' => 5000]);
    }
    elseif ($budget_type === 'governmental' && $et_month !== '' && !in_array($et_month, monthsEC(), true)) { 
        set_flash('Invalid Ethiopian month selected', 'error', ['icon' => '🗓️', 'duration' => 5000]);
    }
    // Budget access validation
    elseif (!$is_admin && !hasBudgetAccess($pdo, $user_id, $budget_type, $owner_id)) { 
        set_flash('You do not have access to this budget owner', 'error', ['icon' => '🔒', 'duration' => 6000]);
    }
    else {
        if (isEmployeeActive($pdo, $employee_id, $is_update ? $id : null)) {
            set_flash('This employee is currently on a perdium; you can create a new one only after the current end date.', 'warning', [
                'icon' => '⚠️',
                'duration' => 7000
            ]);
        } elseif (hasOverlap($pdo, $employee_id, $departure_date, $arrival_date, $is_update ? $id : null)) {
            set_flash('Selected dates overlap with another perdium for this employee.', 'warning', [
                'icon' => '🔄',
                'duration' => 7000
            ]);
        } else {
            try {
                $pdo->beginTransaction();

                $year = ecYear();
                $code_id = 6; // Per diem budget code
                $total_amount = calcPerdiem($perdium_rate, $total_days);

                // Governmental p_koox only
                $p_koox = null;
                if ($budget_type === 'governmental') {
                    $st = $pdo->prepare("SELECT p_koox FROM budget_owners WHERE id = ?");
                    $st->execute([$owner_id]);
                    $row = $st->fetch(PDO::FETCH_ASSOC);
                    $p_koox = $row['p_koox'] ?? null;
                }

                // Reverse old allocations if updating
                if ($is_update) {
                    $old = $pdo->prepare("SELECT *, YEAR(created_at) AS year FROM perdium_transactions WHERE id = ? FOR UPDATE");
                    $old->execute([$id]);
                    $oldTx = $old->fetch(PDO::FETCH_ASSOC);
                    if (!$oldTx) throw new Exception('Transaction not found for update.');

                    $revCount = reverseAllocations($pdo, $id);
                    if ($revCount === 0) {
                        $oldTx['year'] = $oldTx['year'] ?: $year;
                        $oldTx['budget_code_id'] = $oldTx['budget_code_id'] ?: $code_id;
                        reverseLegacy($pdo, $oldTx);
                    }
                }

                // Allocate and persist
                if ($budget_type === 'program') {
                    $allocs = allocateProgram($pdo, $owner_id, $year, $total_amount);

                    if ($is_update) {
                        $u = $pdo->prepare("
                            UPDATE perdium_transactions
                            SET budget_type='program',
                                employee_id=?, budget_owner_id=?, p_koox=?, budget_code_id=?, city_id=?,
                                perdium_rate=?, total_days=?, departure_date=?, arrival_date=?, total_amount=?, et_month=?
                            WHERE id=?
                        ");
                        $u->execute([
                            $employee_id, $owner_id, null, $code_id, $city_id,
                            $perdium_rate, $total_days, $departure_date, $arrival_date, $total_amount, '', $id
                        ]);
                        $perdium_id = $id;
                    } else {
                        $ins = $pdo->prepare("
                            INSERT INTO perdium_transactions (
                                budget_type, employee_id, budget_owner_id, p_koox, budget_code_id, city_id,
                                perdium_rate, total_days, departure_date, arrival_date, total_amount, et_month
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $ins->execute([
                            'program', $employee_id, $owner_id, null, $code_id, $city_id,
                            $perdium_rate, $total_days, $departure_date, $arrival_date, $total_amount, ''
                        ]);
                        $perdium_id = (int)$pdo->lastInsertId();
                    }

                    insertAllocations($pdo, $perdium_id, $allocs);
                    set_flash($is_update ? 'Program perdium transaction updated successfully' : 'Program perdium transaction added successfully', 'success', [
                        'icon' => '✅',
                        'duration' => $is_update ? 4000 : 5000,
                        'showConfetti' => !$is_update
                    ]);

                } else {
                    if ($et_month === '') throw new Exception('Ethiopian month is required for governmental budget.');

                    $allocs = allocateGovernment($pdo, $owner_id, $year, $et_month, $total_amount, $code_id);

                    if ($is_update) {
                        $u = $pdo->prepare("
                            UPDATE perdium_transactions
                            SET budget_type='governmental',
                                employee_id=?, budget_owner_id=?, p_koox=?, budget_code_id=?, city_id=?,
                                perdium_rate=?, total_days=?, departure_date=?, arrival_date=?, total_amount=?, et_month=?
                            WHERE id=?
                        ");
                        $u->execute([
                            $employee_id, $owner_id, $p_koox, $code_id, $city_id,
                            $perdium_rate, $total_days, $departure_date, $arrival_date, $total_amount, $et_month, $id
                        ]);
                        $perdium_id = $id;
                    } else {
                        $ins = $pdo->prepare("
                            INSERT INTO perdium_transactions (
                                budget_type, employee_id, budget_owner_id, p_koox, budget_code_id, city_id,
                                perdium_rate, total_days, departure_date, arrival_date, total_amount, et_month
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $ins->execute([
                            'governmental', $employee_id, $owner_id, $p_koox, $code_id, $city_id,
                            $perdium_rate, $total_days, $departure_date, $arrival_date, $total_amount, $et_month
                        ]);
                        $perdium_id = (int)$pdo->lastInsertId();
                    }

                    insertAllocations($pdo, $perdium_id, $allocs);
                    set_flash($is_update ? 'Perdium transaction updated successfully' : 'Perdium transaction added successfully', 'success', [
                        'icon' => '✅',
                        'duration' => $is_update ? 4000 : 5000,
                        'showConfetti' => !$is_update
                    ]);
                }

                $pdo->commit();
                header('Location: perdium.php'); exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                set_flash('Transaction failed: ' . $e->getMessage(), 'error', [
                    'icon' => '❌',
                    'duration' => 7000,
                    'showReport' => true
                ]);
            }
        }
    }
}

// Flash
$flash_message = $_SESSION['flash_message'] ?? null;
$flash_type = $_SESSION['flash_type'] ?? null;
$flash_options = $_SESSION['flash_options'] ?? [];
unset($_SESSION['flash_message'], $_SESSION['flash_type'], $_SESSION['flash_options']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php
    // give this page a custom title
    $pageTitle = 'Per Diem Management';
    require_once 'includes/head.php';
  ?>
  <!-- SweetAlert2 CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <!-- Animate.css for additional animations -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
  <style>
    /* Purple Gradient Theme Variables */
    :root {
      --purple-primary: #8b5cf6;
      --purple-secondary: #7c3aed;
      --purple-dark: #6d28d9;
      --purple-light: #a78bfa;
      --purple-lighter: #c4b5fd;
      --purple-glow: rgba(139, 92, 246, 0.3);
      --success-color: #10b981;
      --error-color: #ef4444;
      --warning-color: #f59e0b;
      --info-color: #3b82f6;
    }

    /* Enhanced Notification Styles with Purple Theme */
    .swal2-popup {
      font-size: 0.95rem;
      font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
      border-radius: 20px;
      box-shadow: 0 25px 50px rgba(139, 92, 246, 0.15), 0 10px 30px rgba(139, 92, 246, 0.1);
      border: 1px solid rgba(139, 92, 246, 0.2);
      backdrop-filter: blur(15px);
      background: linear-gradient(135deg, #ffffff 0%, #faf5ff 100%);
      overflow: hidden;
      position: relative;
    }

    .swal2-popup::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--purple-primary), var(--purple-light));
      z-index: 1;
    }

    .swal2-title {
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--purple-dark);
      margin-bottom: 10px;
      background: linear-gradient(135deg, var(--purple-dark), var(--purple-primary));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .swal2-html-container {
      font-size: 0.95rem;
      color: #6b7280;
      line-height: 1.6;
      padding: 0 1rem;
    }

    /* Enhanced Purple Progress Bar */
    .swal2-timer-progress-bar {
      height: 5px !important;
      border-radius: 0 0 20px 20px !important;
      background: linear-gradient(
        90deg,
        #c4b5fd 0%,
        #a78bfa 25%,
        #8b5cf6 50%,
        #7c3aed 75%,
        #6d28d9 100%
      ) !important;
      background-size: 200% 100% !important;
      animation: purpleSlide 3s ease-in-out infinite !important;
    }

    @keyframes purpleSlide {
      0% {
        background-position: 200% 0%;
      }
      50% {
        background-position: 0% 0%;
      }
      100% {
        background-position: -200% 0%;
      }
    }

    /* Enhanced Icons with Purple Theme */
    .swal2-icon {
      border: none;
      width: 80px;
      height: 80px;
      margin: 0 auto 20px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 8px 25px var(--purple-glow);
    }

    .swal2-icon.swal2-success {
      background: linear-gradient(135deg, var(--success-color), #059669);
      color: white;
    }

    .swal2-icon.swal2-error {
      background: linear-gradient(135deg, var(--error-color), #dc2626);
      color: white;
    }

    .swal2-icon.swal2-warning {
      background: linear-gradient(135deg, var(--warning-color), #d97706);
      color: white;
    }

    .swal2-icon.swal2-info {
      background: linear-gradient(135deg, var(--info-color), #2563eb);
      color: white;
    }

    .swal2-icon .swal2-icon-content {
      font-size: 2.8rem;
      font-weight: 900;
    }

    /* Custom Toast Colors with Purple Theme */
    .colored-toast {
      border-radius: 16px !important;
      box-shadow: 0 15px 35px rgba(139, 92, 246, 0.2) !important;
      border: 2px solid rgba(255, 255, 255, 0.3) !important;
      backdrop-filter: blur(20px) !important;
      background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark)) !important;
    }

    .colored-toast .swal2-title {
      color: white !important;
      font-weight: 600;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      -webkit-text-fill-color: white;
      background: none;
    }

    .colored-toast .swal2-html-container {
      color: rgba(255, 255, 255, 0.9) !important;
      font-weight: 500;
    }

    /* Enhanced Delete Confirmation with Purple Theme */
    .swal2-popup.swal2-warning {
      border: 2px solid var(--warning-color);
      background: linear-gradient(135deg, #fff7ed, #ffedd5);
    }

    .swal2-popup.swal2-warning .swal2-title {
      color: #9a3412;
      background: linear-gradient(135deg, #9a3412, #dc2626);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .swal2-popup.swal2-warning .swal2-html-container {
      color: #7c2d12;
    }

    /* Enhanced Buttons with Purple Theme */
    .swal2-actions {
      gap: 12px;
      margin: 20px 0 10px;
    }

    .swal2-confirm {
      background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark)) !important;
      border: none !important;
      border-radius: 12px !important;
      font-weight: 600 !important;
      padding: 12px 28px !important;
      box-shadow: 0 6px 20px var(--purple-glow) !important;
      transition: all 0.3s ease !important;
      position: relative;
      overflow: hidden;
    }

    .swal2-confirm::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
      transition: left 0.5s;
    }

    .swal2-confirm:hover::before {
      left: 100%;
    }

    .swal2-confirm:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px var(--purple-glow) !important;
    }

    .swal2-deny {
      background: linear-gradient(135deg, var(--warning-color), #d97706) !important;
      border: none !important;
      border-radius: 12px !important;
      font-weight: 600 !important;
      padding: 12px 28px !important;
      box-shadow: 0 6px 20px rgba(245, 158, 11, 0.3) !important;
      transition: all 0.3s ease !important;
    }

    .swal2-deny:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(245, 158, 11, 0.4) !important;
    }

    .swal2-cancel {
      background: linear-gradient(135deg, #6b7280, #4b5563) !important;
      border: none !important;
      border-radius: 12px !important;
      font-weight: 600 !important;
      padding: 12px 28px !important;
      box-shadow: 0 6px 20px rgba(107, 114, 128, 0.3) !important;
      transition: all 0.3s ease !important;
    }

    .swal2-cancel:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(107, 114, 128, 0.4) !important;
    }

    /* Toast Animation Enhancement */
    .swal2-toast {
      border-radius: 16px !important;
      box-shadow: 0 15px 35px rgba(139, 92, 246, 0.25) !important;
      backdrop-filter: blur(20px) !important;
      border: 1px solid rgba(255, 255, 255, 0.2) !important;
    }

    /* Confetti Animation */
    .confetti {
      position: fixed;
      width: 10px;
      height: 10px;
      background: var(--purple-primary);
      opacity: 0;
      z-index: 9999;
      pointer-events: none;
    }

    /* Notification Stacking */
    .notification-stack {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 10000;
      display: flex;
      flex-direction: column;
      gap: 10px;
      max-width: 400px;
    }

    /* Custom Notification Badges */
    .notification-badge {
      position: absolute;
      top: -8px;
      right: -8px;
      background: var(--error-color);
      color: white;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      font-size: 0.7rem;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
    }

    /* Enhanced Progress Bar for Undo */
    .undo-progress {
      width: 100%;
      height: 4px;
      background: rgba(255,255,255,0.3);
      border-radius: 2px;
      overflow: hidden;
      margin-top: 8px;
    }

    .undo-progress-bar {
      height: 100%;
      background: linear-gradient(90deg, #a78bfa, #ffffff);
      border-radius: 2px;
      transition: width 0.1s linear;
    }

    /* Mobile Responsiveness */
    @media (max-width: 640px) {
      .swal2-popup {
        margin: 0 16px;
        width: auto !important;
        font-size: 0.9rem;
      }
      
      .swal2-actions {
        flex-direction: column;
      }
      
      .swal2-confirm,
      .swal2-deny,
      .swal2-cancel {
        width: 100%;
        margin: 5px 0;
      }

      .notification-stack {
        right: 10px;
        left: 10px;
        max-width: none;
      }
    }

    /* Dark mode support */
    @media (prefers-color-scheme: dark) {
      .swal2-popup {
        background: linear-gradient(135deg, #1f2937, #111827);
        color: #f3f4f6;
      }

      .swal2-title {
        color: #e5e7eb;
      }

      .swal2-html-container {
        color: #d1d5db;
      }
    }

    /* Haptic feedback simulation */
    @keyframes hapticPulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(0.98); }
    }

    .haptic-feedback {
      animation: hapticPulse 0.1s ease;
    }

    /* Sound wave animation for audio cues */
    .sound-wave {
      display: flex;
      align-items: center;
      gap: 2px;
      margin-left: 10px;
    }

    .sound-wave span {
      width: 3px;
      background: currentColor;
      border-radius: 2px;
      animation: soundWave 1s ease infinite;
    }

    .sound-wave span:nth-child(2) { animation-delay: 0.2s; }
    .sound-wave span:nth-child(3) { animation-delay: 0.4s; }
    .sound-wave span:nth-child(4) { animation-delay: 0.6s; }

    @keyframes soundWave {
      0%, 100% { height: 5px; }
      50% { height: 15px; }
    }
  </style>
</head>
<body class="text-slate-700 flex bg-gray-100 min-h-screen">
  <?php require_once 'includes/sidebar.php'; ?>
  <div class="main-content" id="mainContent">
    <div class="p-6">
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 p-6 bg-white rounded-xl shadow-sm">
        <div>
          <h1 class="text-2xl md:text-3xl font-bold text-slate-800">Perdium Management</h1>
          <p class="text-slate-600 mt-2">Manage perdium transactions and expenses</p>
          <div class="mt-3 bg-indigo-100 rounded-lg p-3 max-w-md info-card">
            <i class="fas fa-user-circle text-indigo-600 mr-2"></i>
            <span class="text-indigo-800 font-semibold">
              Welcome, <?php echo htmlspecialchars($user_name); ?>! (<?php echo htmlspecialchars(ucfirst($_SESSION['role'] ?? '')); ?>)
            </span>
          </div>
        </div>
        <div class="flex items-center space-x-4 mt-4 md:mt-0">
          <button class="bg-slate-200 hover:bg-slate-300 text-slate-700 p-2 rounded-lg md:hidden shadow-sm" id="sidebarToggle">
            <i class="fas fa-bars"></i>
          </button>
        </div>
      </div>
      
      
      <div class="bg-white rounded-xl p-6 shadow-sm mb-8">
        <h2 class="text-xl font-bold text-slate-800 mb-6"><?php echo isset($perdium) ? 'Edit Perdium Transaction' : 'Add New Perdium Transaction'; ?></h2>
        <form id="perdiumForm" method="POST" class="space-y-4" onsubmit="return validateBeforeSubmit();">
          <?php if (isset($perdium)): ?>
            <input type="hidden" name="id" value="<?php echo (int)$perdium['id']; ?>">
            <input type="hidden" name="action" value="update">
          <?php endif; ?>
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">

          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Budget Source Type *</label>
              <select name="budget_type" id="budget_type" class="w-full select2" <?php echo $budget_type_locked ? 'disabled' : ''; ?>>
                <option value="governmental" <?php 
                  if (isset($perdium)) {
                    echo $perdium['budget_type']==='governmental' ? 'selected' : '';
                  } else {
                    echo $default_budget_type === 'governmental' ? 'selected' : '';
                  }
                ?>>Government Budget</option>
                <option value="program" <?php 
                  if (isset($perdium)) {
                    echo $perdium['budget_type']==='program' ? 'selected' : '';
                  } else {
                    echo $default_budget_type === 'program' ? 'selected' : '';
                  }
                ?>>Programs Budget</option>
              </select>
              <?php if ($budget_type_locked): ?>
                <input type="hidden" name="budget_type" value="<?php echo $default_budget_type; ?>">
                <p class="text-xs text-gray-500 mt-1">Budget source is automatically set based on your assignments</p>
              <?php endif; ?>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Budget Owner *</label>
              <select name="owner_id" id="owner_id" required class="w-full select2">
                <option value="">Loading your budget owners...</option>
              </select>
            </div>

            <div class="program-card p-4 rounded-lg">
              <h3 class="text-sm font-medium text-indigo-800 mb-2">Source Details</h3>
              <div class="flex items-center">
                <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center mr-3">
                  <i class="fas fa-project-diagram text-indigo-600"></i>
                </div>
                <div>
                  <p id="owner_name_display" class="text-sm font-medium text-gray-900">-</p>
                  <p id="p_koox_row" class="text-xs text-gray-600" style="display:none;">
                    P/Koox: <span id="p_koox_display">-</span>
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Employee *</label>
              <select name="employee_id" id="employee_id" required class="w-full select2">
                <option value="">Select Employee</option>
                <?php foreach ($employees as $e): ?>
                  <option value="<?php echo (int)$e['id']; ?>"
                    data-salary="<?php echo htmlspecialchars($e['salary']); ?>"
                    data-position="<?php echo htmlspecialchars($e['taamagoli'] ?? ''); ?>"
                    data-department="<?php echo htmlspecialchars($e['directorate'] ?? ''); ?>"
                    <?php
                    $sel = false;
                    if (isset($perdium) && $perdium['employee_id'] == $e['id']) $sel = true;
                    if (isset($_POST['employee_id']) && $_POST['employee_id'] == $e['id']) $sel = true;
                    echo $sel ? 'selected' : '';
                    ?>>
                    <?php echo htmlspecialchars(($e['name'] ?? $e['name_am'] ?? '') . ' - ' . ($e['taamagoli'] ?? '')); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Destination City *</label>
              <select name="city_id" id="city_id" required class="w-full select2">
                <option value="">Select City</option>
                <?php foreach ($cities as $c): ?>
                  <option value="<?php echo (int)$c['id']; ?>"
                    data-rate-low="<?php echo htmlspecialchars($c['rate_low']); ?>"
                    data-rate-medium="<?php echo htmlspecialchars($c['rate_medium']); ?>"
                    data-rate-high="<?php echo htmlspecialchars($c['rate_high']); ?>"
                    <?php
                    $sel = false;
                    if (isset($perdium) && $perdium['city_id'] == $c['id']) $sel = true;
                    if (isset($_POST['city_id']) && $_POST['city_id'] == $c['id']) $sel = true;
                    echo $sel ? 'selected' : '';
                    ?>>
                    <?php echo htmlspecialchars($c['name_amharic']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div id="et_month_box">
              <label class="block text-sm font-medium text-slate-700 mb-1">Ethiopian Month *</label>
              <select name="et_month" id="et_month" required class="w-full select2">
                <option value="">Select Month</option>
                <?php foreach ($months as $m): ?>
                  <option value="<?php echo htmlspecialchars($m); ?>"
                    <?php
                      $sel = false;
                      if (isset($perdium) && $perdium['et_month'] == $m && $perdium['budget_type']!=='program') $sel = true;
                      if (isset($_POST['et_month']) && $_POST['et_month'] == $m) $sel = true;
                      echo $sel ? 'selected' : '';
                    ?>>
                    <?php echo htmlspecialchars($m); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div id="employeeActiveWarning" class="mb-4 p-3 rounded-md bg-yellow-100 text-yellow-800 hidden">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            Employee is currently on a perdium until <span id="block_until_date"></span>. You cannot create another perdium until after this date.
          </div>
          <div id="employeeOverlapWarning" class="mb-4 p-3 rounded-md bg-red-100 text-red-800 hidden">
            <i class="fas fa-ban mr-2"></i>
            Selected dates overlap with another perdium for this employee.
          </div>

          <div class="employee-card p-4 rounded-lg">
            <h3 class="text-sm font-medium text-green-800 mb-2">Employee Details</h3>
            <div class="flex items-center">
              <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-3">
                <i class="fas fa-user-tie text-green-600"></i>
              </div>
              <div>
                <p id="employee_position_display" class="text-sm font-medium text-gray-900">-</p>
                <p id="employee_department_display" class="text-xs text-gray-600">Department: -</p>
                <p id="employee_salary_display" class="text-xs text-gray-600">Salary: -</p>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Departure Date *</label>
              <input type="date" name="departure_date" id="departure_date" value="<?php
                echo isset($perdium) ? htmlspecialchars($perdium['departure_date']) : (isset($_POST['departure_date']) ? htmlspecialchars($_POST['departure_date']) : '');
              ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Arrival Date *</label>
              <input type="date" name="arrival_date" id="arrival_date" value="<?php
                echo isset($perdium) ? htmlspecialchars($perdium['arrival_date']) : (isset($_POST['arrival_date']) ? htmlspecialchars($_POST['arrival_date']) : '');
              ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Total Perdium Days *</label>
              <input type="number" name="total_days" id="total_days" value="<?php
                echo isset($perdium) ? (int)$perdium['total_days'] : (isset($_POST['total_days']) ? (int)$_POST['total_days'] : '');
              ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" oninput="updateDates()">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Perdium Rate (per day) *</label>
              <input type="number" step="0.01" name="perdium_rate" id="perdium_rate" value="<?php
                echo isset($perdium) ? htmlspecialchars($perdium['perdium_rate']) : (isset($_POST['perdium_rate']) ? htmlspecialchars($_POST['perdium_rate']) : '100');
              ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" oninput="calculatePerdium()">
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="rounded-xl p-4 bg-gradient-to-r from-sky-100 to-sky-50 border border-sky-200 shadow-sm">
              <div class="text-sm text-sky-700 font-medium">Total Days</div>
              <div id="total_days_card" class="text-2xl font-extrabold text-sky-900 mt-1">0</div>
            </div>
            <div class="rounded-xl p-4 bg-gradient-to-r from-amber-100 to-amber-50 border border-amber-200 shadow-sm">
              <div class="text-sm text-amber-700 font-medium">Total Amount</div>
              <div id="total_amount_card" class="text-2xl font-extrabold text-amber-900 mt-1">0.00 ብር</div>
              <input type="hidden" name="total_amount" id="total_amount" value="0">
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div id="rem_monthly_card" class="rounded-xl p-5 bg-gradient-to-r from-amber-100 to-amber-50 border border-amber-200 shadow-sm">
              <div class="flex items-center gap-3">
                <div class="p-3 rounded-full bg-amber-200 text-amber-800"><i class="fas fa-calendar-alt"></i></div>
                <div>
                  <div class="text-sm text-amber-700 font-medium">Monthly Perdium Budget</div>
                  <div id="rem_monthly" class="text-2xl font-extrabold text-amber-900 mt-1">0.00</div>
                </div>
              </div>
            </div>
            <div class="rounded-xl p-5 bg-gradient-to-r from-emerald-100 to-emerald-50 border border-emerald-200 shadow-sm">
              <div class="flex items-center gap-3">
                <div class="p-3 rounded-full bg-emerald-200 text-emerald-800"><i class="fas fa-coins"></i></div>
                <div>
                  <div class="text-sm text-emerald-700 font-medium" id="yearly_label">Available Yearly Perdium Budget</div>
                  <div id="rem_yearly" class="text-2xl font-extrabold text-emerald-900 mt-1">0.00</div>
                </div>
              </div>
            </div>
            <div id="programs_total_card" class="rounded-xl p-5 bg-gradient-to-r from-purple-100 to-purple-50 border border-purple-200 shadow-sm" style="display:none;">
              <div class="flex items-center gap-3">
                <div class="p-3 rounded-full bg-purple-200 text-purple-800"><i class="fas fa-layer-group"></i></div>
                <div>
                  <div class="text-sm text-purple-700 font-medium">Bureau's Programs Total Budget</div>
                  <div id="programs_total_amount" class="text-2xl font-extrabold text-purple-900 mt-1">0.00 ብር</div>
                </div>
              </div>
            </div>
          </div>

          <div id="government_grand_card" class="rounded-xl p-5 mt-4 bg-gradient-to-r from-purple-100 to-purple-50 border border-purple-200 shadow-sm" style="display:none;">
            <div class="flex items-center gap-3">
              <div class="p-3 rounded-full bg-purple-200 text-purple-800"><i class="fas fa-building"></i></div>
              <div>
                <div id="gov_grand_label" class="text-sm text-purple-700 font-medium">Bureau's Yearly Government Budget</div>
                <div id="gov_grand_amount" class="text-2xl font-extrabold text-purple-900 mt-1">0.00 ብር</div>
              </div>
            </div>
          </div>

          <div class="flex justify-end space-x-4 pt-2">
            <?php if (isset($perdium)): ?>
              <a href="perdium.php" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">Cancel</a>
            <?php endif; ?>
            <button id="submitBtn" type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
              <?php echo isset($perdium) ? 'Update Transaction' : 'Add Transaction'; ?>
            </button>
          </div>
        </form>
      </div>

      <div class="bg-white rounded-xl p-4 shadow-sm mb-4">
        <div class="grid md:grid-cols-4 gap-3">
          <div>
            <label class="block text-sm font-medium mb-1">Budget Source</label>
            <select id="flt_type" class="w-full select2" <?php echo $budget_type_locked ? 'disabled' : ''; ?>>
              <option value="governmental" <?php echo $default_budget_type === 'governmental' ? 'selected' : ''; ?>>Government</option>
              <option value="program" <?php echo $default_budget_type === 'program' ? 'selected' : ''; ?>>Programs</option>
            </select>
            <?php if ($budget_type_locked): ?>
              <input type="hidden" id="flt_type_hidden" value="<?php echo $default_budget_type; ?>">
            <?php endif; ?>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Owner</label>
            <select id="flt_owner" class="w-full select2">
              <option value="">Any Owner</option>
              <?php if ($is_admin): ?>
                <!-- Admin sees all owners -->
                <?php foreach ($gov_owners as $o): ?>
                  <option value="<?php echo (int)$o['id']; ?>" data-budget-type="governmental"><?php echo htmlspecialchars($o['code'].' - '.$o['name']); ?></option>
                <?php endforeach; ?>
                <?php foreach ($prog_owners as $o): ?>
                  <option value="<?php echo (int)$o['id']; ?>" data-budget-type="program"><?php echo htmlspecialchars($o['code'].' - '.$o['name']); ?></option>
                <?php endforeach; ?>
              <?php else: ?>
                <!-- Officer sees only assigned owners -->
                <?php foreach ($gov_owners as $o): ?>
                  <option value="<?php echo (int)$o['id']; ?>" data-budget-type="governmental"><?php echo htmlspecialchars($o['code'].' - '.$o['name']); ?></option>
                <?php endforeach; ?>
                <?php foreach ($prog_owners as $o): ?>
                  <option value="<?php echo (int)$o['id']; ?>" data-budget-type="program"><?php echo htmlspecialchars($o['code'].' - '.$o['name']); ?></option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>
          <div id="flt_month_box">
            <label class="block text-sm font-medium mb-1">Month (Gov only)</label>
            <select id="flt_month" class="w-full select2">
              <option value="">Any Month</option>
              <?php foreach ($months as $m): ?>
                <option value="<?php echo htmlspecialchars($m); ?>"><?php echo htmlspecialchars($m); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Employee</label>
            <select id="flt_employee" class="w-full select2">
              <option value="">Any Employee</option>
              <?php foreach ($employees as $e): ?>
                <option value="<?php echo (int)$e['id']; ?>"><?php echo htmlspecialchars($e['name'] ?? $e['name_am'] ?? ''); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="mb-4">
        <input type="text" id="searchInput" placeholder="Search transactions..." class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" onkeyup="filterTransactions()">
      </div>

      <div class="bg-white rounded-xl p-6 shadow-sm">
        <h2 class="text-xl font-bold text-slate-800 mb-6">Perdium Transactions</h2>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Owner</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destination</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200" id="transactionsTable">
              <tr><td colspan="7" class="px-4 py-4 text-center text-sm text-gray-500">Loading…</td></tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

      <!-- Rest of your HTML content remains the same -->
      <!-- ... (previous HTML content) ... -->

    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <!-- SweetAlert2 JS -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
  <!-- Confetti library for celebrations -->
  <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
  <script>
    const defaultPerdiumRate = <?php echo json_encode((float)($_POST['perdium_rate'] ?? ($perdium['perdium_rate'] ?? 100))); ?>;
    let filling = false;
    const isEdit = <?php echo isset($perdium) ? 'true' : 'false'; ?>;
    const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
    const isOfficer = <?php echo $is_officer ? 'true' : 'false'; ?>;
    const csrfToken = <?php echo json_encode($_SESSION['csrf']); ?>;
    const defaultBudgetType = <?php echo json_encode($default_budget_type); ?>;
    const budgetTypeLocked = <?php echo $budget_type_locked ? 'true' : 'false'; ?>;

    // Enhanced notification functions
    function createConfetti() {
      confetti({
        particleCount: 150,
        spread: 70,
        origin: { y: 0.6 },
        colors: ['#8b5cf6', '#7c3aed', '#6d28d9', '#a78bfa', '#c4b5fd']
      });
    }

    function playHapticFeedback() {
      // Simulate haptic feedback with CSS animation
      document.body.classList.add('haptic-feedback');
      setTimeout(() => {
        document.body.classList.remove('haptic-feedback');
      }, 100);
    }

    function createSoundWave() {
      return `<div class="sound-wave">
        <span></span><span></span><span></span><span></span>
      </div>`;
    }

    // Enhanced Flash message handling with SweetAlert2
    <?php if ($flash_message): ?>
    document.addEventListener('DOMContentLoaded', function() {
      const type = '<?php echo $flash_type; ?>';
      const options = <?php echo json_encode($flash_options); ?>;
      const icon = options.icon || 
                  (type === 'success' ? '✅' : 
                   type === 'error' ? '❌' : 
                   type === 'warning' ? '⚠️' : 'ℹ️');
      
      const duration = options.duration || 5000;

      // Enhanced toast configuration
      const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: duration,
        timerProgressBar: true,
        background: 'linear-gradient(135deg, var(--purple-primary), var(--purple-dark))',
        iconColor: 'white',
        customClass: {
          popup: 'colored-toast animate__animated animate__bounceInRight',
          timerProgressBar: 'purple-progress'
        },
        didOpen: (toast) => {
          toast.addEventListener('mouseenter', Swal.stopTimer);
          toast.addEventListener('mouseleave', Swal.resumeTimer);
          
          // Add haptic feedback
          playHapticFeedback();

          // Add confetti for success messages
          if (options.showConfetti) {
            createConfetti();
          }
        }
      });

      // Show the toast
      Toast.fire({
        icon: type,
        title: `${icon} <?php echo addslashes($flash_message); ?>`,
        ...(options.showUndo && {
          showDenyButton: true,
          denyButtonText: 'Undo',
          denyButtonColor: '#a78bfa',
        })
      }).then((result) => {
        if (result.isDenied && options.undoId) {
          // Handle undo action
          undoDelete(options.undoId);
        }
      });
    });
    <?php endif; ?>

    // Undo delete functionality
    function undoDelete(transactionId) {
      Swal.fire({
        title: 'Restoring Transaction',
        text: 'Please wait...',
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });

      // In a real implementation, you would make an AJAX call to restore the transaction
      setTimeout(() => {
        Swal.fire({
          title: 'Transaction Restored!',
          text: 'The transaction has been successfully restored.',
          icon: 'success',
          confirmButtonText: 'Great!',
          background: 'linear-gradient(135deg, #ffffff, #faf5ff)',
          customClass: {
            confirmButton: 'enhanced-confirm'
          }
        });
      }, 1500);
    }

    // Enhanced notification system
    function showAdvancedNotification(title, message, type = 'info', options = {}) {
      const config = {
        title: title,
        text: message,
        icon: type,
        background: 'linear-gradient(135deg, #ffffff, #faf5ff)',
        showClass: {
          popup: 'animate__animated animate__zoomIn'
        },
        hideClass: {
          popup: 'animate__animated animate__zoomOut'
        },
        ...options
      };

      // Add custom icon if provided
      if (options.customIcon) {
        config.title = `${options.customIcon} ${title}`;
      }

      // Add sound wave for important notifications
      if (options.withSoundWave) {
        config.text = `${message} ${createSoundWave()}`;
      }

      return Swal.fire(config);
    }

    // Notification queue system
    const notificationQueue = [];
    let isShowingNotification = false;

    function queueNotification(title, message, type = 'info', options = {}) {
      notificationQueue.push({ title, message, type, options });
      processNotificationQueue();
    }

    function processNotificationQueue() {
      if (isShowingNotification || notificationQueue.length === 0) return;

      isShowingNotification = true;
      const { title, message, type, options } = notificationQueue.shift();

      showAdvancedNotification(title, message, type, options).then(() => {
        isShowingNotification = false;
        processNotificationQueue();
      });
    }

    // Bulk action notifications
    function showBulkActionNotification(action, count) {
      const actions = {
        delete: { title: 'Bulk Delete', message: `Successfully deleted ${count} transactions`, icon: '🗑️' },
        update: { title: 'Bulk Update', message: `Successfully updated ${count} transactions`, icon: '🔄' },
        create: { title: 'Bulk Create', message: `Successfully created ${count} transactions`, icon: '✨' }
      };

      const config = actions[action] || actions.create;
      
      showAdvancedNotification(config.title, config.message, 'success', {
        customIcon: config.icon,
        showConfetti: true,
        timer: 4000
      });
    }

    // Progressive enhancement for notifications
    function enhanceNotifications() {
      // Add notification badges to elements
      const notificationElements = document.querySelectorAll('[data-notification]');
      notificationElements.forEach(element => {
        const count = element.getAttribute('data-notification-count');
        if (count && count > 0) {
          const badge = document.createElement('div');
          badge.className = 'notification-badge';
          badge.textContent = count;
          element.style.position = 'relative';
          element.appendChild(badge);
        }
      });

      // Add click handlers for notification triggers
      document.addEventListener('click', function(e) {
        const notificationTrigger = e.target.closest('[data-trigger-notification]');
        if (notificationTrigger) {
          const type = notificationTrigger.getAttribute('data-notification-type') || 'info';
          const message = notificationTrigger.getAttribute('data-notification-message');
          queueNotification('System Notification', message, type);
        }
      });
    }

    // Initialize enhanced notifications when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
      enhanceNotifications();
      
      // Show welcome notification for first-time visitors
      if (!localStorage.getItem('perdiumWelcomeShown')) {
        setTimeout(() => {
          showAdvancedNotification(
            'Welcome to Perdium Management!', 
            'You can now manage all your perdium transactions with enhanced features and beautiful notifications.',
            'info',
            {
              customIcon: '👋',
              timer: 6000,
              withSoundWave: true
            }
          );
          localStorage.setItem('perdiumWelcomeShown', 'true');
        }, 2000);
      }
    });

    // Rest of your existing JavaScript code...
    // ... (previous JavaScript code remains the same)
    
    
    

    let currentEmployeeSalary = 0;
    let activeBlock = false;
    let overlapConflict = false;
    let lockForOfficer = false;

    function fmt(n){return (Number(n)||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});}
    function birr(n){return fmt(n)+' ብር';}
    function esc(s){return String(s ?? '').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m]));}

    function setDateInputsDisabled(disabled) {
      ['#departure_date','#arrival_date','#total_days'].forEach(sel=>{
        $(sel).prop('disabled', disabled);
        if (disabled) $(sel).addClass('input-locked'); else $(sel).removeClass('input-locked');
      });
    }
    function applyOfficerLock() {
      if (!isOfficer) return;
      setDateInputsDisabled(lockForOfficer);
      const shouldDisableSubmit = lockForOfficer || activeBlock || overlapConflict;
      $('#submitBtn').prop('disabled', shouldDisableSubmit);
    }

    function calculatePerdium() {
      const daysRaw = parseInt($('#total_days').val(), 10) || 0;
      const days = Math.max(1, daysRaw);
      const rate = parseFloat($('#perdium_rate').val()) || 0;
      let totalAmount = 0;
      if (rate > 0) {
        const A = rate * (0.1 + 0.25 + 0.25);
        const mid = Math.max(0, days - 2);
        const C = rate * (0.1 + 0.25);
        const nights = Math.max(0, days - 1);
        totalAmount = A + (A * mid) + C + (rate * 0.4 * nights);
      }
      $('#total_amount').val(totalAmount.toFixed(2));
      $('#total_amount_card').text(birr(totalAmount));
      $('#total_days_card').text(daysRaw);
    }

    function updateDates() {
      const totalDays = parseInt($('#total_days').val(),10) || 0;
      if (totalDays > 0) {
        if (!$('#departure_date').val()) {
          const tomorrow = new Date();
          tomorrow.setDate(tomorrow.getDate() + 1);
          $('#departure_date').val(tomorrow.toISOString().split('T')[0]);
        }
        if ($('#departure_date').val()) {
          const departureDate = new Date($('#departure_date').val());
          const arrivalDate = new Date(departureDate);
          arrivalDate.setDate(departureDate.getDate() + Math.max(0, totalDays - 1));
          $('#arrival_date').val(arrivalDate.toISOString().split('T')[0]);
        }
      }
      calculatePerdium();
      checkOverlapForDates();
    }

    function calculatePerdiumRate() {
      if (!currentEmployeeSalary || !$('#city_id').val()) return;
      const cityOption = $('#city_id').find('option:selected');
      const rateLow = parseFloat(cityOption.data('rate-low')) || 0;
      const rateMedium = parseFloat(cityOption.data('rate-medium')) || 0;
      const rateHigh = parseFloat(cityOption.data('rate-high')) || 0;
      let rate = rateLow;
      if (currentEmployeeSalary > 10000) rate = rateHigh;
      else if (currentEmployeeSalary > 5000) rate = rateMedium;
      $('#perdium_rate').val(rate.toFixed(2));
      calculatePerdium();
    }

    function updateOwnerDetailsCard() {
      const type = $('#budget_type').val();
      const selectedOption = $('#owner_id').find('option:selected');
      const ownerText = selectedOption.length ? selectedOption.text() : '';
      const ownerName = ownerText.split(' - ')[1] || ownerText || '-';
      $('#owner_name_display').text(ownerName);
      if (type === 'governmental') {
        const pkoox = selectedOption.data('p_koox') || '-';
        $('#p_koox_display').text(pkoox);
        $('#p_koox_row').show();
      } else {
        $('#p_koox_display').text('-');
        $('#p_koox_row').hide();
      }
    }

    function fetchAndPopulateOwners(preselectId=null) {
      const budgetType = $('#budget_type').val();
      const ownerSelect = $('#owner_id');
      const perdiumOwnerId = '<?php echo isset($perdium) ? (int)$perdium["budget_owner_id"] : ""; ?>';
      const perdiumBudgetType = '<?php echo isset($perdium) ? $perdium["budget_type"] : ""; ?>';
      ownerSelect.prop('disabled', true).html('<option value="">Loading...</option>').trigger('change.select2');
      $.ajax({
        url: 'ajax_get_owners.php',
        type: 'GET',
        data: { budget_type: budgetType },
        dataType: 'json',
        success: function(response) {
          if (response.success && Array.isArray(response.owners)) {
            ownerSelect.html('<option value="">Select Owner</option>');
            if (response.owners.length === 0) {
              ownerSelect.html('<option value="">No budget owners assigned to your account</option>');
            } else {
              response.owners.forEach(function(owner) {
                const option = new Option(`${owner.code} - ${owner.name}`, owner.id);
                if (budgetType === 'governmental' && owner.p_koox) $(option).data('p_koox', owner.p_koox);
                ownerSelect.append(option);
              });
              
              if (preselectId) {
                ownerSelect.val(String(preselectId));
              } else if (isEdit && perdiumOwnerId && perdiumBudgetType === budgetType) {
                ownerSelect.val(String(perdiumOwnerId));
              }
            }
          } else {
            ownerSelect.html('<option value="">Error loading owners</option>');
          }
        },
        error: function() { 
            ownerSelect.html('<option value="">Error loading owners</option>'); 
        },
        complete: function() {
            ownerSelect.prop('disabled', false).trigger('change.select2');
            if (ownerSelect.val()) ownerSelect.trigger('change');
            updateOwnerDetailsCard();
        }
      });
    }

    function updateFilterOwnerOptions() {
        const budgetType = $('#flt_type').val();
        const fltOwnerSelect = $('#flt_owner');
        
        // For officers, we need to reload the filter options based on current budget type
        if (!isAdmin) {
            // Clear and disable while loading
            fltOwnerSelect.prop('disabled', true).html('<option value="">Loading...</option>').trigger('change.select2');
            
            $.ajax({
                url: 'ajax_get_owners.php',
                type: 'GET',
                data: { budget_type: budgetType },
                dataType: 'json',
                success: function(response) {
                    if (response.success && Array.isArray(response.owners)) {
                        fltOwnerSelect.html('<option value="">Any Owner</option>');
                        response.owners.forEach(function(owner) {
                            const option = new Option(`${owner.code} - ${owner.name}`, owner.id);
                            $(option).data('budget-type', budgetType);
                            fltOwnerSelect.append(option);
                        });
                    } else {
                        fltOwnerSelect.html('<option value="">Error loading owners</option>');
                    }
                },
                error: function() { 
                    fltOwnerSelect.html('<option value="">Error loading owners</option>'); 
                },
                complete: function() {
                    fltOwnerSelect.prop('disabled', false).trigger('change.select2');
                }
            });
        } else {
            // Admin logic remains the same
            fltOwnerSelect.find('option').each(function() {
                const option = $(this);
                const optionBudgetType = option.data('budget-type');
                if (!optionBudgetType || optionBudgetType === budgetType) {
                    option.prop('disabled', false);
                } else {
                    option.prop('disabled', true);
                    if (option.is(':selected')) fltOwnerSelect.val('');
                }
            });
            fltOwnerSelect.trigger('change.select2');
        }
    }

    function setBudgetTypeUI(type) {
      if (type === 'program') {
        $('#et_month_box').hide();
        $('#et_month').prop('required', false);
        $('#rem_monthly_card').hide();
        $('#yearly_label').text('Available Yearly Budget');
        $('#flt_month_box').hide();
      } else {
        $('#et_month_box').show();
        $('#et_month').prop('required', true);
        $('#rem_monthly_card').show();
        $('#yearly_label').text('Available Yearly Perdium Budget');
        $('#flt_month_box').show();
      }
      applyOfficerLock();
    }

    function resetPerdiumFormOnTypeSwitch(){
      if (budgetTypeLocked) return; // Don't reset if budget type is locked
      
      filling = true;
      $('#owner_id').val('').trigger('change.select2');
      $('#employee_id').val('').trigger('change.select2');
      $('#city_id').val('').trigger('change.select2');
      $('#et_month').val('').trigger('change.select2');
      filling = false;

      $('#departure_date').val('');
      $('#arrival_date').val('');
      $('#total_days').val('');
      $('#perdium_rate').val(defaultPerdiumRate);
      $('#total_amount').val(0);
      $('#employee_position_display').text('-');
      $('#employee_department_display').text('Department: -');
      $('#employee_salary_display').text('Salary: -');
      $('#owner_name_display').text('-');
      $('#p_koox_row').hide();
      $('#p_koox_display').text('-');

      $('#total_days_card').text('0');
      $('#total_amount_card').text('0.00 ብር');

      $('#rem_monthly').text('0.00');
      $('#rem_yearly').text('0.00');
      $('#programs_total_card').hide();
      $('#government_grand_card').hide();

      // Keep lock until employee changes
      $('#employeeActiveWarning').addClass('hidden');
      $('#employeeOverlapWarning').addClass('hidden');

      $('#flt_type').val($('#budget_type').val()).trigger('change.select2');
      $('#flt_owner').val('').trigger('change.select2');
      $('#flt_month').val('').trigger('change.select2');
      $('#flt_employee').val('').trigger('change.select2');

      fetchPerdiumList();
    }

    function onBudgetTypeChange(){
      if (budgetTypeLocked) return; // Don't allow changes if budget type is locked
      
      const t = $('#budget_type').val();
      setBudgetTypeUI(t);
      resetPerdiumFormOnTypeSwitch();
      fetchAndPopulateOwners();
    }

    function onOwnerChange(){
      if (filling) return;
      updateOwnerDetailsCard();
      $('#flt_owner').val($('#owner_id').val()).trigger('change.select2');
      fetchPerdiumList();
      loadPerdiumRemaining();
      refreshGrandTotals();
      applyOfficerLock();
    }

    function onEmployeeChange() {
      if (filling) return;
      // Unlock on employee change; will relock if conflict found
      lockForOfficer = false;
      applyOfficerLock();

      const selectedOption = $('#employee_id').find('option:selected');
      const employeeText = selectedOption.text();
      const parts = employeeText.split(' - ');
      if (parts.length > 1) {
        $('#employee_position_display').text(parts[1]);
        $('#employee_department_display').text('Department: ' + (selectedOption.data('department') || '-'));
      } else {
        $('#employee_position_display').text('-');
        $('#employee_department_display').text('Department: -');
      }
      currentEmployeeSalary = parseFloat(selectedOption.data('salary')) || 0;
      $('#employee_salary_display').text('Salary: ' + fmt(currentEmployeeSalary));
      calculatePerdiumRate();
      $('#flt_employee').val($('#employee_id').val()).trigger('change.select2');
      fetchPerdiumList();
      checkEmployeeActive();
      checkOverlapForDates();
    }

    function onCityChange() { if (!filling) calculatePerdiumRate(); }

    function loadPerdiumRemaining(){
      const ownerId = $('#owner_id').val();
      const etMonth = $('#et_month').val();
      const year = new Date().getFullYear() - 8;
      const type = $('#budget_type').val();

      if (!ownerId) { $('#rem_monthly').text('0.00'); $('#rem_yearly').text('0.00'); return; }
      if (type === 'program') {
        $.get('get_remaining_program.php', { owner_id: ownerId, year: year }, function(resp){
          try {
            const j = typeof resp === 'string' ? JSON.parse(resp) : resp;
            $('#rem_yearly').text(fmt(j.remaining_yearly || 0));
            $('#rem_monthly').text('0.00');
          } catch (e) { $('#rem_yearly').text('0.00'); }
        }).fail(()=>$('#rem_yearly').text('0.00'));
      } else {
        if (!etMonth) { $('#rem_monthly').text('0.00'); $('#rem_yearly').text('0.00'); return; }
        $.get('get_remaining_perdium.php', { owner_id: ownerId, code_id: 6, month: etMonth, year: year }, function(resp){
          try {
            const rem = typeof resp === 'string' ? JSON.parse(resp) : resp;
            $('#rem_monthly').text(fmt(rem.remaining_monthly || 0));
            $('#rem_yearly').text(fmt(rem.remaining_yearly || 0));
          } catch (e) { $('#rem_monthly').text('0.00'); $('#rem_yearly').text('0.00'); }
        }).fail(()=>{ $('#rem_monthly').text('0.00'); $('#rem_yearly').text('0.00'); });
      }
    }

    function refreshGrandTotals(){
      const type    = $('#budget_type').val();
      const ownerId = $('#owner_id').val();
      const year    = new Date().getFullYear() - 8;

      $.get('ajax_perdium_grands.php', { budget_type:type, owner_id:ownerId, year:year }, function(resp){
        try{
          const j = typeof resp === 'string' ? JSON.parse(resp) : resp;
          if (type === 'program') {
            if (!ownerId) {
              $('#programs_total_card').show();
              $('#programs_total_amount').text(birr(j.programsTotalYearly || 0));
            } else {
              $('#programs_total_card').hide();
            }
            $('#government_grand_card').hide();
          } else {
            $('#programs_total_card').hide();
            $('#government_grand_card').show();
            if (!ownerId) {
              $('#gov_grand_label').text("Bureau's Yearly Government Budget");
              $('#gov_grand_amount').text(birr(j.govtBureauRemainingYearly || 0));
            } else {
              const ownerName = $('#owner_id option:selected').text().split(' - ')[1] || 'Selected Owner';
              $('#gov_grand_label').text(`${ownerName}'s Total Yearly Budget (Grand Yearly Budget)`);
              $('#gov_grand_amount').text(birr(j.govtOwnerYearlyRemaining || 0));
            }
          }
        }catch(e){
          $('#programs_total_card').hide();
          $('#government_grand_card').hide();
        }
      }).fail(function(){
        $('#programs_total_card').hide();
        $('#government_grand_card').hide();
      });
    }

    function toggleFilterMonth(){
      const t = $('#flt_type').val();
      if (t === 'program') { $('#flt_month_box').hide(); } else { $('#flt_month_box').show(); }
    }

    function syncFiltersFromForm(){
      $('#flt_type').val($('#budget_type').val()).trigger('change.select2');
      $('#flt_owner').val($('#owner_id').val()).trigger('change.select2');
      $('#flt_month').val($('#et_month').val()).trigger('change.select2');
      $('#flt_employee').val($('#employee_id').val()).trigger('change.select2');
    }

    function validateBeforeSubmit(){
      if (isOfficer && (lockForOfficer || activeBlock || overlapConflict)) {
        Swal.fire({
          icon: 'warning',
          title: 'Submission Blocked',
          text: 'This employee has an active or overlapping perdium. Fields are locked and submission is blocked.',
          confirmButtonColor: '#3b82f6',
          background: 'linear-gradient(135deg, #fff7ed, #ffedd5)',
          customClass: {
            popup: 'enhanced-popup'
          }
        });
        return false;
      }
      syncFiltersFromForm();
      return true;
    }

    function fetchPerdiumList(){
      const type  = $('#flt_type').val();
      const owner = $('#flt_owner').val();
      const month = $('#flt_month').val();
      const employee = $('#flt_employee').val();
      $('#transactionsTable').html('<tr><td colspan="7" class="px-4 py-4 text-center text-sm text-gray-500">Loading…</td></tr>');
      $.get('ajax_perdium_list.php', { budget_type:type, owner_id:owner, et_month:month, employee_id:employee }, function(resp){
        try{
          const j = typeof resp === 'string' ? JSON.parse(resp) : resp;
          const rows = j.rows || [];
          if(rows.length===0){
            $('#transactionsTable').html('<tr><td colspan="7" class="px-4 py-4 text-center text-sm text-gray-500">No perdium transactions found.</td></tr>');
            return;
          }
          let html='';
          rows.forEach(f=>{
            const printUrl = (f.budget_type==='program')
              ? `reports/preport2.php?id=${f.id}`
              : `reports/preport.php?id=${f.id}`;
            const dataJson = encodeURIComponent(JSON.stringify(f));
            let actions = `
              <a href="${printUrl}" class="px-3 py-1 bg-green-100 text-green-700 rounded-md hover:bg-green-200 transition-all duration-200 hover:scale-105" target="_blank">
                <i class="fas fa-print mr-1"></i> Print
              </a>
            `;
            <?php if ($is_admin): ?>
            actions += `
              <a href="?action=edit&id=${f.id}" class="px-3 py-1 bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200 transition-all duration-200 hover:scale-105">
                <i class="fas fa-edit mr-1"></i> Edit
              </a>
              <form method="POST" style="display:inline" onsubmit="return confirmDelete(this)">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="${f.id}">
                <input type="hidden" name="csrf" value="${csrfToken}">
                <button type="submit" class="px-3 py-1 bg-red-100 text-red-700 rounded-md hover:bg-red-200 transition-all duration-200 hover:scale-105">
                  <i class="fas fa-trash mr-1"></i> Delete
                </button>
              </form>
            `;
            <?php endif; ?>
            html += `
              <tr class="row-click hover:bg-gray-50 transition-colors duration-150 cursor-pointer" data-json="${dataJson}">
                <td class="px-4 py-4 text-sm text-gray-900">${esc((f.created_at||'').replace('T',' ').slice(0,19))}</td>
                <td class="px-4 py-4 text-sm text-gray-900">${esc(f.employee_name || '')}</td>
                <td class="px-4 py-4 text-sm text-gray-900">${esc(f.owner_code || '')}</td>
                <td class="px-4 py-4 text-sm text-gray-900">${esc(f.city_name || '')}</td>
                <td class="px-4 py-4 text-sm text-gray-900 ethio-font">${esc(f.et_month || '')}</td>
                <td class="px-4 py-4 text-sm text-gray-900 font-semibold">${Number(f.total_amount||0).toLocaleString(undefined,{minimumFractionDigits:2})}</td>
                <td class="px-4 py-4 text-sm"><div class="flex space-x-2">${actions}</div></td>
              </tr>`;
          });
          $('#transactionsTable').html(html);
          filterTransactions();
        }catch(e){
          $('#transactionsTable').html('<tr><td colspan="7" class="px-4 py-4 text-center text-sm text-gray-500">Failed to load.</td></tr>');
        }
      }).fail(()=>$('#transactionsTable').html('<tr><td colspan="7" class="px-4 py-4 text-center text-sm text-gray-500">Failed to load.</td></tr>'));
    }

    function confirmDelete(form) {
      Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel',
        background: 'linear-gradient(135deg, #fff7ed, #ffedd5)',
        customClass: {
          popup: 'enhanced-popup',
          confirmButton: 'enhanced-confirm',
          cancelButton: 'enhanced-cancel'
        },
        showClass: {
          popup: 'animate__animated animate__fadeInDown'
        },
        hideClass: {
          popup: 'animate__animated animate__fadeOutUp'
        }
      }).then((result) => {
        if (result.isConfirmed) {
          // Show loading state
          Swal.fire({
            title: 'Deleting...',
            text: 'Please wait while we delete the transaction',
            allowOutsideClick: false,
            didOpen: () => {
              Swal.showLoading()
            }
          });
          form.submit();
        }
      });
      return false;
    }

    function filterTransactions() {
      const filter = (document.getElementById('searchInput').value||'').toLowerCase();
      const rows = document.querySelectorAll('#transactionsTable tr');
      rows.forEach(row=>{
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
      });
    }

    function fillFormFromRow(d){
      try{
        filling = true;
        $('#budget_type').val(d.budget_type || 'governmental').trigger('change.select2');
        setBudgetTypeUI($('#budget_type').val());
        fetchAndPopulateOwners(d.budget_owner_id);

        if ((d.budget_type||'governmental') !== 'program') {
          $('#et_month').val(d.et_month || '').trigger('change.select2');
        } else {
          $('#et_month').val('').trigger('change.select2');
        }

        $('#employee_id').val(String(d.employee_id||'')).trigger('change.select2');
        onEmployeeChange();

        $('#city_id').val(String(d.city_id||'')).trigger('change.select2');

        $('#departure_date').val(d.departure_date || '');
        $('#arrival_date').val(d.arrival_date || '');
        $('#total_days').val(Number(d.total_days||0));
        $('#perdium_rate').val(Number(d.perdium_rate||0).toFixed(2));
        
        calculatePerdium();

        $('#flt_type').val(d.budget_type || 'governmental').trigger('change.select2');
        updateFilterOwnerOptions();
        $('#flt_owner').val(String(d.budget_owner_id||'')).trigger('change.select2');
        if ((d.budget_type||'governmental') !== 'program') {
          $('#flt_month').val(d.et_month || '').trigger('change.select2');
        } else {
          $('#flt_month').val('').trigger('change.select2');
        }
        $('#flt_employee').val(String(d.employee_id||'')).trigger('change.select2');
        
        filling = false;
        loadPerdiumRemaining();
        refreshGrandTotals();
      }catch(e){
        filling = false;
        console.error('fillFormFromRow error', e);
      }
    }

    function checkEmployeeActive(){
      const empId = $('#employee_id').val();
      if (!empId) {
        activeBlock = false;
        if (!lockForOfficer) $('#submitBtn').prop('disabled', false);
        $('#employeeActiveWarning').addClass('hidden');
        applyOfficerLock();
        return;
      }
      $.get('ajax_check_employee_perdium.php', { employee_id: empId, mode: 'active' }, function(resp){
        try{
          const j = typeof resp === 'string' ? JSON.parse(resp) : resp;
          if (j.active) {
            activeBlock = true;
            if (isOfficer) { lockForOfficer = true; applyOfficerLock(); }
            $('#block_until_date').text(j.block_until || '-');
            $('#employeeActiveWarning').removeClass('hidden');
            $('#submitBtn').prop('disabled', true);
          } else {
            activeBlock = false;
            $('#employeeActiveWarning').addClass('hidden');
            $('#submitBtn').prop('disabled', isOfficer ? (lockForOfficer || overlapConflict) : overlapConflict);
            applyOfficerLock();
          }
        }catch(e){
          activeBlock = false;
          $('#employeeActiveWarning').addClass('hidden');
          $('#submitBtn').prop('disabled', isOfficer ? (lockForOfficer || overlapConflict) : overlapConflict);
          applyOfficerLock();
        }
      }).fail(function(){
        activeBlock = false;
        $('#employeeActiveWarning').addClass('hidden');
        $('#submitBtn').prop('disabled', isOfficer ? (lockForOfficer || overlapConflict) : overlapConflict);
        applyOfficerLock();
      });
    }

    function checkOverlapForDates(){
      const empId = $('#employee_id').val();
      const dep = $('#departure_date').val();
      const arr = $('#arrival_date').val();
      if (!empId || !dep || !arr) {
        overlapConflict = false;
        $('#employeeOverlapWarning').addClass('hidden');
        $('#submitBtn').prop('disabled', isOfficer ? (lockForOfficer || activeBlock) : activeBlock);
        applyOfficerLock();
        return;
      }
      $.get('ajax_check_employee_perdium.php', { employee_id: empId, start: dep, end: arr, exclude_id: <?php echo isset($perdium) ? (int)$perdium['id'] : 'null'; ?> }, function(resp){
        try{
          const j = typeof resp === 'string' ? JSON.parse(resp) : resp;
          if (j.overlap) {
            overlapConflict = true;
            if (isOfficer) { lockForOfficer = true; applyOfficerLock(); }
            $('#employeeOverlapWarning').removeClass('hidden');
            $('#submitBtn').prop('disabled', true);
          } else {
            overlapConflict = false;
            $('#employeeOverlapWarning').addClass('hidden');
            $('#submitBtn').prop('disabled', isOfficer ? (lockForOfficer || activeBlock) : activeBlock);
            applyOfficerLock();
          }
        }catch(e){
          overlapConflict = false;
          $('#employeeOverlapWarning').addClass('hidden');
          $('#submitBtn').prop('disabled', isOfficer ? (lockForOfficer || activeBlock) : activeBlock);
          applyOfficerLock();
        }
      }).fail(function(){
        overlapConflict = false;
        $('#employeeOverlapWarning').addClass('hidden');
        $('#submitBtn').prop('disabled', isOfficer ? (lockForOfficer || activeBlock) : activeBlock);
        applyOfficerLock();
      });
    }

    $(document).ready(function(){
      $('.select2').select2({ theme:'classic', width:'100%',
        matcher: function(params, data) {
          if ($.trim(params.term) === '') return data;
          if (typeof data.text === 'undefined') return null;
          if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) return data;
          for (const key in data.element.dataset) {
            if (String(data.element.dataset[key]).toLowerCase().indexOf(params.term.toLowerCase()) > -1) return data;
          }
          return null;
        }
      });

      // Set the default budget type on page load
      if (!isEdit) {
        $('#budget_type').val(defaultBudgetType);
        $('#flt_type').val(defaultBudgetType);
      }

      // Main form bindings
      if (!budgetTypeLocked) {
        $('#budget_type').on('change', function(){ if (!filling) onBudgetTypeChange(); });
      }
      $('#owner_id').on('change', function(){ if (!filling) onOwnerChange(); });
      $('#employee_id').on('change', function(){ if (!filling) onEmployeeChange(); });
      $('#city_id').on('change', function(){ if (!filling) onCityChange(); });
      $('#et_month').on('change', function(){
        if (filling) return;
        $('#flt_month').val($('#et_month').val()).trigger('change.select2');
        fetchPerdiumList();
        loadPerdiumRemaining();
      });
      $('#departure_date, #arrival_date').on('change', function(){ checkOverlapForDates(); });

      // Filters
      if (!budgetTypeLocked) {
        $('#flt_type').on('change', function(){
          updateFilterOwnerOptions();
          toggleFilterMonth();
          fetchPerdiumList();
        });
      }
      $('#flt_owner, #flt_month, #flt_employee').on('change', function(){ fetchPerdiumList(); });

      // Row click -> fill form
      $('#transactionsTable').on('click', 'tr.row-click', function(e){
        if ($(e.target).closest('a,button,form').length) return;
        const dataJson = $(this).attr('data-json');
        if (!dataJson) return;
        try {
          const d = JSON.parse(decodeURIComponent(dataJson));
          fillFormFromRow(d);
        } catch (err) { console.error('row parse error', err); }
      });

      // Init
      setBudgetTypeUI($('#budget_type').val());
      fetchAndPopulateOwners();
      updateFilterOwnerOptions();
      toggleFilterMonth();

      if (isEdit) {
        onEmployeeChange();
        loadPerdiumRemaining();
        refreshGrandTotals();
        syncFiltersFromForm();
      } else {
        calculatePerdium();
        loadPerdiumRemaining();
        refreshGrandTotals();
        syncFiltersFromForm();
      }
      fetchPerdiumList();
    });

    // Mobile sidebar toggle
    document.getElementById('sidebarToggle')?.addEventListener('click', ()=>{
      document.getElementById('sidebar')?.classList.toggle('active');
    });

  </script>
</body>
</html>