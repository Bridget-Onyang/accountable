<?php
session_start();

// Check if the user is logged in. If not, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}



// Include database connection and other necessary classes
require_once 'db_connect.php'; // Provides $pdo connection
require_once 'user.php';       // For fetching and updating user details
// For logging activities

class AuditTrail {
    private $pdo; // PDO database connection object

    /**
     * Constructor
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Logs an activity into the AuditTrail.
     *
     * @param int|null $userId The ID of the user performing the activity (can be null for system events).
     * @param string $activityTitle A brief title for the activity.
     * @param string $activityDescription A detailed description of the activity.
     * @param string $activityType The type of activity (e.g., 'Transactions', 'User Actions', 'System Events', 'Clients', 'Invoices').
     * @param string $activityStatus The status of the activity (e.g., 'Success', 'Failed', 'Pending', 'Verified').
     * @param string|null $blockchainHash Optional: A blockchain hash related to the activity.
     * @param string|null $ipAddress Optional: The IP address from which the activity originated.
     * @param string|null $deviceInfo Optional: Information about the device used.
     * @return bool True on successful insertion, false otherwise.
     */
    public function logActivity(
        $userId,
        $activityTitle,
        $activityDescription,
        $activityType,
        $activityStatus = 'Success',
        $blockchainHash = null,
        $ipAddress = null,
        $deviceInfo = null
    ) {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO AuditTrail (user_id, activity_title, activity_description, activity_type, activity_status, blockchain_hash, ip_address, device_info)
                 VALUES (:user_id, :activity_title, :activity_description, :activity_type, :activity_status, :blockchain_hash, :ip_address, :device_info)"
            );

            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':activity_title', $activityTitle);
            $stmt->bindParam(':activity_description', $activityDescription);
            $stmt->bindParam(':activity_type', $activityType);
            $stmt->bindParam(':activity_status', $activityStatus);
            $stmt->bindParam(':blockchain_hash', $blockchainHash);
            $stmt->bindParam(':ip_address', $ipAddress);
            $stmt->bindParam(':device_info', $deviceInfo);

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log("Error logging audit activity: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches audit trail entries for a specific user, with filtering and pagination.
     *
     * @param int $userId The ID of the user whose audit trail to fetch.
     * @param int $limit The maximum number of entries per page.
     * @param int $offset The offset for pagination.
     * @param string $searchTerm Optional: A term to search within activity title or description.
     * @param string $activityTypeFilter Optional: Filter by activity type.
     * @param string $activityStatusFilter Optional: Filter by activity status.
     * @return array An array containing 'entries' (the fetched records) and 'total' (total count).
     */
    public function getAuditTrailEntries(
        $userId,
        $limit,
        $offset,
        $searchTerm = '',
        $activityTypeFilter = '',
        $activityStatusFilter = ''
    ) {
        $entries = [];
        $total = 0;

        try {
            // Ensure the Users table is joined correctly to get user_name
            $sql = "SELECT at.*, u.full_name AS user_name
                    FROM AuditTrail at
                    LEFT JOIN Users u ON at.user_id = u.user_id
                    WHERE at.user_id = :user_id";
            $params = [':user_id' => $userId];

            if (!empty($searchTerm)) {
                $sql .= " AND (at.activity_title LIKE :search_term OR at.activity_description LIKE :search_term OR at.blockchain_hash LIKE :search_term)";
                $params[':search_term'] = '%' . $searchTerm . '%';
            }

            if (!empty($activityTypeFilter) && $activityTypeFilter !== 'All Activities') {
                $sql .= " AND at.activity_type = :activity_type";
                $params[':activity_type'] = $activityTypeFilter;
            }

            if (!empty($activityStatusFilter) && $activityStatusFilter !== 'All Status') {
                $sql .= " AND at.activity_status = :activity_status";
                $params[':activity_status'] = $activityStatusFilter;
            }

            // Get total count first
            $countSql = "SELECT COUNT(*) FROM (" . $sql . ") AS subquery";
            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();

            // Fetch actual entries with limit and offset
            $sql .= " ORDER BY at.timestamp DESC LIMIT :limit OFFSET :offset";
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $entries = $stmt->fetchAll();

        } catch (PDOException $e) {
            error_log("Error fetching audit trail entries: " . $e->getMessage());
        }

        return ['entries' => $entries, 'total' => $total];
    }
}


