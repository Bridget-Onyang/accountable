<?php
/**
 * Verification.php
 *
 * This file provides an interface for verifying transactions on a simulated blockchain
 * and displaying recent blockchain activity. It integrates with a database to fetch
 * transaction details and audit trail entries.
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

// --- AuditTrail Class Definition (copied from Audit-Trail.php with formatTimeAgo) ---
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


// --- Transaction Class Definition (minimal, for fetching transaction details) ---
/**
 * Transaction class (minimal, for fetching transaction details for verification)
 */
class Transaction {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Fetches a transaction by its blockchain hash.
     * @param string $blockchainHash The blockchain hash to search for.
     * @param int $userId The ID of the user who owns the transaction.
     * @return array|false The transaction record if found, otherwise false.
     */
    public function getTransactionByBlockchainHash($blockchainHash, $userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM Transactions WHERE blockchain_hash = :blockchain_hash AND user_id = :user_id");
            $stmt->bindParam(':blockchain_hash', $blockchainHash);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching transaction by hash: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Updates the blockchain status and hash of a transaction.
     * @param int $transactionId The ID of the transaction to update.
     * @param int $userId The ID of the user who owns the transaction.
     * @param string $status The new blockchain status ('verified', 'pending', 'failed').
     * @param string|null $hash The new blockchain hash (can be null if failed/pending).
     * @return bool True on success, false on failure.
     */
    public function updateBlockchainStatus($transactionId, $userId, $status, $hash = null) {
        try {
            $stmt = $this->pdo->prepare("UPDATE Transactions SET blockchain_status = :status, blockchain_hash = :blockchain_hash WHERE transaction_id = :transaction_id AND user_id = :user_id");
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':blockchain_hash', $hash);
            $stmt->bindParam(':transaction_id', $transactionId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating transaction blockchain status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches recent transactions with blockchain hashes for the explorer.
     * @param int $userId The ID of the user.
     * @param int $limit The maximum number of transactions to fetch.
     * @return array An array of recent transaction records.
     */
    public function getRecentBlockchainTransactions($userId, $limit = 5) {
        try {
            $stmt = $this->pdo->prepare("SELECT transaction_id, description, amount, transaction_date, blockchain_hash FROM Transactions WHERE user_id = :user_id AND blockchain_hash IS NOT NULL ORDER BY transaction_date DESC, created_at DESC LIMIT :limit");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching recent blockchain transactions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetches the total count of transactions for a user.
     * This is a simplified version for dashboard stats.
     * @param int $userId The ID of the user.
     * @return int The total number of transactions.
     */
    public function getTotalTransactionsCount($userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM Transactions WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error fetching total transactions count: " . $e->getMessage());
            return 0;
        }
    }
}
// --- End Transaction Class Definition ---

// Initialize handlers
$userHandler = new User($pdo);
$transactionHandler = new Transaction($pdo);
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
$verificationResult = null; // To store the result of a hash verification

// Handle AJAX verification request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'verify_hash') {
    $txHash = trim($_POST['tx_hash'] ?? '');

    if (empty($txHash)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a transaction hash.']);
        exit();
    }

    // Attempt to find the transaction by hash
    $transaction = $transactionHandler->getTransactionByBlockchainHash($txHash, $userId);

    if ($transaction) {
        // Simulate blockchain verification success
        // In a real app, you'd interact with a blockchain node here
        $status = 'verified';
        $message = 'Transaction verified on blockchain!';
        $auditTrailHandler->logActivity($userId, 'Blockchain Verification', "Transaction '{$transaction['description']}' (ID: {$transaction['transaction_id']}) verified on blockchain.", 'Verification', 'Success', $txHash);

        echo json_encode([
            'success' => true,
            'status' => $status,
            'message' => $message,
            'transaction' => [
                'transaction_id' => $transaction['transaction_id'],
                'description' => $transaction['description'],
                'amount' => $transaction['amount'],
                'transaction_date' => $transaction['transaction_date'],
                'blockchain_hash' => $transaction['blockchain_hash'],
                'blockchain_status' => $status,
                'block' => '#BLOCK-' . substr(md5($txHash), 0, 7) // Simulated block number
            ]
        ]);
    } else {
        // Simulate blockchain verification failure
        $status = 'failed';
        $message = 'Transaction not found or not yet verified on blockchain.';
        $auditTrailHandler->logActivity($userId, 'Blockchain Verification Failed', "Attempt to verify hash '{$txHash}' failed (not found).", 'Verification', 'Failed', $txHash);

        echo json_encode([
            'success' => false,
            'status' => $status,
            'message' => $message,
            'transaction' => null
        ]);
    }
    exit();
}

// Fetch recent blockchain transactions for the explorer list
$recentBlockchainTransactions = $transactionHandler->getRecentBlockchainTransactions($userId, 10);

// Fetch recent audit trail entries for "Recent Verifications"
$recentVerificationsData = $auditTrailHandler->getAuditTrailEntries(
    $userId,
    5, // Limit to 5 recent verifications
    0,
    '', // No search term
    'Verification', // Filter by activity type 'Verification'
    'Success' // Filter by status 'Success'
);
$recentVerifications = $recentVerificationsData['entries'];


// Fetch total transactions and verifications for stats
$totalTransactionsCount = $transactionHandler->getTotalTransactionsCount($userId);
$totalVerificationsCount = $auditTrailHandler->getAuditTrailEntries($userId, 1, 0, '', 'Verification', 'Success')['total'];

// Simulate current block number (can be made more dynamic if a blockchain simulation is built)
$currentBlockNumber = '#1,245,879'; // Static for now

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Verification | AccountAble</title>
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    />
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
        --shadow-sm: 0 2px 5px rgba(0, 0, 0, 0.1);
        --shadow-md: 0 4px 10px rgba(0, 0, 0, 0.15);
        --shadow-lg: 0 8px 20px rgba(0, 0, 0, 0.2);
        --transition: all 0.3s ease;
      }

      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: "Segoe UI", "Roboto", "Helvetica Neue", sans-serif;
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

      .nav-item:hover,
      .nav-item.active {
        background-color: rgba(255, 255, 255, 0.1);
        color: var(--text-light);
        border-left: 3px solid var(--accent-color);
      }

      .nav-item.active {
        background-color: rgba(255, 255, 255, 0.05);
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

      /* Verification Section */
      .verification-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
      }

      .verification-card {
        background-color: white;
        border-radius: 8px;
        box-shadow: var(--shadow-sm);
        padding: 2rem;
      }

      .verification-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
      }

