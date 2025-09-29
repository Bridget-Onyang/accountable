-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jul 21, 2025 at 01:49 AM
-- Server version: 8.2.0
-- PHP Version: 8.2.13

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `accountabledb`
--

-- --------------------------------------------------------

--
-- Table structure for table `audittrail`
--

DROP TABLE IF EXISTS `audittrail`;
CREATE TABLE IF NOT EXISTS `audittrail` (
  `audit_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `activity_title` varchar(255) NOT NULL,
  `activity_description` text,
  `activity_type` enum('Transactions','User Actions','System Events','Invoices','Clients') NOT NULL,
  `activity_status` enum('Verified','Pending','Failed','Success') DEFAULT 'Success',
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_info` varchar(255) DEFAULT NULL,
  `blockchain_hash` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`audit_id`),
  UNIQUE KEY `blockchain_hash` (`blockchain_hash`(191)),
  KEY `idx_audit_trail_user_id` (`user_id`),
  KEY `idx_audit_trail_timestamp` (`timestamp`)
) ENGINE=MyISAM AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 ;

--
-- Dumping data for table `audittrail`
--

INSERT INTO `audittrail` (`audit_id`, `user_id`, `activity_title`, `activity_description`, `activity_type`, `activity_status`, `timestamp`, `ip_address`, `device_info`, `blockchain_hash`) VALUES
(1, 1, 'Client Added', 'Client \'Mawiz Industries Ltd\' was added.', 'Clients', 'Success', '2025-07-20 22:02:06', NULL, NULL, NULL),
(2, 1, 'Transaction Added', 'New transaction \'office chairs\' with amount 500000 added.', 'Transactions', 'Success', '2025-07-20 22:04:28', NULL, NULL, '0x03ed9867318c77168bfcca5a7b88f7f9021d05e7b8018181633811fd484e3940'),
(3, 1, 'Invoice Added', 'New invoice for client ID 1 with amount 800000 added.', 'Invoices', 'Success', '2025-07-20 22:55:55', NULL, NULL, '0x63bfc7585ea0bc0722e52f8269157714c21c5139d7d7831e332f30d27b13b5b2'),
(4, 1, 'Blockchain Verification Failed', 'Attempt to verify hash \'0x63bfc7585ea0bc0722e52f8269157714c21c5139d7d7831e332f30d27b13b5b2.\' failed (not found).', '', 'Failed', '2025-07-20 23:04:42', NULL, NULL, '0x63bfc7585ea0bc0722e52f8269157714c21c5139d7d7831e332f30d27b13b5b2.'),
(5, 1, 'Advanced Settings Update', 'User advanced settings updated.', '', 'Success', '2025-07-20 23:10:01', NULL, NULL, NULL),
(6, 1, 'Advanced Settings Update', 'User advanced settings updated.', '', 'Success', '2025-07-20 23:10:43', NULL, NULL, NULL),
(7, 1, 'Advanced Settings Update', 'User advanced settings updated.', '', 'Success', '2025-07-20 23:10:49', NULL, NULL, NULL),
(8, 1, 'Advanced Settings Update', 'User advanced settings updated.', '', 'Success', '2025-07-20 23:11:13', NULL, NULL, NULL),
(9, 1, 'Client Added', 'Client \'Isaac Tech Org\' was added.', 'Clients', 'Success', '2025-07-21 00:09:51', NULL, NULL, NULL),
(10, 1, 'User Logout', 'User logged out successfully.', 'User Actions', 'Success', '2025-07-21 00:26:43', NULL, NULL, NULL),
(11, 1, 'User Logout', 'User logged out successfully.', 'User Actions', 'Success', '2025-07-21 00:29:14', NULL, NULL, NULL),
(12, 1, 'User Logout', 'User logged out successfully.', 'User Actions', 'Success', '2025-07-21 01:04:59', NULL, NULL, NULL),
(13, 1, 'Advanced Settings Update', 'User advanced settings updated.', '', 'Success', '2025-07-21 01:24:31', NULL, NULL, NULL),
(14, 1, 'Advanced Settings Update', 'User advanced settings updated.', '', 'Success', '2025-07-21 01:24:39', NULL, NULL, NULL),
(15, 1, 'Profile Update', 'User profile updated.', '', 'Success', '2025-07-21 01:25:19', NULL, NULL, NULL),
(16, 1, 'Profile Update', 'User profile updated.', '', 'Success', '2025-07-21 01:27:48', NULL, NULL, NULL),
(17, 1, 'Transaction Updated', 'Transaction ID: 1 updated.', 'Transactions', 'Success', '2025-07-21 01:45:07', NULL, NULL, NULL),
(18, 1, 'Transaction Updated', 'Transaction ID: 1 updated.', 'Transactions', 'Success', '2025-07-21 01:45:39', NULL, NULL, NULL),
(19, 1, 'Transaction Updated', 'Transaction ID: 1 updated.', 'Transactions', 'Success', '2025-07-21 01:45:56', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
CREATE TABLE IF NOT EXISTS `clients` (
  `client_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `client_name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone_number` varchar(50) DEFAULT NULL,
  `address` text,
  `total_value` decimal(18,2) DEFAULT '0.00',
  `last_activity` timestamp NULL DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `client_type` enum('Premium','Standard') DEFAULT 'Standard',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`client_id`),
  KEY `idx_clients_user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 ;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`client_id`, `user_id`, `client_name`, `contact_person`, `email`, `phone_number`, `address`, `total_value`, `last_activity`, `status`, `client_type`, `created_at`, `updated_at`) VALUES
(1, 1, 'Mawiz Industries Ltd', 'Moses Magezi Timothy', 'mawiz@gmail.com', '+256700000001', 'Kisaasi', 0.00, NULL, 'Active', 'Standard', '2025-07-20 22:02:05', '2025-07-20 22:02:05'),
(2, 1, 'Isaac Tech Org', 'Isaac Mulamu', 'mulamuisaac@gmail.com', '+256773347665', 'kyanja', 0.00, NULL, 'Inactive', 'Premium', '2025-07-21 00:09:50', '2025-07-21 00:09:50');

-- --------------------------------------------------------

--
-- Table structure for table `connecteddevices`
--

DROP TABLE IF EXISTS `connecteddevices`;
CREATE TABLE IF NOT EXISTS `connecteddevices` (
  `device_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `device_name` varchar(255) NOT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`device_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 ;

-- --------------------------------------------------------

--
-- Table structure for table `invoiceitems`
--

DROP TABLE IF EXISTS `invoiceitems`;
CREATE TABLE IF NOT EXISTS `invoiceitems` (
  `item_id` int NOT NULL AUTO_INCREMENT,
  `invoice_id` int NOT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(18,2) NOT NULL,
  `total_price` decimal(18,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`item_id`),
  KEY `invoice_id` (`invoice_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 ;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
CREATE TABLE IF NOT EXISTS `invoices` (
  `invoice_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `client_id` int NOT NULL,
  `invoice_number` varchar(100) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `currency` varchar(10) NOT NULL,
  `status` enum('Paid','Pending','Overdue','Draft') DEFAULT 'Draft',
  `blockchain_verified` tinyint(1) DEFAULT '0',
  `blockchain_hash` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`invoice_id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  UNIQUE KEY `blockchain_hash` (`blockchain_hash`(191)),
  KEY `idx_invoices_user_id` (`user_id`),
  KEY `idx_invoices_client_id` (`client_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 ;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`invoice_id`, `user_id`, `client_id`, `invoice_number`, `invoice_date`, `due_date`, `amount`, `currency`, `status`, `blockchain_verified`, `blockchain_hash`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '', '2025-07-20', '2025-07-20', 800000.00, '', 'Overdue', 0, '0x63bfc7585ea0bc0722e52f8269157714c21c5139d7d7831e332f30d27b13b5b2', '2025-07-20 22:55:55', '2025-07-20 22:55:55');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `idx_notifications_user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 ;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
CREATE TABLE IF NOT EXISTS `transactions` (
  `transaction_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `client_id` int DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `description` text,
  `category` varchar(100) DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL,
  `currency` varchar(10) NOT NULL,
  `transaction_type` enum('Income','Expense','Transfer') NOT NULL,
  `blockchain_status` enum('Verified','Pending','Failed') DEFAULT 'Pending',
  `blockchain_hash` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`transaction_id`),
  UNIQUE KEY `blockchain_hash` (`blockchain_hash`(191)),
  KEY `client_id` (`client_id`),
  KEY `idx_transactions_user_id` (`user_id`),
  KEY `idx_transactions_date` (`transaction_date`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 ;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`transaction_id`, `user_id`, `client_id`, `transaction_date`, `description`, `category`, `amount`, `currency`, `transaction_type`, `blockchain_status`, `blockchain_hash`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '2025-07-17', 'office chairs', 'General', 500000.00, 'UGX', 'Income', '', '0x03ed9867318c77168bfcca5a7b88f7f9021d05e7b8018181633811fd484e3940', '2025-07-20 22:04:28', '2025-07-21 01:45:55');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(50) NOT NULL,
  `email` varchar(75) NOT NULL,
  `phone_number` varchar(50) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `company_name` varchar(100) DEFAULT NULL,
  `user_role` varchar(15) DEFAULT 'Verified Member',
  `avatar_url` varchar(100) DEFAULT NULL,
  `two_factor_enabled` tinyint(1) DEFAULT '0',
  `blockchain_verification_enabled` tinyint(1) DEFAULT '1',
  `login_notifications_enabled` tinyint(1) DEFAULT '1',
  `default_currency` varchar(10) DEFAULT 'UGX',
  `timezone` varchar(100) DEFAULT 'Africa/Kampala',
  `language` varchar(5) DEFAULT 'en',
  `auto_lock_enabled` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 ;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `phone_number`, `password_hash`, `company_name`, `user_role`, `avatar_url`, `two_factor_enabled`, `blockchain_verification_enabled`, `login_notifications_enabled`, `default_currency`, `timezone`, `language`, `auto_lock_enabled`, `created_at`, `updated_at`) VALUES
(1, 'Onesmus Freedom', 'freeones@gmail.com', '0776541901', '$2y$10$h6fQvmTRbpNwpoE3mYrPiOJZOGkXAkKV1.63TWm6p0laU7gNDeR0y', NULL, 'Verified Member', NULL, 0, 1, 1, 'UGX', 'Africa/Kampala', 'en', 0, '2025-07-20 14:45:23', '2025-07-20 23:11:13');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
