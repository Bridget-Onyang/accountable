<?php
/**
 * Audit-Trail.php
 *
 * This file provides a comprehensive interface for viewing and managing audit trail entries.
 * It includes functionality for displaying a list of activities with filtering, searching, and pagination.
 * All database interactions for audit trail entries are encapsulated within this single file.
 */

session_start();

// Check if the user is logged in. If not, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection and other necessary classes
require_once 'db_connect.php'; // Provides $pdo connection
require_once 'User.php';       // For fetching current user details

// --- AuditTrail Class Definition ---
/**
 * AuditTrail class encapsulates database operations related to the 'AuditTrail' table.
 */
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

    /**
     * Formats a given timestamp into a human-readable "time ago" string.
     * This method avoids the deprecated DateInterval::$w property.
     *
     * @param string $timestamp The timestamp to format (e.g., 'YYYY-MM-DD HH:MM:SS').
     * @return string The formatted time ago string.
     */
    public function formatTimeAgo($timestamp) {
        try {
            $dateTime = new DateTime($timestamp);
            $now = new DateTime();
            $interval = $now->diff($dateTime);

            if ($interval->y > 0) {
                return $interval->y . ' year' . ($interval->y > 1 ? 's' : '') . ' ago';
            } elseif ($interval->m > 0) {
                return $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ' ago';
            } elseif ($interval->d >= 7) {
                // Calculate weeks from total days to avoid deprecated $w
                $weeks = floor($interval->d / 7);
                return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
            } elseif ($interval->d > 0) {
                return $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
            } elseif ($interval->h > 0) {
                return $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
            } elseif ($interval->i > 0) {
                return $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
            } else {
                return 'Just now';
            }
        } catch (Exception $e) {
            error_log("Error formatting time ago: " . $e->getMessage());
            return $timestamp; // Return original timestamp on error
        }
    }
}
// --- End AuditTrail Class Definition ---

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

// Pagination variables
$activities_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $activities_per_page;

// Filter and search terms
$search_term = trim($_GET['search'] ?? '');
$type_filter = trim($_GET['type'] ?? 'All Activities');
$status_filter = trim($_GET['status'] ?? 'All Status');

// Fetch audit trail entries based on filters and pagination
$auditData = $auditTrailHandler->getAuditTrailEntries(
    $userId,
    $activities_per_page,
    $offset,
    $search_term,
    $type_filter,
    $status_filter
);
$auditEntries = $auditData['entries'];
$total_activities = $auditData['total'];
$total_pages = ceil($total_activities / $activities_per_page);

