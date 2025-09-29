<?php
/**
 * Transactions.php
 *
 * This file provides a comprehensive interface for managing financial transactions.
 * It includes functionality for displaying a list of transactions, adding new ones,
 * updating existing ones, and deleting existing ones, with filtering, searching, and pagination.
 * All database interactions for transactions are encapsulated within this single file.
 */

session_start();

// Check if the user is logged in. If not, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=session_invalid"); // Added error parameter
    exit();
}

// Include database connection and other necessary classes
require_once 'db_connect.php'; // Provides $pdo connection
require_once 'user.php';       // For fetching current user details
// For logging activities

// --- AuditTrail Class Definition ---
// (Keeping it here as it was in the provided snippet, but ideally in AuditTrail.php)
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


// --- Client Class Definition (needed for client dropdown in transaction form) ---
/**
 * Client class (minimal, for fetching client names for dropdowns)
 */
class Client {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Fetches client_id and client_name for a specific user.
     * @param int $userId The ID of the user.
     * @return array An array of client records with client_id and client_name.
     */
    public function getClientsByUserId($userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT client_id, client_name FROM Clients WHERE user_id = :user_id ORDER BY client_name ASC");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error fetching clients for dropdown: " . $e->getMessage());
            return [];
        }
    }
}
// --- End Client Class Definition ---


// --- Transaction Class Definition (moved here from separate file) ---
/**
 * Transaction class encapsulates database operations related to the 'Transactions' table.
 */
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
     * Updates an existing transaction.
     *
     * @param int $transactionId The ID of the transaction to update.
     * @param int $userId The ID of the user who owns the transaction.
     * @param array $data An associative array of data to update.
     * @return bool True on success, false on failure.
     */
    public function updateTransaction($transactionId, $userId, $data) {
        $setClauses = [];
        $params = ['transaction_id' => $transactionId, 'user_id' => $userId];

        foreach ($data as $key => $value) {
            // Ensure only allowed fields are updated
            if (in_array($key, ['transaction_date', 'description', 'category', 'amount', 'currency', 'transaction_type', 'client_id', 'blockchain_status', 'blockchain_hash'])) {
                $setClauses[] = "$key = :$key";
                $params[":$key"] = $value;
            }
        }

        if (empty($setClauses)) {
            return false; // No data to update
        }

        $sql = "UPDATE Transactions SET " . implode(', ', $setClauses) . " WHERE transaction_id = :transaction_id AND user_id = :user_id";

        try {
            $stmt = $this->pdo->prepare($sql);
            // Handle client_id which can be null
            if (isset($params[':client_id']) && ($params[':client_id'] === 0 || $params[':client_id'] === null)) {
                $stmt->bindValue(':client_id', null, PDO::PARAM_NULL);
                unset($params[':client_id']); // Remove from params array if binding as null
            }

            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Error updating transaction: " . $e->getMessage());
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

// Initialize handlers
$userHandler = new User($pdo);
$transactionHandler = new Transaction($pdo); // Instantiate the Transaction class
$clientHandler = new Client($pdo); // For fetching clients for the filter dropdown
$auditTrailHandler = new AuditTrail($pdo); // For logging activities

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

$transactions_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $transactions_per_page;

$search_term = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? ''); // 'pending', 'paid', 'overdue', 'verified'
$type_filter = trim($_GET['type'] ?? '');     // 'income', 'expense', 'transfer'
$client_filter = isset($_GET['client']) ? (int)$_GET['client'] : 0; // 0 for 'No Client' or 'All Clients'

// Handle Add Transaction
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_transaction') {
    $date = trim($_POST['transaction_date'] ?? '');
    $description = trim($_POST['transaction_description'] ?? '');
    $amount = filter_var($_POST['transaction_amount'] ?? '', FILTER_VALIDATE_FLOAT);
    $type = trim($_POST['transaction_type'] ?? '');
    $status = trim($_POST['transaction_status'] ?? 'pending'); // Default to pending
    $clientId = filter_var($_POST['client_id'] ?? '', FILTER_VALIDATE_INT);

    // Basic validation
    if (empty($date) || empty($description) || $amount === false || $amount <= 0 || empty($type) || empty($status)) {
        $errorMessage = "Please fill in all required fields correctly.";
    } elseif (!in_array($type, ['income', 'expense', 'transfer'])) {
        $errorMessage = "Invalid transaction type selected.";
    } elseif (!in_array($status, ['pending', 'paid', 'overdue', 'verified'])) {
        $errorMessage = "Invalid transaction status selected.";
    } else {
        // Generate a placeholder blockchain hash for new transactions
        $blockchainHash = '0x' . bin2hex(random_bytes(32)); // Example 64-char hex hash

        if ($transactionHandler->addTransaction($userId, $date, $description, 'General', $amount, 'UGX', $type, $clientId, $blockchainHash, $status)) {
            $successMessage = "Transaction added successfully!";
            $auditTrailHandler->logActivity($userId, 'Transaction Added', "New transaction '{$description}' with amount {$amount} added.", 'Transactions', 'Success', $blockchainHash);
            // Clear POST data to reset form fields
            $_POST = array();
        } else {
            $errorMessage = "Failed to add transaction. Please try again.";
            $auditTrailHandler->logActivity($userId, 'Transaction Add Failed', "Attempt to add transaction '{$description}' failed.", 'Transactions', 'Failed');
        }
    }
}

