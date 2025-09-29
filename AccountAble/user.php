<?php
class User {
    private $pdo; // PDO database connection object

    /**
     * Constructor
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Registers a new user.
     *
     * @param string $fullName The full name of the user.
     * @param string $email The user's email address (must be unique).
     * @param string $phoneNumber The user's phone number.
     * @param string $password The user's plain-text password.
     * @param string|null $companyName The user's company name (optional).
     * @return bool True on successful registration, false otherwise.
     */
    public function register($fullName, $email, $phoneNumber, $password, $companyName = null) {
        // Hash the password before storing it in the database
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        try {
            // Prepare the SQL statement for inserting a new user
            $stmt = $this->pdo->prepare(
                "INSERT INTO Users (full_name, email, phone_number, password_hash, company_name)
                VALUES (:full_name, :email, :phone_number, :password_hash, :company_name)"
            );

            // Bind parameters to the prepared statement
            $stmt->bindParam(':full_name', $fullName);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone_number', $phoneNumber);
            $stmt->bindParam(':password_hash', $passwordHash);
            $stmt->bindParam(':company_name', $companyName);

            // Execute the statement
            return $stmt->execute();

        } catch (PDOException $e) {
            // Log the error (e.g., duplicate email)
            error_log("User registration failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Authenticates a user.
     *
     * @param string $email The user's email address.
     * @param string $password The user's plain-text password.
     * @return array|false An associative array of user data on successful login, false otherwise.
     */
    public function login($email, $password) {
        try {
            // Prepare the SQL statement to fetch user by email
            $stmt = $this->pdo->prepare("SELECT * FROM Users WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            // Fetch the user record
            $user = $stmt->fetch();

            // If a user is found, verify the password
            if ($user && password_verify($password, $user['password_hash'])) {
                // Password is correct, return user data (excluding the hash for security)
                unset($user['password_hash']);
                return $user;
            } else {
                // User not found or password incorrect
                return false;
            }

        } catch (PDOException $e) {
            // Log the error
            error_log("User login failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches user details by user ID.
     *
     * @param int $userId The ID of the user.
     * @return array|false An associative array of user data, or false if not found.
     */
    public function getUserById($userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM Users WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch();
            if ($user) {
                unset($user['password_hash']); // Don't return the hash
            }
            return $user;
        } catch (PDOException $e) {
            error_log("Error fetching user by ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Updates user profile information.
     *
     * @param int $userId The ID of the user to update.
     * @param array $data An associative array of data to update (e.g., ['full_name' => 'New Name']).
     * @return bool True on success, false on failure.
     */
    public function updateProfile($userId, $data) {
        $setClauses = [];
        $params = ['user_id' => $userId];

        foreach ($data as $key => $value) {
            // Ensure only allowed fields are updated
            if (in_array($key, ['full_name', 'email', 'phone_number', 'company_name', 'avatar_url', 'default_currency', 'timezone', 'language'])) {
                $setClauses[] = "$key = :$key";
                $params[":$key"] = $value;
            }
        }

        if (empty($setClauses)) {
            return false; // No valid fields to update
        }

        $sql = "UPDATE Users SET " . implode(', ', $setClauses) . " WHERE user_id = :user_id";

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Error updating user profile: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Updates user security settings (e.g., 2FA, blockchain verification, login notifications).
     *
     * @param int $userId The ID of the user to update.
     * @param array $settings An associative array of settings (e.g., ['two_factor_enabled' => true]).
     * @return bool True on success, false on failure.
     */
    public function updateSecuritySettings($userId, $settings) {
        $setClauses = [];
        $params = ['user_id' => $userId];

        foreach ($settings as $key => $value) {
            if (in_array($key, ['two_factor_enabled', 'blockchain_verification_enabled', 'login_notifications_enabled', 'auto_lock_enabled'])) {
                $setClauses[] = "$key = :$key";
                $params[":$key"] = $value;
            }
        }

        if (empty($setClauses)) {
            return false;
        }

        $sql = "UPDATE Users SET " . implode(', ', $setClauses) . " WHERE user_id = :user_id";

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Error updating security settings: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Updates a user's password.
     *
     * @param int $userId The ID of the user.
     * @param string $newPassword The new plain-text password.
     * @return bool True on success, false on failure.
     */
    public function updatePassword($userId, $newPassword) {
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
        try {
            $stmt = $this->pdo->prepare("UPDATE Users SET password_hash = :password_hash WHERE user_id = :user_id");
            $stmt->bindParam(':password_hash', $passwordHash);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating password: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes a user account.
     *
     * @param int $userId The ID of the user to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteUser($userId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM Users WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error deleting user: " . $e->getMessage());
            return false;
        }
    }
}

// Handle image upload for events
    if (isset($_FILES['image_url']) && $_FILES['image_url']['error'] == 0) {
        $file_tmp_name = $_FILES['image_url']['tmp_name'];
        $file_name = uniqid() . '_' . basename($_FILES['image_url']['name']); // Generate unique filename
        $upload_path = $event_upload_dir . $file_name;

        if (move_uploaded_file($file_tmp_name, $upload_path)) {
            $image_url = $upload_path; // Store the relative path in the database
        } else {
            // Handle upload error (e.g., insufficient permissions, disk space)
            echo "Error uploading event image.";
        }
    }
?>
