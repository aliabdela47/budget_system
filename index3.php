<?php


// --- SETUP ---
session_start();
// This determines which form to show: 'login', '2fa', or 'forgot_password'
$action = $_GET['action'] ?? 'login';

// --- MOCKUP DATABASE & USER DATA (for demonstration) ---
class MockPDO {
    public function prepare($query) { return new MockPDOStatement(); }
}
class MockPDOStatement {
    public function execute($params) {}
    public function fetch() {
        $username = $_POST['username'] ?? $_SESSION['2fa_user'] ?? '';
        // User 1: Standard login
        if ($username === 'admin') {
            return ['id' => 1, 'username' => 'admin', 'name' => 'Ali Abdela', 'role' => 'administrator', 'password_hash' => password_hash('password', PASSWORD_DEFAULT), '2fa_enabled' => false];
        }
        // User 2: Login with 2FA enabled
        if ($username === 'admin2fa') {
            return ['id' => 2, 'username' => 'admin2fa', 'name' => 'Sara Yusuf', 'role' => 'manager', 'password_hash' => password_hash('password', PASSWORD_DEFAULT), '2fa_enabled' => true];
        }
        return false;
    }
}
$pdo = new MockPDO();
// --- END OF MOCKUP ---

// --- FUNCTIONS ---
function set_flash_message($type, $message) {
    $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
}

// --- LOGIC ROUTING ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    switch ($action) {
        case 'login':
            handle_login($pdo);
            break;
        case '2fa':
            handle_2fa();
            break;
        case 'forgot_password':
            handle_forgot_password();
            break;
    }
}

function handle_login($pdo) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // If 2FA is enabled for this user, go to the 2FA step
        if ($user['2fa_enabled']) {
            $_SESSION['2fa_user'] = $user['username']; // Store user for the next step
            header('Location: ' . $_SERVER['PHP_SELF'] . '?action=2fa');
            exit();
        }
        
        // Standard login success
        finalize_login($user);
    } else {
        set_flash_message('error', 'Invalid username or password.');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

function handle_2fa() {
    // In a real app, you'd use a library like PHPGangsta/GoogleAuthenticator to verify the code
    $submitted_code = implode('', $_POST['code']);
    $correct_code = '123456'; // MOCK: Correct 2FA code

    if (isset($_SESSION['2fa_user']) && $submitted_code === $correct_code) {
        $stmt = $GLOBALS['pdo']->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$_SESSION['2fa_user']]);
        $user = $stmt->fetch();
        unset($_SESSION['2fa_user']);
        finalize_login($user);
    } else {
        set_flash_message('error', 'Invalid authentication code.');
        header('Location: ' . $_SERVER['PHP_SELF'] . '?action=2fa');
        exit();
    }
}

function handle_forgot_password() {
    $email = $_POST['email'];
    // In a real app, you would generate a unique token, save it to the DB, and email a reset link.
    set_flash_message('success', 'If an account exists for ' . htmlspecialchars($email) . ', a reset link has been sent.');
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

function finalize_login($user) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['name'] = $user['name'];
    set_flash_message('success', 'Welcome back, ' . htmlspecialchars($user['name']) . '!');
    header('Location: dashboard.php'); // Redirect to the main application
    exit();
}


