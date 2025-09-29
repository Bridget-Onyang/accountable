<?php
session_start();

// Include the database connection and User class
require_once 'db_connect.php'; // Ensure this path is correct
require_once 'user.php';     // Ensure this path is correct

// Initialize the User class with the PDO connection
$userHandler = new User($pdo);

$errorMessage = ''; // Variable to store signup error messages
$successMessage = ''; // Variable to store success messages

// Check if the form was submitted using POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form values
    $fullName = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm-password'] ?? '';
    $terms = isset($_POST['terms']) ? true : false;

    // Basic server-side validation
    if (empty($fullName) || empty($email) || empty($phone) || empty($password) || empty($confirmPassword)) {
        $errorMessage = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Invalid email format.";
    } elseif ($password !== $confirmPassword) {
        $errorMessage = "Passwords do not match!";
    } elseif (strlen($password) < 8) {
        $errorMessage = "Password must be at least 8 characters long.";
    } elseif (!$terms) {
        $errorMessage = "You must agree to the Terms of Service and Privacy Policy.";
    } else {
        // Attempt to register the user
        // The User class handles password hashing internally
        if ($userHandler->register($fullName, $email, $phone, $password)) {
            $successMessage = "Account created successfully! You can now log in.";
            // Optionally, log the user in immediately after successful registration
            // $loggedInUser = $userHandler->login($email, $password);
            // if ($loggedInUser) {
            //     $_SESSION['user_id'] = $loggedInUser['user_id'];
            //     $_SESSION['full_name'] = $loggedInUser['full_name'];
            //     header('Location: udashboard.html');
            //     exit;
            // }
            // Redirect to login page after successful registration
            header('Location: login.php?signup_success=1');
            exit;
        } else {
            // Registration failed (e.g., email already exists)
            $errorMessage = "Registration failed. This email might already be in use.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - AccountAble</title>
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

        .signup-container {
            width: 100%;
            max-width: 480px;
            padding: 2rem;
        }

        .signup-card {
            background-color: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            position: relative;
        }

        .signup-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 8px;
            background-color: var(--primary-color);
        }

        .signup-header {
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

        .signup-header h2 {
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .signup-header p {
            color: var(--gray-color);
            font-size: 0.95rem;
        }

        .signup-form {
            padding: 1.5rem 2rem 2rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
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

        .password-strength {
            height: 4px;
            background-color: #e0e0e0;
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .strength-meter {
            height: 100%;
            width: 0;
            background-color: #ff5252;
            transition: var(--transition);
        }

        .terms {
            display: flex;
            align-items: flex-start;
            margin: 1.5rem 0;
            font-size: 0.9rem;
            color: var(--gray-color);
        }

        .terms input {
            margin-right: 0.75rem;
            margin-top: 0.25rem;
            accent-color: var(--primary-color);
        }

        .terms a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .terms a:hover {
            text-decoration: underline;
        }

        .signup-btn {
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

        .signup-btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .signup-btn:active {
            transform: translateY(0);
        }

        .login-link {
            text-align: center;
            font-size: 0.95rem;
            color: var(--gray-color);
        }

        .login-link a {
            color: var(--primary-color);
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
        }

        .login-link a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        /* Responsive adjustments */
        @media (max-width: 480px) {
            .signup-container {
                padding: 1rem;
            }

            .signup-card {
                border-radius: 8px;
            }

            .signup-header, .signup-form {
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

        .signup-card {
            animation: fadeIn 0.6s ease-out;
        }

        .error-message, .success-message {
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            font-size: 0.9rem;
        }

        .error-message {
            color: var(--danger-color);
            background-color: #ffebee;
            border: 1px solid var(--danger-color);
        }

        .success-message {
            color: var(--success-color);
            background-color: #e8f5e9;
            border: 1px solid var(--success-color);
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <div class="signup-card">
            <div class="signup-header">
                <div class="logo">
                    <i class="fas fa-check-double"></i>
                    <span>AccountAble</span>
                </div>
                <h2>Create Your Account</h2>
                <p>Join thousands achieving their goals through accountability</p>
            </div>

            <?php if (!empty($errorMessage)): ?>
                <div class="error-message"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>
            <?php if (!empty($successMessage)): ?>
                <div class="success-message"><?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>

            <form class="signup-form" method="POST" action="signup.php">
                <div class="form-group">
                    <label for="fullname">Full Name</label>
                    <div class="input-field">
                        <i class="fas fa-user"></i>
                        <input type="text" id="fullname" name="fullname" class="form-control" placeholder="Enter your full name" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-field">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <div class="input-field">
                        <i class="fas fa-phone"></i>
                        <input type="tel" id="phone" name="phone" class="form-control" placeholder="Enter your phone number" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-field">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Create a password" required>
                    </div>
                    <div class="password-strength">
                        <div class="strength-meter" id="strengthMeter"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm-password">Confirm Password</label>
                    <div class="input-field">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="confirm-password" name="confirm-password" class="form-control" placeholder="Confirm your password" required>
                    </div>
                </div>

                <div class="terms">
                    <input type="checkbox" id="terms" name="terms" required>
                    <label for="terms">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></label>
                </div>

                <button type="submit" class="signup-btn">Create Account</button>

                <div class="login-link">
                    Already have an account? <a href="login.php">Log in here</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const strengthMeter = document.getElementById('strengthMeter');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;

            // Check for length
            if (password.length >= 8) strength += 1;
            if (password.length >= 12) strength += 1;

            // Check for character variety
            if (/[A-Z]/.test(password)) strength += 1;
            if (/[0-9]/.test(password)) strength += 1;
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;

            // Update strength meter
            let width = 0;
            let color = '#ff5252'; // Red

            if (strength <= 2) {
                width = 33;
                color = '#ff5252'; // Red
            } else if (strength <= 4) {
                width = 66;
                color = '#ffb74d'; // Orange
            } else {
                width = 100;
                color = '#4caf50'; // Green
            }

            strengthMeter.style.width = width + '%';
            strengthMeter.style.backgroundColor = color;
        });

        // Add focus effects to inputs
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.querySelector('i').style.color = 'var(--primary-color)';
            });

            input.addEventListener('blur', function() {
                this.parentElement.querySelector('i').style.color = 'var(--gray-color)';
            });
        });
    </script>
</body>
</html>
