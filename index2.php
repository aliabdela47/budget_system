<?php
session_start();

// Simulate database connection (replace with your actual DB connection)
$host = 'localhost';
$dbname = 'budget_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // If DB connection fails, we'll use a fallback for demonstration
    $db_connected = false;
}

// Generate CSRF token using native PHP function
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate limiting (simple in-memory counter)
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = ['count' => 0, 'last_attempt' => time()];
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Default credentials for demo (remove in production)
$demo_users = [
    'admin' => password_hash('admin123', PASSWORD_DEFAULT),
    'user' => password_hash('user123', PASSWORD_DEFAULT)
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limiting: max 5 attempts in 5 minutes
    $max_attempts = 5;
    $lockout_time = 300; // 5 minutes
    if ($_SESSION['login_attempts']['count'] >= $max_attempts && (time() - $_SESSION['login_attempts']['last_attempt']) < $lockout_time) {
        $error = "Too many login attempts. Please try again after " . ceil(($lockout_time - (time() - $_SESSION['login_attempts']['last_attempt'])) / 60) . " minute(s).";
    } else {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error = "Invalid request. Please try again.";
        } else {
            // Sanitize inputs
            $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
            $password = $_POST['password'];
            $remember_me = isset($_POST['remember_me']);

            // Increment login attempts
            $_SESSION['login_attempts']['count']++;
            $_SESSION['login_attempts']['last_attempt'] = time();

            // Authenticate user (using demo data if DB not connected)
            if (isset($pdo) {
                try {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    $user = $stmt->fetch();
                } catch (Exception $e) {
                    $user = false;
                }
            } else {
                // Fallback to demo authentication
                $user = isset($demo_users[$username]) ? [
                    'id' => 1,
                    'username' => $username,
                    'password_hash' => $demo_users[$username],
                    'role' => 'admin'
                ] : false;
            }

            if ($user && password_verify($password, $user['password_hash'])) {
                // Reset login attempts on success
                $_SESSION['login_attempts'] = ['count' => 0, 'last_attempt' => time()];
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['username'] = $user['username'];
                
                // Set remember me cookie if selected
                if ($remember_me) {
                    $token = bin2hex(random_bytes(32));
                    $expiry = time() + (30 * 24 * 60 * 60); // 30 days
                    setcookie('remember_token', $token, $expiry, '/', '', true, true);
                    
                    // Store token in database if connected
                    if (isset($pdo)) {
                        try {
                            $stmt = $pdo->prepare("UPDATE users SET remember_token = ?, token_expiry = ? WHERE id = ?");
                            $stmt->execute([$token, date('Y-m-d H:i:s', $expiry), $user['id']]);
                        } catch (Exception $e) {
                            // Silently fail if DB is not available
                        }
                    }
                }
                
                unset($_SESSION['csrf_token']); // Invalidate CSRF token
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid credentials';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Login - Budget System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin: 0;
        }
        
        .login-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            width: 100%;
            max-width: 400px;
        }
        
        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .input-group {
            transition: all 0.3s ease;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.5rem 0.75rem;
            display: flex;
            align-items: center;
        }
        
        .input-group:focus-within {
            transform: translateY(-2px);
            border-color: #4f46e5;
            box-shadow: 0 0 0 1px #4f46e5;
        }
        
        .password-toggle {
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 0.25rem;
            border-radius: 0.25rem;
        }
        
        .password-toggle:hover {
            background-color: #f1f5f9;
        }
        
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
        }
        
        .remember-me input {
            margin-right: 0.5rem;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        .shake {
            animation: shake 0.5s;
        }
        
        input {
            outline: none;
            width: 100%;
            background: transparent;
        }
        
        .btn-primary {
            background-color: #4f46e5;
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            width: 100%;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .btn-primary:hover {
            background-color: #4338ca;
        }
    </style>
</head>
<body>
    <div class="w-full max-w-md">
        <div class="login-card p-8">
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-100 rounded-full mb-4">
                    <i class="fas fa-wallet text-indigo-600 text-2xl"></i>
                </div>
                <h1 class="text-3xl font-bold text-slate-800">Budget System</h1>
                <p class="text-slate-600 mt-2">Sign in to your account</p>
                
                <!-- Demo credentials notice -->
                <div class="mt-4 p-3 bg-yellow-50 text-yellow-700 rounded-lg text-sm">
                    <p class="font-medium">Demo Credentials:</p>
                    <p>Username: <strong>admin</strong> | Password: <strong>admin123</strong></p>
                    <p>Username: <strong>user</strong> | Password: <strong>user123</strong></p>
                </div>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="bg-red-50 text-red-700 p-4 rounded-lg mb-6 flex items-start shake">
                    <i class="fas fa-exclamation-circle mt-1 mr-3"></i>
                    <div>
                        <p class="font-medium"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="post" id="login-form" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div>
                    <label for="username" class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                    <div class="input-group">
                        <i class="fas fa-user text-slate-400 mr-2"></i>
                        <input type="text" id="username" name="username" required 
                               placeholder="Enter your username" 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock text-slate-400 mr-2"></i>
                        <input type="password" id="password" name="password" required 
                               placeholder="Enter your password">
                        <span class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye text-slate-400" id="toggle-icon"></i>
                        </span>
                    </div>
                </div>
                
                <div class="remember-forgot">
                    <div class="remember-me">
                        <input type="checkbox" id="remember_me" name="remember_me" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
                        <label for="remember_me" class="ml-2 block text-sm text-slate-700">Remember me</label>
                    </div>
                    <a href="#" class="text-sm text-indigo-600 hover:text-indigo-500">Forgot password?</a>
                </div>
                
                <button type="submit" class="btn-primary">
                    Sign in
                </button>
            </form>
            
            <div class="mt-6 text-center">
                <p class="text-sm text-slate-600">
                    Secured by Budget System &copy; <?php echo date('Y'); ?>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Password toggle
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggle-icon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('login-form');
            
            form.addEventListener('submit', function(event) {
                let isValid = true;
                
                // Check username
                const username = document.getElementById('username');
                if (!username.value.trim()) {
                    isValid = false;
                    highlightError(username.parentElement);
                } else {
                    removeHighlight(username.parentElement);
                }
                
                // Check password
                const password = document.getElementById('password');
                if (!password.value) {
                    isValid = false;
                    highlightError(password.parentElement);
                } else {
                    removeHighlight(password.parentElement);
                }
                
                if (!isValid) {
                    event.preventDefault();
                    event.stopPropagation();
                }
            });
            
            function highlightError(element) {
                element.style.borderColor = '#ef4444';
                element.style.boxShadow = '0 0 0 1px #ef4444';
            }
            
            function removeHighlight(element) {
                element.style.borderColor = '#d1d5db';
                element.style.boxShadow = 'none';
            }
        });
    </script>
</body>
</html>