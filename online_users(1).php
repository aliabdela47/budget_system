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
<html lang="en" class="">
<head>
    <?php
    $pageTitle = "Online Users";
    require_once 'includes/head.php';
    ?>
</head>
<body class="text-slate-700 dark:text-slate-200">
<?php require_once 'includes/sidebar.php'; ?>
<div class="main-content" id="mainContent">
    <div class="p-6">
        <div class="glass card p-6 mb-6 flex flex-col md:flex-row justify-between items-center rounded-2xl">
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-800 dark:text-white">
                    <i class="fas fa-users-rays mr-2 text-emerald-500"></i>Who's Online
                </h1>
                <p class="mt-2 text-slate-600 dark:text-slate-400">
                    Users active in the last <?php echo $online_threshold; ?> minutes.
                </p>
            </div>
            <div class="flex items-center mt-4 md:mt-0 gap-3">
                <div class="text-center">
                    <div class="text-4xl font-extrabold text-emerald-500"><?php echo count($online_users); ?></div>
                    <div class="text-sm text-slate-500">Users Currently Active</div>
                </div>
                <button id="themeToggle" class="px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 shadow">
                    <i class="fas fa-moon"></i>
                </button>
            </div>
        </div>

        <div class="glass card p-6">
            <div class="overflow-x-auto rounded-lg">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs uppercase bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
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
                                <td colspan="5" class="text-center p-8 text-slate-500">No users are currently active.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($online_users as $user): ?>
                                <tr class="table-row border-b border-slate-200 dark:border-slate-700">
                                    <td class="px-4 py-3 font-semibold"><?php echo h($user['username']); ?></td>
                                    <td class="px-4 py-3"><?php echo h($user['name']); ?></td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $user['role'] === 'admin' ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300' : 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300'; ?>">
                                            <?php echo ucfirst(h($user['role'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-500 dark:text-slate-400" title="<?php echo h($user['last_seen']); ?>">
                                        <?php echo time_ago($user['last_seen']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-slate-500 dark:text-slate-400"><?php echo h($user['ip_address']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    // Theme toggle
    (function(){
      const root = document.documentElement;
      const saved = localStorage.getItem('theme');
      if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) root.classList.add('dark');
      document.getElementById('themeToggle')?.addEventListener('click', ()=>{
        root.classList.toggle('dark');
        localStorage.setItem('theme', root.classList.contains('dark') ? 'dark' : 'light');
      });
    })();
</script>
</body>
</html>