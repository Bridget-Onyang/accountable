<?php
/**
 * This file handles the database connection for the AccountAble application.
 * It uses PDO (PHP Data Objects) for secure and efficient database interactions.
 */

// Database configuration
define('DB_HOST', 'localhost'); // Your database host (e.g., 'localhost' or an IP address)
define('DB_NAME', 'accountabledb'); // The name of your database as defined in the SQL schema
define('DB_USER', 'root'); // Your database username
define('DB_PASS', ''); // Your database password

try {
    // Create a new PDO instance
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Fetch results as associative arrays
            PDO::ATTR_EMULATE_PREPARES => false, // Disable emulation for better security and performance
        ]
    );

    // Optional: Echo a success message (remove in production)
    // echo "Database connection successful!";

} catch (PDOException $e) {
    // Handle database connection errors
    // In a real application, you would log this error and show a user-friendly message.
    die("Database connection failed: " . $e->getMessage());
}

// The $pdo object is now available for use in other files that include this one.
?>