// Handle Export Logs (simulation)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'export_logs') {
    // In a real application, this would generate a CSV or other file
    // and prompt the user for download or email it.
    $successMessage = "Preparing audit trail export... This would generate a CSV file in a real application.";
    $auditTrailHandler->logActivity($userId, 'Audit Trail Export', "User requested audit trail export.", 'System Events', 'Success');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Trail | AccountAble</title>
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

        /* Audit Trail Content */
        .audit-trail-container {
            background-color: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .audit-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .audit-header h2 {
            font-size: 1.4rem;
            color: var(--dark-color);
        }

        .audit-filter-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .search-bar {
            flex-grow: 1;
        }

        .search-bar input {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
            color: var(--text-dark);
            transition: border-color 0.3s ease;
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-group label {
            font-size: 0.9rem;
            color: var(--gray-color);
        }

        .filter-group select {
            padding: 0.6rem 0.8rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
            color: var(--text-dark);
            transition: border-color 0.3s ease;
        }

        .filter-group select:focus {
            outline: none;
            border-color: var(--primary-color);
        }

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

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--light-color);
            color: var(--gray-color);
        }

        .btn-outline:hover {
            background-color: var(--light-color);
        }

        /* Audit List */
        .audit-list {
            list-style: none;
            padding: 0;
        }

        .audit-item {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
            background-color: #fcfcfc;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .audit-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-sm);
        }

        .audit-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .audit-item-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--dark-color);
        }

        .audit-item-time {
            font-size: 0.8rem;
            color: var(--gray-color);
        }

        .audit-item-desc {
            font-size: 0.9rem;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .audit-item-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            font-size: 0.8rem;
            color: var(--gray-color);
        }

        .audit-item-meta span {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .audit-item-meta i {
            color: var(--primary-color);
        }

        .status-badge {
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: capitalize;
        }

        .status-success {
            background-color: #e8f5e9;
            color: var(--success-color);
        }

        .status-failed {
            background-color: #ffebee;
            color: var(--danger-color);
        }

        .status-pending {
            background-color: #fff8e1;
            color: var(--warning-color);
        }

        .status-verified {
            background-color: #e3f2fd;
            color: #2196f3; /* Blue for verified */
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 1.5rem;
            gap: 0.5rem;
        }

        .page-btn {
            background-color: white;
            border: 1px solid #ddd;
            padding: 0.6rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            color: var(--text-dark);
            transition: var(--transition);
        }

        .page-btn:hover:not(.active) {
            background-color: #f0f0f0;
        }

        .page-btn.active {
            background-color: var(--primary-color);
            color: var(--text-light);
            border-color: var(--primary-color);
            cursor: default;
        }

        .page-btn i {
            font-size: 0.8rem;
        }

        /* Message Box */
        .message-box {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 0.95rem;
        }

        .message-box.success {
            background-color: #e8f5e9;
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }

        .message-box.error {
            background-color: #ffebee;
            color: var(--danger-color);
            border: 1px solid var(--danger-color);
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

            .audit-filter-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                width: 100%;
            }

            .search-bar input, .filter-group select {
                width: 100%;
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
            <a href="transactions.php" class="nav-item">
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
            <a href="Audit-Trail.php" class="nav-item active">
                <i class="fas fa-shield-alt"></i>
                <span>Audit Trail</span>
            </a>
            <a href="Verification.php" class="nav-item">
                <i class="fas fa-fingerprint"></i>
                <span>Verification</span>
            </a>

            <div class="menu-title">Account</div>
            <a href="settings.php" class="nav-item">
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
                <h1>Audit Trail</h1>
                <p>Monitor all system activities and user actions</p>
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
            <div class="message-box error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>
        <?php if (!empty($successMessage)): ?>
            <div class="message-box success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>

        <!-- Audit Trail Content -->
        <div class="audit-trail-container">
            <div class="audit-header">
                <h2>Activity Log</h2>
                <form action="Audit-Trail.php" method="POST">
                    <input type="hidden" name="action" value="export_logs">
                    <button type="submit" class="btn btn-outline">
                        <i class="fas fa-download"></i> Export Logs
                    </button>
                </form>
            </div>

            <!-- Filter Bar -->
            <div class="audit-filter-bar">
                <form action="Audit-Trail.php" method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; width: 100%;">
                    <div class="search-bar">
                        <input type="text" name="search" placeholder="Search activity..." value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="type-filter">Type:</label>
                        <select id="type-filter" name="type" onchange="this.form.submit()">
                            <option value="All Activities" <?php echo ($type_filter == 'All Activities') ? 'selected' : ''; ?>>All Activities</option>
                            <option value="Transactions" <?php echo ($type_filter == 'Transactions') ? 'selected' : ''; ?>>Transactions</option>
                            <option value="User Actions" <?php echo ($type_filter == 'User Actions') ? 'selected' : ''; ?>>User Actions</option>
                            <option value="System Events" <?php echo ($type_filter == 'System Events') ? 'selected' : ''; ?>>System Events</option>
                            <option value="Clients" <?php echo ($type_filter == 'Clients') ? 'selected' : ''; ?>>Clients</option>
                            <option value="Invoices" <?php echo ($type_filter == 'Invoices') ? 'selected' : ''; ?>>Invoices</option>
                            <option value="Security" <?php echo ($type_filter == 'Security') ? 'selected' : ''; ?>>Security</option>
                            <option value="Verification" <?php echo ($type_filter == 'Verification') ? 'selected' : ''; ?>>Verification</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="status-filter">Status:</label>
                        <select id="status-filter" name="status" onchange="this.form.submit()">
                            <option value="All Status" <?php echo ($status_filter == 'All Status') ? 'selected' : ''; ?>>All Status</option>
                            <option value="Success" <?php echo ($status_filter == 'Success') ? 'selected' : ''; ?>>Success</option>
                            <option value="Failed" <?php echo ($status_filter == 'Failed') ? 'selected' : ''; ?>>Failed</option>
                            <option value="Pending" <?php echo ($status_filter == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Verified" <?php echo ($status_filter == 'Verified') ? 'selected' : ''; ?>>Verified</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-outline">Apply Filters</button>
                </form>
            </div>

            <ul class="audit-list">
                <?php if (!empty($auditEntries)): ?>
                    <?php foreach ($auditEntries as $entry): ?>
                        <li class="audit-item">
                            <div class="audit-item-header">
                                <div class="audit-item-title"><?php echo htmlspecialchars($entry['activity_title']); ?></div>
                                <div class="audit-item-time"><?php echo htmlspecialchars($auditTrailHandler->formatTimeAgo($entry['timestamp'])); ?></div>
                            </div>
                            <div class="audit-item-desc">
                                <?php echo htmlspecialchars($entry['activity_description']); ?>
                            </div>
                            <div class="audit-item-meta">
                                <span><i class="fas fa-tag"></i> Type: <?php echo htmlspecialchars($entry['activity_type']); ?></span>
                                <span><i class="fas fa-user"></i> User: <?php echo htmlspecialchars($entry['user_name'] ?? 'System'); ?></span>
                                <span>
                                    <i class="fas fa-info-circle"></i> Status:
                                    <span class="status-badge status-<?php echo strtolower(htmlspecialchars($entry['activity_status'])); ?>">
                                        <?php echo htmlspecialchars($entry['activity_status']); ?>
                                    </span>
                                </span>
                                <?php if (!empty($entry['blockchain_hash'])): ?>
                                    <span><i class="fas fa-link"></i> Hash: <?php echo htmlspecialchars(substr($entry['blockchain_hash'], 0, 10)) . '...'; ?></span>
                                <?php endif; ?>
                                <?php if (!empty($entry['ip_address'])): ?>
                                    <span><i class="fas fa-globe"></i> IP: <?php echo htmlspecialchars($entry['ip_address']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($entry['device_info'])): ?>
                                    <span><i class="fas fa-mobile-alt"></i> Device: <?php echo htmlspecialchars($entry['device_info']); ?></span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li style="text-align: center; padding: 20px; color: var(--gray-color);">No audit trail entries found matching your criteria.</li>
                <?php endif; ?>
            </ul>

            <!-- Pagination -->
            <div class="pagination">
                <?php if ($total_pages > 1): ?>
                    <a href="?page=<?php echo max(1, $current_page - 1); ?>&search=<?php echo urlencode($search_term); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>" class="page-btn <?php echo $current_page == 1 ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_term); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>" class="page-btn <?php echo $i == $current_page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    <a href="?page=<?php echo min($total_pages, $current_page + 1); ?>&search=<?php echo urlencode($search_term); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>" class="page-btn <?php echo $current_page == $total_pages ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
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

            // Show success/error messages briefly
            const messageBox = document.querySelector('.message-box');
            if (messageBox) {
                setTimeout(() => {
                    messageBox.style.display = 'none';
                }, 5000); // Hide after 5 seconds
            }
        });

        // Notification click handler
        document.querySelector('.notification-icon').addEventListener('click', function() {
            alert('You have 3 new notifications'); // Replace with custom modal later
            this.querySelector('.notification-badge').style.display = 'none';
        });

        // Search functionality (client-side for instant filtering, but PHP handles server-side search/filters)
        // Note: For a more robust client-side search with server-side filtering,
        // you might debounce this input and trigger a form submit or AJAX call.
        // Currently, the form's GET method handles filtering on submission.
        // The below JS provides immediate visual filtering but won't persist on page reload.
        const searchInput = document.querySelector('.search-bar input');
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const items = document.querySelectorAll('.audit-item');
            
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
