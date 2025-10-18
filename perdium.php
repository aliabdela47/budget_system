<?php
require_once 'includes/init.php';

// Ethiopian calendar library with fallback
$ethiopianCalendarAvailable = false;
try {
    if (file_exists('vendor/autoload.php')) {
        require_once 'vendor/autoload.php';
        if (class_exists('Andegna\EthiopianDateTime')) {
            $ethiopianCalendarAvailable = true;
        }
    }
} catch (Exception $e) {
    $ethiopianCalendarAvailable = false;
}

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function csrf_check($t) {
    return hash_equals($_SESSION['csrf'] ?? '', $t ?? '');
}

// Roles
$is_admin = (($_SESSION['role'] ?? '') === 'admin');
$is_officer = (($_SESSION['role'] ?? '') === 'officer');

// Helpers with fallback for Ethiopian calendar
function ecYear(): int {
    global $ethiopianCalendarAvailable;
    if ($ethiopianCalendarAvailable) {
        try {
            $ethiopian = new Andegna\EthiopianDateTime();
            return $ethiopian->getYear();
        } catch (Exception $e) {
            // Fallback: Ethiopian year is approximately 7-8 years behind Gregorian
            return (int)date('Y') - 8;
        }
    }
    // Fallback calculation
    return (int)date('Y') - 8;
}

function monthsEC(): array {
    return ['መስከረም',
        'ጥቅምት',
        'ህዳር',
        'ታኅሳስ',
        'ጥር',
        'የካቲት',
        'መጋቢት',
        'ሚያዝያ',
        'ግንቦት',
        'ሰኔ',
        'ሐምሌ',
        'ነሃሴ'];
}

function gregorianToEthiopian($gregorianDate) {
    global $ethiopianCalendarAvailable;
    try {
        if (empty($gregorianDate)) return '-';

        $date = DateTime::createFromFormat('Y-m-d H:i:s', $gregorianDate);
        if (!$date) {
            $date = DateTime::createFromFormat('Y-m-d', $gregorianDate);
        }
        if (!$date) return $gregorianDate;

        if ($ethiopianCalendarAvailable) {
            $ethiopian = new Andegna\EthiopianDateTime($date);
            return $ethiopian->format('j-m-Y');
        } else {
            // Fallback: return Gregorian date in different format
            return $date->format('d/m/Y');
        }
    } catch (Exception $e) {
        return $gregorianDate;
    }
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
    $params = [$employee_id,
        $dep,
        $arr];
    if ($exclude_id) {
        $sql .= " AND id <> ?"; $params[] = $exclude_id;
    }
    $s = $pdo->prepare($sql);
    $s->execute($params);
    return ((int)$s->fetchColumn()) > 0;
}
function isEmployeeActive(PDO $pdo, int $employee_id, ?int $exclude_id = null): bool {
    $sql = "SELECT COUNT(*) FROM perdium_transactions
            WHERE employee_id = ?
              AND CURDATE() BETWEEN departure_date AND arrival_date";
    $params = [$employee_id];
    if ($exclude_id) {
        $sql .= " AND id <> ?"; $params[] = $exclude_id;
    }
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
    $year = (int)($tx['year'] ?? ecYear());
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
            'budget_type' => 'program',
            'prog_budget_id' => (int)$row['id'],
            'gov_budget_id' => null,
            'amount' => $amount
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
            'budget_type' => 'program',
            'prog_budget_id' => null,
            'gov_budget_id' => (int)$r['id'],
            'amount' => $use
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
            'budget_type' => 'governmental',
            'gov_budget_id' => (int)$b['id'],
            'prog_budget_id' => null,
            'amount' => $amount
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
        'budget_type' => 'governmental',
        'gov_budget_id' => (int)$y['id'],
        'prog_budget_id' => null,
        'amount' => $amount
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
if (!$user_id) {
    header('Location: login.php'); exit;
}
$stmt = $pdo->prepare("SELECT name, profile_picture, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$user_name = $user_data['name'] ?? ($_SESSION['username'] ?? 'User');
$profile_picture = $user_data['profile_picture'] ?? '';
$user_email = $user_data['email'] ?? '';

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

// Get employees with both English and Amharic names
$employees = $pdo->query("
    SELECT e.*,
           CONCAT(e.name, ' | ', COALESCE(e.name_am, '')) as display_name,
           (SELECT COUNT(*) FROM emp_list e2 WHERE e2.name = e.name) as name_count
    FROM emp_list e
    ORDER BY e.name, e.directorate
    ")->fetchAll(PDO::FETCH_ASSOC);

$cities = $pdo->query("SELECT * FROM cities ORDER BY name_english")->fetchAll(PDO::FETCH_ASSOC);
$months = monthsEC();

// Edit mode
$perdium = null;
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
    if (!$is_admin) {
        http_response_code(403); exit('Forbidden');
    }
    $stmt = $pdo->prepare("SELECT * FROM perdium_transactions WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $perdium = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Flash helpers with SweetAlert2 integration
function set_flash($msg, $type = 'info') {
    $_SESSION['flash_message'] = $msg;
    $_SESSION['flash_type'] = $type;
}

// Handle POST (no output before redirects)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // DELETE via POST
    if ($action === 'delete') {
        if (!$is_admin) {
            http_response_code(403); exit('Forbidden');
        }
        if (!csrf_check($_POST['csrf'] ?? '')) {
            http_response_code(400); exit('Bad CSRF');
        }
        $del_id = (int)($_POST['id'] ?? 0);
        if ($del_id <= 0) {
            set_flash('Invalid delete request', 'error'); header('Location: perdium.php'); exit;
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
            set_flash('Perdium transaction deleted successfully', 'success');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('Error deleting transaction: ' . $e->getMessage(), 'error');
        }
        header('Location: perdium.php'); exit;
    }

    // ADD/UPDATE
    if (!in_array($_SESSION['role'] ?? '', ['admin', 'officer'], true)) {
        http_response_code(403); exit('Forbidden');
    }
    if (!csrf_check($_POST['csrf'] ?? '')) {
        http_response_code(400); exit('Bad CSRF');
    }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
    $is_update = (($action === 'update') && $id);
    if ($is_update && !$is_admin) {
        http_response_code(403); exit('Forbidden');
    }

    $budget_type = (($_POST['budget_type'] ?? 'governmental') === 'program') ? 'program' : 'governmental';
    $employee_id = (int)($_POST['employee_id'] ?? 0);
    $owner_id = (int)($_POST['owner_id'] ?? 0);
    $city_id = (int)($_POST['city_id'] ?? 0);
    $perdium_rate = (float)($_POST['perdium_rate'] ?? 0);
    $total_days = (int)($_POST['total_days'] ?? 0);
    $departure_date = trim($_POST['departure_date'] ?? '');
    $arrival_date = trim($_POST['arrival_date'] ?? '');
    $et_month = $budget_type === 'program' ? '' : trim($_POST['et_month'] ?? '');

    // Validation
    if ($employee_id <= 0 || !employeeExists($pdo, $employee_id)) {
        set_flash('Invalid employee selected', 'error');
    } elseif ($owner_id <= 0 || !ownerExists($pdo, $budget_type, $owner_id)) {
        set_flash('Invalid budget owner selected', 'error');
    } elseif ($city_id <= 0 || !cityExists($pdo, $city_id)) {
        set_flash('Invalid destination city selected', 'error');
    } elseif ($perdium_rate <= 0) {
        set_flash('Perdium rate must be greater than 0', 'error');
    } elseif ($total_days < 1) {
        set_flash('Total days must be at least 1', 'error');
    } elseif (!$departure_date || !$arrival_date) {
        set_flash('Departure and arrival dates are required', 'error');
    } elseif (strtotime($arrival_date) < strtotime($departure_date)) {
        set_flash('Arrival date cannot be before departure date', 'error');
    } elseif ($budget_type === 'governmental' && $et_month !== '' && !in_array($et_month, monthsEC(), true)) {
        set_flash('Invalid Ethiopian month selected', 'error');
    }
    // Budget access validation
    elseif (!$is_admin && !hasBudgetAccess($pdo, $user_id, $budget_type, $owner_id)) {
        set_flash('You do not have access to this budget owner', 'error');
    } else {
        if (isEmployeeActive($pdo, $employee_id, $is_update ? $id : null)) {
            set_flash('This employee is currently on a perdium; you can create a new one only after the current end date.', 'warning');
        } elseif (hasOverlap($pdo, $employee_id, $departure_date, $arrival_date, $is_update ? $id : null)) {
            set_flash('Selected dates overlap with another perdium for this employee.', 'warning');
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
                    set_flash($is_update ? 'Program perdium transaction updated successfully' : 'Program perdium transaction added successfully', 'success');

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
                    set_flash($is_update ? 'Perdium transaction updated successfully' : 'Perdium transaction added successfully', 'success');
                }

                $pdo->commit();
                header('Location: perdium.php'); exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                set_flash('Transaction failed: ' . $e->getMessage(), 'error');
            }
        }
    }
}

// Flash
$flash_message = $_SESSION['flash_message'] ?? null;
$flash_type = $_SESSION['flash_type'] ?? null;
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php
    $pageTitle = 'Per Diem Management';
    require_once 'includes/head.php';

    ?>





    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Add these animations to your existing CSS */
        @keyframes gentle-bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }

        .welcome-bounce {
            animation: gentle-bounce 2s infinite;
        }

        /* Gradient text effect */
        .gradient-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Pulse animation for welcome elements */
        @keyframes welcome-pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }

        .welcome-pulse {
            animation: welcome-pulse 2s ease-in-out infinite;
        }
        /* Modern gradient backgrounds and animations */
        .gradient-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .gradient-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .gradient-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: all 0.3s ease;
        }

        .gradient-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        /* Mobile Responsive Table */
        @media (max-width: 768px) {
            .table-responsive {
                display: block;
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            /* Modern table styles */
            .table-modern {
                min-width: 800px;
                /* Minimum table width for mobile */
            }

            .table-modern th,
            .table-modern td {
                padding: 8px 12px;
                font-size: 0.875rem;
            }

            /* Hide month column for program budget type */
            .budget-type-program .month-column {
                display: none;
            }
            .table-modern thead {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }

            .table-modern tbody tr {
                transition: all 0.2s ease;
            }

            .table-modern tbody tr:hover {
                background-color: #f8fafc;
                transform: scale(1.01);
            }

            /* Card hover effects */
            .modern-card {
                transition: all 0.3s ease;
                border: 1px solid rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(10px);
            }

            .modern-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            }

            /* Input focus effects */
            .modern-input:focus {
                border-color: #667eea;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
                transform: translateY(-1px);
            }

            /* Custom scrollbar */
            .custom-scrollbar::-webkit-scrollbar {
                width: 6px;
            }

            .custom-scrollbar::-webkit-scrollbar-track {
                background: #f1f5f9;
                border-radius: 10px;
            }

            .custom-scrollbar::-webkit-scrollbar-thumb {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 10px;
            }
            /* Responsive text handling */
            .employee-name-cell {
                max-width: 150px;
                min-width: 120px;
            }

            .owner-name-cell {
                max-width: 120px;
                min-width: 100px;
            }

            .city-name-cell {
                max-width: 100px;
                min-width: 80px;
            }

            .actions-cell {
                min-width: 140px;
            }
        }

        /* Better table cell handling */
        .employee-name-cell {
            white-space: normal !important;
            word-wrap: break-word;
            max-width: 200px;
        }

        .owner-name-cell {
            white-space: normal !important;
            word-wrap: break-word;
        }

        .city-name-cell {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Program budget specific styles */
        .budget-type-program .month-column {
            display: none;
        }

        /* Pulse animation for important elements */
        @keyframes gentle-pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        .pulse-gentle {
            animation: gentle-pulse 2s infinite;
        }

        /* Glass morphism effect */
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* User Profile Styles */
        .user-profile {
            position: relative;
            cursor: pointer;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }

        .user-avatar:hover {
            border-color: rgba(255, 255, 255, 0.6);
            transform: scale(1.05);
        }

        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 280px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }

        .user-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .user-dropdown-header {
            padding: 20px;
            border-bottom: 1px solid #f3f4f6;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px 16px 0 0;
        }

        .user-dropdown-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            color: #4b5563;
            text-decoration: none;
            transition: all 0.2s ease;
            border-bottom: 1px solid #f9fafb;
        }

        .user-dropdown-item:hover {
            background: #f8fafc;
            color: #1f2937;
        }

        .user-dropdown-item:last-child {
            border-bottom: none;
            border-radius: 0 0 16px 16px;
        }

        .user-dropdown-item.logout {
            color: #ef4444;
        }

        .user-dropdown-item.logout:hover {
            background: #fef2f2;
            color: #dc2626;
        }

        .user-dropdown-icon {
            width: 20px;
            margin-right: 12px;
            text-align: center;
        }

        /* Warning styles */
        .employee-warning {
            border-color: #ef4444 !important;
            background-color: #fef2f2 !important;
        }

        .text-red-600 {
            color: #dc2626;
        }

        /* Ethiopian font for months */
        .ethio-font {
            font-family: 'Nyala', 'Ebrima', 'Abyssinica SIL', 'GF Zemen', sans-serif;
            font-size: 1.1em;
        }

        .rotate-180 {
            transform: rotate(180deg);
        }

        /* Ensure sidebar covers full viewport height */
        .sidebar {
            height: 100vh !important;
            overflow-y: auto;
        }

        /* Main Content Layout */
        .main-content {
            margin-left: 280px;
            transition: margin-left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 100vh;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

        /* When sidebar is collapsed on desktop */
        .sidebar.collapsed ~ .main-content {
            margin-left: 80px;
        }

        /* Mobile full width */
        @media (max-width: 1023px) {
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }

        /* Content Container */
        .content-container {
            padding: 2rem;
            width: 100%;
            box-sizing: border-box;
        }
        
        /* Employee Card Styles */
        .employee-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        /* City Card Styles */
        .city-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        /* Enhanced Warning Styles */
        .warning-card {
            border-left: 6px solid #f59e0b;
            animation: pulse-warning 2s infinite;
        }

        .error-card {
            border-left: 6px solid #ef4444;
            animation: pulse-error 2s infinite;
        }

        @keyframes pulse-warning {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4);
            }
            50% {
                box-shadow: 0 0 0 10px rgba(245, 158, 11, 0);
            }
        }

        @keyframes pulse-error {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
            }
            50% {
                box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
            }
        }

        /* Employee dropdown enhanced styling */
        .employee-dropdown-option {
            padding: 8px 12px;
            border-bottom: 1px solid #f1f5f9;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .employee-name {
            font-weight: 600;
            color: white;
            font-size: 1em;
        }

        .employee-name-am {
            font-family: 'Nyala', 'Abyssinica SIL', 'GF Zemen', sans-serif;
            color: #e0e0e0;
            font-size: 0.95em;
            margin-top: 2px;
        }

        .employee-department {
            font-size: 0.8em;
            color: #c0c0c0;
            font-style: italic;
            margin-top: 2px;
        }

        /* Select2 dropdown styling for employee */
        .select2-results__option--highlighted .employee-name,
        .select2-results__option--highlighted .employee-name-am,
        .select2-results__option--highlighted .employee-department {
            color: white !important;
        }

        .select2-results__option--highlighted {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }

        .select2-results__option {
            padding: 8px 12px !important;
            border-bottom: 1px solid rgba(255,255,255,0.1) !important;
        }
    </style>