// --- FLASH MESSAGE HANDLING ---
$flash_message = null;
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
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
        /* All styles from the previous design are kept */
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
        /* Styles for 2FA inputs */
        .code-input { width: 48px; height: 56px; text-align: center; font-size: 1.5rem; }
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

            <?php if ($action === 'login'): ?>
            <div id="login-form">
                <h2 id="form-title-login" class="text-center text-xl text-white/90 font-medium">Sign in to your account</h2>
                <form method="post" action="?action=login" class="space-y-6 mt-4" onsubmit="handleLoginSubmit(event)">
                    <div class="relative">
                        <i class="fa-solid fa-user absolute top-1/2 -translate-y-1/2 left-4 text-white/50"></i>
                        <input name="username" type="text" required placeholder="Username" id="login-username"
                               class="form-input w-full pl-12 pr-4 py-3 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-white/50 transition-all">
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
                        <a href="?action=forgot_password" id="forgot-password-link" class="font-medium text-white/80 hover:text-white">Forgot Password?</a>
                    </div>
                    <div>
                        <button type="submit" id="login-button" class="w-full py-3 rounded-lg text-white font-semibold aurora-button">
                            <span class="button-text">Sign In</span>
                            <i class="fas fa-spinner fa-spin button-loader hidden"></i>
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <?php if ($action === '2fa'): ?>
            <div id="2fa-form">
                <h2 id="form-title-2fa" class="text-center text-xl text-white/90 font-medium">Two-Factor Authentication</h2>
                <p id="form-subtitle-2fa" class="text-center text-sm text-white/70 mt-2">Enter the 6-digit code from your authenticator app.</p>
                <form method="post" action="?action=2fa" class="mt-6" onsubmit="handleLoginSubmit(event)">
                    <div class="flex justify-center gap-3" id="code-inputs">
                        <?php for ($i = 0; $i < 6; $i++): ?>
                        <input type="text" name="code[]" maxlength="1" required class="code-input form-input rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-white/50 transition-all">
                        <?php endfor; ?>
                    </div>
                    <div class="mt-6">
                        <button type="submit" id="login-button" class="w-full py-3 rounded-lg text-white font-semibold aurora-button">
                            <span class="button-text">Verify Code</span>
                            <i class="fas fa-spinner fa-spin button-loader hidden"></i>
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <?php if ($action === 'forgot_password'): ?>
            <div id="forgot-password-form">
                <h2 id="form-title-forgot" class="text-center text-xl text-white/90 font-medium">Reset Password</h2>
                <p id="form-subtitle-forgot" class="text-center text-sm text-white/70 mt-2">Enter your email to receive a reset link.</p>
                <form method="post" action="?action=forgot_password" class="space-y-6 mt-6" onsubmit="handleLoginSubmit(event)">
                    <div class="relative">
                        <i class="fa-solid fa-envelope absolute top-1/2 -translate-y-1/2 left-4 text-white/50"></i>
                        <input name="email" type="email" required placeholder="Email Address" id="forgot-email"
                               class="form-input w-full pl-12 pr-4 py-3 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-white/50 transition-all">
                    </div>
                    <div>
                        <button type="submit" id="login-button" class="w-full py-3 rounded-lg text-white font-semibold aurora-button">
                            <span class="button-text">Send Reset Link</span>
                            <i class="fas fa-spinner fa-spin button-loader hidden"></i>
                        </button>
                    </div>
                     <div class="text-center">
                        <a href="?action=login" id="back-to-login" class="font-medium text-sm text-white/80 hover:text-white">&larr; Back to Login</a>
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
                forgotLink: "Forgot Password?",
                signInButton: "Sign In",
                twoFaTitle: "Two-Factor Authentication",
                twoFaSubtitle: "Enter the 6-digit code from your authenticator app.",
                verifyButton: "Verify Code",
                resetTitle: "Reset Password",
                resetSubtitle: "Enter your email to receive a reset link.",
                emailPlaceholder: "Email Address",
                sendLinkButton: "Send Reset Link",
                backToLogin: "← Back to Login"
            },
            am: {
                greeting: ["እንደምን አደሩ!", "እንደምን ዋሉ!", "እንደምን አመሹ!"],
                signInTitle: "ወደ መለያዎ ይግቡ",
                usernamePlaceholder: "የተጠቃሚ ስም",
                passwordPlaceholder: "የይለፍ ቃል",
                forgotLink: "የይለፍ ቃልዎን ረሱ?",
                signInButton: "ግባ",
                twoFaTitle: "ባለ ሁለት-ደረጃ ማረጋገጫ",
                twoFaSubtitle: "ባለ 6-አሃዝ ኮዱን ከመተግበሪያዎ ያስገቡ።",
                verifyButton: "ኮዱን አረጋግጥ",
                resetTitle: "የይለፍ ቃል ዳግም ያስጀምሩ",
                resetSubtitle: "የዳግም ማስጀመሪያ ሊንክ ለመቀበል ኢሜልዎን ያስገቡ።",
                emailPlaceholder: "ኢሜይል አድራሻ",
                sendLinkButton: "ሊንክ ላክ",
                backToLogin: "← ወደ መግቢያ ተመለስ"
            },
            aa: {
                greeting: ["Subac Nabá!", "As Nabá!", "Bari Nabá!"],
                signInTitle: "Akáwntik Culus",
                usernamePlaceholder: "Fayyattih Migaq",
                passwordPlaceholder: "Sirrih Bii",
                forgotLink: "Sirrih Bii Buttenté?",
                signInButton: "Culus",
                twoFaTitle: "Namma-Dabqih Tussuquk",
                twoFaSubtitle: "Appik 6-Laqanh Koodu Culus.",
                verifyButton: "Koodu Tussuqu",
                resetTitle: "Sirrih Bii Cusubsi",
                resetSubtitle: "Cusubsih Linki Lih E-mailik Culus.",
                emailPlaceholder: "E-mail Adres",
                sendLinkButton: "Linki Rade",
                backToLogin: "← Culusuh Gac"
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
                    document.getElementById('forgot-password-link').textContent = t.forgotLink;
                    loginForm.querySelector('.button-text').textContent = t.signInButton;
                }

                const twoFaForm = document.getElementById('2fa-form');
                if (twoFaForm) {
                    document.getElementById('form-title-2fa').textContent = t.twoFaTitle;
                    document.getElementById('form-subtitle-2fa').textContent = t.twoFaSubtitle;
                    twoFaForm.querySelector('.button-text').textContent = t.verifyButton;
                }

                const forgotForm = document.getElementById('forgot-password-form');
                if (forgotForm) {
                    document.getElementById('form-title-forgot').textContent = t.resetTitle;
                    document.getElementById('form-subtitle-forgot').textContent = t.resetSubtitle;
                    document.getElementById('forgot-email').placeholder = t.emailPlaceholder;
                    forgotForm.querySelector('.button-text').textContent = t.sendLinkButton;
                    document.getElementById('back-to-login').textContent = t.backToLogin;
                }
            });

            // 4. 2FA INPUT AUTO-FOCUS & PASTE
            const codeInputsContainer = document.getElementById('code-inputs');
            if (codeInputsContainer) {
                const inputs = [...codeInputsContainer.children];
                inputs.forEach((input, index) => {
                    input.addEventListener('input', () => {
                        if (input.value && index < inputs.length - 1) {
                            inputs[index + 1].focus();
                        }
                    });
                    input.addEventListener('keydown', (e) => {
                        if (e.key === "Backspace" && !input.value && index > 0) {
                            inputs[index - 1].focus();
                        }
                    });
                    input.addEventListener('paste', (e) => {
                        const pasteData = e.clipboardData.getData('text');
                        if (pasteData.length === 6) {
                            inputs.forEach((i, idx) => i.value = pasteData[idx] || '');
                            inputs[5].focus();
                        }
                    });
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
    </script>
</body>
</html>
