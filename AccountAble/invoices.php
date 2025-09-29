<?php

session_start();

// Check if the user is logged in. If not, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection and other necessary classes
require_once 'db_connect.php'; // Provides $pdo connection
require_once 'User.php';       // For fetching current user details
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

// --- Client Class Definition (needed for client dropdown in invoice form) ---
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

// --- Invoice Class Definition ---
/**
 * Invoice class encapsulates database operations related to the 'Invoices' table.
 */
class Invoice {
    private $pdo; // PDO database connection object

    /**
     * Constructor
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Adds a new invoice to the database.
     *
     * @param int $userId The ID of the user creating the invoice.
     * @param int $clientId The ID of the client.
     * @param string $invoiceDate The date of the invoice (YYYY-MM-DD).
     * @param string $dueDate The due date of the invoice (YYYY-MM-DD).
     * @param float $amount The total amount of the invoice.
     * @param string $status The status of the invoice ('draft', 'pending', 'paid', 'overdue').
     * @param string|null $blockchainHash Optional: The blockchain hash if verified.
     * @return bool True on successful insertion, false otherwise.
     */
    public function addInvoice(
        $userId,
        $clientId,
        $invoiceDate,
        $dueDate,
        $amount,
        $status = 'draft',
        $blockchainHash = null
    ) {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO Invoices (user_id, client_id, invoice_date, due_date, amount, status, blockchain_hash)
                 VALUES (:user_id, :client_id, :invoice_date, :due_date, :amount, :status, :blockchain_hash)"
            );
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':client_id', $clientId, PDO::PARAM_INT);
            $stmt->bindParam(':invoice_date', $invoiceDate);
            $stmt->bindParam(':due_date', $dueDate);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':blockchain_hash', $blockchainHash);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error adding invoice: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches invoices for a specific user, with filtering and pagination.
     *
     * @param int $userId The ID of the user.
     * @param int $limit The maximum number of invoices to fetch.
     * @param int $offset The offset for pagination.
     * @param string $statusFilter Optional: Filter by invoice status.
     * @param string $searchTerm Optional: A term to search within invoice ID or client name.
     * @return array An array containing 'entries' (the fetched records) and 'total' (total count).
     */
    public function getInvoices(
        $userId,
        $limit = 10,
        $offset = 0,
        $statusFilter = '',
        $searchTerm = ''
    ) {
        $entries = [];
        $total = 0;

        try {
            $sql = "SELECT i.*, c.client_name
                    FROM Invoices i
                    LEFT JOIN Clients c ON i.client_id = c.client_id
                    WHERE i.user_id = :user_id";
            $params = [':user_id' => $userId];

            // Apply status filter
            if (!empty($statusFilter) && $statusFilter !== 'All') {
                $sql .= " AND i.status = :status_filter";
                $params[':status_filter'] = $statusFilter;
            }

            // Apply search term
            if (!empty($searchTerm)) {
                $sql .= " AND (i.invoice_id LIKE :search_term OR c.client_name LIKE :search_term)";
                $params[':search_term'] = '%' . $searchTerm . '%';
            }

            // Get total count first
            $countSql = "SELECT COUNT(*) FROM (" . $sql . ") AS subquery";
            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();

            // Fetch actual entries with limit and offset
            $sql .= " ORDER BY i.invoice_date DESC, i.created_at DESC LIMIT :limit OFFSET :offset";
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $entries = $stmt->fetchAll();

        } catch (PDOException $e) {
            error_log("Error fetching invoices: " . $e->getMessage());
        }

        return ['entries' => $entries, 'total' => $total];
    }

    /**
     * Gets a single invoice by its ID and user ID.
     *
     * @param int $invoiceId The ID of the invoice.
     * @param int $userId The ID of the user who owns the invoice.
     * @return array|false The invoice record if found, otherwise false.
     */
    public function getInvoiceById($invoiceId, $userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM Invoices WHERE invoice_id = :invoice_id AND user_id = :user_id");
            $stmt->bindParam(':invoice_id', $invoiceId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting invoice by ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes an invoice.
     *
     * @param int $invoiceId The ID of the invoice to delete.
     * @param int $userId The ID of the user who owns the invoice.
     * @return bool True on success, false on failure.
     */
    public function deleteInvoice($invoiceId, $userId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM Invoices WHERE invoice_id = :invoice_id AND user_id = :user_id");
            $stmt->bindParam(':invoice_id', $invoiceId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error deleting invoice: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Updates an existing invoice.
     *
     * @param int $invoiceId The ID of the invoice to update.
     * @param int $userId The ID of the user who owns the invoice.
     * @param array $data An associative array of data to update.
     * @return bool True on success, false on failure.
     */
    public function updateInvoice($invoiceId, $userId, $data) {
        $setClauses = [];
        $params = ['invoice_id' => $invoiceId, 'user_id' => $userId];

        foreach ($data as $key => $value) {
            if (in_array($key, ['client_id', 'invoice_date', 'due_date', 'amount', 'status', 'blockchain_hash'])) {
                $setClauses[] = "$key = :$key";
                $params[":$key"] = $value;
            }
        }

        if (empty($setClauses)) {
            return false;
        }

        $sql = "UPDATE Invoices SET " . implode(', ', $setClauses) . " WHERE invoice_id = :invoice_id AND user_id = :user_id";

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Error updating invoice: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gets count of invoices by status.
     * @param int $userId The ID of the user.
     * @param string $status The status to count.
     * @return int The count of invoices with the given status.
     */
    public function getInvoiceCountByStatus($userId, $status) {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM Invoices WHERE user_id = :user_id AND status = :status");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':status', $status);
            $stmt->execute();
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error getting invoice count by status: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Gets total sum of amounts for invoices by status.
     * @param int $userId The ID of the user.
     * @param string $status The status to sum.
     * @return float The total amount of invoices with the given status.
     */
    public function getInvoiceAmountByStatus($userId, $status) {
        try {
            $stmt = $this->pdo->prepare("SELECT SUM(amount) FROM Invoices WHERE user_id = :user_id AND status = :status");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':status', $status);
            $stmt->execute();
            return $stmt->fetchColumn() ?? 0;
        } catch (PDOException $e) {
            error_log("Error getting invoice amount by status: " . $e->getMessage());
            return 0;
        }
    }
}
// --- End Invoice Class Definition ---

// Initialize handlers
$userHandler = new User($pdo);
$invoiceHandler = new Invoice($pdo);
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

// --- Handle Add Invoice Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_invoice') {
    $clientId = filter_var($_POST['client_id'] ?? '', FILTER_VALIDATE_INT);
    $invoiceDate = trim($_POST['invoice_date'] ?? '');
    $dueDate = trim($_POST['due_date'] ?? '');
    $amount = filter_var($_POST['amount'] ?? '', FILTER_VALIDATE_FLOAT);
    $status = trim($_POST['status'] ?? 'draft');

    // Basic validation
    if (empty($clientId) || empty($invoiceDate) || empty($dueDate) || $amount === false || $amount <= 0) {
        $errorMessage = "Please fill in all required fields correctly.";
    } elseif ($invoiceDate > $dueDate) {
        $errorMessage = "Due date cannot be before invoice date.";
    } else {
        // Generate a placeholder blockchain hash for new invoices (if not 'draft')
        $blockchainHash = ($status !== 'draft') ? '0x' . bin2hex(random_bytes(32)) : null;

        if ($invoiceHandler->addInvoice($userId, $clientId, $invoiceDate, $dueDate, $amount, $status, $blockchainHash)) {
            $successMessage = "Invoice added successfully!";
            $auditTrailHandler->logActivity($userId, 'Invoice Added', "New invoice for client ID {$clientId} with amount {$amount} added.", 'Invoices', 'Success', $blockchainHash);
            // Clear POST data to reset form fields
            $_POST = array();
        } else {
            $errorMessage = "Failed to add invoice. Please try again.";
            $auditTrailHandler->logActivity($userId, 'Invoice Add Failed', "Attempt to add invoice for client ID {$clientId} failed.", 'Invoices', 'Failed');
        }
    }
}

// --- Handle Delete Invoice Action ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_invoice') {
    $invoiceIdToDelete = filter_var($_POST['invoice_id'] ?? '', FILTER_VALIDATE_INT);

    if ($invoiceIdToDelete) {
        $invoiceDetails = $invoiceHandler->getInvoiceById($invoiceIdToDelete, $userId); // Get details for audit log

        if ($invoiceHandler->deleteInvoice($invoiceIdToDelete, $userId)) {
            echo json_encode(['success' => true, 'message' => 'Invoice deleted successfully!']);
            $auditDesc = "Invoice ID: {$invoiceIdToDelete} for client '{$invoiceDetails['client_name']}' deleted.";
            $auditTrailHandler->logActivity($userId, 'Invoice Deleted', $auditDesc, 'Invoices', 'Success');
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete invoice or invoice not found.']);
            $auditTrailHandler->logActivity($userId, 'Invoice Delete Failed', "Attempt to delete invoice ID: {$invoiceIdToDelete} failed.", 'Invoices', 'Failed');
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid invoice ID for deletion.']);
    }
    exit(); // Important for AJAX requests
}

// --- Handle Export Invoices to CSV ---
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    $allInvoices = $invoiceHandler->getInvoices($userId, 999999, 0, $status_filter, $search_term)['entries'];

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="invoices_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // CSV Header: Only include requested fields
    fputcsv($output, ['Invoice Date', 'Client Name', 'Amount', 'Status']);

    // CSV Data: Only include requested fields
    foreach ($allInvoices as $invoice) {
        fputcsv($output, [
            $invoice['invoice_date'],
            $invoice['client_name'] ?? 'N/A',
            number_format($invoice['amount'], 2),
            ucfirst($invoice['status'])
        ]);
    }

    fclose($output);
    exit();
}


// --- Fetch Invoices for Display (with Filtering and Pagination) ---
$invoices_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $invoices_per_page;

$status_filter = trim($_GET['status'] ?? 'All'); // 'All', 'paid', 'pending', 'overdue', 'draft', 'blockchain_verified'
$search_term = trim($_GET['search'] ?? '');

$invoiceData = $invoiceHandler->getInvoices(
    $userId,
    $invoices_per_page,
    $offset,
    $status_filter,
    $search_term
);

$invoices = $invoiceData['entries'];
$total_invoices = $invoiceData['total'];
$total_pages = ceil($total_invoices / $invoices_per_page);

// Fetch counts for stats cards
$totalInvoicesCount = $invoiceHandler->getInvoices($userId, 999999, 0)['total']; // Get total count
$paidInvoicesCount = $invoiceHandler->getInvoiceCountByStatus($userId, 'paid');
$overdueInvoicesCount = $invoiceHandler->getInvoiceCountByStatus($userId, 'overdue');
$draftInvoicesCount = $invoiceHandler->getInvoiceCountByStatus($userId, 'draft');

// Fetch clients for the New Invoice modal dropdown
$clients = $clientHandler->getClientsByUserId($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AccountAble | Invoices</title>
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

        .stat-icon.invoices {
            background-color: rgba(46, 125, 50, 0.1);
            color: var(--primary-color);
        }

        .stat-icon.paid {
            background-color: rgba(139, 195, 74, 0.1);
            color: var(--accent-color);
        }

        .stat-icon.overdue {
            background-color: rgba(255, 152, 0, 0.1);
            color: var(--warning-color);
        }

        .stat-icon.draft {
            background-color: rgba(117, 117, 117, 0.1);
            color: var(--gray-color);
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
            grid-template-columns: 1fr;
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

        /* Invoice Table */
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
        }

        .invoice-table th {
            text-align: left;
            padding: 0.75rem;
            font-size: 0.75rem;
            color: var(--gray-color);
            font-weight: 500;
            text-transform: uppercase;
            border-bottom: 1px solid var(--light-gray);
        }

        .invoice-table td {
            padding: 1rem 0.75rem;
            border-bottom: 1px solid var(--light-gray);
            font-size: 0.875rem;
        }

        .invoice-table tr:last-child td {
            border-bottom: none;
        }

        .invoice-table tr:hover {
            background-color: #f8fafc;
        }

        .invoice-status {
            display: inline-block;
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-paid {
            background-color: #e8f5e9;
            color: var(--success-color);
        }

        .status-pending {
            background-color: #fff8e1;
            color: var(--warning-color);
        }

        .status-overdue {
            background-color: #ffebee;
            color: var(--danger-color);
        }

        .status-draft {
            background-color: #eceff1;
            color: var(--gray-color);
        }

        /* Invoice Actions */
        .invoice-actions {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--light-gray);
            color: var(--gray-color);
            cursor: pointer;
            transition: var(--transition);
            border: none;
        }

        .action-btn:hover {
            background-color: var(--primary-light);
            color: white;
        }

        /* Invoice Filters */
        .filter-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: var(--transition);
            border: 1px solid var(--light-gray);
            background-color: white;
        }

        .filter-btn.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .filter-btn:hover:not(.active) {
            background-color: var(--light-gray);
        }

        /* Modal Styles */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1000; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            width: 90%;
            max-width: 600px;
            animation: fadeInModal 0.3s ease-out;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.5rem;
            color: var(--dark-color);
        }

        .close-modal {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            border: none;
            background: none;
        }

        .close-modal:hover,
        .close-modal:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }

        .modal-body .form-group {
            margin-bottom: 15px;
        }

        .modal-body label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text-dark);
        }

        .modal-body input[type="text"],
        .modal-body input[type="date"],
        .modal-body input[type="number"],
        .modal-body select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }

        .modal-body input:focus,
        .modal-body select:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .modal-footer .btn {
            padding: 8px 15px;
        }

        @keyframes fadeInModal {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

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

            .invoice-table {
                display: block;
                overflow-x: auto;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .card-actions {
                flex-direction: column;
                gap: 0.5rem;
                align-items: flex-end;
            }

            .btn {
                width: 100%;
                justify-content: center;
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
            <a href="invoices.php" class="nav-item active">
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
                <h1>Invoice Management</h1>
                <p>Create, manage, and track all your invoices with blockchain verification</p>
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
                    <div class="stat-title">Total Invoices</div>
                    <div class="stat-icon invoices">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                </div>
                <div class="stat-value" id="totalInvoicesCount"><?php echo $totalInvoicesCount; ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i> 8% from last month
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Paid Invoices</div>
                    <div class="stat-icon paid">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="stat-value" id="paidInvoicesCount"><?php echo $paidInvoicesCount; ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i> 12% from last month
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Overdue Invoices</div>
                    <div class="stat-icon overdue">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                </div>
                <div class="stat-value" id="overdueInvoicesCount"><?php echo $overdueInvoicesCount; ?></div>
                <div class="stat-change negative">
                    <i class="fas fa-arrow-down"></i> 2 from last month
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Draft Invoices</div>
                    <div class="stat-icon draft">
                        <i class="fas fa-edit"></i>
                    </div>
                </div>
                <div class="stat-value" id="draftInvoicesCount"><?php echo $draftInvoicesCount; ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i> 3 from last month
                </div>
            </div>
        </section>

        <?php if (!empty($errorMessage)): ?>
            <div class="message-box error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>
        <?php if (!empty($successMessage)): ?>
            <div class="message-box success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>

        <!-- Main Content Grid -->
        <section class="content-grid">
            <!-- Invoices Section -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">All Invoices</h2>
                    <div class="card-actions">
                        <button class="btn btn-outline" id="exportInvoicesBtn">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <button class="btn btn-primary" id="newInvoiceBtn">
                            <i class="fas fa-plus"></i> New Invoice
                        </button>
                    </div>
                </div>

                <!-- Filter Bar -->
                <div class="filter-bar">
                    <form action="invoices.php" method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <button type="submit" name="status" value="All" class="filter-btn <?php echo ($status_filter == 'All') ? 'active' : ''; ?>">All</button>
                        <button type="submit" name="status" value="paid" class="filter-btn <?php echo ($status_filter == 'paid') ? 'active' : ''; ?>">Paid</button>
                        <button type="submit" name="status" value="pending" class="filter-btn <?php echo ($status_filter == 'pending') ? 'active' : ''; ?>">Pending</button>
                        <button type="submit" name="status" value="overdue" class="filter-btn <?php echo ($status_filter == 'overdue') ? 'active' : ''; ?>">Overdue</button>
                        <button type="submit" name="status" value="draft" class="filter-btn <?php echo ($status_filter == 'draft') ? 'active' : ''; ?>">Draft</button>
                        <button type="submit" name="status" value="verified" class="filter-btn <?php echo ($status_filter == 'verified') ? 'active' : ''; ?>">Blockchain Verified</button>
                        <input type="text" name="search" placeholder="Search invoices..." value="<?php echo htmlspecialchars($search_term); ?>" style="flex-grow: 1; padding: 0.5rem 1rem; border: 1px solid var(--light-gray); border-radius: 20px;">
                    </form>
                </div>

                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Due Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Blockchain</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($invoices)): ?>
                            <?php foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($invoice['invoice_id']); ?></td>
                                    <td><?php echo htmlspecialchars($invoice['client_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($invoice['invoice_date']); ?></td>
                                    <td><?php echo htmlspecialchars($invoice['due_date']); ?></td>
                                    <td>UGX <?php echo htmlspecialchars(number_format($invoice['amount'], 2)); ?></td>
                                    <td>
                                        <span class="invoice-status status-<?php echo htmlspecialchars($invoice['status']); ?>">
                                            <?php echo htmlspecialchars(ucfirst($invoice['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($invoice['blockchain_hash'])): ?>
                                            <i class="fas fa-check-circle" style="color: var(--success-color);" title="<?php echo htmlspecialchars($invoice['blockchain_hash']); ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-times-circle" style="color: var(--danger-color);" title="Not on Blockchain"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td class="invoice-actions">
                                        <button class="action-btn view-invoice-btn" title="View">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if (!empty($invoice['blockchain_hash'])): ?>
                                            <button class="action-btn blockchain-invoice-btn" title="Blockchain Record" data-id="<?php echo htmlspecialchars($invoice['invoice_id']); ?>" data-hash="<?php echo htmlspecialchars($invoice['blockchain_hash']); ?>">
                                                <i class="fas fa-link"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="action-btn delete-invoice-btn" title="Delete" data-id="<?php echo htmlspecialchars($invoice['invoice_id']); ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 20px;">No invoices found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="pagination">
                    <?php if ($total_pages > 1): ?>
                        <a href="?page=<?php echo max(1, $current_page - 1); ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search_term); ?>" class="page-btn <?php echo $current_page == 1 ? 'disabled' : ''; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search_term); ?>" class="page-btn <?php echo $i == $current_page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        <a href="?page=<?php echo min($total_pages, $current_page + 1); ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search_term); ?>" class="page-btn <?php echo $current_page == $total_pages ? 'disabled' : ''; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <!-- New Invoice Modal -->
    <div class="modal" id="newInvoiceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create New Invoice</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="newInvoiceForm" method="POST" action="invoices.php">
                    <input type="hidden" name="action" value="add_invoice">
                    <div class="form-group">
                        <label for="client_id">Client</label>
                        <select id="client_id" name="client_id" required>
                            <option value="">Select Client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo htmlspecialchars($client['client_id']); ?>">
                                    <?php echo htmlspecialchars($client['client_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="invoice_date">Invoice Date</label>
                        <input type="date" id="invoice_date" name="invoice_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="due_date">Due Date</label>
                        <input type="date" id="due_date" name="due_date" required>
                    </div>
                    <div class="form-group">
                        <label for="amount">Amount (UGX)</label>
                        <input type="number" id="amount" name="amount" step="0.01" placeholder="e.g., 50000.00" required>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" required>
                            <option value="draft">Draft</option>
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                            <option value="overdue">Overdue</option>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline close-modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Invoice</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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

        // Modal functionality
        const newInvoiceBtn = document.getElementById('newInvoiceBtn');
        const newInvoiceModal = document.getElementById('newInvoiceModal');
        const closeModalBtns = document.querySelectorAll('.close-modal');

        newInvoiceBtn.addEventListener('click', function() {
            newInvoiceModal.style.display = 'flex'; // Use flex to center
        });

        closeModalBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                newInvoiceModal.style.display = 'none';
            });
        });

        window.addEventListener('click', function(event) {
            if (event.target == newInvoiceModal) {
                newInvoiceModal.style.display = 'none';
            }
        });

        // Invoice action buttons (using AJAX for delete for smoother experience)
        document.querySelectorAll('.delete-invoice-btn').forEach(button => {
            button.addEventListener('click', function() {
                const invoiceId = this.dataset.id;
                if (confirm(`Are you sure you want to delete invoice ${invoiceId}? This action cannot be undone.`)) {
                    fetch('invoices.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=delete_invoice&invoice_id=${invoiceId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            // Remove the row from the table
                            this.closest('tr').remove();
                            // Optionally, refresh the page to update counts/pagination
                            window.location.reload();
                        } else {
                            alert(data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred during deletion.');
                    });
                }
            });
        });

        // View Invoice button (placeholder)
        document.querySelectorAll('.view-invoice-btn').forEach(button => {
            button.addEventListener('click', function() {
                const invoiceId = this.dataset.id;
                alert(`Viewing invoice ${invoiceId}. This would typically open a detailed view or modal.`);
            });
        });

        // This will now trigger the PHP export logic
        document.getElementById('exportInvoicesBtn').addEventListener('click', function() {
            // Construct the URL with current filters to export filtered data
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('export', 'csv'); // Add export parameter
            // Preserve existing search and status filters
            const statusFilter = document.querySelector('button.filter-btn.active')?.value || 'All';
            const searchTerm = document.querySelector('input[name="search"]')?.value || '';
            currentUrl.searchParams.set('status', statusFilter);
            currentUrl.searchParams.set('search', searchTerm);

            window.location.href = currentUrl.toString(); // Redirect to trigger download
        });


        // Blockchain Invoice button (placeholder)
        document.querySelectorAll('.blockchain-invoice-btn').forEach(button => {
            button.addEventListener('click', function() {
                const invoiceId = this.dataset.id;
                const blockchainHash = this.dataset.hash;
                alert(`Viewing blockchain record for invoice ${invoiceId}. Hash: ${blockchainHash}.`);
            });
        });

        // Filter buttons (already handled by PHP form submission)
        // No client-side JS needed for filter buttons beyond the form submit.
    </script>
</body>
</html>