</head>
<body class="text-slate-700 flex bg-gray-50 min-h-screen">
    
   <?php require_once  'includes/sidebar-component.php'; ?>
    <!-- Main Content -->
    <div class="main-content flex-1 min-h-screen" id="mainContent">
        <?php require_once 'includes/header.php'; ?>

       

           


            <!-- Enhanced Flash Messaging System -->
            <?php if ($flash_message): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    <?php
                    // Ensure flash_message is properly formatted
                    $flash_message_text = $flash_message;
                    $flash_message_type = $flash_type ?? 'info';

                    // Handle JSON strings if they exist
                    if (is_string($flash_message_text) && strpos($flash_message_text, '{') === 0) {
                        try {
                            $decoded = json_decode($flash_message_text, true);
                            if (isset($decoded['message'])) {
                                $flash_message_text = $decoded['message'];
                            }
                            if (isset($decoded['type'])) {
                                $flash_message_type = $decoded['type'];
                            }
                        } catch (e) {
                            // Keep original message if JSON decode fails
                        }
                    }
                    ?>

                    const message = <?php echo json_encode($flash_message_text); ?>;
                    const messageType = <?php echo json_encode($flash_message_type); ?>;

                    // Special handling for welcome messages
                    if (message.toLowerCase().includes('welcome') || message.toLowerCase().includes('welcome back')) {
                        showWelcomeMessage(message);
                    } else {
                        showRegularFlashMessage(message, messageType);
                    }

                    function showWelcomeMessage(welcomeText) {
                        Swal.fire({
                            title: '<div class="flex items-center justify-center mb-4">' +
                            '<div class="w-16 h-16 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center mr-4">' +
                            '<i class="fas fa-user-check text-2xl text-white"></i>' +
                            '</div>' +
                            '<div class="text-left">' +
                            '<h2 class="text-2xl font-bold text-gray-800">Welcome Back!</h2>' +
                            '<p class="text-gray-600">Great to see you again</p>' +
                            '</div>' +
                            '</div>',
                            html: `
                            <div class="text-center py-4">
                            <div class="mb-6">
                            <div class="w-20 h-20 bg-gradient-to-r from-green-400 to-blue-500 rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg">
                            <i class="fas fa-smile-beam text-3xl text-white"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-800 mb-2">${welcomeText}</h3>
                            <p class="text-gray-600">You have successfully logged in to the Financial Management Portal</p>
                            </div>

                            <div class="grid grid-cols-3 gap-4 mb-6">
                            <div class="text-center">
                            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-wallet text-blue-600"></i>
                            </div>
                            <span class="text-sm text-gray-600">Per Diem</span>
                            </div>
                            <div class="text-center">
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-gas-pump text-green-600"></i>
                            </div>
                            <span class="text-sm text-gray-600">Fuel</span>
                            </div>
                            <div class="text-center">
                            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-chart-bar text-purple-600"></i>
                            </div>
                            <span class="text-sm text-gray-600">Reports</span>
                            </div>
                            </div>

                            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-4 border border-blue-200">
                            <div class="flex items-center justify-center space-x-2 text-blue-700">
                            <i class="fas fa-clock"></i>
                            <span class="text-sm font-medium">Login Time: ${new Date().toLocaleTimeString()}</span>
                            </div>
                            </div>
                            </div>
                            `,
                            showConfirmButton: true,
                            confirmButtonText: 'Continue to Dashboard',
                            confirmButtonColor: '#3b82f6',
                            background: '#ffffff',
                            width: '500px',
                            customClass: {
                                popup: 'rounded-2xl shadow-2xl border border-gray-200',
                                confirmButton: 'px-6 py-3 rounded-lg font-semibold shadow-lg hover:shadow-xl transition-all duration-200'
                            },
                            showClass: {
                                popup: 'animate__animated animate__fadeInDown animate__faster'
                            },
                            hideClass: {
                                popup: 'animate__animated animate__fadeOutUp animate__faster'
                            },
                            timer: 5000,
                            timerProgressBar: true,
                            didOpen: () => {
                                // Add some interactive effects
                                const popup = Swal.getPopup();
                                popup.style.transform = 'scale(0.95)';
                                setTimeout(() => {
                                    popup.style.transform = 'scale(1)';
                                    popup.style.transition = 'transform 0.3s ease';
                                }, 100);
                            }
                        });
                    }

                    function showRegularFlashMessage(message, type) {
                        const toastConfigs = {
                            success: {
                                icon: 'success',
                                title: 'Success!',
                                background: '#f0f9ff',
                                iconColor: '#10b981',
                                timer: 4000
                            },
                            error: {
                                icon: 'error',
                                title: 'Error!',
                                background: '#fef2f2',
                                iconColor: '#ef4444',
                                timer: 5000
                            },
                            warning: {
                                icon: 'warning',
                                title: 'Warning!',
                                background: '#fffbeb',
                                iconColor: '#f59e0b',
                                timer: 4500
                            },
                            info: {
                                icon: 'info',
                                title: 'Information',
                                background: '#eff6ff',
                                iconColor: '#3b82f6',
                                timer: 4000
                            }
                        };

                        const config = toastConfigs[type] || toastConfigs.info;

                        Swal.fire({
                            icon: config.icon,
                            title: config.title,
                            text: message,
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: config.timer,
                            timerProgressBar: true,
                            background: config.background,
                            iconColor: config.iconColor,
                            customClass: {
                                popup: 'rounded-xl shadow-xl border border-gray-200'
                            },
                            didOpen: (toast) => {
                                toast.addEventListener('mouseenter', Swal.stopTimer);
                                toast.addEventListener('mouseleave', Swal.resumeTimer);
                            }
                        });
                    }
                });
            </script>
            <?php endif; ?>

            <!-- Perdium Form -->
            <div class="bg-white rounded-2xl p-8 shadow-xl mb-8 border border-gray-100">
                <h2 class="text-2xl font-bold text-slate-800 mb-6 flex items-center">
                    <i class="fas fa-plus-circle mr-3 text-blue-500"></i>
                    <?php echo isset($perdium) ? 'Edit Perdium Transaction' : 'Add New Perdium Transaction'; ?>
                </h2>
                <form id="perdiumForm" method="POST" class="space-y-6" onsubmit="return validateBeforeSubmit();">
                    <?php if (isset($perdium)): ?>
                    <input type="hidden" name="id" value="<?php echo (int)$perdium['id']; ?>">
                    <input type="hidden" name="action" value="update">
                    <?php endif; ?>
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">

                    <!-- Top row -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Budget Source Type -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Budget Source Type *</label>
                            <select name="budget_type" id="budget_type" class="w-full select2 modern-input" <?php echo $budget_type_locked ? 'disabled' : ''; ?>>
                                <option value="governmental" <?php
                                    if (isset($perdium)) {
                                        echo $perdium['budget_type'] === 'governmental' ? 'selected' : '';
                                    } else {
                                        echo $default_budget_type === 'governmental' ? 'selected' : '';
                                    }
                                    ?>>Government Budget</option>
                                <option value="program" <?php
                                    if (isset($perdium)) {
                                        echo $perdium['budget_type'] === 'program' ? 'selected' : '';
                                    } else {
                                        echo $default_budget_type === 'program' ? 'selected' : '';
                                    }
                                    ?>>Programs Budget</option>
                            </select>
                            <?php if ($budget_type_locked): ?>
                            <input type="hidden" name="budget_type" value="<?php echo $default_budget_type; ?>">
                            <p class="text-xs text-gray-500 mt-2">
                                Budget source is automatically set based on your assignments
                            </p>
                            <?php endif; ?>
                        </div>

                        <!-- Budget Owner -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Budget Owner *</label>
                            <select name="owner_id" id="owner_id" required class="w-full select2 modern-input">
                                <option value="">Loading your budget owners...</option>
                            </select>
                        </div>

                        <!-- Program/Owner Card -->
                        <div class="program-card p-5 rounded-2xl bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500 text-white shadow-lg transform transition-all duration-300">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h3 class="text-sm font-semibold text-white opacity-90 mb-3 flex items-center">
                                        <i class="fas fa-project-diagram mr-2"></i>Budget Owner Details
                                    </h3>
                                    <div class="space-y-2">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-xl flex items-center justify-center backdrop-blur-sm">
                                                <i class="fas fa-code-branch text-xl text-white"></i>
                                            </div>
                                            <div>
                                                <p id="program_p_koox_display" class="text-lg font-bold text-white">
                                                    -
                                                </p>
                                                <p id="program_name_display" class="text-sm text-white opacity-80">
                                                    Owner: -
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="w-16 h-16 bg-white bg-opacity-10 rounded-full flex items-center justify-center backdrop-blur-sm">
                                    <i class="fas fa-chart-line text-2xl text-white"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Second row -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Employee -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Employee *</label>
                            <select name="employee_id" id="employee_id" required class="w-full select2 modern-input">
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $e): ?>
                                <option value="<?php echo (int)$e['id']; ?>"
                                    data-salary="<?php echo htmlspecialchars($e['salary']); ?>"
                                    data-position="<?php echo htmlspecialchars($e['taamagoli'] ?? ''); ?>"
                                    data-department="<?php echo htmlspecialchars($e['directorate'] ?? ''); ?>"
                                    data-name-am="<?php echo htmlspecialchars($e['name_am'] ?? ''); ?>"
                                    <?php
                                    $sel = false;
                                    if (isset($perdium) && $perdium['employee_id'] == $e['id']) $sel = true;
                                    if (isset($_POST['employee_id']) && $_POST['employee_id'] == $e['id']) $sel = true;
                                    echo $sel ? 'selected' : '';
                                    ?>>
                                    <?php
                                    $displayName = ($e['name'] ?? '') . ' | ' . ($e['name_am'] ?? '');
                                    // Add department if there are duplicate names
                                    if ($e['name_count'] > 1 && !empty($e['directorate'])) {
                                        $displayName .= ' - ' . $e['directorate'];
                                    }
                                    echo htmlspecialchars($displayName);
                                    ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Destination City -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Destination City *</label>
                            <select name="city_id" id="city_id" required class="w-full select2 modern-input">
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

                        <!-- Ethiopian Month (Gov only) -->
                        <div id="et_month_box">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Ethiopian Month *</label>
                            <select name="et_month" id="et_month" required class="w-full select2 modern-input">
                                <option value="">Select Month</option>
                                <?php foreach ($months as $m): ?>
                                <option value="<?php echo htmlspecialchars($m); ?>"
                                    <?php
                                    $sel = false;
                                    if (isset($perdium) && $perdium['et_month'] == $m && $perdium['budget_type'] !== 'program') $sel = true;
                                    if (isset($_POST['et_month']) && $_POST['et_month'] == $m) $sel = true;
                                    echo $sel ? 'selected' : '';
                                    ?>>
                                    <?php echo htmlspecialchars($m); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Employee Details Card -->
                    <div class="employee-card p-5 rounded-2xl bg-gradient-to-br from-cyan-500 via-blue-500 to-teal-500 text-white shadow-lg transform transition-all duration-300">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h3 class="text-sm font-semibold text-white opacity-90 mb-3 flex items-center">
                                    <i class="fas fa-user-tie mr-2"></i>Employee Details
                                </h3>
                                <div class="space-y-2">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-xl flex items-center justify-center backdrop-blur-sm">
                                            <i class="fas fa-id-card text-xl text-white"></i>
                                        </div>
                                        <div>
                                            <p id="employee_position_display" class="text-lg font-bold text-white">
                                                -
                                            </p>
                                            <p id="employee_department_display" class="text-sm text-white opacity-80">
                                                Department: -
                                            </p>
                                            <p id="employee_salary_display" class="text-sm text-white opacity-80">
                                                Salary: -
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="w-16 h-16 bg-white bg-opacity-10 rounded-full flex items-center justify-center backdrop-blur-sm">
                                <i class="fas fa-user text-2xl text-white"></i>
                            </div>
                        </div>
                    </div>

                    <!-- City Details Card -->
                    <div class="city-card p-5 rounded-2xl bg-gradient-to-br from-amber-500 via-orange-500 to-red-500 text-white shadow-lg transform transition-all duration-300">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h3 class="text-sm font-semibold text-white opacity-90 mb-3 flex items-center">
                                    <i class="fas fa-map-marker-alt mr-2"></i>Destination Details
                                </h3>
                                <div class="space-y-2">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-xl flex items-center justify-center backdrop-blur-sm">
                                            <i class="fas fa-city text-xl text-white"></i>
                                        </div>
                                        <div>
                                            <p id="city_rate_low_display" class="text-sm text-white opacity-80">
                                                Low Rate: -
                                            </p>
                                            <p id="city_rate_medium_display" class="text-sm text-white opacity-80">
                                                Medium Rate: -
                                            </p>
                                            <p id="city_rate_high_display" class="text-sm text-white opacity-80">
                                                High Rate: -
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="w-16 h-16 bg-white bg-opacity-10 rounded-full flex items-center justify-center backdrop-blur-sm">
                                <i class="fas fa-plane text-2xl text-white"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Dates and Rates -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Departure Date *</label>
                            <input type="date" name="departure_date" id="departure_date" value="<?php
                            echo isset($perdium) ? htmlspecialchars($perdium['departure_date']) : (isset($_POST['departure_date']) ? htmlspecialchars($_POST['departure_date']) : '');
                            ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent modern-input transition-all duration-200" onchange="updateDates()">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Arrival Date *</label>
                            <input type="date" name="arrival_date" id="arrival_date" value="<?php
                            echo isset($perdium) ? htmlspecialchars($perdium['arrival_date']) : (isset($_POST['arrival_date']) ? htmlspecialchars($_POST['arrival_date']) : '');
                            ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent modern-input transition-all duration-200" onchange="updateDates()">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Total Days *</label>
                            <input type="number" name="total_days" id="total_days" value="<?php
                            echo isset($perdium) ? (int)$perdium['total_days'] : (isset($_POST['total_days']) ? (int)$_POST['total_days'] : '');
                            ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent modern-input transition-all duration-200" oninput="updateDates()">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Perdium Rate (per day) *</label>
                            <input type="number" step="0.01" name="perdium_rate" id="perdium_rate" value="<?php
                            echo isset($perdium) ? htmlspecialchars($perdium['perdium_rate']) : (isset($_POST['perdium_rate']) ? htmlspecialchars($_POST['perdium_rate']) : '');
                            ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent modern-input transition-all duration-200" oninput="calculatePerdium()">
                        </div>
                    </div>

                    <!-- Enhanced Warnings -->
                    <div id="employeeActiveWarning" class="p-4 rounded-xl bg-yellow-50 text-yellow-800 border border-yellow-200 warning-card hidden">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-yellow-500 text-xl mt-1 mr-3"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-lg font-semibold text-yellow-800 mb-1">Employee Currently on Perdium</h4>
                                <p class="text-yellow-700">
                                    This employee is currently on a perdium until <span id="block_until_date" class="font-bold"></span>. You cannot create another perdium until after this date.
                                </p>
                                <div class="mt-2 flex items-center text-sm text-yellow-600">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    <span>Please select a different employee or wait until the current perdium ends.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Enhanced Date Conflict Warning -->
                    <div id="employeeOverlapWarning" class="p-6 rounded-2xl bg-gradient-to-r from-red-50 to-orange-50 text-red-800 border border-red-200 error-card hidden mb-6 shadow-lg">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-calendar-times text-red-500 text-xl"></i>
                                </div>
                            </div>
                            <div class="flex-1 ml-4">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xl font-bold text-red-800 mb-2 flex items-center">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                        Date Conflict Detected
                                    </h4>
                                    <button onclick="$('#employeeOverlapWarning').addClass('hidden')" class="text-red-400 hover:text-red-600 transition-colors">
                                        <i class="fas fa-times text-lg"></i>
                                    </button>
                                </div>

                                <div class="bg-white rounded-xl p-4 border border-red-100 mb-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-red-50 rounded-full flex items-center justify-center">
                                            <i class="fas fa-user text-red-500"></i>
                                        </div>
                                        <div>
                                            <p class="text-red-700 font-semibold" id="conflictEmployeeName">
                                                Employee Name
                                            </p>
                                            <p class="text-red-600 text-sm">
                                                Has overlapping perdium dates
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <p class="text-red-700 mb-4 flex items-center">
                                    <i class="fas fa-info-circle mr-2 text-red-500"></i>
                                    Selected dates overlap with another perdium for this employee. Please adjust the departure or arrival dates to avoid conflicts.
                                </p>

                                <div class="flex flex-wrap gap-3">
                                    <button onclick="suggestAlternativeDates()" class="px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-lg hover:from-blue-600 hover:to-indigo-700 transition-all duration-200 font-medium flex items-center">
                                        <i class="fas fa-lightbulb mr-2"></i> Suggest Alternative Dates
                                    </button>
                                    <button onclick="clearDates()" class="px-4 py-2 bg-gradient-to-r from-gray-500 to-gray-600 text-white rounded-lg hover:from-gray-600 hover:to-gray-700 transition-all duration-200 font-medium flex items-center">
                                        <i class="fas fa-eraser mr-2"></i> Clear Dates
                                    </button>
                                    <button onclick="$('#employeeOverlapWarning').addClass('hidden')" class="px-4 py-2 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-lg hover:from-green-600 hover:to-emerald-700 transition-all duration-200 font-medium flex items-center">
                                        <i class="fas fa-eye mr-2"></i> View Existing Perdiums
                                    </button>
                                </div>

                                <div class="mt-4 p-3 bg-red-50 rounded-lg border border-red-200">
                                    <p class="text-sm text-red-700 flex items-center">
                                        <i class="fas fa-clock mr-2"></i>
                                        <span id="conflictDateRange">Conflict detected in selected date range</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="rounded-2xl p-6 bg-gradient-to-r from-sky-500 to-blue-600 text-white shadow-lg transform transition-all duration-300 hover:scale-[1.02]">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-sm text-sky-100 font-medium">
                                        Total Days
                                    </div>
                                    <div id="total_days_card" class="text-2xl font-extrabold text-white mt-1">
                                        0
                                    </div>
                                </div>
                                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                    <i class="fas fa-calendar-alt text-xl text-white"></i>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-2xl p-6 bg-gradient-to-r from-amber-500 to-orange-500 text-white shadow-lg transform transition-all duration-300 hover:scale-[1.02]">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-sm text-amber-100 font-medium">
                                        Total Amount
                                    </div>
                                    <div id="total_amount_card" class="text-2xl font-extrabold text-white mt-1">
                                        0.00 ብር
                                    </div>
                                </div>
                                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                    <i class="fas fa-money-bill-wave text-xl text-white"></i>
                                </div>
                            </div>
                            <input type="hidden" name="total_amount" id="total_amount" value="0">
                        </div>
                    </div>

                    <!-- Availability cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div id="rem_monthly_card" class="rounded-2xl p-6 bg-gradient-to-r from-amber-400 to-yellow-500 text-white shadow-lg transform transition-all duration-300 hover:scale-[1.02]">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-sm text-amber-100 font-medium">
                                        Monthly Perdium Budget
                                    </div>
                                    <div id="rem_monthly" class="text-2xl font-extrabold text-white mt-1">
                                        0.00
                                    </div>
                                </div>
                                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                    <i class="fas fa-calendar-alt text-xl text-white"></i>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-2xl p-6 bg-gradient-to-r from-emerald-500 to-green-600 text-white shadow-lg transform transition-all duration-300 hover:scale-[1.02]">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-sm text-emerald-100 font-medium" id="yearly_label">
                                        Available Yearly Perdium Budget
                                    </div>
                                    <div id="rem_yearly" class="text-2xl font-extrabold text-white mt-1">
                                        0.00
                                    </div>
                                </div>
                                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                    <i class="fas fa-coins text-xl text-white"></i>
                                </div>
                            </div>
                        </div>
                        <!-- Programs Bureau Total (right when programs + no owner) -->
                        <div id="programs_total_card" class="rounded-2xl p-6 bg-gradient-to-r from-purple-500 to-indigo-600 text-white shadow-lg transform transition-all duration-300 hover:scale-[1.02]" style="display:none;">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-sm text-purple-100 font-medium">
                                        Bureau's Programs Total Budget
                                    </div>
                                    <div id="programs_total_amount" class="text-2xl font-extrabold text-white mt-1">
                                        0.00 ብር
                                    </div>
                                </div>
                                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                    <i class="fas fa-layer-group text-xl text-white"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Government Grand (below) -->
                    <div id="government_grand_card" class="rounded-2xl p-6 mt-4 bg-gradient-to-r from-purple-500 to-indigo-600 text-white shadow-lg transform transition-all duration-300 hover:scale-[1.02]" style="display:none;">
                        <div class="flex items-center justify-between">
                            <div>
                                <div id="gov_grand_label" class="text-sm text-purple-100 font-medium">
                                    Bureau's Yearly Government Budget
                                </div>
                                <div id="gov_grand_amount" class="text-2xl font-extrabold text-white mt-1">
                                    0.00 ብር
                                </div>
                            </div>
                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                <i class="fas fa-building text-xl text-white"></i>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-4 pt-4">
                        <?php if (isset($perdium)): ?>
                        <a href="perdium.php" class="px-6 py-3 bg-gray-300 text-gray-700 rounded-xl hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-all duration-200 font-medium">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </a>
                        <?php endif; ?>
                        <button id="submitBtn" type="submit" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-xl hover:from-blue-600 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200 font-medium shadow-lg hover:shadow-xl transform hover:scale-105">
                            <i class="fas fa-save mr-2"></i>
                            <?php echo isset($perdium) ? 'Update Transaction' : 'Add Transaction'; ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Filter toolbar (AJAX list) -->
            <div class="bg-white rounded-2xl p-6 shadow-xl mb-6 border border-gray-100">
                <h3 class="text-lg font-semibold text-slate-800 mb-4 flex items-center">
                    <i class="fas fa-filter mr-2 text-blue-500"></i>Filter Transactions
                </h3>
                <div class="grid md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Budget Source</label>
                        <select id="flt_type" class="w-full select2 modern-input" <?php echo $budget_type_locked ? 'disabled' : ''; ?>>
                            <option value="governmental" <?php echo $default_budget_type === 'governmental' ? 'selected' : ''; ?>>Government</option>
                            <option value="program" <?php echo $default_budget_type === 'program' ? 'selected' : ''; ?>>Programs</option>
                        </select>
                        <?php if ($budget_type_locked): ?>
                        <input type="hidden" id="flt_type_hidden" value="<?php echo $default_budget_type; ?>">
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Owner</label>
                        <select id="flt_owner" class="w-full select2 modern-input">
                            <option value="">Any Owner</option>
                            <?php if ($is_admin): ?>
                            <!-- Admin sees all owners -->
                            <?php foreach ($gov_owners as $o): ?>
                            <option value="<?php echo (int)$o['id']; ?>" data-budget-type="governmental"><?php echo htmlspecialchars($o['code'].' - '.$o['name']); ?></option>
                            <?php endforeach; ?>
                            <?php foreach ($prog_owners as $o): ?>
                            <option value="<?php echo (int)$o['id']; ?>" data-budget-type="program"><?php echo htmlspecialchars($o['code'].' - '.$o['name']); ?></option>
                            <?php endforeach; ?>
                            <?php else : ?>
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
                        <label class="block text-sm font-medium mb-2">Month (Gov only)</label>
                        <select id="flt_month" class="w-full select2 modern-input">
                            <option value="">Any Month</option>
                            <?php foreach ($months as $m): ?>
                            <option value="<?php echo htmlspecialchars($m); ?>"><?php echo htmlspecialchars($m); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Employee</label>
                        <select id="flt_employee" class="w-full select2 modern-input">
                            <option value="">Any Employee</option>
                            <?php foreach ($employees as $e): ?>
                            <option value="<?php echo (int)$e['id']; ?>">
                                <?php
                                $displayName = ($e['name'] ?? '') . ' | ' . ($e['name_am'] ?? '');
                                if ($e['name_count'] > 1 && !empty($e['directorate'])) {
                                    $displayName .= ' - ' . $e['directorate'];
                                }
                                echo htmlspecialchars($displayName);
                                ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Live Search Box -->
            <div class="mb-6">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                    <input type="text" id="searchInput" placeholder="Search transactions by employee, owner, city..." class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent modern-input transition-all duration-200" onkeyup="filterTransactions()">
                </div>
            </div>

            <!-- Perdium Transactions Table -->
            <div class="bg-white rounded-2xl p-6 shadow-xl border border-gray-100">
                <h2 class="text-2xl font-bold text-slate-800 mb-6 flex items-center">
                    <i class="fas fa-list-alt mr-3 text-blue-500"></i>Perdium Transactions
                </h2>
                <div class="table-responsive overflow-x-auto custom-scrollbar">
                    <table class="min-w-full divide-y divide-gray-200 table-modern budget-type-governmental">
                        <thead class="bg-gradient-to-r from-blue-500 to-indigo-600">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Employee</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Owner</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Destination</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider month-column">Month</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="transactionsTable">
                            <tr><td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">
                                <div class="flex flex-col items-center justify-center py-4">
                                    <i class="fas fa-spinner fa-spin text-2xl text-blue-500 mb-2"></i>
                                    <span>Loading transactions...</span>
                                </div>
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        const defaultPerdiumRate = <?php echo json_encode((float)($_POST['perdium_rate'] ?? ($perdium['perdium_rate'] ?? ''))); ?>;
        let filling = false;
        const isEdit = <?php echo isset($perdium) ? 'true' : 'false'; ?>;
        const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
        const isOfficer = <?php echo $is_officer ? 'true' : 'false'; ?>;
        const csrfToken = <?php echo json_encode($_SESSION['csrf']); ?>;
        const defaultBudgetType = <?php echo json_encode($default_budget_type); ?>;
        const budgetTypeLocked = <?php echo $budget_type_locked ? 'true' : 'false'; ?>;

        let currentEmployeeSalary = 0;
        let activeBlock = false;
        let overlapConflict = false;
        let lockForOfficer = false;

        function fmt(n) {
            return (Number(n) || 0).toLocaleString(undefined, {
                minimumFractionDigits: 2, maximumFractionDigits: 2
            });
        }
        function birr(n) {
            return fmt(n)+' ብር';
        }

        function formatDateToEthiopian(dateString) {
            if (!dateString) return '-';
            try {
                // Use PHP conversion via AJAX if Ethiopian calendar library is not available in JS
                return dateString.split(' ')[0]; // Fallback to Gregorian date part
            } catch (e) {
                return dateString.split(' ')[0];
            }
        }

        function showSuccessMessage(message) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                background: '#f0f9ff',
                iconColor: '#10b981',
                customClass: {
                    popup: 'rounded-xl shadow-xl border border-gray-200'
                },
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });
        }

        function showErrorMessage(message) {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true,
                background: '#fef2f2',
                iconColor: '#ef4444',
                customClass: {
                    popup: 'rounded-xl shadow-xl border border-gray-200'
                },
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });
        }

        function setDateInputsDisabled(disabled) {
            ['#departure_date',
                '#arrival_date',
                '#total_days'].forEach(sel => {
                    $(sel).prop('disabled', disabled);
                    if (disabled) $(sel).addClass('employee-warning'); else $(sel).removeClass('employee-warning');
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
            const totalDays = parseInt($('#total_days').val(), 10) || 0;
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
            const ownerText = selectedOption.length ? selectedOption.text(): '';
            const ownerName = ownerText.split(' - ')[1] || ownerText || '-';
            $('#program_name_display').text('Owner: ' + ownerName);
            if (type === 'governmental') {
                const pkoox = selectedOption.data('p_koox') || '-';
                $('#program_p_koox_display').text(pkoox);
            } else {
                $('#program_p_koox_display').text('-');
            }
        }

        function updateCityDetailsCard() {
            const cityOption = $('#city_id').find('option:selected');
            const rateLow = parseFloat(cityOption.data('rate-low')) || 0;
            const rateMedium = parseFloat(cityOption.data('rate-medium')) || 0;
            const rateHigh = parseFloat(cityOption.data('rate-high')) || 0;

            $('#city_rate_low_display').text('Low Rate: ' + fmt(rateLow));
            $('#city_rate_medium_display').text('Medium Rate: ' + fmt(rateMedium));
            $('#city_rate_high_display').text('High Rate: ' + fmt(rateHigh));
        }

        function onEmployeeChange() {
            if (filling) return;
            // Unlock on employee change; will relock if conflict found
            lockForOfficer = false;
            applyOfficerLock();

            const selectedOption = $('#employee_id').find('option:selected');
            const employeeId = selectedOption.val();

            if (!employeeId) {
                // Reset employee details if no employee selected
                $('#employee_position_display').text('-');
                $('#employee_department_display').text('Department: -');
                $('#employee_salary_display').text('Salary: -');
                currentEmployeeSalary = 0;
                return;
            }

            // Get data from data attributes
            const position = selectedOption.data('position') || 'Not specified';
            const department = selectedOption.data('department') || 'Not specified';
            const salary = parseFloat(selectedOption.data('salary')) || 0;

            currentEmployeeSalary = salary;

            // Update employee details card
            $('#employee_position_display').text(position);
            $('#employee_department_display').text('Department: ' + department);
            $('#employee_salary_display').text('Salary: ' + fmt(salary));

            calculatePerdiumRate();
            $('#flt_employee').val(employeeId).trigger('change.select2');
            fetchPerdiumList();
            checkEmployeeActive();
            checkOverlapForDates();
        }

        function onCityChange() {
            if (!filling) {
                updateCityDetailsCard();
                calculatePerdiumRate();
            }
        }

        function fetchAndPopulateOwners(preselectId = null) {
            const budgetType = $('#budget_type').val();
            const ownerSelect = $('#owner_id');
            const perdiumOwnerId = '<?php echo isset($perdium) ? (int)$perdium["budget_owner_id"] : ""; ?>';
            const perdiumBudgetType = '<?php echo isset($perdium) ? $perdium["budget_type"] : ""; ?>';
            ownerSelect.prop('disabled', true).html('<option value="">Loading...</option>').trigger('change.select2');
            $.ajax({
                url: 'ajax_get_owners.php',
                type: 'GET',
                data: {
                    budget_type: budgetType
                },
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
                    data: {
                        budget_type: budgetType
                    },
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

        function resetPerdiumFormOnTypeSwitch() {
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
            $('#perdium_rate').val('');
            $('#total_amount').val(0);
            $('#employee_position_display').text('-');
            $('#employee_department_display').text('Department: -');
            $('#employee_salary_display').text('Salary: -');
            $('#program_name_display').text('Owner: -');
            $('#program_p_koox_display').text('-');
            $('#city_rate_low_display').text('Low Rate: -');
            $('#city_rate_medium_display').text('Medium Rate: -');
            $('#city_rate_high_display').text('High Rate: -');

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

        function onBudgetTypeChange() {
            if (budgetTypeLocked) return; // Don't allow changes if budget type is locked

            const t = $('#budget_type').val();
            setBudgetTypeUI(t);
            resetPerdiumFormOnTypeSwitch();
            fetchAndPopulateOwners();
        }

        function onOwnerChange() {
            if (filling) return;
            updateOwnerDetailsCard();
            $('#flt_owner').val($('#owner_id').val()).trigger('change.select2');
            fetchPerdiumList();
            loadPerdiumRemaining();
            refreshGrandTotals();
            applyOfficerLock();
        }

        function loadPerdiumRemaining() {
            const ownerId = $('#owner_id').val();
            const etMonth = $('#et_month').val();
            const year = new Date().getFullYear() - 8;
            const type = $('#budget_type').val();

            if (!ownerId) {
                $('#rem_monthly').text('0.00'); $('#rem_yearly').text('0.00'); return;
            }
            if (type === 'program') {
                $.get('get_remaining_program.php', {
                    owner_id: ownerId, year: year
                }, function(resp) {
                    try {
                        const j = typeof resp === 'string' ? JSON.parse(resp): resp;
                        $('#rem_yearly').text(fmt(j.remaining_yearly || 0));
                        $('#rem_monthly').text('0.00');
                    } catch (e) {
                        $('#rem_yearly').text('0.00');
                    }
                }).fail(()=>$('#rem_yearly').text('0.00'));
            } else {
                if (!etMonth) {
                    $('#rem_monthly').text('0.00'); $('#rem_yearly').text('0.00'); return;
                }
                $.get('get_remaining_perdium.php', {
                    owner_id: ownerId, code_id: 6, month: etMonth, year: year
                }, function(resp) {
                    try {
                        const rem = typeof resp === 'string' ? JSON.parse(resp): resp;
                        $('#rem_monthly').text(fmt(rem.remaining_monthly || 0));
                        $('#rem_yearly').text(fmt(rem.remaining_yearly || 0));
                    } catch (e) {
                        $('#rem_monthly').text('0.00'); $('#rem_yearly').text('0.00');
                    }
                }).fail(()=> {
                    $('#rem_monthly').text('0.00'); $('#rem_yearly').text('0.00');
                });
            }
        }

        function refreshGrandTotals() {
            const type = $('#budget_type').val();
            const ownerId = $('#owner_id').val();
            const year = new Date().getFullYear() - 8;

            $.get('ajax_perdium_grands.php', {
                budget_type: type, owner_id: ownerId, year: year
            }, function(resp) {
                try {
                    const j = typeof resp === 'string' ? JSON.parse(resp): resp;
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
                }catch(e) {
                    $('#programs_total_card').hide();
                    $('#government_grand_card').hide();
                }
            }).fail(function() {
                $('#programs_total_card').hide();
                $('#government_grand_card').hide();
            });
        }

        function toggleFilterMonth() {
            const t = $('#flt_type').val();
            if (t === 'program') {
                $('#flt_month_box').hide();
            } else {
                $('#flt_month_box').show();
            }
        }

        function syncFiltersFromForm() {
            $('#flt_type').val($('#budget_type').val()).trigger('change.select2');
            $('#flt_owner').val($('#owner_id').val()).trigger('change.select2');
            $('#flt_month').val($('#et_month').val()).trigger('change.select2');
            $('#flt_employee').val($('#employee_id').val()).trigger('change.select2');
        }

        function validateBeforeSubmit() {
            if (isOfficer && (lockForOfficer || activeBlock || overlapConflict)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Submission Blocked',
                    html: `
                    <div class="text-center">
                    <i class="fas fa-ban text-4xl text-yellow-500 mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Submission Blocked</h3>
                    <p class="text-gray-600">This employee has an active or overlapping perdium. Fields are locked and submission is blocked.</p>
                    <div class="mt-4 p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                    <p class="text-sm text-yellow-700"><i class="fas fa-info-circle mr-2"></i>Please resolve the conflict before submitting.</p>
                    </div>
                    </div>
                    `,
                    confirmButtonColor: '#f59e0b',
                    confirmButtonText: 'Understand',
                    background: '#fff',
                    customClass: {
                        popup: 'rounded-2xl shadow-2xl'
                    }
                });
                return false;
            }
            syncFiltersFromForm();
            return true;
        }

        function fetchPerdiumList() {
            const type = $('#flt_type').val();
            const owner = $('#flt_owner').val();
            const month = $('#flt_month').val();
            const employee = $('#flt_employee').val();

            $('#transactionsTable').html('<tr><td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500"><div class="flex flex-col items-center justify-center py-4"><i class="fas fa-spinner fa-spin text-2xl text-blue-500 mb-2"></i><span>Loading transactions...</span></div></td></tr>');

            // Set budget type class for responsive handling
            $('.table-modern').removeClass('budget-type-governmental budget-type-program').addClass('budget-type-' + type);

            $.get('ajax_perdium_list.php', {
                budget_type: type,
                owner_id: owner,
                et_month: month,
                employee_id: employee
            })
            .done(function(resp) {
                try {
                    const response = typeof resp === 'string' ? JSON.parse(resp): resp;

                    if (response.success) {
                        const rows = response.rows || [];

                        if (rows.length === 0) {
                            $('#transactionsTable').html('<tr><td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500"><div class="flex flex-col items-center justify-center py-4"><i class="fas fa-inbox text-3xl text-gray-300 mb-2"></i><span>No perdium transactions found.</span></div></td></tr>');
                            return;
                        }

                        let html = '';
                        rows.forEach(f => {
                            const printUrl = (f.budget_type === 'program')
                            ? `reports/preport2.php?id=${f.id}`: `reports/preport.php?id=${f.id}`;
                            const dataJson = encodeURIComponent(JSON.stringify(f));

                            const formattedDate = f.ethiopian_date || f.created_at || '-';

                            // Format owner display based on budget type
                            let ownerDisplay = f.owner_code || '';
                            if (f.budget_type === 'program') {
                                ownerDisplay = f.owner_name || f.owner_code || ''; // Show program name like UNDAF
                            } else {
                                ownerDisplay = f.owner_p_koox || f.owner_code || ''; // Show koox for governmental
                            }

                            let actions = `
                            <a href="${printUrl}" class="px-3 py-2 bg-gradient-to-r from-emerald-500 to-green-600 text-white rounded-lg hover:from-emerald-600 hover:to-green-700 transition-all duration-200 shadow-sm flex items-center text-xs" target="_blank">
                            <i class="fas fa-print mr-1"></i> Print
                            </a>
                            `;

                            <?php if ($is_admin): ?>
                            actions += `
                            <a href="?action=edit&id=${f.id}" class="px-3 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-lg hover:from-blue-600 hover:to-indigo-700 transition-all duration-200 shadow-sm flex items-center text-xs">
                            <i class="fas fa-edit mr-1"></i> Edit
                            </a>
                            <a href="?action=delete&id=${f.id}" class="px-3 py-2 bg-gradient-to-r from-red-500 to-pink-600 text-white rounded-lg hover:from-red-600 hover:to-pink-700 transition-all duration-200 shadow-sm flex items-center text-xs" onclick="confirmDelete(event, this.href)">
                            <i class="fas fa-trash mr-1"></i> Delete
                            </a>
                            `;
                            <?php endif; ?>

                            html += `
                            <tr class="row-click hover:bg-gray-50 transition-colors duration-150" data-json="${dataJson}">
                            <td class="px-4 py-3 text-sm text-gray-900 font-medium ethio-font">${formattedDate}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 employee-name-cell">${f.employee_display_name || f.employee_name || ''}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 owner-name-cell">${ownerDisplay}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 city-name-cell">${f.city_display_name || f.city_name || ''}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 ethio-font month-column">${f.et_month || ''}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 font-bold">${Number(f.total_amount || 0).toLocaleString(undefined, {
                                minimumFractionDigits: 2
                            })} ብር</td>
                            <td class="px-4 py-3 text-sm actions-cell"><div class="flex flex-wrap gap-1">${actions}</div></td>
                            </tr>`;
                        });

                        $('#transactionsTable').html(html);
                        filterTransactions();

                    } else {
                        const errorMsg = response.error || 'Unknown error occurred';
                        console.error('Server error:', response);
                        $('#transactionsTable').html('<tr><td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500"><div class="flex flex-col items-center justify-center py-4"><i class="fas fa-exclamation-triangle text-2xl text-red-500 mb-2"></i><span>Error: ' + errorMsg + '</span></div></td></tr>');
                    }
                } catch (e) {
                    console.error('Error processing response:', e, resp);
                    $('#transactionsTable').html('<tr><td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500"><div class="flex flex-col items-center justify-center py-4"><i class="fas fa-exclamation-triangle text-2xl text-red-500 mb-2"></i><span>Error parsing response</span></div></td></tr>');
                }
            })
            .fail(function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                $('#transactionsTable').html('<tr><td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500"><div class="flex flex-col items-center justify-center py-4"><i class="fas fa-exclamation-triangle text-2xl text-red-500 mb-2"></i><span>Failed to load transactions. Check console for details.</span></div></td></tr>');
            });
        }
        function confirmDelete(event, url) {
            event.preventDefault();

            Swal.fire({
                title: '<div class="flex items-center justify-center mb-4"><i class="fas fa-trash-alt text-4xl text-red-500 mr-3"></i><span class="text-2xl font-bold text-gray-800">Confirm Deletion</span></div>',
                html: `
                <div class="text-center py-4">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-exclamation-triangle text-2xl text-red-600"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Are you sure you want to delete this perdium transaction?</h3>
                <p class="text-gray-600 mb-4">This action cannot be undone and will permanently remove the transaction from the system.</p>
                <div class="bg-red-50 border border-red-200 rounded-lg p-3 mt-4">
                <p class="text-sm text-red-700 flex items-center justify-center">
                <i class="fas fa-info-circle mr-2"></i>
                This will also reverse any budget allocations associated with this transaction.
                </p>
                </div>
                </div>
                `,
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-trash mr-2"></i>Yes, Delete It',
                cancelButtonText: '<i class="fas fa-times mr-2"></i>Cancel',
                background: '#fff',
                customClass: {
                    popup: 'rounded-2xl shadow-2xl border border-gray-200',
                    confirmButton: 'px-6 py-3 rounded-lg font-semibold',
                    cancelButton: 'px-6 py-3 rounded-lg font-semibold'
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
                        title: '<div class="flex items-center justify-center"><i class="fas fa-spinner fa-spin text-2xl text-blue-500 mr-3"></i><span class="text-lg">Deleting Transaction...</span></div>',
                        text: 'Please wait while we remove the transaction',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        background: '#fff',
                        customClass: {
                            popup: 'rounded-2xl shadow-2xl'
                        }
                    });
                    window.location.href = url;
                }
            });
        }

        function filterTransactions() {
            const filter = (document.getElementById('searchInput').value || '').toLowerCase();
            const rows = document.querySelectorAll('#transactionsTable tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '': 'none';
            });
        }

        function fillFormFromRow(d) {
            try {
                filling = true;
                $('#budget_type').val(d.budget_type || 'governmental').trigger('change.select2');
                setBudgetTypeUI($('#budget_type').val());
                fetchAndPopulateOwners(d.budget_owner_id);

                if ((d.budget_type || 'governmental') !== 'program') {
                    $('#et_month').val(d.et_month || '').trigger('change.select2');
                } else {
                    $('#et_month').val('').trigger('change.select2');
                }

                $('#employee_id').val(String(d.employee_id || '')).trigger('change.select2');
                onEmployeeChange();

                $('#city_id').val(String(d.city_id || '')).trigger('change.select2');
                updateCityDetailsCard();

                $('#departure_date').val(d.departure_date || '');
                $('#arrival_date').val(d.arrival_date || '');
                $('#total_days').val(Number(d.total_days || 0));
                $('#perdium_rate').val(Number(d.perdium_rate || 0).toFixed(2));

                calculatePerdium();

                $('#flt_type').val(d.budget_type || 'governmental').trigger('change.select2');
                updateFilterOwnerOptions();
                $('#flt_owner').val(String(d.budget_owner_id || '')).trigger('change.select2');
                if ((d.budget_type || 'governmental') !== 'program') {
                    $('#flt_month').val(d.et_month || '').trigger('change.select2');
                } else {
                    $('#flt_month').val('').trigger('change.select2');
                }
                $('#flt_employee').val(String(d.employee_id || '')).trigger('change.select2');

                filling = false;
                loadPerdiumRemaining();
                refreshGrandTotals();
            }catch(e) {
                filling = false;
                console.error('fillFormFromRow error', e);
            }
        }

        function checkEmployeeActive() {
            const empId = $('#employee_id').val();
            if (!empId) {
                activeBlock = false;
                if (!lockForOfficer) $('#submitBtn').prop('disabled', false);
                $('#employeeActiveWarning').addClass('hidden');
                applyOfficerLock();
                return;
            }
            $.get('ajax_check_employee_perdium.php', {
                employee_id: empId, mode: 'active'
            }, function(resp) {
                try {
                    const j = typeof resp === 'string' ? JSON.parse(resp): resp;
                    if (j.active) {
                        activeBlock = true;
                        if (isOfficer) {
                            lockForOfficer = true; applyOfficerLock();
                        }
                        $('#block_until_date').text(j.block_until || '-');
                        $('#employeeActiveWarning').removeClass('hidden');
                        $('#submitBtn').prop('disabled', true);
                    } else {
                        activeBlock = false;
                        $('#employeeActiveWarning').addClass('hidden');
                        $('#submitBtn').prop('disabled', isOfficer ? (lockForOfficer || overlapConflict): overlapConflict);
                        applyOfficerLock();
                    }
                }catch(e) {
                    activeBlock = false;
                    $('#employeeActiveWarning').addClass('hidden');
                    $('#submitBtn').prop('disabled', isOfficer ? (lockForOfficer || overlapConflict): overlapConflict);
                    applyOfficerLock();
                }
            }).fail(function() {
                activeBlock = false;
                $('#employeeActiveWarning').addClass('hidden');
                $('#submitBtn').prop('disabled', isOfficer ? (lockForOfficer || overlapConflict): overlapConflict);
                applyOfficerLock();
            });
        }

        // Enhanced conflict detection with employee name
        function checkOverlapForDates() {
            const empId = $('#employee_id').val();
            const dep = $('#departure_date').val();
            const arr = $('#arrival_date').val();
            const employeeName = $('#employee_id option:selected').text().split('|')[0].trim() || 'Selected Employee';

            if (!empId || !dep || !arr) {
                overlapConflict = false;
                $('#employeeOverlapWarning').addClass('hidden');
                $('#submitBtn').prop('disabled', isOfficer ? (lockForOfficer || activeBlock): activeBlock);
                applyOfficerLock();
                return;
            }

            // Update employee name in warning
            $('#conflictEmployeeName').text(employeeName);
            $('#conflictDateRange').text(`Conflict detected for dates: ${dep} to ${arr}`);

            $.get('ajax_check_employee_perdium.php', {
                employee_id: empId,
                start: dep,
                end: arr,
                exclude_id: <?php echo isset($perdium) ? (int)$perdium['id'] : 'null'; ?>
            }, function(resp) {
                try {
                    const j = typeof resp === 'string' ? JSON.parse(resp): resp;
                    if (j.overlap) {
                        overlapConflict = true;
                        if (isOfficer) {
                            lockForOfficer = true;
                            applyOfficerLock();
                        }

                        // Enhanced warning with animations
                        $('#employeeOverlapWarning').removeClass('hidden').addClass('animate__animated animate__shakeX');
                        setTimeout(() => {
                            $('#employeeOverlapWarning').removeClass('animate__shakeX');
                        }, 1000);

                        $('#submitBtn').prop('disabled', true);

                        // Add pulsing effect to date inputs
                        $('#departure_date, #arrival_date').addClass('animate-pulse border-2 border-red-500');

                    } else {
                        overlapConflict = false;
                        $('#employeeOverlapWarning').addClass('hidden');
                        $('#departure_date, #arrival_date').removeClass('animate-pulse border-2 border-red-500');
                        $('#submitBtn').prop('disabled', isOfficer ? (lockForOfficer || activeBlock): activeBlock);
                        applyOfficerLock();
                    }
                }catch(e) {
                    overlapConflict = false;
                    $('#employeeOverlapWarning').addClass('hidden');
                    $('#submitBtn').prop('disabled', isOfficer ? (lockForOfficer || activeBlock): activeBlock);
                    applyOfficerLock();
                }
            }).fail(function() {
                overlapConflict = false;
                $('#employeeOverlapWarning').addClass('hidden');
                $('#submitBtn').prop('disabled', isOfficer ? (lockForOfficer || activeBlock): activeBlock);
                applyOfficerLock();
            });
        }

        // Helper functions for the warning buttons
        function suggestAlternativeDates() {
            const dep = $('#departure_date').val();
            const arr = $('#arrival_date').val();

            if (dep && arr) {
                const departureDate = new Date(dep);
                const arrivalDate = new Date(arr);

                // Suggest next available dates (7 days after current arrival)
                const newDeparture = new Date(arrivalDate);
                newDeparture.setDate(newDeparture.getDate() + 7);

                const newArrival = new Date(newDeparture);
                const daysDiff = Math.floor((arrivalDate - departureDate) / (1000 * 60 * 60 * 24));
                newArrival.setDate(newArrival.getDate() + daysDiff);

                $('#departure_date').val(newDeparture.toISOString().split('T')[0]);
                $('#arrival_date').val(newArrival.toISOString().split('T')[0]);

                // Show success message
                Swal.fire({
                    icon: 'success',
                    title: 'Dates Adjusted',
                    html: `Suggested alternative dates:<br>
                    <strong>${newDeparture.toISOString().split('T')[0]}</strong> to
                    <strong>${newArrival.toISOString().split('T')[0]}</strong>`,
                    confirmButtonColor: '#10b981',
                    background: '#f0f9ff'
                });

                // Re-check for conflicts
                setTimeout(() => checkOverlapForDates(), 1000);
            }
        }

        function clearDates() {
            $('#departure_date').val('');
            $('#arrival_date').val('');
            $('#total_days').val('');
            $('#employeeOverlapWarning').addClass('hidden');
            $('#departure_date, #arrival_date').removeClass('animate-pulse border-2 border-red-500');
        }

        // Custom Select2 template for employee dropdown
        function employeeTemplate(employee) {
            if (!employee.id) return employee.text;

            const $employee = $(
                `<div class="employee-dropdown-option">
                <div class="employee-name">${employee.text.split(' | ')[0] || employee.text}</div>
                ${employee.element.dataset.nameAm ? `<div class="employee-name-am">${employee.element.dataset.nameAm}</div>`: ''}
                ${employee.element.dataset.department ? `<div class="employee-department">${employee.element.dataset.department}</div>`: ''}
                </div>`
            );
            return $employee;
        }

        $(document).ready(function() {
            $('.select2').select2({
                theme: 'classic',
                width: '100%',
                dropdownCssClass: 'rounded-xl shadow-xl border border-gray-200',
                templateResult: function(data) {
                    // Apply custom template only for employee dropdown
                    if (data.element && data.element.parentElement.id === 'employee_id') {
                        return employeeTemplate(data);
                    }
                    return data.text;
                },
                matcher: function(params, data) {
                    if ($.trim(params.term) === '') return data;
                    if (typeof data.text === 'undefined') return null;

                    // Enhanced search for employee dropdown
                    if (data.element && data.element.parentElement.id === 'employee_id') {
                        const searchTerm = params.term.toLowerCase();
                        const englishName = data.text.split(' | ')[0]?.toLowerCase() || '';
                        const amharicName = data.element.dataset.nameAm?.toLowerCase() || '';
                        const department = data.element.dataset.department?.toLowerCase() || '';

                        if (englishName.includes(searchTerm) ||
                            amharicName.includes(searchTerm) ||
                            department.includes(searchTerm)) {
                            return data;
                        }
                        return null;
                    }

                    // Default search for other dropdowns
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
                $('#budget_type').on('change', function() {
                    if (!filling) onBudgetTypeChange();
                });
            }
            $('#owner_id').on('change',
                function() {
                    if (!filling) onOwnerChange();
                });
            $('#employee_id').on('change',
                function() {
                    if (!filling) onEmployeeChange();
                });
            $('#city_id').on('change',
                function() {
                    if (!filling) onCityChange();
                });
            $('#et_month').on('change',
                function() {
                    if (filling) return;
                    $('#flt_month').val($('#et_month').val()).trigger('change.select2');
                    fetchPerdiumList();
                    loadPerdiumRemaining();
                });
            $('#departure_date, #arrival_date').on('change',
                function() {
                    checkOverlapForDates();
                });

            // Filters
            if (!budgetTypeLocked) {
                $('#flt_type').on('change', function() {
                    updateFilterOwnerOptions();
                    toggleFilterMonth();
                    fetchPerdiumList();
                });
            }
            $('#flt_owner, #flt_month, #flt_employee').on('change', function() {
                fetchPerdiumList();
            });

            // Row click -> fill form
            $('#transactionsTable').on('click', 'tr.row-click', function(e) {
                if ($(e.target).closest('a,button').length) return;
                const dataJson = $(this).attr('data-json');
                if (!dataJson) return;
                try {
                    const d = JSON.parse(decodeURIComponent(dataJson));
                    fillFormFromRow(d);
                } catch (err) {
                    console.error('row parse error', err);
                }
            });

            // Initialize form with empty values
            calculatePerdium(); // This will set total amount to 0.00

            // Init
            setBudgetTypeUI($('#budget_type').val());
            fetchAndPopulateOwners();
            updateFilterOwnerOptions();
            toggleFilterMonth();

            if (isEdit) {
                onEmployeeChange();
                onCityChange();
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

        document.getElementById('sidebarToggle')?.addEventListener('click', ()=> {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('mainContent').classList.toggle('expanded');
        });
    </script>
</body>
</html>