// Initialize handlers
$userHandler = new User($pdo);
$auditTrailHandler = new AuditTrail($pdo);

// Fetch logged-in user's details
$userId = $_SESSION['user_id'];
$currentUser = $userHandler->getUserById($userId);

// If user data cannot be fetched, something is wrong with the session or DB.
// Log out the user for security.
if (!$currentUser) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=session_invalid');
    exit;
}

$errorMessage = '';
$successMessage = '';

// Handle Profile Information Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_profile') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $company = trim($_POST['company'] ?? '');

    if (empty($fullName) || empty($email)) {
        $errorMessage = "Full Name and Email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Invalid email format.";
    } else {
        $updateData = [
            'full_name' => $fullName,
            'email' => $email,
            'phone_number' => $phone,
            'company' => $company
        ];
        if ($userHandler->updateProfile($userId, $updateData)) {
            $successMessage = "Profile updated successfully!";
            $auditTrailHandler->logActivity($userId, 'Profile Update', "User profile updated.", 'Settings', 'Success');
            // Refresh current user data after update
            $currentUser = $userHandler->getUserById($userId);
        } else {
            $errorMessage = "Failed to update profile. Email might already be in use.";
            $auditTrailHandler->logActivity($userId, 'Profile Update Failed', "Attempt to update profile failed.", 'Settings', 'Failed');
        }
    }
}

// Handle Password Change (simplified: no old password check for this example)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'change_password') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmNewPassword = $_POST['confirm_new_password'] ?? '';

    if (empty($newPassword) || empty($confirmNewPassword)) {
        $errorMessage = "Please enter and confirm your new password.";
    } elseif ($newPassword !== $confirmNewPassword) {
        $errorMessage = "New passwords do not match.";
    } elseif (strlen($newPassword) < 8) {
        $errorMessage = "Password must be at least 8 characters long.";
    } else {
        if ($userHandler->updatePassword($userId, $newPassword)) {
            $successMessage = "Password changed successfully!";
            $auditTrailHandler->logActivity($userId, 'Password Change', "User password changed.", 'Security', 'Success');
        } else {
            $errorMessage = "Failed to change password. Please try again.";
            $auditTrailHandler->logActivity($userId, 'Password Change Failed', "Attempt to change password failed.", 'Security', 'Failed');
        }
    }
}

// Handle Security Settings (Two-Factor, Blockchain Verification, Login Notifications)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_security_settings') {
    $twoFactorEnabled = isset($_POST['two_factor_enabled']) ? 1 : 0;
    $blockchainVerificationEnabled = isset($_POST['blockchain_verification_enabled']) ? 1 : 0;
    $loginNotificationsEnabled = isset($_POST['login_notifications_enabled']) ? 1 : 0;

    $updateData = [
        'two_factor_enabled' => $twoFactorEnabled,
        'blockchain_verification_enabled' => $blockchainVerificationEnabled,
        'login_notifications_enabled' => $loginNotificationsEnabled
    ];

    if ($userHandler->updateProfile($userId, $updateData)) {
        $successMessage = "Security settings updated successfully!";
        $auditTrailHandler->logActivity($userId, 'Security Settings Update', "User security settings updated.", 'Security', 'Success');
        // Refresh current user data after update
        $currentUser = $userHandler->getUserById($userId);
    } else {
        $errorMessage = "Failed to update security settings. Please try again.";
        $auditTrailHandler->logActivity($userId, 'Security Settings Update Failed', "Attempt to update security settings failed.", 'Security', 'Failed');
    }
}

