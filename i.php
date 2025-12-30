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
   
<script src="css/tailwind.css"></script> 
       <link rel="stylesheet" href="css/all.min.css"> 
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4f46e5',
                        secondary: '#7c3aed',
                        light: '#f8fafc',
                        lighter: '#f1f5f9',
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { 
            font-family: 'Inter', sans-serif;
            background: linear-gradient(120deg, #f0f9ff 0%, #e0f2fe 100%);
        }
        .card-hover {
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        .input-group:focus {
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        .btn-primary {
            background: linear-gradient(to right, #4f46e5, #7c3aed);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(to right, #4338ca, #6d28d9);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
        .bureau-logo {
            transition: all 0.3s ease;
        }
        .bureau-logo:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="absolute top-0 left-0 w-full h-1/2 bg-gradient-to-r from-primary to-secondary opacity-10 -z-10"></div>
    
    <div class="max-w-md w-full space-y-8 bg-white rounded-2xl shadow-xl overflow-hidden card-hover">
        <!-- Header Section -->
        <div class="bg-gradient-to-r from-primary to-secondary text-white py-8 px-10 text-center">
            <div class="flex justify-center mb-4">
              
              <img src="images/bureau-logo.png" alt="Bureau Logo" 
                     class="bureau-logo h-24 w-24 object-contain bg-white p-2 rounded-full shadow-md"
                     onerror="this.src='https://via.placeholder.com/96/4f46e5/ffffff?text=B'">
            </div>
            <h2 class="text-3xl font-bold mb-2">Budget System</h2>
            <p class="text-blue-100 pb-8">Sign in to your account</p>
        </div>

        <!-- Form Section -->
        <div class="px-10 pb-10">
            <?php if (isset($error)): ?>
                <div class="mb-6 p-4 rounded-lg bg-red-50 text-red-700 flex items-start">
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
        &copy; <?php echo date('Y'); ?> All rights reserved @ 2025 Developed by:<strong> Ali Abdela.</strong>
    </div>
</body>
</html>