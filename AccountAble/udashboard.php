<?php
/**
 * Udashboard.php
 *
 * This file provides the main user dashboard, displaying an overview of financial data,
 * recent blockchain transactions, and a blockchain audit trail. It integrates with
 * the database to fetch dynamic content and uses the AuditTrail class for timestamp formatting.
 */

session_start();

// Check if the user is logged in. If not, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection and necessary classes
require_once 'db_connect.php';     // Provides $pdo connection
require_once 'User.php';          // For fetching current user details
// For fetching transaction details (includes Transaction class)

class Transaction {
    private $pdo; // PDO database connection object

    /**
     * Constructor
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Adds a new transaction to the database.
     *
     * @param int $userId The ID of the user creating the transaction.
     * @param string $transactionDate The date of the transaction (YYYY-MM-DD).
     * @param string $description A description of the transaction.
     * @param string $category The category of the transaction.
     * @param float $amount The amount of the transaction.
     * @param string $currency The currency of the transaction.
     * @param string $transactionType The type of transaction ('income', 'expense', 'transfer').
     * @param int|null $clientId Optional: The ID of the client associated with the transaction.
     * @param string|null $blockchainHash Optional: The blockchain hash if already verified.
     * @param string $blockchainStatus The status of the blockchain verification ('verified', 'pending', 'failed').
     * @return bool True on successful insertion, false otherwise.
     */
    public function addTransaction(
        $userId,
        $transactionDate,
        $description,
        $category,
        $amount,
        $currency,
        $transactionType,
        $clientId = null,
        $blockchainHash = null,
        $blockchainStatus = 'pending' // Default to pending if not provided
    ) {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO Transactions (user_id, client_id, transaction_date, description, category, amount, currency, transaction_type, blockchain_status, blockchain_hash)
                VALUES (:user_id, :client_id, :transaction_date, :description, :category, :amount, :currency, :transaction_type, :blockchain_status, :blockchain_hash)"
            );

            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            // Handle client_id which can be null
            if ($clientId === 0 || $clientId === null) { // If '0' is used for 'No Client'
                $stmt->bindValue(':client_id', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindParam(':client_id', $clientId, PDO::PARAM_INT);
            }

            $stmt->bindParam(':transaction_date', $transactionDate);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':currency', $currency);
            $stmt->bindParam(':transaction_type', $transactionType);
            $stmt->bindParam(':blockchain_status', $blockchainStatus);
            $stmt->bindParam(':blockchain_hash', $blockchainHash);

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log("Error adding transaction: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches transactions for a specific user, with filtering and pagination.
     *
     * @param int $userId The ID of the user.
     * @param int $limit The maximum number of transactions to fetch.
     * @param int $offset The offset for pagination.
     * @param string $searchTerm Optional: A term to search within description or category.
     * @param string $statusFilter Optional: Filter by transaction status.
     * @param string $typeFilter Optional: Filter by transaction type.
     * @param int $clientFilter Optional: Filter by client ID.
     * @return array An array containing 'entries' (the fetched records) and 'total' (total count).
     */
    public function getTransactions(
        $userId,
        $limit = 10,
        $offset = 0,
        $searchTerm = '',
        $statusFilter = '',
        $typeFilter = '',
        $clientFilter = 0
    ) {
        $entries = [];
        $total = 0;

        try {
            $sql = "SELECT t.*, c.client_name
                    FROM Transactions t
                    LEFT JOIN Clients c ON t.client_id = c.client_id
                    WHERE t.user_id = :user_id";
            $params = [':user_id' => $userId];

            // Apply search filter
            if (!empty($searchTerm)) {
                $sql .= " AND (t.description LIKE :search_term OR t.category LIKE :search_term OR c.client_name LIKE :search_term)";
                $params[':search_term'] = '%' . $searchTerm . '%';
            }

            // Apply status filter
            if (!empty($statusFilter)) {
                $sql .= " AND t.blockchain_status = :status_filter";
                $params[':status_filter'] = $statusFilter;
            }

            // Apply type filter
            if (!empty($typeFilter)) {
                $sql .= " AND t.transaction_type = :type_filter";
                $params[':type_filter'] = $typeFilter;
            }

            // Apply client filter
            if ($clientFilter > 0) {
                $sql .= " AND t.client_id = :client_filter";
                $params[':client_filter'] = $clientFilter;
            }

            // Get total count first
            $countSql = "SELECT COUNT(*) FROM (" . $sql . ") AS subquery";
            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();

            // Fetch actual entries with limit and offset
            $sql .= " ORDER BY t.transaction_date DESC, t.created_at DESC LIMIT :limit OFFSET :offset";
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $entries = $stmt->fetchAll();

        } catch (PDOException $e) {
            error_log("Error fetching transactions: " . $e->getMessage());
        }

        return ['entries' => $entries, 'total' => $total];
    }

    /**
     * Gets a single transaction by its ID and user ID.
     *
     * @param int $transactionId The ID of the transaction.
     * @param int $userId The ID of the user who owns the transaction.
     * @return array|false The transaction record if found, otherwise false.
     */
    public function getTransactionById($transactionId, $userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM Transactions WHERE transaction_id = :transaction_id AND user_id = :user_id");
            $stmt->bindParam(':transaction_id', $transactionId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting transaction by ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes a transaction.
     *
     * @param int $transactionId The ID of the transaction to delete.
     * @param int $userId The ID of the user who owns the transaction.
     * @return bool True on success, false on failure.
     */
    public function deleteTransaction($transactionId, $userId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM Transactions WHERE transaction_id = :transaction_id AND user_id = :user_id");
            $stmt->bindParam(':transaction_id', $transactionId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error deleting transaction: " . $e->getMessage());
            return false;
        }
    }
}
// --- End Transaction Class Definition ---


// --- AuditTrail Class Definition (moved here and corrected for DateInterval::$w) ---
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
        $offset = 0, // Added default value for offset
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
$transactionHandler = new Transaction($pdo); // Assuming Transaction class is available via Transactions.php

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

// --- Fetch Data for Dashboard Sections ---

// Fetch recent transactions for the table
$recentTransactionsData = $transactionHandler->getTransactions($userId, 5); // Get latest 5 transactions
$recentTransactions = $recentTransactionsData['entries'];

// Fetch recent audit trail entries for the audit list
// Corrected the call to getAuditTrailEntries to match its signature
$recentAuditTrailData = $auditTrailHandler->getAuditTrailEntries($userId, 3, 0); // Get latest 3 audit entries, starting from offset 0
$recentAuditTrail = $recentAuditTrailData['entries'];

// --- Placeholder for Stats Cards (replace with actual calculations from DB) ---
// In a real application, these would be calculated from your Transactions table
// For now, they remain static or are simple counts.
$totalTransactionsCount = $transactionHandler->getTransactions($userId, 1, 0, '', '', '', 0)['total']; // Get total count
$verifiedTransactionsCount = $transactionHandler->getTransactions($userId, 1, 0, '', 'verified', '', 0)['total'];
$integrityAlertsCount = 0; // Placeholder for actual alerts
$accountabilityScore = 95; // Placeholder for a calculated score

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AccountAble | Blockchain Accountability Dashboard</title>
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

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background-color: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .stat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-3px);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-title {
            font-size: 0.95rem;
            color: var(--gray-color);
            font-weight: 500;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.transactions {
            background-color: rgba(46, 125, 50, 0.1);
            color: var(--primary-color);
        }

        .stat-icon.revenue {
            background-color: rgba(139, 195, 74, 0.1);
            color: var(--accent-color);
        }

        .stat-icon.alerts {
            background-color: rgba(255, 152, 0, 0.1);
            color: var(--warning-color);
        }

        .stat-icon.verified {
            background-color: rgba(67, 160, 71, 0.1);
            color: var(--success-color);
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-change {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            gap: 0.5rem;
        }

        .stat-change.positive {
            color: var(--success-color);
        }

        .stat-change.negative {
            color: var(--danger-color);
        }

        /* Main Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }

        .card {
            background-color: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .card-actions {
            display: flex;
            gap: 0.8rem;
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
            border: 1px solid var(--light-gray);
            color: var(--gray-color);
        }

        .btn-outline:hover {
            background-color: var(--light-gray);
        }

        /* Transaction Table */
        .transaction-table {
            width: 100%;
            border-collapse: collapse;
        }

        .transaction-table th {
            text-align: left;
            padding: 0.75rem;
            font-size: 0.75rem;
            color: var(--gray-color);
            font-weight: 500;
            text-transform: uppercase;
            border-bottom: 1px solid var(--light-gray);
        }

        .transaction-table td {
            padding: 1rem 0.75rem;
            border-bottom: 1px solid var(--light-gray);
            font-size: 0.875rem;
        }

        .transaction-table tr:last-child td {
            border-bottom: none;
        }

        .transaction-status {
            display: inline-block;
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-verified {
            background-color: #e8f5e9;
            color: var(--success-color);
        }

        .status-pending {
            background-color: #fff8e1;
            color: var(--warning-color);
        }

        .status-failed {
            background-color: #ffebee;
            color: var(--danger-color);
        }

        /* Audit Trail List */
        .audit-list {
            list-style: none;
        }

        .audit-item {
            padding: 1rem 0;
            border-bottom: 1px solid var(--light-gray);
        }

        .audit-item:last-child {
            border-bottom: none;
        }

        .audit-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .audit-title {
            font-weight: 500;
        }

        .audit-time {
            font-size: 0.75rem;
            color: var(--gray-color);
        }

        .audit-desc {
            font-size: 0.875rem;
            color: var(--gray-color);
        }

        .audit-hash {
            font-family: monospace;
            font-size: 0.75rem;
            color: var(--gray-color);
            background-color: var(--light-gray);
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            margin-top: 0.5rem;
            display: inline-block;
            word-break: break-all;
        }

        /* Blockchain Verification */
        .verification-card {
            background-color: #e8f5e9;
            padding: 1.5rem;
            border-radius: 10px;
            margin-top: 1.5rem;
        }

        .verification-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .verification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .verification-title {
            font-weight: 600;
        }

        .verification-desc {
            font-size: 0.875rem;
            color: var(--gray-color);
            margin-bottom: 1rem;
        }

        .hash-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-family: monospace;
            font-size: 0.875rem;
            margin-bottom: 1rem;
            transition: var(--transition);
        }

        .hash-input:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
        }

        /* Responsive Styles */
        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

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
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
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
            <a href="udashboard.php" class="nav-item active">
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
                <h1>Blockchain Accountability Dashboard</h1>
                <p>Track and verify all your transactions on the blockchain</p>
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

        <!-- Stats Cards -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Total Transactions</div>
                    <div class="stat-icon transactions">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                </div>
                <div class="stat-value" id="totalTransactionsValue"><?php echo htmlspecialchars($totalTransactionsCount); ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i> 12% from last month
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Blockchain Verified</div>
                    <div class="stat-icon verified">
                        <i class="fas fa-link"></i>
                    </div>
                </div>
                <div class="stat-value" id="verifiedTransactionsValue"><?php echo htmlspecialchars($verifiedTransactionsCount); ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i> 3% from last month
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Integrity Alerts</div>
                    <div class="stat-icon alerts">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
                <div class="stat-value" id="integrityAlertsValue"><?php echo htmlspecialchars($integrityAlertsCount); ?></div>
                <div class="stat-change negative">
                    <i class="fas fa-arrow-down"></i> 2 from last month
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Accountability Score</div>
                    <div class="stat-icon verified">
                        <i class="fas fa-star"></i>
                    </div>
                </div>
                <div class="stat-value" id="accountabilityScoreValue"><?php echo htmlspecialchars($accountabilityScore); ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i> 5 points
                </div>
            </div>
        </section>

        <!-- Main Content Grid -->
        <section class="content-grid">
            <!-- Transactions Section -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Recent Blockchain Transactions</h2>
                    <div class="card-actions">
                        <a href="Transactions.php" class="btn btn-outline">
                            <i class="fas fa-filter"></i> Filter
                        </a>
                        <a href="Transactions.php#addTransactionForm" class="btn btn-primary">
                            <i class="fas fa-plus"></i> New Transaction
                        </a>
                    </div>
                </div>
                
                <table class="transaction-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Blockchain Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentTransactions)): ?>
                            <?php foreach ($recentTransactions as $transaction): ?>
                                <tr>
                                    <td>#TRX-<?php echo htmlspecialchars($transaction['transaction_id']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['transaction_date']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                    <td>UGX <?php echo htmlspecialchars(number_format($transaction['amount'], 2)); ?></td>
                                    <td>
                                        <span class="transaction-status status-<?php echo htmlspecialchars($transaction['blockchain_status']); ?>">
                                            <?php echo htmlspecialchars(ucfirst($transaction['blockchain_status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="action-btn view-transaction-btn" data-id="<?php echo htmlspecialchars($transaction['transaction_id']); ?>" title="View Transaction">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--gray-color);">No recent transactions found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Right Column -->
            <div class="right-column">
                <!-- Audit Trail -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Blockchain Audit Trail</h2>
                        <div class="card-actions">
                            <a href="Audit-Trail.php" class="btn btn-outline">
                                <i class="fas fa-list"></i> View All
                            </a>
                        </div>
                    </div>
                    
                    <ul class="audit-list">
                        <?php if (!empty($recentAuditTrail)): ?>
                            <?php foreach ($recentAuditTrail as $entry): ?>
                                <li class="audit-item">
                                    <div class="audit-header">
                                        <div class="audit-title"><?php echo htmlspecialchars($entry['activity_title']); ?></div>
                                        <div class="audit-time"><?php echo htmlspecialchars($auditTrailHandler->formatTimeAgo($entry['timestamp'])); ?></div>
                                    </div>
                                    <div class="audit-desc">
                                        <?php echo htmlspecialchars($entry['activity_description']); ?>
                                    </div>
                                    <?php if (!empty($entry['blockchain_hash'])): ?>
                                        <div class="audit-hash">
                                            <?php echo htmlspecialchars(substr($entry['blockchain_hash'], 0, 10)) . '...' . htmlspecialchars(substr($entry['blockchain_hash'], -4)); ?>
                                        </div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li style="text-align: center; padding: 10px; color: var(--gray-color);">No recent audit entries.</li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <!-- Blockchain Verification -->
                <div class="verification-card">
                    <div class="verification-header">
                        <div class="verification-icon">
                            <i class="fas fa-link"></i>
                        </div>
                        <div class="verification-title">Verify on Blockchain</div>
                    </div>
                    <div class="verification-desc">
                        Enter a transaction hash to verify its authenticity and traceability on the blockchain.
                    </div>
                    <input
                        type="text"
                        class="hash-input"
                        id="verifyHashInput"
                        placeholder="Enter transaction hash (0x...)"
                    />
                    <button class="btn btn-primary" id="verifyBlockchainBtn" style="width: 100%">
                        <i class="fas fa-search"></i> Verify Transaction
                    </button>
                </div>
            </div>
        </section>
    </main>

    <script>
        // Navigation menu active state
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname.split('/').pop();
            document.querySelectorAll('.nav-item').forEach(item => {
                // Adjust for .php extension in hrefs
                const itemHref = item.getAttribute('href').split('/').pop();
                if (itemHref === currentPath) {
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

        // Blockchain verification functionality (AJAX call to Verification.php)
        document.getElementById('verifyBlockchainBtn').addEventListener('click', function() {
            const hashInput = document.getElementById('verifyHashInput');
            const txHash = hashInput.value.trim();

            if (txHash === '') {
                alert('Please enter a transaction hash to verify.');
                return;
            }
            
            // Show loading state
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
            this.disabled = true;

            fetch('Verification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=verify_hash&tx_hash=${txHash}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Verification successful!\nTransaction: ${data.transaction.description}\nAmount: UGX ${parseFloat(data.transaction.amount).toLocaleString('en-UG', {minimumFractionDigits: 2, maximumFractionDigits: 2})}\nBlock: ${data.transaction.block}`);
                    // Optionally, refresh a section or the page to show the new audit trail entry
                    window.location.reload(); 
                } else {
                    alert(`Verification failed: ${data.message}`);
                }
            })
            .catch(error => {
                console.error('Error during verification:', error);
                alert('An error occurred during verification. Please try again.');
            })
            .finally(() => {
                this.innerHTML = '<i class="fas fa-search"></i> Verify Transaction';
                this.disabled = false;
            });
        });

        // Placeholder for stat card animations (if needed, these would animate numbers)
        // const animateValue = (id, start, end, duration) => {
        //     let range = end - start;
        //     let current = start;
        //     let increment = end > start ? 1 : -1;
        //     let stepTime = Math.abs(Math.floor(duration / range));
        //     let obj = document.getElementById(id);
        //     let timer = setInterval(() => {
        //         current += increment;
        //         obj.textContent = current.toLocaleString();
        //         if (current == end) {
        //             clearInterval(timer);
        //         }
        //     }, stepTime);
        // };

        // // Example of how to trigger animation (if implemented)
        // // animateValue("totalTransactionsValue", 0, <?php echo $totalTransactionsCount; ?>, 2000);
    </script>
</body>
</html>
