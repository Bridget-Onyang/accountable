<?php
session_start(); // Start the session

// Include the database connection and AuditTrail class
require_once 'db_connect.php';
require_once 'user.php';

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


// Initialize AuditTrail for logging
$auditTrailHandler = new AuditTrail($pdo);

// Log the logout activity if a user was logged in
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $auditTrailHandler->logActivity($userId, 'User Logout', 'User logged out successfully.', 'User Actions', 'Success');
}

// Destroy all session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Redirect to the login page after logout
header("Location: login.php?logout_success=1");
exit();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Logout</title>
    <style>
      body {
        background: #f5f5f5;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        margin: 0;
        font-family: Arial, sans-serif;
      }

      .logout-container {
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        text-align: center;
        max-width: 400px;
        width: 90%;
      }

      h4 {
        color: #2c3e50;
        font-size: 1.5rem;
        margin-bottom: 10px;
      }

      p {
        color: #555;
        font-size: 1rem;
        margin-bottom: 20px;
      }

      .btn {
        background: #2e7d32;
        color: white;
        text-decoration: none;
        padding: 10px 20px;
        border-radius: 5px;
        font-size: 1rem;
        display: inline-block;
        transition: 0.3s ease;
      }

      .btn:hover {
        background: #1b5e20; /* Darker green on hover */
      }
    </style>
  </head>
  <body>
    <div class="logout-container">
      <h4>You have been logged out successfully.</h4>
      <p>Thank you for using our service. Click below to log in again.</p>
      <a href="login.php" class="btn">Login Again</a>
    </div>
  </body>
</html>