      .verification-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background-color: rgba(46, 125, 50, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary-color);
        font-size: 1.5rem;
      }

      .verification-title {
        font-size: 1.25rem;
        font-weight: 600;
      }

      .verification-desc {
        color: var(--gray-color);
        margin-bottom: 1.5rem;
      }

      .verification-form {
        margin-bottom: 2rem;
      }

      .form-group {
        margin-bottom: 1.5rem;
      }

      .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
      }

      .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid var(--light-gray);
        border-radius: 6px;
        font-size: 0.9rem;
        transition: var(--transition);
      }

      .form-control:focus {
        outline: none;
        border-color: var(--primary-light);
        box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
      }

      .hash-input {
        font-family: monospace;
      }

      .btn {
        padding: 0.75rem 1.5rem;
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

      .btn-block {
        display: block;
        width: 100%;
      }

      /* Verification Results */
      .verification-results {
        margin-top: 2rem;
      }

      .result-card {
        background-color: #f8fafc;
        border-radius: 8px;
        padding: 1.5rem;
        margin-bottom: 1rem;
      }

      .result-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
      }

      .result-title {
        font-weight: 600;
      }

      .result-status {
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
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

      .result-details {
        font-size: 0.875rem;
      }

      .result-details p {
        margin-bottom: 0.5rem;
      }

      .result-hash {
        font-family: monospace;
        font-size: 0.75rem;
        color: var(--gray-color);
        word-break: break-all;
        background-color: white;
        padding: 0.5rem;
        border-radius: 4px;
        margin-top: 0.5rem;
      }

      /* Recent Verifications */
      .recent-verifications {
        margin-top: 2rem;
      }

      .verification-item {
        display: flex;
        align-items: center;
        padding: 1rem 0;
        border-bottom: 1px solid var(--light-gray);
      }

      .verification-item:last-child {
        border-bottom: none;
      }

      .verification-item-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: rgba(46, 125, 50, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1rem;
        color: var(--primary-color);
      }

      .verification-item-content {
        flex: 1;
      }

      .verification-item-title {
        font-weight: 500;
        margin-bottom: 0.25rem;
      }

      .verification-item-desc {
        font-size: 0.8rem;
        color: var(--gray-color);
      }

      .verification-item-time {
        font-size: 0.75rem;
        color: var(--gray-color);
      }

      /* Blockchain Explorer */
      .blockchain-explorer {
        background-color: white;
        border-radius: 8px;
        box-shadow: var(--shadow-sm);
        padding: 2rem;
        height: 100%;
      }

      .blockchain-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
      }

      .blockchain-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background-color: rgba(139, 195, 74, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--accent-color);
        font-size: 1.5rem;
      }

      .blockchain-title {
        font-size: 1.25rem;
        font-weight: 600;
      }

      .blockchain-desc {
        color: var(--gray-color);
        margin-bottom: 1.5rem;
      }

      .blockchain-stats {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 2rem;
      }

      .stat-item {
        background-color: #f8fafc;
        border-radius: 8px;
        padding: 1rem;
      }

      .stat-label {
        font-size: 0.75rem;
        color: var(--gray-color);
        margin-bottom: 0.5rem;
        text-transform: uppercase;
      }

      .stat-value {
        font-weight: 600;
      }

      .blockchain-btn {
        width: 100%;
        margin-bottom: 1.5rem;
      }

      .blockchain-tx-list {
        max-height: 400px;
        overflow-y: auto;
      }

      .tx-item {
        padding: 1rem 0;
        border-bottom: 1px solid var(--light-gray);
      }

      .tx-item:last-child {
        border-bottom: none;
      }

      .tx-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
      }

      .tx-hash {
        font-family: monospace;
        font-size: 0.8rem;
        color: var(--primary-color);
        text-decoration: none;
      }

      .tx-time {
        font-size: 0.75rem;
        color: var(--gray-color);
      }

      .tx-details {
        font-size: 0.875rem;
        color: var(--gray-color);
      }

      /* Responsive Styles */
      @media (max-width: 1200px) {
        .verification-container {
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

        .blockchain-stats {
          grid-template-columns: 1fr;
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
        <a href="Audit-Trail.php" class="nav-item">
            <i class="fas fa-shield-alt"></i>
            <span>Audit Trail</span>
        </a>
        <a href="Verification.php" class="nav-item active">
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
          <h1>Blockchain Verification</h1>
          <p>Verify the authenticity of your transactions on the blockchain</p>
        </div>
        <div class="user-menu">
          <div class="notification-icon">
            <i class="fas fa-bell"></i>
            <span class="notification-badge">2</span>
          </div>
          <div class="user-profile">
            <img
              src="https://placehold.co/40x40/2e7d32/ffffff?text=<?php echo substr($currentUser['full_name'], 0, 1); ?>"
              alt="User"
              class="user-avatar"
            />
            <div>
              <div class="user-name"><?php echo htmlspecialchars($currentUser['full_name']); ?></div>
              <div class="user-role"><?php echo htmlspecialchars($currentUser['user_role']); ?></div>
            </div>
          </div>
        </div>
      </header>

      <!-- Verification Content -->
      <div class="verification-container">
        <!-- Verification Panel -->
        <div class="verification-card">
          <div class="verification-header">
            <div class="verification-icon">
              <i class="fas fa-search"></i>
            </div>
            <div>
              <h2 class="verification-title">Verify Transaction</h2>
              <p class="verification-desc">
                Enter a transaction hash to verify its authenticity on the
                blockchain
              </p>
            </div>
          </div>

          <div class="verification-form">
            <div class="form-group">
              <label for="tx-hash">Transaction Hash</label>
              <input
                type="text"
                id="tx-hash"
                class="form-control hash-input"
                placeholder="0x..."
              />
            </div>
            <button class="btn btn-primary btn-block" id="verify-btn">
              <i class="fas fa-search"></i> Verify Transaction
            </button>
          </div>

          <!-- Verification Results -->
          <div
            class="verification-results"
            id="verification-results"
            style="display: none"
          >
            <div class="result-card">
              <div class="result-header">
                <div class="result-title">Verification Result</div>
                <div
                  class="result-status"
                  id="verification-status"
                >
                  <!-- Status will be dynamically updated -->
                </div>
              </div>
              <div class="result-details" id="verification-details">
                <p>
                  <strong>Transaction:</strong>
                  <span id="tx-id"></span>
                </p>
                <p>
                  <strong>Description:</strong>
                  <span id="tx-description"></span>
                </p>
                <p>
                  <strong>Amount:</strong>
                  <span id="tx-amount"></span>
                </p>
                <p>
                  <strong>Date:</strong> <span id="tx-date"></span>
                </p>
                <p>
                  <strong>Block:</strong> <span id="tx-block"></span>
                </p>
                <div class="result-hash" id="tx-full-hash">
                  <!-- Full hash will be dynamically updated -->
                </div>
              </div>
            </div>
          </div>

          <!-- Recent Verifications -->
          <div class="recent-verifications">
            <h3 style="margin-bottom: 1rem">Recent Verifications</h3>
            <div id="recent-verifications-list">
                <?php if (!empty($recentVerifications)): ?>
                    <?php foreach ($recentVerifications as $verification): ?>
                        <div class="verification-item">
                            <div class="verification-item-icon">
                                <i class="fas fa-<?php echo ($verification['activity_status'] === 'Success') ? 'check' : 'exclamation'; ?>"></i>
                            </div>
                            <div class="verification-item-content">
                                <div class="verification-item-title"><?php echo htmlspecialchars($verification['activity_title']); ?></div>
                                <div class="verification-item-desc"><?php echo htmlspecialchars($verification['activity_description']); ?></div>
                            </div>
                            <div class="verification-item-time"><?php echo htmlspecialchars($auditTrailHandler->formatTimeAgo($verification['timestamp'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; color: var(--gray-color);">No recent verifications.</p>
                <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Blockchain Explorer -->
        <div class="blockchain-explorer">
          <div class="blockchain-header">
            <div class="blockchain-icon">
              <i class="fas fa-link"></i>
            </div>
            <div>
              <h2 class="blockchain-title">Blockchain Explorer</h2>
              <p class="blockchain-desc">
                View recent transactions on the AccountAble blockchain
              </p>
            </div>
          </div>

          <div class="blockchain-stats">
            <div class="stat-item">
              <div class="stat-label">Current Block</div>
              <div class="stat-value"><?php echo htmlspecialchars($currentBlockNumber); ?></div>
            </div>
            <div class="stat-item">
              <div class="stat-label">Transactions</div>
              <div class="stat-value"><?php echo htmlspecialchars($totalTransactionsCount); ?></div>
            </div>
          </div>

          <button class="btn btn-outline blockchain-btn">
            <i class="fas fa-external-link-alt"></i> View Full Explorer
          </button>

          <h3 style="margin-bottom: 1rem">Recent Transactions</h3>
          <div class="blockchain-tx-list">
            <?php if (!empty($recentBlockchainTransactions)): ?>
                <?php foreach ($recentBlockchainTransactions as $tx): ?>
                    <div class="tx-item">
                        <div class="tx-header">
                            <a href="#" class="tx-hash" data-hash="<?php echo htmlspecialchars($tx['blockchain_hash']); ?>">
                                <?php echo htmlspecialchars(substr($tx['blockchain_hash'], 0, 10)) . '...' . htmlspecialchars(substr($tx['blockchain_hash'], -4)); ?>
                            </a>
                            <div class="tx-time"><?php echo htmlspecialchars($tx['transaction_date']); ?></div>
                        </div>
                        <div class="tx-details">
                            <?php echo htmlspecialchars($tx['description']); ?> - UGX <?php echo htmlspecialchars(number_format($tx['amount'], 2)); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; color: var(--gray-color);">No recent blockchain transactions.</p>
            <?php endif; ?>
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
      document
        .querySelector(".notification-icon")
        .addEventListener("click", function () {
          alert("You have 2 new notifications"); // Replace with custom modal later
          this.querySelector(".notification-badge").style.display = "none";
        });

      // Verification functionality
      document
        .getElementById("verify-btn")
        .addEventListener("click", function () {
          const txHash = document.getElementById("tx-hash").value.trim();
          const resultsDiv = document.getElementById("verification-results");
          const verifyButton = this;

          if (!txHash) {
            alert("Please enter a transaction hash");
            return;
          }

          // Show loading state
          verifyButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
          verifyButton.disabled = true;
          resultsDiv.style.display = "none"; // Hide previous results

          // Make an AJAX call to the PHP backend
          fetch('Verification.php', {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/x-www-form-urlencoded',
              },
              body: `action=verify_hash&tx_hash=${txHash}`
          })
          .then(response => response.json())
          .then(data => {
              // Update results based on data from PHP
              const statusElement = document.getElementById("verification-status");
              const txIdElement = document.getElementById("tx-id");
              const txDescriptionElement = document.getElementById("tx-description");
              const txAmountElement = document.getElementById("tx-amount");
              const txDateElement = document.getElementById("tx-date");
              const txBlockElement = document.getElementById("tx-block");
              const txFullHashElement = document.getElementById("tx-full-hash");
              const recentVerificationsList = document.getElementById("recent-verifications-list");

              // Clear existing recent verifications to rebuild
              recentVerificationsList.innerHTML = '';

              if (data.success) {
                  statusElement.textContent = 'Verified';
                  statusElement.className = 'result-status status-verified';
                  txIdElement.textContent = `#TRX-${data.transaction.transaction_id}`;
                  txDescriptionElement.textContent = data.transaction.description;
                  txAmountElement.textContent = `UGX ${parseFloat(data.transaction.amount).toLocaleString('en-UG', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                  txDateElement.textContent = data.transaction.transaction_date;
                  txBlockElement.textContent = data.transaction.block;
                  txFullHashElement.textContent = data.transaction.blockchain_hash;

                  // Add to recent verifications list dynamically
                  const newItem = document.createElement("div");
                  newItem.className = "verification-item";
                  newItem.innerHTML = `
                      <div class="verification-item-icon">
                          <i class="fas fa-check"></i>
                      </div>
                      <div class="verification-item-content">
                          <div class="verification-item-title">Transaction Verified</div>
                          <div class="verification-item-desc">${data.transaction.description} • UGX ${parseFloat(data.transaction.amount).toLocaleString('en-UG', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                      </div>
                      <div class="verification-item-time">Just now</div>
                  `;
                  recentVerificationsList.insertBefore(newItem, recentVerificationsList.firstChild);

              } else {
                  statusElement.textContent = 'Failed';
                  statusElement.className = 'result-status status-failed';
                  txIdElement.textContent = 'N/A';
                  txDescriptionElement.textContent = 'Transaction not found or not verified.';
                  txAmountElement.textContent = 'N/A';
                  txDateElement.textContent = 'N/A';
                  txBlockElement.textContent = 'N/A';
                  txFullHashElement.textContent = txHash; // Show the entered hash for failed attempts

                  // Add to recent verifications list dynamically (failed)
                  const newItem = document.createElement("div");
                  newItem.className = "verification-item";
                  newItem.innerHTML = `
                      <div class="verification-item-icon">
                          <i class="fas fa-exclamation"></i>
                      </div>
                      <div class="verification-item-content">
                          <div class="verification-item-title">Verification failed</div>
                          <div class="verification-item-desc">Hash: ${txHash}</div>
                      </div>
                      <div class="verification-item-time">Just now</div>
                  `;
                  recentVerificationsList.insertBefore(newItem, recentVerificationsList.firstChild);
              }

              resultsDiv.style.display = "block";
              verifyButton.innerHTML = '<i class="fas fa-search"></i> Verify Transaction';
              verifyButton.disabled = false;
          })
          .catch(error => {
              console.error('Error:', error);
              alert('An error occurred during verification. Please try again.');
              verifyButton.innerHTML = '<i class="fas fa-search"></i> Verify Transaction';
              verifyButton.disabled = false;
              resultsDiv.style.display = "none";
          });
        });

      // View full explorer (placeholder)
      document
        .querySelector(".blockchain-btn")
        .addEventListener("click", function () {
          alert("Opening full blockchain explorer in new window");
        });

      // Click on transaction hash in explorer to populate input
      document.querySelectorAll(".tx-hash").forEach((hashLink) => {
        hashLink.addEventListener("click", function (e) {
          e.preventDefault();
          const txHash = this.dataset.hash; // Use data-hash attribute
          document.getElementById("tx-hash").value = txHash;
          document.getElementById("verify-btn").click(); // Trigger verification
        });
      });
    </script>
  </body>
</html>
