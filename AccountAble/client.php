<?php
session_start();

// Check if the user is logged in. If not, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Include database connection and necessary classes
require_once 'db_connect.php';
require_once 'user.php';
// Ensure AuditTrail class is available for logging

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


// --- Client Class Definition ---
/**
 * Client class encapsulates database operations related to the 'Clients' table.
 */
class Client {
    private $pdo; // PDO database connection object

    /**
     * Constructor
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Fetches all clients for a specific user, with filtering and pagination.
     *
     * @param int $userId The ID of the user whose clients to fetch.
     * @param int $limit The maximum number of clients to fetch per page.
     * @param int $offset The offset for pagination.
     * @param string $searchTerm Optional: A term to search within client name, email, or phone.
     * @param string $statusFilter Optional: Filter by client status ('Active', 'Inactive').
     * @param string $typeFilter Optional: Filter by client type ('Standard', 'Premium').
     * @return array An array containing 'entries' (the fetched records) and 'total' (total count).
     */
    public function getClients(
        $userId,
        $limit = 5,
        $offset = 0,
        $searchTerm = '',
        $statusFilter = 'All Status',
        $typeFilter = 'All Clients'
    ) {
        $entries = [];
        $total = 0;

        try {
            // Modified SQL: Removed total_value and last_activity subqueries
            $sql = "SELECT c.client_id, c.user_id, c.client_name, c.contact_person, c.email, c.phone_number, c.address, c.status, c.client_type, c.created_at
                    FROM Clients c
                    WHERE c.user_id = :user_id_main";
            $params = [
                ':user_id_main' => $userId,
            ];

            // Apply search filter
            if (!empty($searchTerm)) {
                $sql .= " AND (c.client_name LIKE :search_term OR c.email LIKE :search_term OR c.phone_number LIKE :search_term)";
                $params[':search_term'] = '%' . $searchTerm . '%';
            }

            // Apply status filter
            if ($statusFilter !== 'All Status') {
                $sql .= " AND c.status = :status";
                $params[':status'] = $statusFilter;
            }

            // Apply type filter
            if ($typeFilter !== 'All Clients') {
                $sql .= " AND c.client_type = :type";
                $params[':type'] = $typeFilter;
            }

            // Get total count for pagination
            $countSql = "SELECT COUNT(*) FROM (" . $sql . ") AS subquery";
            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();

            // Fetch actual entries with limit and offset
            $sql .= " ORDER BY c.client_name ASC LIMIT :limit OFFSET :offset";
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $entries = $stmt->fetchAll();

        } catch (PDOException $e) {
            error_log("Error fetching clients: " . $e->getMessage());
        }

        return ['entries' => $entries, 'total' => $total];
    }