// Handle Update Transaction
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_transaction') {
    $transactionId = filter_var($_POST['transaction_id_hidden'] ?? '', FILTER_VALIDATE_INT); // Get from hidden field
    $transactionDate = trim($_POST['transaction_date'] ?? '');
    $description = trim($_POST['transaction_description'] ?? '');
    $amount = filter_var($_POST['transaction_amount'] ?? '', FILTER_VALIDATE_FLOAT);
    $type = trim($_POST['transaction_type'] ?? '');
    $status = trim($_POST['transaction_status'] ?? 'pending');
    $clientId = filter_var($_POST['client_id'] ?? null, FILTER_VALIDATE_INT);

    // Get original transaction details for audit logging
    $originalTransaction = $transactionHandler->getTransactionById($transactionId, $userId);

    if (!$originalTransaction) {
        $errorMessage = 'Transaction not found or you do not have permission to edit it.';
        $auditTrailHandler->logActivity($userId, 'Transaction Update Failed', "Attempted to update non-existent or unauthorized transaction ID: {$transactionId}.", 'Transactions', 'Failed');
    } elseif (empty($transactionDate) || empty($description) || $amount === false || $amount <= 0 || empty($type) || empty($status)) {
        $errorMessage = 'Invalid or missing input for transaction update.';
        $auditTrailHandler->logActivity($userId, 'Transaction Update Failed', "Invalid input for transaction ID: {$transactionId}.", 'Transactions', 'Failed');
    } else {
        $updateData = [
            'transaction_date' => $transactionDate,
            'description' => $description,
            'category' => $originalTransaction['category'], // Assuming category is not updated via this form
            'amount' => $amount,
            'currency' => $originalTransaction['currency'], // Assuming currency is not updated
            'transaction_type' => $type,
            'client_id' => $clientId,
            'blockchain_status' => $status,
            'blockchain_hash' => $originalTransaction['blockchain_hash'] // Keep original hash or update if needed
        ];

        if ($transactionHandler->updateTransaction($transactionId, $userId, $updateData)) {
            $successMessage = 'Transaction updated successfully!';
            $auditTrailHandler->logActivity($userId, 'Transaction Updated', "Transaction ID: {$transactionId} updated.", 'Transactions', 'Success');
            // Clear POST data to reset form fields
            $_POST = array();
        } else {
            $errorMessage = 'Failed to update transaction.';
            $auditTrailHandler->logActivity($userId, 'Transaction Update Failed', "Failed to update transaction ID: {$transactionId} in DB.", 'Transactions', 'Failed');
        }
    }
}


// Handle Delete Transaction
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_transaction') {
    $transaction_id = filter_var($_POST['transaction_id'] ?? '', FILTER_VALIDATE_INT);

    if ($transaction_id) {
        $transaction_details = $transactionHandler->getTransactionById($transaction_id, $userId); // Get details for audit log

        if ($transactionHandler->deleteTransaction($transaction_id, $userId)) {
            echo json_encode(['success' => true, 'message' => 'Transaction deleted successfully!']);
            $auditDesc = "Transaction '{$transaction_details['description']}' (ID: {$transaction_id}) deleted.";
            $auditTrailHandler->logActivity($userId, 'Transaction Deleted', $auditDesc, 'Transactions', 'Success');
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete transaction.']);
            $auditTrailHandler->logActivity($userId, 'Transaction Delete Failed', "Attempt to delete transaction ID: {$transaction_id} failed.", 'Transactions', 'Failed');
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid transaction ID for deletion.']);
    }
    exit(); // Important for AJAX requests
}

