<?php

// Includes essential files for database connection, session management, and the mailer.
require_once 'includes/init.php';
require_once 'includes/mailer.php';

// Set secure HTTP headers to prevent common attacks.
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$sent = false;
$error = null;

// Handle the form submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Perform a CSRF token check to ensure the request is from a valid form.
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = 'Security check failed.';
    } else {
        $identity = trim($_POST['identity'] ?? '');

        if ($identity === '') {
            $error = 'Please enter your email or username.';
        } else {
            // Find the user by either email or username to prevent account enumeration.
            $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$identity]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $stmt = $pdo->prepare("SELECT id, email FROM users WHERE username = ? LIMIT 1");
                $stmt->execute([$identity]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            // Always provide the same message to avoid leaking information about which accounts exist.
            $sent = true;

            // If a user is found and they have a registered email address, process the password reset.
            if ($user && !empty($user['email'])) {
                $token      = bin2hex(random_bytes(32));
                $tokenHash  = hash('sha256', $token);
                $expires    = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

                // Insert a new password reset token into the database.
                $ins = $pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
                $ins->execute([(int)$user['id'], $tokenHash, $expires]);

                // Construct the full reset link.
                $resetLink = sprintf(
                    '%s://%s%s/reset_password.php?token=%s',
                    !empty($_SERVER['HTTPS']) ? 'https' : 'http',
                    $_SERVER['HTTP_HOST'],
                    rtrim(dirname($_SERVER['PHP_SELF']), '/\\') === '/budget_system' ? '/budget_system' : '',
                    urlencode($token)
                );

                // Send the password reset email.
                try {
                    send_mail(
                        $user['email'],
                        'Password Reset Request',
                        "We received a request to reset your password.\n\n" .
                        "Click the link below to reset it (valid for 1 hour):\n$resetLink\n\n" .
                        "If you did not request this, you can ignore this email."
                    );
                } catch (Throwable $e) {
                    // Log the error silently if mail fails to send.
                    // You can log $e->getMessage() for debugging.
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
    <title>Forgot Password - Budget System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center bg-slate-50 p-4">
    <div class="max-w-md w-full bg-white p-6 rounded-xl shadow">
        <h1 class="text-xl font-bold text-slate-800 mb-4">Forgot Password</h1>
        
        <?php if ($sent): ?>
            <div class="p-4 rounded bg-blue-50 text-blue-800">
                If the account exists, a reset link has been sent to the email on file.
            </div>
        <?php else: ?>
            <?php if (!empty($error)): ?>
                <div class="p-3 mb-4 rounded bg-red-50 text-red-700">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="post" class="space-y-4">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                
                <label class="block">
                    <span class="text-sm text-slate-700">Email or Username</span>
                    <input name="identity" class="mt-1 w-full border rounded p-2" placeholder="example@domain.com or username" required>
                </label>
                
                <button type="submit" class="w-full py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                    Send Reset Link
                </button>
            </form>
        <?php endif; ?>

        <div class="mt-4 text-center">
            <a href="index.php" class="text-sm text-indigo-600 hover:underline">Back to login</a>
        </div>
    </div>
</body>
</html>
