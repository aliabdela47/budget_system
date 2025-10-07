<?php
// --- SETUP ---
session_start();
include 'includes/db.php';
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Handle "Remember Me" functionality
if (isset($_COOKIE['remembered_user'])) {
    $remembered_user = $_COOKIE['remembered_user'];
} else {
    $remembered_user = '';
}

// --- FLASH MESSAGE HANDLING ---
function set_flash_message($type, $message) {
    $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
}

$flash_message = null;
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// --- LOGIN HANDLING ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember_me']);
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
        
        // Handle "Remember Me" functionality
        if ($remember_me) {
            // Set cookie to expire in 30 days
            setcookie('remembered_user', $username, time() + (30 * 24 * 60 * 60), "/");
        } else {
            // Clear the cookie if not checked
            setcookie('remembered_user', '', time() - 3600, "/");
        }
        
        set_flash_message('success', 'Welcome back, ' . htmlspecialchars($user['name']) . '!');
        header('Location: dashboard.php');
        exit();
    } else {
        set_flash_message('error', 'Invalid username or password.');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

// --- FORGOT PASSWORD HANDLING ---
if (isset($_GET['action']) && $_GET['action'] === 'forgot_password') {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $email = $_POST['email'];
        
        // Check if email exists in database
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate a unique token
            $token = bin2hex(random_bytes(50));
            $expires = date("Y-m-d H:i:s", strtotime('+1 hour'));
            
            // Store token in database
            $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires) VALUES (?, ?, ?)");
            $stmt->execute([$email, $token, $expires]);
            
            // Send email with reset link
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?action=reset_password&token=$token";
            
            // Email content
            $subject = "Password Reset Request";
            $message = "
                <html>
                <head>
                    <title>Password Reset</title>
                </head>
                <body>
                    <h2>Password Reset Request</h2>
                    <p>You have requested to reset your password. Click the link below to proceed:</p>
                    <p><a href='$reset_link'>Reset Password</a></p>
                    <p>This link will expire in 1 hour.</p>
                    <p>If you didn't request this, please ignore this email.</p>
                </body>
                </html>
            ";
            
            // Email headers
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: no-reply@yourdomain.com" . "\r\n";
            
            // Send email using Hostinger SMTP (assuming SMTP is configured on server)
            if (mail($email, $subject, $message, $headers)) {
                set_flash_message('success', 'Password reset link has been sent to your email.');
            } else {
                set_flash_message('error', 'Failed to send reset email. Please try again.');
            }
        } else {
            set_flash_message('error', 'No account found with that email address.');
        }
        
        header('Location: ' . $_SERVER['PHP_SELF'] . '?action=forgot_password');
        exit();
    }
}

