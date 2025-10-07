<?php

// Includes essential files for database connection and session management.
require_once 'includes/init.php';

// Set secure HTTP headers to prevent common attacks like clickjacking and XSS.
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$token = $_GET['token'] ?? '';
$error = null;
$done  = false;

// Step 1: Validate the token from the URL.
if ($token === '' || !ctype_xdigit($token) || strlen($token) !== 64) {
    $error = 'Invalid token.';
} else {
    // Hash the token for database lookup.
    $tokenHash = hash('sha256', $token);

    // Fetch the password reset record.
    $stmt = $pdo->prepare("SELECT pr.id, pr.user_id, pr.expires_at, pr.used, u.username 
                           FROM password_resets pr 
                           JOIN users u ON u.id = pr.user_id 
                           WHERE pr.token_hash = ? LIMIT 1");
    $stmt->execute([$tokenHash]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    // Step 2: Check the token's validity.
    if (!$reset) {
        $error = 'Invalid or expired token.';
    } elseif ((int)$reset['used'] === 1 || new DateTime() > new DateTime($reset['expires_at'])) {
        $error = 'This token has expired or already been used.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Step 3: Handle the form submission to reset the password.

        // Check for CSRF token.
        if (!csrf_check($_POST['csrf'] ?? '')) {
            $error = 'Security check failed.';
        } else {
            $p1 = (string)($_POST['password'] ?? '');
            $p2 = (string)($_POST['confirm'] ?? '');

            // Validate password and confirmation.
            if ($p1 !== $p2) {
                $error = 'Passwords do not match.';
            } elseif (strlen($p1) < 8) {
                $error = 'Password must be at least 8 characters.';
            } else {
                // All checks pass, hash the new password and update the database.
                $hash = password_hash($p1, PASSWORD_DEFAULT);
                $pdo->beginTransaction(); // Use a transaction to ensure both updates succeed or fail together.

                try {
                    // Update the user's password.
                    $up = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $up->execute([$hash, (int)$reset['user_id']]);

                    // Mark the reset token as used.
                    $mark = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
                    $mark->execute([(int)$reset['id']]);

                    // Commit the transaction.
                    $pdo->commit();
                    $done = true;

                } catch (Throwable $e) {
                    // Roll back on failure.
                    $pdo->rollBack();
                    $error = 'Could not reset password. Try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Budget System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center bg-slate-50 p-4">
    <div class="max-w-md w-full bg-white p-6 rounded-xl shadow">
        <h1 class="text-xl font-bold text-slate-800 mb-4">Reset Password</h1>

        <?php if ($done): ?>
            <div class="p-3 mb-4 rounded bg-green-50 text-green-700">
                Password updated successfully. You can now <a href="index.php" class="underline">sign in</a>.
            </div>
        <?php elseif (!empty($error)): ?>
            <div class="p-3 mb-4 rounded bg-red-50 text-red-700">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php
        // Only show the form if the password hasn't been reset and there's no fatal error.
        if (!$done && empty($error) || (!$done && $error && $_SERVER['REQUEST_METHOD'] !== 'POST')) :
        ?>
            <form method="post" class="space-y-4" autocomplete="off">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                
                <label class="block">
                    <span class="text-sm text-slate-700">New Password</span>
                    <input name="password" type="password" class="mt-1 w-full border rounded p-2" required minlength="8">
                </label>
                
                <label class="block">
                    <span class="text-sm text-slate-700">Confirm Password</span>
                    <input name="confirm" type="password" class="mt-1 w-full border rounded p-2" required minlength="8">
                </label>
                
                <button type="submit" class="w-full py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                    Set New Password
                </button>
            </form>
        <?php endif; ?>

    </div>
</body>
</html>