    /**
     * Fetches a single client by ID and user ID.
     *
     * @param int $clientId The ID of the client.
     * @param int $userId The ID of the user who owns the client.
     * @return array|false An associative array of client data, or false if not found.
     */
    public function getClientByIdAndUserId($clientId, $userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM Clients WHERE client_id = :client_id AND user_id = :user_id");
            $stmt->bindParam(':client_id', $clientId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error fetching client by ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Adds a new client.
     *
     * @param int $userId The ID of the user creating the client.
     * @param string $clientName The name of the client.
     * @param string|null $contactPerson The contact person for the client.
     * @param string|null $email The client's email.
     * @param string|null $phoneNumber The client's phone number.
     * @param string|null $address The client's address.
     * @param string $status The client's status ('Active', 'Inactive').
     * @param string $clientType The client's type ('Standard', 'Premium').
     * @return bool True on success, false on failure.
     */
    public function addClient($userId, $clientName, $contactPerson = null, $email = null, $phoneNumber = null, $address = null, $status = 'Active', $clientType = 'Standard') {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO Clients (user_id, client_name, contact_person, email, phone_number, address, status, client_type)
                 VALUES (:user_id, :client_name, :contact_person, :email, :phone_number, :address, :status, :client_type)"
            );
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':client_name', $clientName);
            $stmt->bindParam(':contact_person', $contactPerson);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone_number', $phoneNumber);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':client_type', $clientType);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error adding client: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Updates an existing client.
     *
     * @param int $clientId The ID of the client to update.
     * @param int $userId The ID of the user who owns the client.
     * @param array $data An associative array of data to update.
     * @return bool True on success, false on failure.
     */
    public function updateClient($clientId, $userId, $data) {
        $setClauses = [];
        $params = ['client_id' => $clientId, 'user_id' => $userId];

        foreach ($data as $key => $value) {
            if (in_array($key, ['client_name', 'contact_person', 'email', 'phone_number', 'address', 'status', 'client_type'])) {
                $setClauses[] = "$key = :$key";
                $params[":$key"] = $value;
            }
        }

        if (empty($setClauses)) {
            return false;
        }

        $sql = "UPDATE Clients SET " . implode(', ', $setClauses) . " WHERE client_id = :client_id AND user_id = :user_id";

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Error updating client: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes a client.
     *
     * @param int $clientId The ID of the client to delete.
     * @param int $userId The ID of the user who owns the client.
     * @return bool True on success, false on failure.
     */
    public function deleteClient($clientId, $userId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM Clients WHERE client_id = :client_id AND user_id = :user_id");
            $stmt->bindParam(':client_id', $clientId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error deleting client: " . $e->getMessage());
            return false;
        }
    }
}
// --- End Client Class Definition ---

// Initialize handlers
$userHandler = new User($pdo);
$clientHandler = new Client($pdo);
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

// --- Handle Add Client Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_client_submit'])) {
    $clientName = trim($_POST['client_name'] ?? '');
    $contactPerson = trim($_POST['contact_person'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phoneNumber = trim($_POST['phone_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $clientStatus = trim($_POST['client_status'] ?? 'Active');
    $clientType = trim($_POST['client_type'] ?? 'Standard');

    if (empty($clientName) || empty($email)) {
        $errorMessage = "Client Name and Email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Invalid email format.";
    } else {
        $addSuccess = $clientHandler->addClient(
            $userId,
            $clientName,
            $contactPerson,
            $email,
            $phoneNumber,
            $address,
            $clientStatus,
            $clientType
        );

        if ($addSuccess) {
            $successMessage = "Client '{$clientName}' added successfully!";
            $auditTrailHandler->logActivity($userId, 'Client Added', "Client '{$clientName}' was added.", 'Clients', 'Success');
            // Clear form fields after successful submission
            $_POST = array(); // Clear POST data to reset form
        } else {
            $errorMessage = "Failed to add client. Please try again or check if email already exists.";
            $auditTrailHandler->logActivity($userId, 'Client Add Failed', "Attempt to add client '{$clientName}' failed.", 'Clients', 'Failed');
        }
    }
}

// --- Handle Delete Client Action ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_client_id'])) {
    $clientIdToDelete = filter_var($_POST['delete_client_id'], FILTER_VALIDATE_INT);

    if ($clientIdToDelete) {
        $clientDetails = $clientHandler->getClientByIdAndUserId($clientIdToDelete, $userId); // Get details for audit log

        if ($clientHandler->deleteClient($clientIdToDelete, $userId)) {
            $successMessage = "Client deleted successfully!";
            $auditDesc = "Client '{$clientDetails['client_name']}' (ID: {$clientIdToDelete}) deleted.";
            $auditTrailHandler->logActivity($userId, 'Client Deleted', $auditDesc, 'Clients', 'Success');
        } else {
            $errorMessage = "Failed to delete client or client not found.";
            $auditTrailHandler->logActivity($userId, 'Client Delete Failed', "Attempt to delete client ID: {$clientIdToDelete} failed.", 'Clients', 'Failed');
        }
    } else {
        $errorMessage = "Invalid client ID for deletion.";
    }
}


// --- Fetch Clients for Display (with Filtering and Pagination) ---
$searchTerm = trim($_GET['search'] ?? '');
$filterStatus = trim($_GET['status'] ?? 'All Status');
$filterType = trim($_GET['type'] ?? 'All Clients');

$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$page = $page > 0 ? $page : 1;
$limit = 5; // Number of clients per page
$offset = ($page - 1) * $limit;

$clientData = $clientHandler->getClients(
    $userId,
    $limit,
    $offset,
    $searchTerm,
    $filterStatus,
    $filterType
);

$clients = $clientData['entries'];
$totalClients = $clientData['total'];
$totalPages = ceil($totalClients / $limit);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients | AccountAble</title>
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

        /* Client Filters */
        .filter-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            background-color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: var(--shadow-sm);
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .search-bar {
            position: relative;
            width: 300px;
        }

        .search-bar input {
            width: 100%;
            padding: 0.6rem 1rem 0.6rem 2.5rem;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
        }

        .search-bar i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-color);
        }

        .filter-select {
            padding: 0.6rem 1rem;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-size: 0.9rem;
            background-color: white;
            color: var(--gray-color);
            cursor: pointer;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary-light);
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

        /* Clients Table */
        .clients-table-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .clients-table {
            width: 100%;
            border-collapse: collapse;
        }

        .clients-table th {
            text-align: left;
            padding: 1rem 1.5rem;
            font-size: 0.75rem;
            color: var(--gray-color);
            font-weight: 500;
            text-transform: uppercase;
            background-color: #f8fafc;
            border-bottom: 1px solid var(--light-gray);
        }

        .clients-table td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--light-gray);
            font-size: 0.875rem;
            vertical-align: middle;
        }

        .clients-table tr:last-child td {
            border-bottom: none;
        }

        .clients-table tr:hover {
            background-color: #f8fafc;
        }

        .client-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 0.75rem;
            border: 2px solid var(--primary-light);
        }

        .client-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .client-company {
            font-size: 0.8rem;
            color: var(--gray-color);
        }

        .client-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-badge {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .status-active {
            background-color: #e8f5e9;
            color: var(--success-color);
        }

        .status-active .status-badge {
            background-color: var(--success-color);
        }

        .status-inactive {
            background-color: #ffebee;
            color: var(--danger-color);
        }

        .status-inactive .status-badge {
            background-color: var(--danger-color);
        }

        .client-actions {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: transparent;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            color: var(--gray-color);
        }

        .action-btn:hover {
            background-color: var(--light-gray);
        }

        .action-btn.view:hover {
            color: var(--primary-color);
        }

        .action-btn.edit:hover {
            color: var(--warning-color);
        }

        .action-btn.delete:hover {
            color: var(--danger-color);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-top: 1px solid var(--light-gray);
        }

        .pagination-info {
            font-size: 0.875rem;
            color: var(--gray-color);
        }

        .pagination-controls {
            display: flex;
            gap: 0.5rem;
        }

        .page-btn {
            width: 36px;
            height: 36px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: white;
            border: 1px solid var(--light-gray);
            cursor: pointer;
            transition: var(--transition);
        }

        .page-btn:hover {
            background-color: var(--light-gray);
        }

        .page-btn.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            cursor: default;
        }

        .page-btn i {
            font-size: 0.8rem;
        }

        /* Add Client Form Styles */
        .add-client-form-container {
            background-color: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: var(--shadow-md);
            max-width: 700px;
            margin: 2rem auto; /* Center the form and add margin */
            display: none; /* Hidden by default */
        }

        .add-client-form-container.active {
            display: block; /* Show when active */
        }

        .form-header {
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .form-header h2 {
            font-size: 1.5rem;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .form-header p {
            color: var(--gray-color);
            font-size: 0.95rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark-color);
            font-size: 0.95rem;
        }

        .form-control, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: var(--transition);
        }

        .form-control:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
        }

        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }

        .btn-form-submit {
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
            margin-top: 1.5rem;
        }

        .btn-form-submit:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
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
            .filter-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .search-bar {
                width: 100%;
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

            .clients-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 1.5rem;
            }

            .filter-group {
                flex-direction: column;
                width: 100%;
                gap: 0.5rem;
            }

            .filter-select {
                width: 100%;
            }

            .client-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-check-double"></i>
                <span>AccountAble</span>
            </div>
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
            <a href="client.php" class="nav-item active">
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
                <h1>Client Management</h1>
                <p>Manage all your client relationships in one place</p>
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

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="filter-group">
                <form method="GET" action="client.php" style="display: flex; gap: 1rem;">
                    <div class="search-bar">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search clients..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    </div>
                    <select class="filter-select" name="status" onchange="this.form.submit()">
                        <option value="All Status" <?php echo ($filterStatus == 'All Status') ? 'selected' : ''; ?>>All Status</option>
                        <option value="Active" <?php echo ($filterStatus == 'Active') ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo ($filterStatus == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                    <select class="filter-select" name="type" onchange="this.form.submit()">
                        <option value="All Clients" <?php echo ($filterType == 'All Clients') ? 'selected' : ''; ?>>All Clients</option>
                        <option value="Premium" <?php echo ($filterType == 'Premium') ? 'selected' : ''; ?>>Premium</option>
                        <option value="Standard" <?php echo ($filterType == 'Standard') ? 'selected' : ''; ?>>Standard</option>
                    </select>
                </form>
            </div>
            <button class="btn btn-primary" id="addClientBtn">
                <i class="fas fa-plus"></i> Add Client
            </button>
        </div>

        <?php if (!empty($errorMessage)): ?>
            <div class="message-box error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>
        <?php if (!empty($successMessage)): ?>
            <div class="message-box success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>

        <!-- Add Client Form Container -->
        <div class="add-client-form-container" id="addClientFormContainer">
            <div class="form-header">
                <h2>Add New Client</h2>
                <p>Enter the details for the new client.</p>
            </div>
            <form method="POST" action="client.php">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="client_name">Client Name</label>
                        <input type="text" id="client_name" name="client_name" class="form-control" placeholder="e.g., ABC Corp" value="<?php echo htmlspecialchars($_POST['client_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="contact_person">Contact Person</label>
                        <input type="text" id="contact_person" name="contact_person" class="form-control" placeholder="e.g., John Doe" value="<?php echo htmlspecialchars($_POST['contact_person'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="e.g., info@abccorp.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="phone_number">Phone Number</label>
                        <input type="tel" id="phone_number" name="phone_number" class="form-control" placeholder="e.g., +256770000000" value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="client_status">Status</label>
                        <select id="client_status" name="client_status" class="form-select">
                            <option value="Active" <?php echo (($_POST['client_status'] ?? 'Active') == 'Active') ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo (($_POST['client_status'] ?? '') == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="client_type">Client Type</label>
                        <select id="client_type" name="client_type" class="form-select">
                            <option value="Standard" <?php echo (($_POST['client_type'] ?? 'Standard') == 'Standard') ? 'selected' : ''; ?>>Standard</option>
                            <option value="Premium" <?php echo (($_POST['client_type'] ?? '') == 'Premium') ? 'selected' : ''; ?>>Premium</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" class="form-textarea" placeholder="Client's full address"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                </div>
                <button type="submit" name="add_client_submit" class="btn-form-submit">Add Client</button>
            </form>
        </div>

        <!-- Clients Table -->
        <div class="clients-table-container">
            <table class="clients-table">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($clients)): ?>
                        <?php foreach ($clients as $client): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center;">
                                        <img src="https://placehold.co/40x40/4caf50/ffffff?text=<?php echo substr($client['client_name'], 0, 1); ?>" alt="Client Avatar" class="client-avatar">
                                        <div>
                                            <div class="client-name"><?php echo htmlspecialchars($client['client_name']); ?></div>
                                            <div class="client-company"><?php echo htmlspecialchars($client['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php echo !empty($client['contact_person']) ? htmlspecialchars($client['contact_person']) . '<br>' : ''; ?>
                                    <?php echo htmlspecialchars($client['phone_number']); ?>
                                </td>
                                <td>
                                    <span class="client-status status-<?php echo strtolower($client['status']); ?>">
                                        <span class="status-badge"></span>
                                        <?php echo htmlspecialchars($client['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="client-actions">
                                        <button class="action-btn view" title="View Client">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="action-btn edit" title="Edit Client">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" action="client.php" onsubmit="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($client['client_name']); ?>?');" style="display: inline;">
                                            <input type="hidden" name="delete_client_id" value="<?php echo htmlspecialchars($client['client_id']); ?>">
                                            <button type="submit" class="action-btn delete" title="Delete Client">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 20px;">No clients found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="pagination">
                <div class="pagination-info">
                    Showing <?php echo min($offset + 1, $totalClients); ?> to <?php echo min($offset + $limit, $totalClients); ?> of <?php echo $totalClients; ?> clients
                </div>
                <div class="pagination-controls">
                    <a href="client.php?page=<?php echo max(1, $page - 1); ?>&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo urlencode($filterStatus); ?>&type=<?php echo urlencode($filterType); ?>" class="page-btn <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="client.php?page=<?php echo $i; ?>&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo urlencode($filterStatus); ?>&type=<?php echo urlencode($filterType); ?>" class="page-btn <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    <a href="client.php?page=<?php echo min($totalPages, $page + 1); ?>&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo urlencode($filterStatus); ?>&type=<?php echo urlencode($filterType); ?>" class="page-btn <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
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

            // Show success/error messages briefly
            const messageBox = document.querySelector('.message-box');
            if (messageBox) {
                setTimeout(() => {
                    messageBox.style.display = 'none';
                }, 5000); // Hide after 5 seconds
            }
        });

        // Notification click handler (static for now)
        document.querySelector('.notification-icon').addEventListener('click', function() {
            alert('You have 3 new notifications'); // Replace with custom modal later
            this.querySelector('.notification-badge').style.display = 'none';
        });

        // Toggle Add Client Form visibility
        const addClientBtn = document.getElementById('addClientBtn');
        const addClientFormContainer = document.getElementById('addClientFormContainer');

        addClientBtn.addEventListener('click', function() {
            addClientFormContainer.classList.toggle('active');
            // Scroll to the form if it becomes visible
            if (addClientFormContainer.classList.contains('active')) {
                addClientFormContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });

        // Client action buttons (View/Edit - currently alerts, would navigate/open modal in real app)
        document.querySelectorAll('.action-btn.view').forEach(btn => {
            btn.addEventListener('click', function() {
                // To get the client ID, you'd typically have a data attribute on the button or a hidden input in the row.
                // Assuming client_id is available in a data attribute on the button itself or a parent row.
                const clientId = this.closest('tr').querySelector('.client-name').textContent; // Example: using client name for alert
                alert(`Viewing client: ${clientId}. This would open a detailed view.`);
            });
        });

        document.querySelectorAll('.action-btn.edit').forEach(btn => {
            btn.addEventListener('click', function() {
                const clientId = this.closest('tr').querySelector('.client-name').textContent; // Example: using client name for alert
                alert(`Editing client: ${clientId}. This would redirect to an edit form.`);
            });
        });

        // Search functionality (client-side for immediate feedback)
        const searchInput = document.querySelector('.search-bar input');
        searchInput.addEventListener('input', function() {
            // Re-submit the form on input change to trigger PHP filtering
            // For a smoother UX, consider debouncing this or using AJAX for live search
            // For now, it will trigger a full page reload with new search term
            this.closest('form').submit();
        });

        // Pagination controls handled by PHP links, no JS needed for active state
        // The JS for pagination buttons is removed as PHP now handles the links
    </script>
</body>
</html>