// --- RESET PASSWORD HANDLING ---
if (isset($_GET['action']) && $_GET['action'] === 'reset_password') {
    $token = $_GET['token'] ?? '';
    
    // Verify token
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires > NOW()");
    $stmt->execute([$token]);
    $reset_request = $stmt->fetch();
    
    if (!$reset_request) {
        set_flash_message('error', 'Invalid or expired reset token.');
        header('Location: ' . $_SERVER['PHP_SELF'] . '?action=forgot_password');
        exit();
    }
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($password !== $confirm_password) {
            set_flash_message('error', 'Passwords do not match.');
            header('Location: ' . $_SERVER['PHP_SELF'] . '?action=reset_password&token=' . $token);
            exit();
        }
        
        // Update password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
        $stmt->execute([$hashed_password, $reset_request['email']]);
        
        // Delete used token
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
        $stmt->execute([$token]);
        
        set_flash_message('success', 'Your password has been reset successfully. You can now login.');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Integrated Financial System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-animated { background: linear-gradient(-45deg, #4f46e5, #7c3aed, #06b6d4, #3b82f6); background-size: 400% 400%; animation: gradientBG 15s ease infinite; }
        @keyframes gradientBG { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        .glass-card { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.2); }
        .form-input { background: rgba(255, 255, 255, 0.15); border: 1px solid rgba(255, 255, 255, 0.3); }
        .form-input::placeholder { color: rgba(255, 255, 255, 0.6); }
        .aurora-button { background-image: linear-gradient(to right, #8b5cf6, #ec4899, #f59e0b); transition: all 0.3s ease; }
        .aurora-button:hover { box-shadow: 0 0 20px rgba(236, 72, 153, 0.5); transform: translateY(-2px); }
        .aurora-button:disabled { opacity: 0.7; cursor: not-allowed; }
        .flash-alert { animation: slideInDown 0.5s ease-out forwards, fadeOut 0.5s ease-in forwards 4.5s; }
        @keyframes slideInDown { from { opacity: 0; transform: translateY(-100%); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; visibility: hidden; } }
        .checkbox-wrapper {
            display: flex;
            align-items: center;
            position: relative;
            cursor: pointer;
        }
        .checkbox-wrapper input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }
        .checkmark {
            height: 20px;
            width: 20px;
            background-color: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
        }
        .checkbox-wrapper input[type="checkbox"]:checked ~ .checkmark {
            background-color: #8b5cf6;
        }
        .checkmark:after {
            content: "";
            display: none;
        }
        .checkbox-wrapper input[type="checkbox"]:checked ~ .checkmark:after {
            display: block;
            width: 5px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        .forgot-password-link {
            transition: all 0.3s ease;
        }
        .forgot-password-link:hover {
            text-shadow: 0 0 8px rgba(255, 255, 255, 0.8);
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 3px;
            transition: all 0.3s ease;
        }
        .strength-0 { width: 20%; background-color: #ef4444; }
        .strength-1 { width: 40%; background-color: #f59e0b; }
        .strength-2 { width: 60%; background-color: #f59e0b; }
        .strength-3 { width: 80%; background-color: #10b981; }
        .strength-4 { width: 100%; background-color: #10b981; }
    </style>
</head>
<body class="bg-animated min-h-screen flex items-center justify-center p-4 overflow-hidden">
    <div class="fixed top-5 right-5 z-50">
        <?php if ($flash_message): ?>
            <div id="flash-alert" class="flex items-center gap-4 p-4 rounded-lg shadow-2xl text-white <?php echo $flash_message['type'] === 'success' ? 'bg-green-500/80' : 'bg-red-500/80'; ?> backdrop-blur-sm border <?php echo $flash_message['type'] === 'success' ? 'border-green-400' : 'border-red-400'; ?>">
                <i class="fa-solid <?php echo $flash_message['type'] === 'success' ? 'fa-check-circle' : 'fa-times-circle'; ?> text-2xl"></i>
                <span class="font-medium"><?php echo htmlspecialchars($flash_message['message']); ?></span>
                <button onclick="document.getElementById('flash-alert').style.display='none'" class="ml-4 text-2xl leading-none">&times;</button>
            </div>
        <?php endif; ?>
    </div>

    <div class="w-full max-w-md">
        <div class="glass-card rounded-2xl shadow-2xl p-8 space-y-6 relative">
            <div class="absolute top-4 right-4">
                <select id="language-switcher" class="form-input text-white/80 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-white/50 cursor-pointer">
                    <option value="en">English</option>
                    <option value="am">አማርኛ</option>
                    <option value="aa">Qafár af</option>
                </select>
            </div>
            
            <div class="text-center text-white">
                <img src="images/bureau-logo.png" alt="Bureau Logo" class="mx-auto h-20 w-20 mb-4 bg-white/20 p-2 rounded-full" onerror="this.src='https://via.placeholder.com/80/ffffff/4f46e5?text=B'">
                <h1 id="header-title" class="text-3xl font-bold tracking-tight">Financial System</h1>
                <p id="header-greeting" class="text-white/80 text-lg">Good Morning!</p>
            </div>

            <?php if (!isset($_GET['action']) || $_GET['action'] === 'login'): ?>
            <div id="login-form">
                <h2 id="form-title-login" class="text-center text-xl text-white/90 font-medium">Sign in to your account</h2>
                <form method="post" action="?action=login" class="space-y-6 mt-4" onsubmit="handleLoginSubmit(event)">
                    <div class="relative">
                        <i class="fa-solid fa-user absolute top-1/2 -translate-y-1/2 left-4 text-white/50"></i>
                        <input name="username" type="text" required placeholder="Username" id="login-username"
                               class="form-input w-full pl-12 pr-4 py-3 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-white/50 transition-all"
                               value="<?php echo htmlspecialchars($remembered_user); ?>">
                    </div>
                    <div class="relative">
                        <i class="fa-solid fa-lock absolute top-1/2 -translate-y-1/2 left-4 text-white/50"></i>
                        <input id="password" name="password" type="password" required placeholder="Password" id="login-password"
                               class="form-input w-full pl-12 pr-12 py-3 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-white/50 transition-all">
                        <button type="button" class="password-toggle absolute top-1/2 -translate-y-1/2 right-4 text-white/50 hover:text-white">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <label class="checkbox-wrapper text-white/80">
                            <input type="checkbox" name="remember_me" <?php echo $remembered_user ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                            <span id="remember-me-text">Remember me</span>
                        </label>
                        <a href="?action=forgot_password" class="forgot-password-link font-medium text-white/80 hover:text-white" id="forgot-password-link">Forgot Password?</a>
                    </div>
                    <div>
                        <button type="submit" id="login-button" class="w-full py-3 rounded-lg text-white font-semibold aurora-button">
                            <span class="button-text" id="login-button-text">Sign In</span>
                            <i class="fas fa-spinner fa-spin button-loader hidden"></i>
                        </button>
                    </div>
                </form>
            </div>
            <?php elseif (isset($_GET['action']) && $_GET['action'] === 'forgot_password'): ?>
            <div id="forgot-password-form">
                <h2 id="form-title-forgot" class="text-center text-xl text-white/90 font-medium">Reset Password</h2>
                <p id="form-subtitle-forgot" class="text-center text-sm text-white/70 mt-2">Enter your email to receive a reset link.</p>
                <form method="post" action="?action=forgot_password" class="space-y-6 mt-6" onsubmit="handleForgotPasswordSubmit(event)">
                    <div class="relative">
                        <i class="fa-solid fa-envelope absolute top-1/2 -translate-y-1/2 left-4 text-white/50"></i>
                        <input name="email" type="email" required placeholder="Email Address" id="forgot-email"
                               class="form-input w-full pl-12 pr-4 py-3 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-white/50 transition-all">
                    </div>
                    <div>
                        <button type="submit" id="forgot-button" class="w-full py-3 rounded-lg text-white font-semibold aurora-button">
                            <span class="button-text" id="forgot-button-text">Send Reset Link</span>
                            <i class="fas fa-spinner fa-spin button-loader hidden"></i>
                        </button>
                    </div>
                     <div class="text-center">
                        <a href="?action=login" class="font-medium text-sm text-white/80 hover:text-white" id="back-to-login">&larr; Back to Login</a>
                    </div>
                </form>
            </div>
            <?php elseif (isset($_GET['action']) && $_GET['action'] === 'reset_password' && isset($_GET['token'])): ?>
            <div id="reset-password-form">
                <h2 id="form-title-reset" class="text-center text-xl text-white/90 font-medium">Set New Password</h2>
                <p id="form-subtitle-reset" class="text-center text-sm text-white/70 mt-2">Enter your new password below.</p>
                <form method="post" action="?action=reset_password&token=<?php echo htmlspecialchars($_GET['token']); ?>" class="space-y-6 mt-6" onsubmit="handleResetPasswordSubmit(event)">
                    <div class="relative">
                        <i class="fa-solid fa-lock absolute top-1/2 -translate-y-1/2 left-4 text-white/50"></i>
                        <input name="password" type="password" required placeholder="New Password" id="reset-password"
                               class="form-input w-full pl-12 pr-12 py-3 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-white/50 transition-all"
                               onkeyup="checkPasswordStrength(this.value)">
                        <button type="button" class="password-toggle absolute top-1/2 -translate-y-1/2 right-4 text-white/50 hover:text-white">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                        <div id="password-strength" class="password-strength strength-0"></div>
                        <p id="password-strength-text" class="text-xs text-white/70 mt-1 hidden">Password strength: <span id="strength-text">Weak</span></p>
                    </div>
                    <div class="relative">
                        <i class="fa-solid fa-lock absolute top-1/2 -translate-y-1/2 left-4 text-white/50"></i>
                        <input name="confirm_password" type="password" required placeholder="Confirm New Password" id="reset-confirm-password"
                               class="form-input w-full pl-12 pr-12 py-3 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-white/50 transition-all">
                        <button type="button" class="password-toggle absolute top-1/2 -translate-y-1/2 right-4 text-white/50 hover:text-white">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <div>
                        <button type="submit" id="reset-button" class="w-full py-3 rounded-lg text-white font-semibold aurora-button">
                            <span class="button-text" id="reset-button-text">Reset Password</span>
                            <i class="fas fa-spinner fa-spin button-loader hidden"></i>
                        </button>
                    </div>
                     <div class="text-center">
                        <a href="?action=login" class="font-medium text-sm text-white/80 hover:text-white" id="back-to-login-reset">&larr; Back to Login</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>

        </div>
        <div class="text-center mt-8 text-sm text-white/60">
            <p>&copy; <?php echo date('Y'); ?> Integrated Financial System | Developed by <strong>Ali Abdela</strong></p>
        </div>
    </div>
    
    <script>
        // --- TRANSLATION STRINGS ---
        const translations = {
            en: {
                greeting: ["Good Morning!", "Good Afternoon!", "Good Evening!"],
                signInTitle: "Sign in to your account",
                usernamePlaceholder: "Username",
                passwordPlaceholder: "Password",
                rememberMe: "Remember me",
                forgotLink: "Forgot Password?",
                signInButton: "Sign In",
                resetTitle: "Reset Password",
                resetSubtitle: "Enter your email to receive a reset link.",
                emailPlaceholder: "Email Address",
                sendLinkButton: "Send Reset Link",
                backToLogin: "← Back to Login",
                setNewPasswordTitle: "Set New Password",
                setNewPasswordSubtitle: "Enter your new password below.",
                newPasswordPlaceholder: "New Password",
                confirmPasswordPlaceholder: "Confirm New Password",
                resetButton: "Reset Password",
                passwordStrength: "Password strength: ",
                strengthWeak: "Weak",
                strengthMedium: "Medium",
                strengthStrong: "Strong",
                strengthVeryStrong: "Very Strong"
            },
            am: {
                greeting: ["እንደምን አደሩ!", "እንደምን ዋሉ!", "እንደምን አመሹ!"],
                signInTitle: "ወደ መለያዎ ይግቡ",
                usernamePlaceholder: "የተጠቃሚ ስም",
                passwordPlaceholder: "የይለፍ ቃል",
                rememberMe: "አስታውሰኝ",
                forgotLink: "የይለፍ ቃልዎን ረሱ?",
                signInButton: "ግባ",
                resetTitle: "የይለፍ ቃል ዳግም ያስጀምሩ",
                resetSubtitle: "የዳግም ማስጀመሪያ ሊንክ ለመቀበል ኢሜልዎን ያስገቡ።",
                emailPlaceholder: "ኢሜይል አድራሻ",
                sendLinkButton: "ሊንክ ላክ",
                backToLogin: "← ወደ መግቢያ ተመለስ",
                setNewPasswordTitle: "አዲስ የይለፍ ቃል ያስጀምሩ",
                setNewPasswordSubtitle: "አዲሱን የይለፍ ቃልዎን ከዚህ በታች ያስገቡ።",
                newPasswordPlaceholder: "አዲስ የይለፍ ቃል",
                confirmPasswordPlaceholder: "አዲሱን የይለፍ ቃል ያረጋግጡ",
                resetButton: "የይለፍ ቃል ዳግም ያስጀምሩ",
                passwordStrength: "የይለፍ ቃል ጥንካሬ: ",
                strengthWeak: "ደካማ",
                strengthMedium: "መካከለኛ",
                strengthStrong: "ጠንካራ",
                strengthVeryStrong: "በጣም ጠንካራ"
            },
            aa: {
                greeting: ["Subac Nabá!", "As Nabá!", "Bari Nabá!"],
                signInTitle: "Akáwntik Culus",
                usernamePlaceholder: "Fayyattih Migaq",
                passwordPlaceholder: "Sirrih Bii",
                rememberMe: "Nee Xissiyo",
                forgotLink: "Sirrih Bii Buttenté?",
                signInButton: "Culus",
                resetTitle: "Sirrih Bii Cusubsi",
                resetSubtitle: "Cusubsih Linki Lih E-mailik Culus.",
                emailPlaceholder: "E-mail Adres",
                sendLinkButton: "Linki Rade",
                backToLogin: "← Culusuh Gac",
                setNewPasswordTitle: "Sirrih Bii Cusub Siisiyyo",
                setNewPasswordSubtitle: "Sirrih Bii Cusub Siisiyyo Hinkic.",
                newPasswordPlaceholder: "Sirrih Bii Cusub",
                confirmPasswordPlaceholder: "Sirrih Bii Cusub Mirkaneessi",
                resetButton: "Sirrih Bii Cusubsi",
                passwordStrength: "Sirrih Bii Caddoyti: ",
                strengthWeak: "Caddo",
                strengthMedium: "Geyto",
                strengthStrong: "Caddow",
                strengthVeryStrong: "Baaxó Caddow"
            }
        };

        // --- DYNAMIC FEATURES ---
        document.addEventListener('DOMContentLoaded', function() {
            // 1. DYNAMIC GREETING
            const hour = new Date().getHours();
            const greetingEl = document.getElementById('header-greeting');
            let greetingIndex = hour < 12 ? 0 : hour < 18 ? 1 : 2;
            greetingEl.textContent = translations.en.greeting[greetingIndex];
            
            // 2. PASSWORD TOGGLE
            document.querySelectorAll('.password-toggle').forEach(toggle => {
                toggle.addEventListener('click', (e) => {
                    const passwordInput = e.currentTarget.previousElementSibling;
                    const icon = e.currentTarget.querySelector('i');
                    const isPassword = passwordInput.type === 'password';
                    passwordInput.type = isPassword ? 'text' : 'password';
                    icon.classList.toggle('fa-eye', !isPassword);
                    icon.classList.toggle('fa-eye-slash', isPassword);
                });
            });

            // 3. LANGUAGE SWITCHER
            const langSwitcher = document.getElementById('language-switcher');
            langSwitcher.addEventListener('change', (e) => {
                const lang = e.target.value;
                const t = translations[lang];
                
                document.getElementById('header-greeting').textContent = t.greeting[greetingIndex];
                
                // Update text based on which form is visible
                const loginForm = document.getElementById('login-form');
                if (loginForm) {
                    document.getElementById('form-title-login').textContent = t.signInTitle;
                    document.querySelector('input[name="username"]').placeholder = t.usernamePlaceholder;
                    document.getElementById('password').placeholder = t.passwordPlaceholder;
                    document.getElementById('remember-me-text').textContent = t.rememberMe;
                    document.getElementById('forgot-password-link').textContent = t.forgotLink;
                    document.getElementById('login-button-text').textContent = t.signInButton;
                }

                const forgotForm = document.getElementById('forgot-password-form');
                if (forgotForm) {
                    document.getElementById('form-title-forgot').textContent = t.resetTitle;
                    document.getElementById('form-subtitle-forgot').textContent = t.resetSubtitle;
                    document.getElementById('forgot-email').placeholder = t.emailPlaceholder;
                    document.getElementById('forgot-button-text').textContent = t.sendLinkButton;
                    document.getElementById('back-to-login').textContent = t.backToLogin;
                }

                const resetForm = document.getElementById('reset-password-form');
                if (resetForm) {
                    document.getElementById('form-title-reset').textContent = t.setNewPasswordTitle;
                    document.getElementById('form-subtitle-reset').textContent = t.setNewPasswordSubtitle;
                    document.getElementById('reset-password').placeholder = t.newPasswordPlaceholder;
                    document.getElementById('reset-confirm-password').placeholder = t.confirmPasswordPlaceholder;
                    document.getElementById('reset-button-text').textContent = t.resetButton;
                    document.getElementById('back-to-login-reset').textContent = t.backToLogin;
                    document.querySelector('#password-strength-text span').textContent = t.strengthWeak;
                    document.getElementById('password-strength-text').firstChild.textContent = t.passwordStrength;
                }
            });

            // 4. PASSWORD STRENGTH INDICATOR (for reset password form)
            const passwordInput = document.getElementById('reset-password');
            if (passwordInput) {
                passwordInput.addEventListener('input', function() {
                    checkPasswordStrength(this.value);
                });
            }
        });

        // 5. BUTTON LOADING STATE
        function handleLoginSubmit(event) {
            const button = event.target.querySelector('button[type="submit"]');
            button.disabled = true;
            button.querySelector('.button-text').classList.add('hidden');
            button.querySelector('.button-loader').classList.remove('hidden');
        }
        
        function handleForgotPasswordSubmit(event) {
            const button = event.target.querySelector('button[type="submit"]');
            button.disabled = true;
            button.querySelector('.button-text').classList.add('hidden');
            button.querySelector('.button-loader').classList.remove('hidden');
        }
        
        function handleResetPasswordSubmit(event) {
            const password = document.getElementById('reset-password').value;
            const confirmPassword = document.getElementById('reset-confirm-password').value;
            
            if (password !== confirmPassword) {
                event.preventDefault();
                alert('Passwords do not match');
                return false;
            }
            
            const button = event.target.querySelector('button[type="submit"]');
            button.disabled = true;
            button.querySelector('.button-text').classList.add('hidden');
            button.querySelector('.button-loader').classList.remove('hidden');
        }

        // 6. PASSWORD STRENGTH CHECKER
        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('password-strength');
            const strengthText = document.getElementById('strength-text');
            const strengthTextContainer = document.getElementById('password-strength-text');
            
            if (!strengthBar || !strengthText) return;
            
            // Reset classes
            strengthBar.className = 'password-strength';
            
            // Calculate strength
            let strength = 0;
            if (password.length > 6) strength++;
            if (password.length > 10) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            // Update strength bar
            strengthBar.classList.add('strength-' + strength);
            
            // Update strength text
            const t = translations[document.getElementById('language-switcher').value] || translations.en;
            strengthTextContainer.classList.remove('hidden');
            
            switch(strength) {
                case 0:
                case 1:
                    strengthText.textContent = t.strengthWeak;
                    break;
                case 2:
                case 3:
                    strengthText.textContent = t.strengthMedium;
                    break;
                case 4:
                    strengthText.textContent = t.strengthStrong;
                    break;
                case 5:
                    strengthText.textContent = t.strengthVeryStrong;
                    break;
            }
        }
    </script>
</body>
</html>