// Handle Advanced Settings (Currency, Timezone, Language, Auto-lock)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_advanced_settings') {
    $defaultCurrency = trim($_POST['default_currency'] ?? '');
    $timezone = trim($_POST['timezone'] ?? '');
    $language = trim($_POST['language'] ?? '');
    $autoLockEnabled = isset($_POST['auto_lock_enabled']) ? 1 : 0;

    $updateData = [
        'default_currency' => $defaultCurrency,
        'timezone' => $timezone,
        'language' => $language,
        'auto_lock_enabled' => $autoLockEnabled
    ];

    if ($userHandler->updateProfile($userId, $updateData)) {
        $successMessage = "Advanced settings updated successfully!";
        $auditTrailHandler->logActivity($userId, 'Advanced Settings Update', "User advanced settings updated.", 'Settings', 'Success');
        // Refresh current user data after update
        $currentUser = $userHandler->getUserById($userId);
    } else {
        $errorMessage = "Failed to update advanced settings. Please try again.";
        $auditTrailHandler->logActivity($userId, 'Advanced Settings Update Failed', "Attempt to update advanced settings failed.", 'Settings', 'Failed');
    }
}

// Handle Delete Account
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_account') {
    // In a real application, you would require password confirmation and
    // potentially a re-authentication step before deleting an account.
    // For simplicity, this example just logs the attempt.
    if ($userHandler->deleteUser($userId)) {
        $auditTrailHandler->logActivity($userId, 'Account Deletion', "User account permanently deleted.", 'Account Actions', 'Success');
        session_unset();
        session_destroy();
        header('Location: login.php?message=account_deleted');
        exit;
    } else {
        $errorMessage = "Failed to delete account. Please try again.";
        $auditTrailHandler->logActivity($userId, 'Account Deletion Failed', "Attempt to delete account failed.", 'Account Actions', 'Failed');
    }
}

// Handle Export Data (simulation)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'export_data') {
    // In a real application, this would trigger a background process
    // to generate and email the data export.
    $successMessage = "Preparing your data export. You will receive an email when it's ready for download.";
    $auditTrailHandler->logActivity($userId, 'Data Export Request', "User requested data export.", 'Account Actions', 'Success');
}

