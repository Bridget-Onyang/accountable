<?php
session_start();

// Include the database connection and User class
require_once 'db_connect.php'; // Ensure this path is correct
require_once 'user.php';     // Ensure this path is correct

// Initialize the User class with the PDO connection
$userHandler = new User($pdo);

$errorMessage = ''; // Variable to store login error messages

// Check if the form was submitted using POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get email and password from the POST request
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember']) ? true : false;

    // Basic input validation
    if (empty($email) || empty($password)) {
        $errorMessage = "Please enter both email and password.";
    } else {
        // Attempt to log in the user
        $loggedInUser = $userHandler->login($email, $password);

        if ($loggedInUser) {
            // Login successful!
            // Store user data in session
            $_SESSION['user_id'] = $loggedInUser['user_id'];
            $_SESSION['full_name'] = $loggedInUser['full_name'];
            $_SESSION['email'] = $loggedInUser['email'];
            // You can store more user data as needed

            // Handle "remember me" functionality (simplified for this example)
            if ($rememberMe) {
                // In a real application, you would generate a persistent token
                // and store it in a cookie and the database.
                // For demonstration, we'll just acknowledge it.
                // echo "<script>console.log('Remember me checked. Implement persistent login here.');</script>";
            }

            // Redirect to the dashboard page
            header('Location: udashboard.php'); // Adjust to your actual dashboard page
            exit;
        } else {
            // Login failed
            $errorMessage = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AccountAble - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2e7d32; /* Deep green */
            --primary-dark: #1b5e20; /* Darker green */
            --primary-light: #4caf50; /* Lighter green */
            --accent-color: #8bc34a; /* Lime green accent */
            --dark-color: #212121; /* Dark gray/black */
            --light-color: #f5f5f5; /* Light gray */
            --text-dark: #333333;
            --text-light: #ffffff;
            --gray-color: #757575;
            --shadow-sm: 0 2px 5px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 10px rgba(0,0,0,0.15);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', sans-serif;
        }

        body {
            background-color: var(--light-color);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-image:
                radial-gradient(circle at 10% 20%, rgba(139, 195, 74, 0.1) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(46, 125, 50, 0.1) 0%, transparent 20%);
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 2rem;
        }

        .login-card {
            background-color: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            position: relative;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 8px;
            background-color: var(--primary-color);
        }

        .login-header {
            padding: 2rem 2rem 1rem;
            text-align: center;
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            color: var(--primary-color);
            font-size: 1.8rem;
            font-weight: 700;
        }

        .logo i {
            margin-right: 0.75rem;
            color: var(--accent-color);
        }

        .login-header h2 {
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .login-header p {
            color: var(--gray-color);
            font-size: 0.95rem;
        }

        .login-form {
            padding: 1.5rem 2rem 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark-color);
            font-size: 0.95rem;
        }

        .input-field {
            position: relative;
        }

        .input-field i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-color);
            font-size: 1rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .remember-me {
            display: flex;
            align-items: center;
        }

        .remember-me input {
            margin-right: 0.5rem;
            accent-color: var(--primary-color);
        }

        .forgot-password {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .forgot-password:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .login-btn {
            width: 100%;
            padding: 0.85rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-bottom: 1.5rem;
        }

        .login-btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 1.5rem 0;
            color: var(--gray-color);
            font-size: 0.85rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e0e0e0;
        }

        .divider::before {
            margin-right: 1rem;
        }

        .divider::after {
            margin-left: 1rem;
        }

        .social-login {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .social-btn {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: white;
            border: 1px solid #e0e0e0;
            color: var(--gray-color);
            font-size: 1.1rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .social-btn:hover {
            background-color: var(--light-color);
            transform: translateY(-2px);
        }

        .register-link {
            text-align: center;
            font-size: 0.95rem;
            color: var(--gray-color);
        }

        .register-link a {
            color: var(--primary-color);
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
        }

        .register-link a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        /* Responsive adjustments */
        @media (max-width: 480px) {
            .login-container {
                padding: 1rem;
            }

            .login-card {
                border-radius: 8px;
            }

            .login-header, .login-form {
                padding: 1.5rem;
            }
        }

        /* Animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-card {
            animation: fadeIn 0.6s ease-out;
        }

        .error-message {
            color: var(--danger-color);
            background-color: #ffebee;
            border: 1px solid var(--danger-color);
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-check-double"></i>
                    <span>AccountAble</span>
                </div>
                <h2>Welcome Back</h2>
                <p>Sign in to continue to your accountability dashboard</p>
            </div>

            <?php if (!empty($errorMessage)): ?>
                <div class="error-message"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>

            <form class="login-form" method="POST" action="login.php">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-field">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-field">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                    </div>
                </div>

                <div class="form-options">
                    <div class="remember-me">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    <a href="#" class="forgot-password">Forgot password?</a>
                </div>

                <button type="submit" class="login-btn">Log In</button>

                <div class="divider">or continue with</div>

                <div class="social-login">
                    <button type="button" class="social-btn">
                        <i class="fab fa-google"></i>
                    </button>
                    <button type="button" class="social-btn">
                        <i class="fab fa-apple"></i>
                    </button>
                    <button type="button" class="social-btn">
                        <i class="fab fa-facebook-f"></i>
                    </button>
                </div>

                <div class="register-link">
                    Don't have an account? <a href="signup.php">Sign up</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Add focus effects to inputs
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                // Ensure the variable is correctly referenced in JS
                this.parentElement.querySelector('i').style.color = 'var(--primary-color)';
            });

            input.addEventListener('blur', function() {
                // Ensure the variable is correctly referenced in JS
                this.parentElement.querySelector('i').style.color = 'var(--gray-color)';
            });
        });
    </script>
</body>
</html>
