<?php
include 'includes/db.php';
if (isset($_SESSION['user_id'])) header('Location: dashboard.php');
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['username'] = $user['username'];
        header('Location: dashboard.php');
    } else {
        $error = 'Invalid credentials';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Budget System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1e40af',
                        secondary: '#3b82f6',
                        accent: '#2563eb',
                        light: '#f8fafc',
                        lighter: '#f1f5f9',
                        dark: '#1e3a8a'
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap');
        
        /* Additional styles that complement your existing styles.css */
        .login-body {
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #4b5563, #1e40af);
            min-height: 100vh;
        }
        
        .card-hover {
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }
        
        .input-group:focus {
            box-shadow: 0 0 0 0.2rem rgba(30, 64, 175, 0.25);
            border-color: #1e40af;
        }
        
        .btn-primary {
            background: linear-gradient(90deg, #1e40af, #3b82f6);
            border: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .btn-primary:hover {
            background: linear-gradient(90deg, #1e3a8a, #2563eb);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
        }
        
        .bureau-logo {
            transition: all 0.3s ease;
        }
        
        .bureau-logo:hover {
            transform: scale(1.05);
        }
        
        /* Animations from your styles.css */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .animate-fadeIn {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        .animate-slideIn {
            animation: slideIn 0.3s ease-in-out;
        }
    </style>
</head>
<body class="login-body">
    <div class="max-w-md w-full space-y-8 bg-white rounded-2xl shadow-xl overflow-hidden card-hover animate-fadeIn">
        <!-- Header Section with Bureau Logo -->
        <div class="bg-gradient-to-r from-primary to-dark text-white pt-10 px-10 text-center">
            <div class="flex justify-center mb-4">
                <img src="images/bureau-logo.png" alt="Bureau Logo" 
                     class="bureau-logo h-24 w-24 object-contain"
                     onerror="this.src='https://via.placeholder.com/96/1e40af/ffffff?text=B'">
            </div>
            <h2 class="text-3xl font-bold mb-2">Budget System</h2>
            <p class="text-blue-100 pb-8">Sign in to your account</p>
        </div>

        <!-- Form Section -->
        <div class="px-10 pb-10">
            <?php if (isset($error)): ?>
                <div class="mb-6 p-4 rounded-lg bg-red-50 text-red-700 flex items-start animate-slideIn">
                    <i class="fas fa-exclamation-circle mt-1 mr-3"></i>
                    <div><?php echo $error; ?></div>
                </div>
            <?php endif; ?>

            <form class="space-y-6" method="post">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-user text-gray-400"></i>
                        </div>
                        <input id="username" name="username" type="text" required 
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
                        <input id="password" name="password" type="password" required 
                            class="input-group py-3 px-10 block w-full rounded-lg border border-gray-300 focus:outline-none focus:ring-0"
                            placeholder="Enter your password">
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember-me" name="remember-me" type="checkbox" 
                            class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
                        <label for="remember-me" class="ml-2 block text-sm text-gray-700">Remember me</label>
                    </div>

                    <div class="text-sm">
                        <a href="#" class="font-medium text-primary hover:text-primary-dark">Forgot password?</a>
                    </div>
                </div>

                <div>
                    <button type="submit" 
                        class="btn-primary w-full py-3 px-4 rounded-lg text-white font-semibold shadow-md">
                        <i class="fas fa-sign-in-alt mr-2"></i>Sign in
                    </button>
                </div>
            </form>

            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600">
                    Demo Credentials? 
                    <a href="#" class="font-medium text-primary hover:text-primary-dark">Click here</a>
                </p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="absolute bottom-4 w-full text-center text-sm text-gray-500">
        &copy; <?php echo date('Y'); ?> Budget System. All rights reserved.
    </div>
</body>
</html>