// Fetch connected devices (simulated data for now)
// In a real application, this would come from a database storing session/device info
$connectedDevices = [
    ['type' => 'laptop', 'name' => 'MacBook Pro', 'location' => 'Kampala, Uganda', 'status' => 'Active now'],
    ['type' => 'mobile', 'name' => 'iPhone 13', 'location' => 'Kampala, Uganda', 'status' => '2 hours ago'],
    ['type' => 'tablet', 'name' => 'iPad Pro', 'location' => 'Nairobi, Kenya', 'status' => '3 days ago'],
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AccountAble | Settings</title>
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
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --danger-color: #f44336;
            --shadow-sm: 0 2px 5px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 10px rgba(0,0,0,0.15);
            --shadow-lg: 0 8px 20px rgba(0,0,0,0.2);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', sans-serif;
        }

        body {
            background-color: #f9f9f9;
            color: var(--text-dark);
            display: grid;
            grid-template-columns: 240px 1fr;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            background-color: var(--dark-color);
            color: var(--text-light);
            height: 100vh;
            position: sticky;
            top: 0;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logo {
            display: flex;
            align-items: center;
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-light);
        }

        .logo i {
            margin-right: 0.75rem;
            color: var(--accent-color);
        }

        .nav-menu {
            padding: 1.5rem 0;
        }

        .menu-title {
            padding: 0 1.5rem 0.5rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.5);
            letter-spacing: 0.5px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 0.8rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
        }

        .nav-item i {
            margin-right: 1rem;
            width: 20px;
            text-align: center;
        }

        .nav-item:hover, .nav-item.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: var(--text-light);
            border-left: 3px solid var(--accent-color);
        }

        .nav-item.active {
            background-color: rgba(255, 255, 255, 0.05);
        }

        .nav-item .badge {
            margin-left: auto;
            background-color: var(--primary-color);
            color: white;
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
            border-radius: 10px;
        }

        /* Main Content Styles */
        .main-content {
            padding: 2rem;
        }

        /* Header */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title h1 {
            font-size: 1.8rem;
            color: var(--dark-color);
        }

        .page-title p {
            color: var(--gray-color);
            font-size: 0.9rem;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .notification-icon {
            position: relative;
            color: var(--gray-color);
            cursor: pointer;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-light);
        }

        .user-name {
            font-weight: 600;
        }

        .user-role {
            font-size: 0.8rem;
            color: var(--gray-color);
        }

        /* Settings Content */
        .settings-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
        }

        .settings-card {
            background-color: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .settings-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--light-color);
        }

        .settings-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark-color);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--light-color);
            border-radius: 6px;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
        }

        .form-text {
            font-size: 0.8rem;
            color: var(--gray-color);
            margin-top: 0.5rem;
        }

        /* Button Styles */
        .btn {
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            box-shadow: var(--shadow-sm);
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--light-color);
            color: var(--gray-color);
        }

        .btn-outline:hover {
            background-color: var(--light-color);
        }

        /* Toggle Switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--primary-color);
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        /* Avatar Upload */
        .avatar-upload {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .avatar-preview {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-light);
        }

        .avatar-upload-btn {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        /* Security Badges */
        .security-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .badge-success {
            background-color: #e8f5e9;
            color: var(--success-color);
        }

        .badge-warning {
            background-color: #fff8e1;
            color: var(--warning-color);
        }

        /* Connected Devices */
        .device-list {
            list-style: none;
        }

        .device-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 0;
            border-bottom: 1px solid var(--light-color);
        }

        .device-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .device-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background-color: var(--light-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
        }

        .device-meta h4 {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .device-meta p {
            font-size: 0.8rem;
            color: var(--gray-color);
        }

        .device-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            body {
                grid-template-columns: 1fr;
            }

            .sidebar {
                height: auto;
                position: static;
            }
        }

        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .user-menu {
                width: 100%;
                justify-content: space-between;
            }

            .avatar-upload {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="udashboard.php" class="logo">
                <i class="fas fa-check-double"></i>
                <span>AccountAble</span>
            </a>
        </div>
        <nav class="nav-menu">
            <div class="menu-title">Main</div>
            <a href="udashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="Transactions.php" class="nav-item">
                <i class="fas fa-exchange-alt"></i>
                <span>Transactions</span>
            </a>
            <a href="invoices.php" class="nav-item">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Invoices</span>
            </a>
            <a href="client.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Clients</span>
            </a>

            <a href="Audit-Trail.php" class="nav-item">
                <i class="fas fa-shield-alt"></i>
                <span>Audit Trail</span>
            </a>
            <a href="Verification.php" class="nav-item">
                <i class="fas fa-fingerprint"></i>
                <span>Verification</span>
            </a>
            
            <div class="menu-title">Account</div>
            <a href="settings.php" class="nav-item active">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="Logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Dashboard Header -->
        <header class="dashboard-header">
            <div class="page-title">
                <h1>Account Settings</h1>
                <p>Manage your account preferences and security settings</p>
            </div>
            <div class="user-menu">
                <div class="notification-icon">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </div>
                <div class="user-profile">
                    <img src="https://placehold.co/40x40/2e7d32/ffffff?text=<?php echo substr($currentUser['full_name'], 0, 1); ?>" alt="User" class="user-avatar">
                    <div>
                        <div class="user-name"><?php echo htmlspecialchars($currentUser['full_name']); ?></div>
                        <div class="user-role"><?php echo htmlspecialchars($currentUser['user_role']); ?></div>
                    </div>
                </div>
            </div>
        </header>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger" style="padding: 1rem; margin-bottom: 1rem; border-radius: 5px; background-color: #f8d7da; color: #721c24;">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success" style="padding: 1rem; margin-bottom: 1rem; border-radius: 5px; background-color: #d4edda; color: #155724;">
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>

        <!-- Settings Content -->
        <div class="settings-container">
            <!-- Profile Settings -->
            <div class="settings-card">
                <div class="settings-header">
                    <h2 class="settings-title">Profile Information</h2>
                </div>
                
                <div class="avatar-upload">
                    <img src="https://placehold.co/80x80/2e7d32/ffffff?text=<?php echo substr($currentUser['full_name'], 0, 1); ?>" alt="Profile" class="avatar-preview">
                    <div class="avatar-upload-btn">
                        <button class="btn btn-outline" id="changeAvatarBtn">
                            <i class="fas fa-camera"></i> Change Avatar
                        </button>
                        <span class="form-text">JPG, GIF or PNG. Max size of 2MB</span>
                    </div>
                </div>
                
                <form action="settings.php" method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-group">
                        <label for="name" class="form-label">Full Name</label>
                        <input type="text" id="name" name="full_name" class="form-control" value="<?php echo htmlspecialchars($currentUser['full_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
                        <span class="form-text">This is the email associated with your account</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($currentUser['phone_number']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="company" class="form-label">Company</label>
                        <input type="text" id="company" name="company" class="form-control" value="<?php echo htmlspecialchars($currentUser['company'] ?? ''); ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
            </div>
            
            <!-- Security Settings -->
            <div class="settings-card">
                <div class="settings-header">
                    <h2 class="settings-title">Security Settings</h2>
                </div>
                
                <div class="form-group">
                    <div class="form-label">Password</div>
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <span>••••••••••</span>
                        <button class="btn btn-outline" style="padding: 0.4rem 0.8rem;" id="changePasswordBtn">
                            <i class="fas fa-edit"></i> Change
                        </button>
                    </div>
                    <!-- Password Change Form (Hidden by default) -->
                    <form id="passwordChangeForm" action="settings.php" method="POST" style="display: none; margin-top: 1rem;">
                        <input type="hidden" name="action" value="change_password">
                        <div class="form-group">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_new_password" class="form-label">Confirm New Password</label>
                            <input type="password" id="confirm_new_password" name="confirm_new_password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Password</button>
                        <button type="button" class="btn btn-outline" id="cancelPasswordChange">Cancel</button>
                    </form>
                </div>
                
                <form action="settings.php" method="POST">
                    <input type="hidden" name="action" value="update_security_settings">
                    <div class="form-group">
                        <div class="form-label">Two-Factor Authentication</div>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <label class="switch">
                                <input type="checkbox" name="two_factor_enabled" value="1" <?php echo $currentUser['two_factor_enabled'] ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                            <span><?php echo $currentUser['two_factor_enabled'] ? 'Enabled' : 'Disabled'; ?></span>
                            <?php if ($currentUser['two_factor_enabled']): ?>
                                <span class="security-badge badge-success">
                                    <i class="fas fa-check-circle"></i> Active
                                </span>
                            <?php endif; ?>
                        </div>
                        <span class="form-text">Add an extra layer of security to your account</span>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-label">Blockchain Verification</div>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <label class="switch">
                                <input type="checkbox" name="blockchain_verification_enabled" value="1" <?php echo $currentUser['blockchain_verification_enabled'] ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                            <span><?php echo $currentUser['blockchain_verification_enabled'] ? 'Enabled' : 'Disabled'; ?></span>
                            <?php if ($currentUser['blockchain_verification_enabled']): ?>
                                <span class="security-badge badge-success">
                                    <i class="fas fa-link"></i> Connected
                                </span>
                            <?php endif; ?>
                        </div>
                        <span class="form-text">Verify all transactions on the blockchain</span>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-label">Login Notifications</div>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <label class="switch">
                                <input type="checkbox" name="login_notifications_enabled" value="1" <?php echo $currentUser['login_notifications_enabled'] ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                            <span><?php echo $currentUser['login_notifications_enabled'] ? 'Enabled' : 'Disabled'; ?></span>
                        </div>
                        <span class="form-text">Get notified when someone logs into your account</span>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Security Settings
                    </button>
                </form>
            </div>
            
            <!-- Connected Devices -->
            <div class="settings-card">
                <div class="settings-header">
                    <h2 class="settings-title">Connected Devices</h2>
                </div>
                
                <ul class="device-list">
                    <?php if (!empty($connectedDevices)): ?>
                        <?php foreach ($connectedDevices as $device): ?>
                            <li class="device-item">
                                <div class="device-info">
                                    <div class="device-icon">
                                        <?php
                                            $iconClass = '';
                                            if ($device['type'] === 'laptop') $iconClass = 'fa-laptop';
                                            elseif ($device['type'] === 'mobile') $iconClass = 'fa-mobile-alt';
                                            elseif ($device['type'] === 'tablet') $iconClass = 'fa-tablet-alt';
                                        ?>
                                        <i class="fas <?php echo $iconClass; ?>"></i>
                                    </div>
                                    <div class="device-meta">
                                        <h4><?php echo htmlspecialchars($device['name']); ?></h4>
                                        <p><?php echo htmlspecialchars($device['location']); ?> • <?php echo htmlspecialchars($device['status']); ?></p>
                                    </div>
                                </div>
                                <div class="device-actions">
                                    <button class="btn btn-outline logout-device-btn" data-device="<?php echo htmlspecialchars($device['name']); ?>" style="padding: 0.4rem 0.8rem;">
                                        <i class="fas fa-sign-out-alt"></i> Logout
                                    </button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: var(--gray-color);">No connected devices found.</p>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- Advanced Settings -->
            <div class="settings-card">
                <div class="settings-header">
                    <h2 class="settings-title">Advanced Settings</h2>
                </div>
                
                <form action="settings.php" method="POST">
                    <input type="hidden" name="action" value="update_advanced_settings">
                    <div class="form-group">
                        <label for="currency" class="form-label">Default Currency</label>
                        <select id="currency" name="default_currency" class="form-control">
                            <option value="UGX" <?php echo ($currentUser['default_currency'] == 'UGX') ? 'selected' : ''; ?>>Ugandan Shilling (UGX)</option>
                            <option value="USD" <?php echo ($currentUser['default_currency'] == 'USD') ? 'selected' : ''; ?>>US Dollar (USD)</option>
                            <option value="EUR" <?php echo ($currentUser['default_currency'] == 'EUR') ? 'selected' : ''; ?>>Euro (EUR)</option>
                            <option value="GBP" <?php echo ($currentUser['default_currency'] == 'GBP') ? 'selected' : ''; ?>>British Pound (GBP)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="timezone" class="form-label">Timezone</label>
                        <select id="timezone" name="timezone" class="form-control">
                            <option value="Africa/Kampala" <?php echo ($currentUser['timezone'] == 'Africa/Kampala') ? 'selected' : ''; ?>>Africa/Kampala (EAT)</option>
                            <option value="UTC" <?php echo ($currentUser['timezone'] == 'UTC') ? 'selected' : ''; ?>>UTC</option>
                            <option value="America/New_York" <?php echo ($currentUser['timezone'] == 'America/New_York') ? 'selected' : ''; ?>>America/New York (EST)</option>
                            <option value="Europe/London" <?php echo ($currentUser['timezone'] == 'Europe/London') ? 'selected' : ''; ?>>Europe/London (GMT)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="language" class="form-label">Language</label>
                        <select id="language" name="language" class="form-control">
                            <option value="en" <?php echo ($currentUser['language'] == 'en') ? 'selected' : ''; ?>>English</option>
                            <option value="sw" <?php echo ($currentUser['language'] == 'sw') ? 'selected' : ''; ?>>Swahili</option>
                            <option value="fr" <?php echo ($currentUser['language'] == 'fr') ? 'selected' : ''; ?>>French</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-label">Auto-lock Account</div>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <label class="switch">
                                <input type="checkbox" name="auto_lock_enabled" value="1" <?php echo $currentUser['auto_lock_enabled'] ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                            <span><?php echo $currentUser['auto_lock_enabled'] ? 'Enabled' : 'Disabled'; ?></span>
                        </div>
                        <span class="form-text">Automatically lock account after 30 minutes of inactivity</span>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Preferences
                    </button>
                </form>
            </div>
            
            <!-- Danger Zone -->
            <div class="settings-card" style="border-left: 4px solid var(--danger-color);">
                <div class="settings-header">
                    <h2 class="settings-title" style="color: var(--danger-color);">Danger Zone</h2>
                </div>
                
                <div class="form-group">
                    <div class="form-label">Delete Account</div>
                    <p style="margin-bottom: 1rem; font-size: 0.9rem;">
                        Permanently delete your account and all associated data. This action cannot be undone.
                    </p>
                    <button class="btn btn-outline" id="deleteAccountBtn" style="border-color: var(--danger-color); color: var(--danger-color);">
                        <i class="fas fa-trash-alt"></i> Delete Account
                    </button>
                </div>
                
                <div class="form-group">
                    <div class="form-label">Export Data</div>
                    <p style="margin-bottom: 1rem; font-size: 0.9rem;">
                        Download a copy of all your data including transactions, invoices, and audit logs.
                    </p>
                    <button class="btn btn-outline" id="exportDataBtn">
                        <i class="fas fa-file-export"></i> Export Data
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Navigation menu active state
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname.split('/').pop();
            document.querySelectorAll('.nav-item').forEach(item => {
                if (item.getAttribute('href') === currentPath) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });
        });

        // Notification click handler
        document.querySelector('.notification-icon').addEventListener('click', function() {
            alert('You have 3 new notifications'); // Replace with custom modal later
            this.querySelector('.notification-badge').style.display = 'none';
        });

        // Avatar upload simulation
        document.getElementById('changeAvatarBtn').addEventListener('click', function(e) {
            e.preventDefault();
            alert('Avatar upload dialog would open here'); // Replace with custom modal later
        });

        // Password change form toggle
        document.getElementById('changePasswordBtn').addEventListener('click', function() {
            const form = document.getElementById('passwordChangeForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        });

        document.getElementById('cancelPasswordChange').addEventListener('click', function() {
            document.getElementById('passwordChangeForm').style.display = 'none';
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_new_password').value = '';
        });

        // Logout device simulation
        document.querySelectorAll('.logout-device-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const device = this.dataset.device;
                if (confirm(`Are you sure you want to log out from ${device}?`)) { // Replace with custom modal later
                    alert(`Logged out from ${device}`); // Replace with custom modal later
                    // In a real app, send AJAX request to invalidate device session
                }
            });
        });

        // Delete account confirmation
        document.getElementById('deleteAccountBtn').addEventListener('click', function() {
            if (confirm('Are you absolutely sure you want to delete your account? This cannot be undone.')) { // Replace with custom modal later
                // Submit form for account deletion
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'settings.php';
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'action';
                input.value = 'delete_account';
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        });

        // Export data simulation
        document.getElementById('exportDataBtn').addEventListener('click', function() {
            alert('Preparing your data export. You will receive an email when it\'s ready for download.'); // Replace with custom modal later
            // In a real app, send AJAX request to trigger data export
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'settings.php';
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'action';
            input.value = 'export_data';
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        });
    </script>
</body>
</html>
