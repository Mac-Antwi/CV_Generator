<?php
session_start();
require_once 'db_connect.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: generate_cv.php");
    exit();
}

// Initialize variables
$error = '';
$success = '';
$username = '';
$email = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        // Login processing
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        
        if (empty($username) || empty($password)) {
            $error = "Please fill all fields";
        } else {
            try {
                // Check user credentials
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    
                    // Redirect to cv genetator
                    header("Location: generate_cv.php");
                    exit();
                } else {
                    $error = "Invalid username or password";
                }
            } catch(PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    } else {
        // Registration processing
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm-password'];
        
        if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
            $error = "Please fill all fields";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters";
        } else {
            try {
                // Check if user already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                
                if ($stmt->rowCount() > 0) {
                    $error = "Username or email already exists";
                } else {
                    // Hash password and insert user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
                    if ($stmt->execute([$username, $email, $hashed_password])) {
                        $success = "Registration successful! You can now login.";
                        // Clear form fields
                        $username = '';
                        $email = '';
                    } else {
                        $error = "Registration failed. Please try again.";
                    }
                }
            } catch(PDOException $e) {
                $error = "Database error: " . $e->getMessage();
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
    <title>CV Generator - Register & Login</title>
    <style>
        :root {
            --primary: #3498db;
            --secondary: #2c3e50;
            --accent: #e74c3c;
            --light: #ecf0f1;
            --dark: #34495e;
            --success: #2ecc71;
            --warning: #f39c12;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #758088ff, #2c3e50);
            color: #333;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            width: 100%;
            max-width: 400px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        
        .header {
            background: var(--secondary);
            color: white;
            padding: 25px 20px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .form-container {
            padding: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }
        
        input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border 0.3s;
        }
        
        input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        button {
            width: 100%;
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: background 0.3s;
            margin-top: 10px;
        }
        
        button:hover {
            background-color: var(--secondary);
        }
        
        .switch-form {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .switch-form p {
            color: var(--dark);
        }
        
        .switch-form a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        
        .switch-form a:hover {
            text-decoration: underline;
        }
        
        .message {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .error {
            background-color: #ffebee;
            color: #d32f2f;
            border: 1px solid #ffcdd2;
        }
        
        .success {
            background-color: #e8f5e9;
            color: #388e3c;
            border: 1px solid #c8e6c9;
        }
        
        .password-container {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #777;
            cursor: pointer;
            width: auto;
            padding: 0;
            font-size: 14px;
        }
        
        .app-name {
            font-size: 1.2rem;
            font-weight: bold;
            color: white;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="app-name">CV Generator</div>
            <h1><?php echo isset($_GET['login']) ? 'Login' : 'Create Account'; ?></h1>
            <p><?php echo isset($_GET['login']) ? 'Login to access your CVs' : 'Register to create your professional CV'; ?></p>
        </div>
        
        <div class="form-container">
            <?php if (!empty($error)): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="message success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (!isset($_GET['login'])): ?>
            <!-- Registration Form -->
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required placeholder="Choose a username" value="<?php echo htmlspecialchars($username); ?>">
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="Your email address" value="<?php echo htmlspecialchars($email); ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" required placeholder="Create a password">
                        <button type="button" class="toggle-password" onclick="togglePassword('password', this)">Show</button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm-password">Confirm Password</label>
                    <input type="password" id="confirm-password" name="confirm-password" required placeholder="Confirm your password">
                </div>
                
                <button type="submit">Register</button>
            </form>
            
            <div class="switch-form">
                <p>Already have an account? <a href="?login">Login here</a></p>
            </div>
            
            <?php else: ?>
            <!-- Login Form -->
            <form method="POST" action="">
                <input type="hidden" name="login" value="true">
                <div class="form-group">
                    <label for="login-username">Username or Email</label>
                    <input type="text" id="login-username" name="username" required placeholder="Enter your username or email" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="login-password">Password</label>
                    <div class="password-container">
                        <input type="password" id="login-password" name="password" required placeholder="Enter your password">
                        <button type="button" class="toggle-password" onclick="togglePassword('login-password', this)">Show</button>
                    </div>
                </div>
                
                <button type="submit">Login</button>
            </form>
            
            <div class="switch-form">
                <p>Don't have an account? <a href="signin.php">Register here</a></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Simple JavaScript for toggling password visibility
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                button.textContent = 'Hide';
            } else {
                input.type = 'password';
                button.textContent = 'Show';
            }
        }
    </script>
</body>
</html>