// Handle Fetch Single Transaction for View/Edit Form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'get_transaction_details') {
    $transaction_id = filter_var($_POST['transaction_id'] ?? '', FILTER_VALIDATE_INT);

    if ($transaction_id) {
        $transaction_details = $transactionHandler->getTransactionById($transaction_id, $userId);
        if ($transaction_details) {
            echo json_encode(['success' => true, 'transaction' => $transaction_details]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Transaction not found.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid transaction ID.']);
    }
    exit();
}


// Fetch transactions based on filters and pagination
$transactionData = $transactionHandler->getTransactions(
    $userId,
    $transactions_per_page,
    $offset,
    $search_term,
    $status_filter,
    $type_filter,
    $client_filter
);
$transactions = $transactionData['entries'];
$total_transactions = $transactionData['total'];
$total_pages = ceil($total_transactions / $transactions_per_page);

// Fetch clients for the filter dropdown
$clients = $clientHandler->getClientsByUserId($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions | AccountAble</title>
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

        .header-actions {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.25rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn i {
            margin-right: 0.5rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: var(--text-light);
            border: 1px solid var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .btn-outline {
            background-color: transparent;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }

        .btn-outline:hover {
            background-color: var(--primary-light);
            color: var(--text-light);
        }

        /* Cards */
        .transactions-card, .add-transaction-card {
            background-color: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .card-header h2 {
            font-size: 1.4rem;
            color: var(--dark-color);
        }

        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
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

        .filter-group select, .search-bar input {
            padding: 0.6rem 0.8rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
            color: var(--text-dark);
            transition: border-color 0.3s ease;
        }

        .filter-group select:focus, .search-bar input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .search-bar {
            flex-grow: 1;
        }

        .search-bar input {
            width: 100%;
        }

        /* Table Styles */
        .transactions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .transactions-table th, .transactions-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .transactions-table th {
            background-color: #f5f5f5;
            color: var(--gray-color);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .transactions-table td {
            font-size: 0.9rem;
            color: var(--text-dark);
        }

        .transactions-table tbody tr:hover {
            background-color: #fcfcfc;
        }

        .status-badge {
            padding: 0.4rem 0.7rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
            text-transform: capitalize;
        }

        .status-paid { background-color: #e8f5e9; color: var(--success-color); }
        .status-pending { background-color: #fff3e0; color: var(--warning-color); }
        .status-overdue { background-color: #ffebee; color: var(--danger-color); }
        .status-verified { background-color: #e3f2fd; color: #2196f3; } /* Blue for verified */

        .action-btns {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            background: none;
            border: none;
            color: var(--gray-color);
            cursor: pointer;
            font-size: 1rem;
            transition: var(--transition);
            padding: 0.5rem;
            border-radius: 4px;
        }

        .action-btn:hover {
            color: var(--primary-color);
            background-color: #f0f0f0;
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

        /* Add Transaction Form */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-dark);
            font-weight: 500;
        }

        .form-group input[type="date"],
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
            color: var(--text-dark);
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-actions {
            margin-top: 1.5rem;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .form-actions .btn {
            min-width: 120px;
            justify-content: center;
        }

        /* Responsive */
        @media (max-width: 768px) {
            body {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: relative;
                height: auto;
            }

            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .filters {
                flex-direction: column;
                width: 100%;
            }

            .filter-group {
                width: 100%;
            }

            .filter-group select, .search-bar input {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="udashboard.php" class="logo">
                <i class="fas fa-coins"></i> AccountAble
            </a>
        </div>
        <nav class="nav-menu">
            <div class="menu-title">Main Navigation</div>
            <a href="udashboard.php" class="nav-item">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="Transactions.php" class="nav-item active">
                <i class="fas fa-exchange-alt"></i> Transactions
            </a>
            <a href="invoices.php" class="nav-item">
                <i class="fas fa-file-invoice-dollar"></i> Invoices
            </a>
            <a href="client.php" class="nav-item">
                <i class="fas fa-users"></i> Clients
            </a>
            <a href="Audit-Trail.php" class="nav-item">
                <i class="fas fa-history"></i> Audit Trail
            </a>
            <a href="Verification.php" class="nav-item">
                <i class="fas fa-fingerprint"></i> Verification
            </a>
            <div class="menu-title">Settings & Profile</div>
            <a href="settings.php" class="nav-item">
                <i class="fas fa-cog"></i> Settings
            </a>
            <a href="Logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="dashboard-header">
            <div class="page-title">
                <h1>Transactions</h1>
                <p>Manage all your financial transactions</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" id="addTransactionBtn">
                    <i class="fas fa-plus-circle"></i> Add New Transaction
                </button>
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

        <div class="add-transaction-card" id="addTransactionForm" style="display: none;">
            <div class="card-header">
                <h2 id="formTitle">Add New Transaction</h2>
            </div>
            <form action="Transactions.php" method="POST" id="transactionForm">
                <input type="hidden" name="action" id="formAction" value="add_transaction">
                <input type="hidden" id="transaction_id_hidden" name="transaction_id_hidden">
                <div class="form-group">
                    <label for="transaction_date">Date</label>
                    <input type="date" id="transaction_date" name="transaction_date" value="<?php echo htmlspecialchars($_POST['transaction_date'] ?? date('Y-m-d')); ?>" required>
                </div>
                <div class="form-group">
                    <label for="transaction_description">Description</label>
                    <input type="text" id="transaction_description" name="transaction_description" placeholder="e.g., Office Supplies" value="<?php echo htmlspecialchars($_POST['transaction_description'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="transaction_amount">Amount</label>
                    <input type="number" id="transaction_amount" name="transaction_amount" step="0.01" placeholder="e.g., 150.00" value="<?php echo htmlspecialchars($_POST['transaction_amount'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="transaction_type">Type</label>
                    <select id="transaction_type" name="transaction_type" required>
                        <option value="">Select Type</option>
                        <option value="income" <?php echo (($_POST['transaction_type'] ?? '') == 'income') ? 'selected' : ''; ?>>Income</option>
                        <option value="expense" <?php echo (($_POST['transaction_type'] ?? '') == 'expense') ? 'selected' : ''; ?>>Expense</option>
                        <option value="transfer" <?php echo (($_POST['transaction_type'] ?? '') == 'transfer') ? 'selected' : ''; ?>>Transfer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="transaction_status">Status</label>
                    <select id="transaction_status" name="transaction_status" required>
                        <option value="">Select Status</option>
                        <option value="pending" <?php echo (($_POST['transaction_status'] ?? '') == 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="paid" <?php echo (($_POST['transaction_status'] ?? '') == 'paid') ? 'selected' : ''; ?>>Paid</option>
                        <option value="overdue" <?php echo (($_POST['transaction_status'] ?? '') == 'overdue') ? 'selected' : ''; ?>>Overdue</option>
                        <option value="verified" <?php echo (($_POST['transaction_status'] ?? '') == 'verified') ? 'selected' : ''; ?>>Verified</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="client_id">Client</label>
                    <select id="client_id" name="client_id">
                        <option value="0">No Client</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?php echo htmlspecialchars($client['client_id']); ?>"
                                <?php echo ((isset($_POST['client_id']) && $_POST['client_id'] == $client['client_id']) ? 'selected' : ''); ?>>
                                <?php echo htmlspecialchars($client['client_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-outline" id="cancelAddTransaction">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitTransactionBtn">Add Transaction</button>
                </div>
            </form>
        </div>

        <div class="transactions-card">
            <div class="card-header">
                <h2>All Transactions</h2>
            </div>
            <div class="filters">
                <form action="Transactions.php" method="GET" class="filter-form" style="display: flex; gap: 1rem; width: 100%;">
                    <div class="search-bar">
                        <input type="text" name="search" placeholder="Search transactions..." value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="status-filter">Status:</label>
                        <select id="status-filter" name="status" onchange="this.form.submit()">
                            <option value="">All</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="paid" <?php echo $status_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="overdue" <?php echo $status_filter == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                            <option value="verified" <?php echo $status_filter == 'verified' ? 'selected' : ''; ?>>Verified</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="type-filter">Type:</label>
                        <select id="type-filter" name="type" onchange="this.form.submit()">
                            <option value="">All</option>
                            <option value="income" <?php echo $type_filter == 'income' ? 'selected' : ''; ?>>Income</option>
                            <option value="expense" <?php echo $type_filter == 'expense' ? 'selected' : ''; ?>>Expense</option>
                            <option value="transfer" <?php echo $type_filter == 'transfer' ? 'selected' : ''; ?>>Transfer</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="client-filter">Client:</label>
                        <select id="client-filter" name="client" onchange="this.form.submit()">
                            <option value="0">All Clients</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo htmlspecialchars($client['client_id']); ?>" <?php echo $client_filter == $client['client_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client['client_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                </form>
            </div>

            <table class="transactions-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Client</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($transactions)): ?>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($transaction['transaction_date']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                <td>UGX <?php echo htmlspecialchars(number_format($transaction['amount'], 2)); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($transaction['transaction_type'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo htmlspecialchars($transaction['blockchain_status']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($transaction['blockchain_status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($transaction['client_name'] ? $transaction['client_name'] : 'N/A'); ?></td>
                                <td class="action-btns">
                                    <button class="action-btn view-btn" title="View/Edit" data-id="<?php echo htmlspecialchars($transaction['transaction_id']); ?>">
                                        <i class="fas fa-edit"></i> <!-- Changed to edit icon for view/edit functionality -->
                                    </button>
                                    <?php if (!empty($transaction['blockchain_hash'])): ?>
                                        <button class="action-btn copy-hash-btn" title="Copy Blockchain Hash" data-hash="<?php echo htmlspecialchars($transaction['blockchain_hash']); ?>">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button class="action-btn delete-btn" title="Delete" data-id="<?php echo htmlspecialchars($transaction['transaction_id']); ?>">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">No transactions found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="pagination">
                <?php if ($total_pages > 1): ?>
                    <a href="?page=<?php echo max(1, $current_page - 1); ?>&search=<?php echo urlencode($search_term); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>&client=<?php echo urlencode($client_filter); ?>" class="page-btn <?php echo $current_page == 1 ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_term); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>&client=<?php echo urlencode($client_filter); ?>" class="page-btn <?php echo $i == $current_page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    <a href="?page=<?php echo min($total_pages, $current_page + 1); ?>&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>&client=<?php echo urlencode($client_filter); ?>" class="page-btn <?php echo $current_page == $total_pages ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        document.getElementById('addTransactionBtn').addEventListener('click', function() {
            document.getElementById('addTransactionForm').style.display = 'block';
            this.style.display = 'none'; // Hide the add button
            // Reset form for new entry
            document.getElementById('transactionForm').reset();
            document.getElementById('transaction_id_hidden').value = ''; // Clear hidden ID
            document.getElementById('formTitle').textContent = 'Add New Transaction'; // Set form title
            document.getElementById('submitTransactionBtn').textContent = 'Add Transaction';
            document.getElementById('formAction').value = 'add_transaction'; // Set form action to add
        });

        document.getElementById('cancelAddTransaction').addEventListener('click', function() {
            document.getElementById('addTransactionForm').style.display = 'none';
            document.getElementById('addTransactionBtn').style.display = 'block'; // Show the add button
            // Clear any error/success messages
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => alert.style.display = 'none');
        });

        // Delete functionality (using AJAX for a smoother experience)
        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', function() {
                const transactionId = this.dataset.id;
                if (confirm('Are you sure you want to delete this transaction? This action cannot be undone.')) {
                    fetch('Transactions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=delete_transaction&transaction_id=${transactionId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Instead of alert, display a message on the page
                            displayMessage('success', data.message);
                            // Remove the row from the table
                            this.closest('tr').remove();
                            // Optionally, refresh the page to update pagination/counts
                            // window.location.reload(); // Removed for smoother UX, but can be re-enabled if needed
                        } else {
                            displayMessage('error', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        displayMessage('error', 'An error occurred during deletion.');
                    });
                }
            });
        });

        // View/Edit functionality - populate form with transaction details
        document.querySelectorAll('.view-btn').forEach(button => {
            button.addEventListener('click', function() {
                const transactionId = this.dataset.id;
                fetch('Transactions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=get_transaction_details&transaction_id=${transactionId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.transaction) {
                        const transaction = data.transaction;
                        // Populate the form fields
                        document.getElementById('transaction_id_hidden').value = transaction.transaction_id;
                        document.getElementById('transaction_date').value = transaction.transaction_date;
                        document.getElementById('transaction_description').value = transaction.description;
                        document.getElementById('transaction_amount').value = parseFloat(transaction.amount).toFixed(2);
                        document.getElementById('transaction_type').value = transaction.transaction_type;
                        document.getElementById('transaction_status').value = transaction.blockchain_status;
                        document.getElementById('client_id').value = transaction.client_id || '0'; // Handle null client_id

                        // Show the form and change button text
                        document.getElementById('addTransactionForm').style.display = 'block';
                        document.getElementById('addTransactionBtn').style.display = 'none';
                        document.getElementById('formTitle').textContent = 'Edit Transaction'; // Change form title
                        document.getElementById('submitTransactionBtn').textContent = 'Update Transaction';
                        document.getElementById('formAction').value = 'update_transaction'; // Set form action to update
                    } else {
                        displayMessage('error', data.message || 'Could not fetch transaction details.');
                    }
                })
                .catch(error => {
                    console.error('Error fetching transaction details:', error);
                    displayMessage('error', 'An error occurred while fetching transaction details.');
                });
            });
        });

        // Copy Blockchain Hash functionality
        document.querySelectorAll('.copy-hash-btn').forEach(button => {
            button.addEventListener('click', function() {
                const hashToCopy = this.dataset.hash;
                // Use a temporary textarea to copy to clipboard
                const tempTextArea = document.createElement('textarea');
                tempTextArea.value = hashToCopy;
                document.body.appendChild(tempTextArea);
                tempTextArea.select();
                try {
                    document.execCommand('copy');
                    displayMessage('success', 'Blockchain hash copied to clipboard!');
                } catch (err) {
                    console.error('Failed to copy hash: ', err);
                    displayMessage('error', 'Failed to copy hash. Please copy it manually: ' + hashToCopy);
                }
                document.body.removeChild(tempTextArea);
            });
        });


        // Client-side search functionality (kept for instant filtering, but PHP handles server-side search/filters)
        // Note: For a more robust client-side search with server-side filtering,
        // you might debounce this input and trigger a form submit or AJAX call.
        // Currently, the form's GET method handles filtering on submission.
        // The below JS provides immediate visual filtering but won't persist on page reload.
        const searchInput = document.querySelector('.search-bar input');
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.transactions-table tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Function to display messages (replaces alert)
        function displayMessage(type, message) {
            const mainContent = document.querySelector('main.main-content');
            let messageBox = mainContent.querySelector(`.alert.alert-${type}`);

            if (!messageBox) {
                messageBox = document.createElement('div');
                messageBox.className = `alert alert-${type}`;
                // Add inline styles for the alert box
                messageBox.style.padding = '1rem';
                messageBox.style.marginBottom = '1rem';
                messageBox.style.borderRadius = '5px';
                messageBox.style.textAlign = 'center';
                messageBox.style.fontSize = '0.95rem';

                if (type === 'success') {
                    messageBox.style.backgroundColor = '#d4edda';
                    messageBox.style.color = '#155724';
                } else if (type === 'error') {
                    messageBox.style.backgroundColor = '#f8d7da';
                    messageBox.style.color = '#721c24';
                }
                mainContent.insertBefore(messageBox, mainContent.firstChild.nextSibling); // Insert after header
            }
            messageBox.textContent = message;
            messageBox.style.display = 'block';

            // Hide after 5 seconds
            setTimeout(() => {
                messageBox.style.display = 'none';
            }, 5000);
        }

        // Hide existing PHP-generated messages after DOM load
        document.addEventListener('DOMContentLoaded', () => {
            const phpAlerts = document.querySelectorAll('.alert');
            phpAlerts.forEach(alert => {
                if (alert.textContent.trim() !== '') { // Only hide if it has content
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 5000);
                }
            });
        });
    </script>
</body>
</html>
