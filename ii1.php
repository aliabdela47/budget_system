<?php require_once "includes/init.php";
// should set $pdo and session_start, no output // Optional: ensure DB is available via $pdo; remove includes/db.php if init already sets it. // Security headers (before any output) 
header('X-Frame-Options: DENY'); 
header('X-Content-Type-Options: nosniff'); 
header('Referrer-Policy: strict-origin-when-cross-origin'); // Content-Security-Policy tuned for your CDNs; adjust if you add/remove CDNs. 
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.tailwindcss.com https://code.jquery.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; img-src 'self' data: https://via.placeholder.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'"); // Redirect if already logged in if (!empty($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; } // CSRF token if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); } function csrf_check($t) { return hash_equals($_SESSION['csrf'] ?? '', $t ?? ''); } // Simple rate limiting (session-based). For global defense, add a DB-based IP+username throttle too. $LOCK_SECONDS = 300; // 5 minutes $MAX_FAILS = 5; function login_locked() { $lockUntil = $_SESSION['login_lock_until'] ?? 0; return $lockUntil && time() < $lockUntil; } function register_fail() { $fails = (int)($_SESSION['login_fail_count'] ?? 0) + 1; $_SESSION['login_fail_count'] = $fails; if ($fails >= 5) { $_SESSION['login_lock_until'] = time() + 300; // lock for 5 min } } function clear_fail() { unset($_SESSION['login_fail_count'], $_SESSION['login_lock_until']); } $error = null; $lockedRemaining = 0; // Handle POST if ($_SERVER['REQUEST_METHOD'] === 'POST') { if (!csrf_check($_POST['csrf'] ?? '')) { $error = 'Security check failed. Please refresh and try again.'; } elseif (login_locked()) { $lockedRemaining = max(0, ($_SESSION['login_lock_until'] ?? 0) - time()); $error = 'Too many failed attempts. Please try again in ' . ceil($lockedRemaining / 60) . ' minute(s).'; } else { $username = trim($_POST['username'] ?? ''); $password = (string)($_POST['password'] ?? ''); $remember = !empty($_POST['remember_me']); if ($username === '' || $password === '') { $error = 'Please enter username and password.'; } else { $stmt = $pdo->prepare("SELECT id, username, name, password_hash, role FROM users WHERE username = ?"); $stmt->execute([$username]); $user = $stmt->fetch(PDO::FETCH_ASSOC); // Perform timing-safe password check if ($user && password_verify($password, $user['password_hash'])) { // Optional: password_needs_rehash update here if you changed algorithm session_regenerate_id(true); $_SESSION['user_id'] = (int)$user['id']; $_SESSION['username'] = $user['username']; $_SESSION['role'] = $user['role']; $_SESSION['user_name'] = $user['name'] ?? $user['username']; clear_fail(); // Remember me (requires remember_tokens table and logic in includes/init.php to auto-login) if ($remember) { // Generate selector + validator $selector = bin2hex(random_bytes(9)); // 18 chars $validator = bin2hex(random_bytes(32)); // 64 chars $hash = hash('sha256', $validator); $ua_hash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''); $expires = (new DateTime('+30 days'))->format('Y-m-d H:i:s'); // Store in DB $ins = $pdo->prepare("INSERT INTO remember_tokens (user_id, selector, validator_hash, user_agent_hash, expires_at) VALUES (?,?,?,?,?)"); try { $ins->execute([(int)$user['id'], $selector, $hash, $ua_hash, $expires]); } catch (Throwable $e) { /* ignore if table not present */ } // Set cookie $cookieVal = $selector . ':' . $validator; setcookie('remember', $cookieVal, [ 'expires' => time() + 60*60*24*30, 'path' => '/', 'domain' => $_SERVER['HTTP_HOST'], 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax' ]); } header('Location: dashboard.php'); exit; } else { register_fail(); $lockedRemaining = login_locked() ? (($_SESSION['login_lock_until'] ?? 0) - time()) : 0; $error = 'Invalid credentials.'; } } } }
?><!DOCTYPE html><html lang="en"> <head> <meta charset="UTF-8"> <meta name="robots" content="noindex, nofollow"> <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Login - Budget System</title> <link rel="icon" type="image/png" href="images/bureau-logo.png" sizes="32x32"> <link rel="apple-touch-icon" href="images/bureau-logo.png"> <meta name="theme-color" content="#4f46e5"> <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin> <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin> <script src="https://cdn.tailwindcss.com"></script> <link rel="stylesheet" href="css/all.min.css"> <script> tailwind.config = { theme: { extend: { colors: { primary: '#4f46e5', secondary: '#7c3aed', light: '#f8fafc', lighter: '#f1f5f9', } } } } </script> <style> @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap'); body{ font-family:'Inter',sans-serif; background: radial-gradient(60rem 30rem at 20% 10%, #e0e7ff 0%, transparent 60%), radial-gradient(60rem 30rem at 80% 20%, #fce7f3 0%, transparent 60%), linear-gradient(120deg,#f0f9ff 0%,#e0f2fe 100%); min-height:100vh; } .card-hover{transition:all .3s ease; box-shadow:0 4px 6px rgba(0,0,0,.05);} .card-hover:hover{transform:translateY(-4px); box-shadow:0 12px 28px rgba(0,0,0,.12);} .input-group:focus{box-shadow:0 0 0 3px rgba(99,102,241,.2);} .btn-primary{background:linear-gradient(to right,#4f46e5,#7c3aed);transition:all .25s;} .btn-primary:hover{background:linear-gradient(to right,#4338ca,#6d28d9);transform:translateY(-1px);box-shadow:0 6px 16px rgba(79,70,229,.25);} </style> </head> <body class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8"> <div class="absolute top-0 left-0 w-full h-1/2 bg-gradient-to-r from-primary to-secondary opacity-10 -z-10"></div> <div class="max-w-md w-full space-y-8 bg-white rounded-2xl shadow-xl overflow-hidden card-hover"> <div class="bg-gradient-to-r from-primary to-secondary text-white pt-10 px-10 text-center"> <div class="flex justify-center mb-4"> <img src="images/bureau-logo.png" alt="Bureau Logo" class="bureau-logo h-24 w-24 object-contain" loading="eager" onerror="this.src='https://via.placeholder.com/96/4f46e5/ffffff?text=B'"> </div> <h3 class="text-2xl font-semibold">Q.A.R.D Qaafiyat Biirok</h3> <h3 class="text-3xl font-extrabold mb-2">Integrated Financial System</h3> <p class="text-blue-100 pb-8">Sign in to your account</p> </div>
<div class="px-10 pb-10">
  <?php if ($error): ?>
    <div class="mb-6 p-4 rounded-lg bg-red-50 text-red-700 flex items-start">
      <i class="fas fa-exclamation-circle mt-1 mr-3"></i>
      <div><?php echo htmlspecialchars($error); ?>
        <?php if ($lockedRemaining > 0): ?>
          <div class="text-xs text-red-600 mt-1">Locked for <?php echo ceil($lockedRemaining/60); ?> minute(s).</div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <form class="space-y-6" method="post" autocomplete="on" novalidate>
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">

    <div>
      <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
      <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <i class="fas fa-user text-gray-400"></i>
        </div>
        <input id="username" name="username" type="text" required autocomplete="username"
               class="input-group py-3 px-10 block w-full rounded-lg border border-gray-300 focus:outline-none focus:ring-0"
               placeholder="Enter your username">
      </div>
    </div>

    <div>
      <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
      <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <i class="fas fa-lock text-gray-400"></i>
        </div>
        <input id="password" name="password" type="password" required autocomplete="current-password"
               class="input-group py-3 px-10 block w-full rounded-lg border border-gray-300 focus:outline-none focus:ring-0"
               placeholder="Enter your password" minlength="8">
        <button type="button" aria-label="Show/Hide password"
                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700"
                onclick="const p=document.getElementById('password'); p.type = p.type==='password' ? 'text' : 'password'; this.firstElementChild.classList.toggle('fa-eye'); this.firstElementChild.classList.toggle('fa-eye-slash');">
          <i class="fas fa-eye"></i>
        </button>
      </div>
    </div>

    <div class="flex items-center justify-between">
      <div class="flex items-center">
        <input id="remember_me" name="remember_me" type="checkbox"
          class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
        <label for="remember_me" class="ml-2 block text-sm text-gray-700">Remember me</label>
      </div>

      <div class="text-sm">
        <a href="forgot_password.php" class="font-medium text-primary hover:text-primary-dark">Forgot Password?</a>
      </div>
    </div>

    <?php /* Optional reCAPTCHA: show after too many fails or always
    <div class="g-recaptcha" data-sitekey="YOUR_SITE_KEY"></div>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    */ ?>

    <div>
      <button type="submit" class="btn-primary w-full py-3 px-4 rounded-lg text-white font-semibold shadow-md disabled:opacity-50"
        <?php if (login_locked()) echo 'disabled'; ?>>
        <i class="fas fa-sign-in-alt mr-2"></i>Sign in
      </button>
    </div>
  </form>

  <div class="mt-6 text-center">
    <p class="text-xs text-gray-500">
      For security, access is monitored and audited. Do not share credentials.
    </p>
  </div>
</div>

</div> <div class="absolute bottom-4 w-full text-center text-sm text-gray-500"> &copy; <?php echo date('Y'); ?> Budget System. All rights reserved. Developed by: <strong>Ali Abdela</strong> 
</div> 
</body> 
</html>