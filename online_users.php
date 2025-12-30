<?php
require_once 'includes/init.php';
require_admin(); // Only admins can view this page

// Define "online" as active in the last 5 minutes
$online_threshold = 5; // in minutes

$stmt = $pdo->prepare("
    SELECT username, name, role, last_seen, ip_address
    FROM users
    WHERE last_seen > NOW() - INTERVAL ? MINUTE
    ORDER BY last_seen DESC
");
$stmt->execute([$online_threshold]);
$online_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

function time_ago($datetime) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->y) return $diff->y . ' year(s) ago';
    if ($diff->m) return $diff->m . ' month(s) ago';
    if ($diff->d) return $diff->d . ' day(s) ago';
    if ($diff->h) return $diff->h . ' hour(s) ago';
    if ($diff->i) return $diff->i . ' minute(s) ago';
    return 'Just now';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php
    $pageTitle = "Online Users";
    require_once 'includes/head.php';
    ?>
    <style>
        body {
            background-color: white !important;
            color: #333 !important;
        }
        .main-content {
            background-color: white !important;
        }
        .glass.card {
            background-color: white !important;
            border: 1px solid #e5e7eb !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important;
        }
        .text-slate-800, .text-slate-700, .text-slate-600, .text-slate-500 {
            color: #333 !important;
        }
        .bg-slate-100 {
            background-color: #f9fafb !important;
        }
        .border-slate-200, .border-slate-300 {
            border-color: #e5e7eb !important;
        }
        table th {
            background-color: #f9fafb !important;
            color: #333 !important;
        }
        table td {
            background-color: white !important;
            color: #333 !important;
        }
        .bg-indigo-100 {
            background-color: #e0e7ff !important;
            color: #3730a3 !important;
        }
        .bg-slate-100 {
            background-color: #f9fafb !important;
            color: #333 !important;
        }
    </style>
</head>
<body style="background-color: white; color: #333;">
<?php require_once 'includes/sidebar-new.php'; ?>
<div class="main-content" id="mainContent" style="background-color: white;">
    <div class="p-6">
        <div class="card p-6 mb-6 flex flex-col md:flex-row justify-between items-center rounded-2xl" style="background-color: white; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold" style="color: #333;">
                    <i class="fas fa-users-rays mr-2 text-emerald-500"></i>Who's Online
                </h1>
                <p class="mt-2" style="color: #666;">
                    Users active in the last <?php echo $online_threshold; ?> minutes.
                </p>
            </div>
            <div class="flex items-center mt-4 md:mt-0 gap-3">
                <div class="text-center">
                    <div class="text-4xl font-extrabold text-emerald-500"><?php echo count($online_users); ?></div>
                    <div class="text-sm" style="color: #666;">Users Currently Active</div>
                </div>
            </div>
        </div>

        <div class="card p-6" style="background-color: white; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div class="overflow-x-auto rounded-lg">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs uppercase" style="background-color: #f9fafb; color: #333;">
                        <tr>
                            <th class="px-4 py-3">Username</th>
                            <th class="px-4 py-3">Full Name</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Last Seen</th>
                            <th class="px-4 py-3">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($online_users)): ?>
                            <tr>
                                <td colspan="5" class="text-center p-8" style="color: #666;">No users are currently active.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($online_users as $user): ?>
                                <tr class="table-row border-b" style="border-color: #e5e7eb;">
                                    <td class="px-4 py-3 font-semibold" style="color: #333;"><?php echo h($user['username']); ?></td>
                                    <td class="px-4 py-3" style="color: #333;"><?php echo h($user['name']); ?></td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $user['role'] === 'admin' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-800'; ?>">
                                            <?php echo ucfirst(h($user['role'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3" style="color: #666;" title="<?php echo h($user['last_seen']); ?>">
                                        <?php echo time_ago($user['last_seen']); ?>
                                    </td>
                                    <td class="px-4 py-3" style="color: #666;"><?php echo h($user['ip_address']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>