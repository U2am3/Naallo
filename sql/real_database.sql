-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 31, 2025 at 06:15 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ems_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `activity_type` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `emp_id` int(11) DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `status` enum('present','late','absent','half-day') DEFAULT 'present',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_hours` decimal(4,2) DEFAULT NULL,
  `auto_status` enum('present','late','absent','half-day') DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_info` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`attendance_id`, `emp_id`, `attendance_date`, `time_in`, `time_out`, `status`, `notes`, `created_at`, `total_hours`, `auto_status`, `ip_address`, `device_info`) VALUES
(1, 3, '2025-04-11', '01:36:00', '00:00:00', 'present', 'vbgfgf fhgfjh fgfjk', '2025-04-11 03:37:22', NULL, NULL, NULL, NULL),
(2, 3, '2025-04-12', '09:47:00', '21:47:00', 'late', '', '2025-04-12 06:47:48', NULL, NULL, NULL, NULL),
(3, 3, '2025-04-14', '09:41:37', '09:49:57', 'present', NULL, '2025-04-14 07:41:37', NULL, NULL, NULL, NULL),
(4, 5, '2025-04-14', '17:01:36', '20:00:21', 'present', NULL, '2025-04-14 15:01:36', NULL, NULL, NULL, NULL),
(5, 5, '2025-04-15', '08:51:46', '08:51:48', 'present', NULL, '2025-04-15 06:51:46', NULL, NULL, NULL, NULL),
(6, 3, '2025-04-15', '08:57:52', NULL, 'present', NULL, '2025-04-15 06:57:52', NULL, NULL, NULL, NULL),
(7, NULL, '2025-04-22', '10:51:46', NULL, 'present', NULL, '2025-04-22 08:51:46', NULL, NULL, NULL, NULL),
(8, NULL, '2025-04-22', '10:51:53', NULL, 'present', NULL, '2025-04-22 08:51:53', NULL, NULL, NULL, NULL),
(9, NULL, '2025-04-22', '10:52:02', NULL, 'present', NULL, '2025-04-22 08:52:02', NULL, NULL, NULL, NULL),
(10, NULL, '2025-04-22', '10:52:08', NULL, 'present', NULL, '2025-04-22 08:52:08', NULL, NULL, NULL, NULL),
(11, 3, '2025-04-22', '10:52:36', '12:59:10', 'present', NULL, '2025-04-22 08:52:36', NULL, NULL, NULL, NULL),
(12, 7, '2025-04-22', '13:22:51', NULL, 'present', NULL, '2025-04-22 11:22:51', NULL, NULL, NULL, NULL),
(13, 5, '2025-04-22', '13:55:14', '13:55:15', 'present', NULL, '2025-04-22 11:55:14', NULL, NULL, NULL, NULL),
(14, 9, '2025-04-22', '14:32:08', '14:32:17', 'present', NULL, '2025-04-22 12:32:08', NULL, NULL, NULL, NULL),
(15, 10, '2025-04-22', '16:04:40', '16:04:49', 'present', NULL, '2025-04-22 14:04:40', NULL, NULL, NULL, NULL),
(16, 5, '2025-04-23', '10:38:59', '10:39:11', 'late', NULL, '2025-04-23 08:38:59', 0.00, 'late', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36'),
(17, 6, '2025-04-23', '08:00:00', '17:00:00', 'present', NULL, '2025-04-23 09:05:52', 9.00, 'present', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36'),
(18, 6, '2025-04-23', '08:00:00', '17:00:00', 'present', NULL, '2025-04-23 09:06:54', 9.00, 'present', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36'),
(19, 4, '2025-04-23', '11:06:56', NULL, 'late', NULL, '2025-04-23 09:06:56', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36'),
(20, 3, '2025-04-24', '09:23:36', '09:40:39', 'late', NULL, '2025-04-24 07:23:36', 0.28, 'late', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36'),
(21, 5, '2025-04-24', '09:45:27', NULL, 'late', NULL, '2025-04-24 07:45:27', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36'),
(22, 10, '2025-04-24', '11:12:00', '19:14:00', 'present', NULL, '2025-04-24 08:12:46', 8.03, 'present', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36'),
(23, 10, '2025-04-24', '11:12:00', '19:14:00', 'present', NULL, '2025-04-24 08:13:30', 8.03, 'present', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36'),
(24, 10, '2025-04-24', '11:12:00', '19:14:00', 'present', NULL, '2025-04-24 08:15:11', 8.03, 'present', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36'),
(25, 10, '2025-04-24', '11:12:00', '19:14:00', 'present', NULL, '2025-04-24 08:16:26', 8.03, 'present', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36'),
(26, 5, '2025-04-25', '17:16:53', NULL, 'late', NULL, '2025-04-25 15:16:53', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36'),
(27, 3, '2025-04-25', '17:17:46', NULL, 'late', NULL, '2025-04-25 15:17:46', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36'),
(28, 5, '2025-05-02', '18:40:51', NULL, 'late', NULL, '2025-05-02 16:40:51', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36'),
(29, NULL, '2025-05-02', '19:43:16', NULL, 'late', NULL, '2025-05-02 17:43:16', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36'),
(30, 5, '2025-05-27', '21:42:19', '23:09:43', 'late', NULL, '2025-05-27 19:42:19', 1.46, 'late', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36'),
(31, 5, '2025-05-28', '05:47:25', NULL, 'late', NULL, '2025-05-28 02:47:25', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36'),
(32, 3, '2025-05-28', '19:37:26', '20:10:05', 'late', NULL, '2025-05-28 17:37:26', 0.54, 'late', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36'),
(33, 3, '2025-05-29', '04:49:13', NULL, 'late', NULL, '2025-05-29 02:49:13', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36'),
(34, 3, '2025-05-31', '05:38:39', NULL, 'late', NULL, '2025-05-31 03:38:39', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_bonus_config`
--

CREATE TABLE `attendance_bonus_config` (
  `config_id` int(11) NOT NULL,
  `min_attendance_percentage` decimal(5,2) NOT NULL,
  `max_attendance_percentage` decimal(5,2) NOT NULL,
  `bonus_percentage` decimal(5,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `attendance_bonus_config`
--

INSERT INTO `attendance_bonus_config` (`config_id`, `min_attendance_percentage`, `max_attendance_percentage`, `bonus_percentage`, `created_at`, `updated_at`) VALUES
(1, 95.00, 100.00, 5.00, '2025-04-14 08:37:26', '2025-04-14 08:37:26'),
(2, 90.00, 94.99, 3.00, '2025-04-14 08:37:26', '2025-04-14 08:37:26'),
(3, 85.00, 89.99, 2.00, '2025-04-14 08:37:26', '2025-04-14 08:37:26'),
(4, 80.00, 84.99, 1.00, '2025-04-14 08:37:26', '2025-04-14 08:37:26');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_notifications`
--

CREATE TABLE `attendance_notifications` (
  `notification_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `notification_type` enum('check_in_reminder','check_out_reminder','absent_notification') NOT NULL,
  `notification_date` date NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `attendance_notifications`
--

INSERT INTO `attendance_notifications` (`notification_id`, `emp_id`, `notification_type`, `notification_date`, `is_read`, `created_at`) VALUES
(1, 5, '', '2025-04-23', 0, '2025-04-23 08:38:59'),
(2, 5, '', '2025-04-23', 0, '2025-04-23 08:39:11'),
(3, 6, '', '2025-04-23', 0, '2025-04-23 09:05:52'),
(4, 6, '', '2025-04-23', 0, '2025-04-23 09:06:54'),
(5, 4, '', '2025-04-23', 0, '2025-04-23 09:06:56'),
(6, 3, '', '2025-04-24', 0, '2025-04-24 07:23:36'),
(7, 3, '', '2025-04-24', 0, '2025-04-24 07:40:39'),
(8, 5, '', '2025-04-24', 0, '2025-04-24 07:45:27'),
(9, 10, '', '2025-04-24', 0, '2025-04-24 08:12:46'),
(10, 10, '', '2025-04-24', 0, '2025-04-24 08:13:30'),
(11, 10, '', '2025-04-24', 0, '2025-04-24 08:15:11'),
(12, 10, '', '2025-04-24', 0, '2025-04-24 08:16:26'),
(13, 5, '', '2025-04-25', 0, '2025-04-25 15:16:53'),
(14, 3, '', '2025-04-25', 0, '2025-04-25 15:17:46'),
(15, 5, '', '2025-05-02', 0, '2025-05-02 16:40:51'),
(16, 5, '', '2025-05-27', 0, '2025-05-27 19:42:19'),
(17, 5, '', '2025-05-27', 0, '2025-05-27 20:09:43'),
(18, 5, '', '2025-05-28', 0, '2025-05-28 02:47:25'),
(19, 3, '', '2025-05-28', 0, '2025-05-28 17:37:26'),
(20, 3, '', '2025-05-28', 0, '2025-05-28 18:08:19'),
(21, 3, '', '2025-05-28', 0, '2025-05-28 18:09:09'),
(22, 3, '', '2025-05-28', 0, '2025-05-28 18:09:29'),
(23, 3, '', '2025-05-28', 0, '2025-05-28 18:10:05'),
(24, 3, '', '2025-05-29', 0, '2025-05-29 02:49:13'),
(25, 3, '', '2025-05-31', 0, '2025-05-31 03:38:39');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_performance`
--

CREATE TABLE `attendance_performance` (
  `performance_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `total_working_days` int(11) NOT NULL,
  `days_present` int(11) NOT NULL,
  `days_late` int(11) NOT NULL,
  `days_absent` int(11) NOT NULL,
  `days_half_day` int(11) NOT NULL,
  `attendance_percentage` decimal(5,2) NOT NULL,
  `bonus_percentage` decimal(5,2) NOT NULL,
  `bonus_amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `attendance_performance`
--

INSERT INTO `attendance_performance` (`performance_id`, `emp_id`, `month`, `year`, `total_working_days`, `days_present`, `days_late`, `days_absent`, `days_half_day`, `attendance_percentage`, `bonus_percentage`, `bonus_amount`, `created_at`, `updated_at`) VALUES
(1, 3, 4, 2025, 22, 2, 1, 0, 0, 9.09, 0.00, 0.00, '2025-04-14 08:48:39', '2025-04-14 08:48:39'),
(2, 5, 4, 2025, 22, 1, 0, 0, 0, 4.55, 0.00, 0.00, '2025-04-15 06:29:37', '2025-04-15 06:29:37');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_policy`
--

CREATE TABLE `attendance_policy` (
  `policy_id` int(11) NOT NULL,
  `min_hours_present` decimal(4,2) NOT NULL DEFAULT 8.00,
  `min_hours_late` decimal(4,2) NOT NULL DEFAULT 5.00,
  `grace_period_minutes` int(11) NOT NULL DEFAULT 15,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_reports`
--

CREATE TABLE `attendance_reports` (
  `report_id` int(11) NOT NULL,
  `report_type` enum('daily','monthly','department','employee') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `emp_id` int(11) DEFAULT NULL,
  `report_data` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `dept_id` int(11) NOT NULL,
  `dept_name` varchar(100) NOT NULL,
  `dept_head` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`dept_id`, `dept_name`, `dept_head`, `created_at`) VALUES
(1, 'Information Technology', 9, '2025-04-11 03:32:24'),
(2, 'marketing', 8, '2025-04-14 14:32:00'),
(3, 'finance', 20, '2025-04-22 13:59:40'),
(4, 'ooo', 22, '2025-05-29 18:42:43');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `emp_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `dept_id` int(11) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `basic_salary` decimal(10,2) DEFAULT 0.00,
  `salary` decimal(10,2) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `gender` enum('male','female') NOT NULL DEFAULT 'male'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`emp_id`, `user_id`, `first_name`, `last_name`, `dept_id`, `position`, `hire_date`, `basic_salary`, `salary`, `phone`, `address`, `profile_image`, `gender`) VALUES
(3, 4, 'usame', 'abdiwahab', 1, 'ui designer', '2025-04-11', 500.00, 1000.00, '0614594306', 'kismayo-somalia', 'employee_4_1746427062.png', ''),
(4, 8, 'mohamed', 'yusuf', 2, 'manager', '2025-04-14', 1000.00, 1000.00, '0614500309', 'kismayo-somalia', NULL, ''),
(5, 9, 'aden', 'mohamed', 1, 'manager', '2025-04-14', 1000.00, 1000.00, '0618726609', 'kismayo-somalia', 'manager_9_1746776043.jpeg', ''),
(6, 11, 'Abdikadar', 'Mohamed', 2, 'manager', '2025-04-15', 500.00, NULL, 'yiuyh', 'dhfbdhfdbdje', NULL, ''),
(7, 12, 'najiib', 'waberi', 2, 'marketer', '2025-04-22', 2000.00, NULL, '0615432124', 'kismayo - guulwade\r\n', NULL, 'male'),
(8, 13, 'sadam', 'mohamed', 2, 'marketer', '2025-04-22', 500.00, NULL, '0615658743', 'kismao - faanole', NULL, 'male'),
(9, 15, 'cabdi', 'axmed', 1, 'developer', '2025-04-22', 1000.00, NULL, '0876543265', 'Calanley-kismayo', NULL, 'male'),
(10, 16, 'axmed', 'mohamed', 1, 'developer', '2025-04-22', 500.00, NULL, '0615765432', 'Calanley-kismayo', NULL, 'male'),
(11, 18, 'yusuf', 'mohamed??', 3, 'developer', '2025-05-02', 500.00, NULL, 'bjkkggn', 'Calanley-kismayo', NULL, 'male'),
(12, 20, 'ismail', 'aden', NULL, NULL, '2025-05-05', 1000.00, NULL, '0615657687', NULL, NULL, 'male'),
(13, 21, 'aasiyo', 'mohamed', 3, 'manager', '2025-05-10', 2000.00, NULL, '5432657854', 'Calanley-kismayo', NULL, 'male'),
(14, 22, 'abubakar', 'cabdiwahab', NULL, NULL, '2025-05-29', 1000.00, NULL, '0614594300', NULL, NULL, 'male');

-- --------------------------------------------------------

--
-- Table structure for table `employee_leave_balance`
--

CREATE TABLE `employee_leave_balance` (
  `balance_id` int(11) NOT NULL,
  `emp_id` int(11) DEFAULT NULL,
  `leave_type_id` int(11) DEFAULT NULL,
  `total_leaves` int(11) DEFAULT 0,
  `used_leaves` int(11) DEFAULT 0,
  `year` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `employee_leave_balance`
--

INSERT INTO `employee_leave_balance` (`balance_id`, `emp_id`, `leave_type_id`, `total_leaves`, `used_leaves`, `year`) VALUES
(1, 3, 1, 0, 2, 2025),
(2, 10, 1, 0, 3, 2025);

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `leave_id` int(11) NOT NULL,
  `emp_id` int(11) DEFAULT NULL,
  `requested_by_role` enum('employee','manager') NOT NULL DEFAULT 'employee',
  `leave_type_id` int(11) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reason` text DEFAULT NULL,
  `admin_remarks` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `leave_requests`
--

INSERT INTO `leave_requests` (`leave_id`, `emp_id`, `requested_by_role`, `leave_type_id`, `start_date`, `end_date`, `status`, `reason`, `admin_remarks`, `approved_by`, `approved_at`, `created_at`) VALUES
(1, 3, 'employee', 1, '2025-04-15', '2025-04-16', 'approved', 'hjfyuhjvj yfjtiu', 'okay!!', 1, NULL, '2025-04-14 07:55:30'),
(2, 10, 'employee', 1, '2025-04-22', '2025-04-24', 'approved', 'i\'m sick', 'waa ku ogolaade\r\n', 1, NULL, '2025-04-22 14:05:36'),
(3, 3, 'employee', 3, '2025-04-26', '2025-04-27', '', 'PERSONAL', NULL, NULL, NULL, '2025-04-26 07:13:01'),
(4, 11, 'employee', 6, '2025-05-06', '2025-05-07', 'pending', 'hhhhhhhhhh', NULL, NULL, NULL, '2025-05-05 07:19:07'),
(5, 5, 'manager', 6, '2025-05-10', '2025-05-24', '', 'sick', NULL, NULL, NULL, '2025-05-09 13:46:32'),
(6, 3, 'employee', 4, '2025-05-27', '2025-05-30', 'approved', 'nkeknkle', 'ok', 9, '2025-05-27 23:54:56', '2025-05-27 19:54:21'),
(7, 3, 'employee', 6, '2025-05-28', '2025-05-29', '', 'jdkdd', NULL, NULL, NULL, '2025-05-28 18:16:01');

-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--

CREATE TABLE `leave_types` (
  `leave_type_id` int(11) NOT NULL,
  `leave_type_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `default_days` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `leave_types`
--

INSERT INTO `leave_types` (`leave_type_id`, `leave_type_name`, `description`, `default_days`) VALUES
(1, 'Annual Leave', 'Yearly vacation leave', 14),
(2, 'Sick Leave', 'Medical and health-related leave', 5),
(3, 'Personal Leave', 'Leave for personal matters', 5),
(4, 'Maternity Leave', 'Leave for childbirth and care', 90),
(5, 'Paternity Leave', 'Leave for new fathers', 7),
(6, 'Bereavement Leave', 'Leave for family death', 3);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `type`, `message`, `reference_id`, `is_read`, `created_at`) VALUES
(1, 12, 'project_update', 'Project \"desing\" status updated to: in_progress by manager', 5, 0, '2025-04-25 14:01:22'),
(2, 16, 'project_assignment', 'You have been assigned to project: uaz', 7, 0, '2025-04-25 14:05:42'),
(3, 4, 'project_assignment', 'You have been assigned to project: uaz', 7, 0, '2025-04-25 14:05:42'),
(4, 9, 'project_assignment', 'You have been assigned as manager for project: uaz', 7, 0, '2025-04-25 14:05:42'),
(5, 16, 'project_update', 'Project \"uaz\" status updated to: in_progress by manager', 7, 0, '2025-04-30 05:18:28'),
(6, 4, 'project_update', 'Project \"uaz\" status updated to: in_progress by manager', 7, 0, '2025-04-30 05:18:28'),
(8, 16, 'project_update', 'Project \"uaz\" status updated to: completed by manager', 7, 0, '2025-05-03 04:59:20'),
(9, 4, 'project_update', 'Project \"uaz\" status updated to: completed by manager', 7, 0, '2025-05-03 04:59:20'),
(11, 16, 'project_update', 'Project \"uaz\" status updated to: in_progress by manager', 7, 0, '2025-05-04 06:54:21'),
(12, 4, 'project_update', 'Project \"uaz\" status updated to: in_progress by manager', 7, 0, '2025-05-04 06:54:21'),
(14, 16, 'project_update', 'Project \"uaz\" status updated to: completed by manager', 7, 0, '2025-05-04 06:54:45'),
(15, 4, 'project_update', 'Project \"uaz\" status updated to: completed by manager', 7, 0, '2025-05-04 06:54:45'),
(17, 16, 'project_update', 'Project \"uaz\" status updated to: in_progress by manager', 7, 0, '2025-05-04 06:54:54'),
(18, 4, 'project_update', 'Project \"uaz\" status updated to: in_progress by manager', 7, 0, '2025-05-04 06:54:54'),
(20, 16, 'project_update', 'Project \"uaz\" status updated to: completed by manager', 7, 0, '2025-05-04 06:55:06'),
(21, 4, 'project_update', 'Project \"uaz\" status updated to: completed by manager', 7, 0, '2025-05-04 06:55:06'),
(25, 11, 'project_assignment', 'You have been assigned to project: wbkjww', 12, 0, '2025-05-21 18:58:21'),
(26, 9, 'project_assignment', 'You have been assigned as manager for project: wbkjww', 12, 0, '2025-05-21 18:58:21'),
(27, 16, 'project_assignment', 'You have been assigned to project: hfhjfkf', 13, 0, '2025-05-21 19:15:20'),
(28, 8, 'project_assignment', 'You have been assigned as manager for project: hfhjfkf', 13, 0, '2025-05-21 19:15:20');

-- --------------------------------------------------------

--
-- Table structure for table `payroll`
--

CREATE TABLE `payroll` (
  `payroll_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `period_id` int(11) NOT NULL,
  `basic_salary` decimal(10,2) NOT NULL,
  `gross_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `net_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','approved','paid','cancelled') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `payroll`
--

INSERT INTO `payroll` (`payroll_id`, `employee_id`, `period_id`, `basic_salary`, `gross_salary`, `net_salary`, `status`, `created_at`, `updated_at`) VALUES
(1, 3, 1, 500.00, 500.00, 500.00, 'paid', '2025-04-14 08:48:39', '2025-04-15 06:40:52'),
(3, 5, 3, 1000.00, 1000.00, 1000.00, 'paid', '2025-04-15 06:29:37', '2025-04-15 06:34:24'),
(4, 3, 4, 500.00, 500.00, 500.00, 'paid', '2025-04-21 16:04:16', '2025-04-21 16:04:32'),
(5, 3, 5, 500.00, 500.00, 500.00, 'paid', '2025-05-02 15:56:21', '2025-05-02 16:11:34'),
(6, 7, 6, 2000.00, 2000.00, 2000.00, 'draft', '2025-05-02 16:05:32', '2025-05-02 16:05:32'),
(7, 7, 7, 2000.00, 2000.00, 2000.00, 'draft', '2025-05-02 16:10:46', '2025-05-02 16:10:46'),
(8, 9, 8, 1000.00, 1000.00, 1000.00, 'draft', '2025-05-03 05:18:43', '2025-05-03 05:18:43'),
(9, 9, 9, 1000.00, 1000.00, 1000.00, 'paid', '2025-05-03 05:19:06', '2025-05-03 05:20:27'),
(10, 11, 10, 500.00, 500.00, 500.00, 'draft', '2025-05-03 05:30:15', '2025-05-03 05:30:15'),
(11, 11, 11, 500.00, 500.00, 500.00, 'paid', '2025-05-03 05:30:29', '2025-05-03 05:31:13'),
(12, 7, 12, 3000.00, 3000.00, 3000.00, 'paid', '2025-05-03 05:34:00', '2025-05-03 05:39:15');

-- --------------------------------------------------------

--
-- Table structure for table `payroll_adjustments`
--

CREATE TABLE `payroll_adjustments` (
  `adjustment_id` int(11) NOT NULL,
  `payroll_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `adjustment_type` enum('bonus','overtime','advance','loan','other') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_config`
--

CREATE TABLE `payroll_config` (
  `config_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `payroll_config`
--

INSERT INTO `payroll_config` (`config_id`, `created_at`, `updated_at`) VALUES
(1, '2025-04-11 02:59:45', '2025-04-11 02:59:45');

-- --------------------------------------------------------

--
-- Table structure for table `payroll_periods`
--

CREATE TABLE `payroll_periods` (
  `period_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('draft','processing','completed','cancelled') DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `payroll_periods`
--

INSERT INTO `payroll_periods` (`period_id`, `start_date`, `end_date`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, '2025-04-01', '2025-04-30', 'draft', 1, '2025-04-14 08:48:39', '2025-04-14 08:48:39'),
(2, '2025-04-01', '2025-04-30', 'draft', 1, '2025-04-15 06:10:37', '2025-04-15 06:10:37'),
(3, '2025-04-01', '2025-04-30', 'draft', 1, '2025-04-15 06:29:37', '2025-04-15 06:29:37'),
(4, '2025-04-01', '2025-04-30', 'draft', 1, '2025-04-21 16:04:16', '2025-04-21 16:04:16'),
(5, '2025-05-01', '2025-05-31', 'draft', 1, '2025-05-02 15:56:21', '2025-05-02 15:56:21'),
(6, '2025-04-01', '2025-04-30', 'draft', 1, '2025-05-02 16:05:32', '2025-05-02 16:05:32'),
(7, '2025-04-01', '2025-04-30', 'draft', 1, '2025-05-02 16:10:46', '2025-05-02 16:10:46'),
(8, '2025-04-01', '2025-04-30', 'draft', 1, '2025-05-03 05:18:43', '2025-05-03 05:18:43'),
(9, '2025-05-01', '2025-05-31', '', 1, '2025-05-03 05:19:06', '2025-05-03 05:20:27'),
(10, '2025-06-01', '2025-06-30', 'draft', 1, '2025-05-03 05:30:15', '2025-05-03 05:30:15'),
(11, '2025-05-01', '2025-05-31', '', 1, '2025-05-03 05:30:29', '2025-05-03 05:31:13'),
(12, '2025-05-01', '2025-05-31', '', 1, '2025-05-03 05:34:00', '2025-05-03 05:39:15');

-- --------------------------------------------------------

--
-- Table structure for table `payslips`
--

CREATE TABLE `payslips` (
  `payslip_id` int(11) NOT NULL,
  `payroll_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `period_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `generated_by` int(11) NOT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('generated','downloaded','cancelled') DEFAULT 'generated'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `profile`
--

CREATE TABLE `profile` (
  `profile_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','hr','manager','employee') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `dept_id` int(11) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `basic_salary` decimal(10,2) DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `gender` enum('male','female') DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `profile`
--

INSERT INTO `profile` (`profile_id`, `user_id`, `username`, `email`, `role`, `status`, `first_name`, `last_name`, `dept_id`, `position`, `hire_date`, `basic_salary`, `salary`, `gender`, `profile_image`, `phone`, `address`, `bio`, `created_at`, `updated_at`) VALUES
(1, 1, 'admin', 'admin@example.com', 'admin', 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-28 03:04:51', '2025-05-28 03:04:51'),
(2, 4, 'usame', 'usame@gmail.com', 'employee', 'active', 'usame', 'abdiwahab', 1, 'developer', '2025-04-11', 500.00, 1000.00, '', 'employee_4_1746427062.png', '0614594306', 'kismayo-somalia', NULL, '2025-05-28 03:04:51', '2025-05-28 03:04:51'),
(3, 8, 'moha', 'moha@gmail.com', 'manager', 'active', 'mohamed', 'yusuf', 2, 'manager', '2025-04-14', 1000.00, 1000.00, '', NULL, '0614500309', 'kismayo-somalia', NULL, '2025-05-28 03:04:51', '2025-05-28 03:04:51'),
(4, 9, 'aden', 'aden@gmail.com', 'manager', 'active', 'aden', 'ismacil', 1, 'manager', '2025-04-14', 1000.00, 1000.00, '', 'manager_9_1746776043.jpeg', '0618726609', 'kismayo-somalia', NULL, '2025-05-28 03:04:51', '2025-05-28 03:04:51'),
(5, 11, 'abdi', 'abdi@g', 'employee', 'active', 'Abdikadar', 'Mohamed', 2, 'manager', '2025-04-15', 500.00, NULL, '', NULL, 'yiuyh', 'dhfbdhfdbdje', NULL, '2025-05-28 03:04:51', '2025-05-28 03:04:51'),
(6, 12, 'najiip', 'najiip@gmail.com', 'employee', 'active', 'najiib', 'waberi', 2, 'marketer', '2025-04-22', 2000.00, NULL, 'male', NULL, '0615432124', 'kismayo - guulwade\r\n', NULL, '2025-05-28 03:04:51', '2025-05-28 03:04:51'),
(7, 13, 'sadam', 'sadam@gmail.com', 'employee', 'active', 'sadam', 'mohamed', 2, 'marketer', '2025-04-22', 500.00, NULL, 'male', NULL, '0615658743', 'kismao - faanole', NULL, '2025-05-28 03:04:51', '2025-05-28 03:04:51'),
(8, 14, 'abdiqadir', 'abdiqadir@gmail.com', 'employee', 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-28 03:04:51', '2025-05-28 03:04:51'),
(9, 15, 'cabdi', 'cabdi@gmail.com', 'employee', 'active', 'cabdi', 'axmed', 1, 'developer', '2025-04-22', 1000.00, NULL, 'male', NULL, '0876543265', 'Calanley-kismayo', NULL, '2025-05-28 03:04:51', '2025-05-28 03:04:51'),
(10, 16, 'axmed', 'axmed@gmail.com', 'employee', 'active', 'axmed', 'mohamed', 1, 'developer', '2025-04-22', 500.00, NULL, 'male', NULL, '0615765432', 'Calanley-kismayo', NULL, '2025-05-28 03:04:51', '2025-05-28 03:04:51'),
(11, 17, 'luul', 'luul@gmail.com', 'manager', 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-28 03:04:51', '2025-05-28 03:04:51'),
(12, 18, 'yusuf', 'yusuf@gmail.com', 'employee', 'active', 'yusuf', 'mohamed??', 3, 'developer', '2025-05-02', 500.00, NULL, 'male', NULL, 'bjkkggn', 'Calanley-kismayo', NULL, '2025-05-28 03:04:51', '2025-05-28 03:04:51'),
(13, 19, 'oooo', 'o@gmail.com', 'employee', 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-28 03:04:51', '2025-05-28 03:04:51'),
(14, 20, 'ismail', 'ismail@gmail.com', 'manager', 'active', 'ismail', 'aden', NULL, NULL, '2025-05-05', 1000.00, NULL, 'male', NULL, '0615657687', NULL, NULL, '2025-05-28 03:04:51', '2025-05-28 03:04:51'),
(15, 21, 'aasiyo', 'aasiyo@gmail.com', 'employee', 'active', 'aasiyo', 'mohamed', 3, 'manager', '2025-05-10', 2000.00, NULL, 'male', NULL, '5432657854', 'Calanley-kismayo', NULL, '2025-05-28 03:04:51', '2025-05-28 03:04:51');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `project_id` int(11) NOT NULL,
  `project_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('not_started','in_progress','completed','on_hold') DEFAULT 'not_started',
  `manager_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`project_id`, `project_name`, `description`, `start_date`, `end_date`, `status`, `manager_id`, `created_at`, `updated_at`) VALUES
(5, 'desing', 'fadlan dhamee', '2025-04-23', '2025-04-30', 'in_progress', 4, '2025-04-22 14:13:52', '2025-05-21 19:12:58'),
(7, 'uaz', 'website', '2025-04-25', '2025-05-01', 'completed', 5, '2025-04-25 14:05:42', '2025-05-21 19:12:58'),
(12, 'wbkjww', 'webkjhw', '2025-05-21', '2025-06-07', 'not_started', 5, '2025-05-21 18:58:21', '2025-05-21 19:12:58'),
(13, 'hfhjfkf', 'nrj4ln3', '2025-05-21', '2025-05-24', 'not_started', 4, '2025-05-21 19:15:20', '2025-05-21 19:15:20');

-- --------------------------------------------------------

--
-- Table structure for table `project_assignments`
--

CREATE TABLE `project_assignments` (
  `assignment_id` int(11) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `emp_id` int(11) DEFAULT NULL,
  `assigned_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `project_assignments`
--

INSERT INTO `project_assignments` (`assignment_id`, `project_id`, `emp_id`, `assigned_date`, `created_at`) VALUES
(7, 5, 7, '2025-04-22', '2025-04-22 14:13:52'),
(8, 7, 10, '2025-04-25', '2025-04-25 14:05:42'),
(9, 7, 3, '2025-04-25', '2025-04-25 14:05:42'),
(11, 12, 6, '2025-05-21', '2025-05-21 18:58:21'),
(12, 13, 10, '2025-05-21', '2025-05-21 19:15:20');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
(1, 'company_name', 'Naallo', '2025-04-11 02:59:45', '2025-05-29 19:36:09'),
(2, 'company_email', 'Naallo@gmail.com', '2025-04-11 02:59:45', '2025-05-29 19:36:09'),
(3, 'company_address', 'kismayo-somalia', '2025-04-11 02:59:45', '2025-04-22 06:30:13'),
(4, 'work_hours', '8', '2025-04-11 02:59:45', '2025-04-11 02:59:45'),
(5, 'late_threshold', '15', '2025-04-11 02:59:45', '2025-04-11 02:59:45'),
(26, 'company_phone', '+252615905477', '2025-05-29 19:38:55', '2025-05-29 19:39:38');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','hr','manager','employee') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `email`, `role`, `status`, `created_at`, `last_login`) VALUES
(1, 'admin', '$2y$10$eKcVucTDlJkGRa3C88bJGO5LUIz5I1MZx62mA4lXZvdP8WR0yqT9u', 'admin@gmail.com', 'admin', 'active', '2025-04-11 02:59:45', '2025-05-31 04:10:09'),
(4, 'usame', '$2y$10$BUkL048EbjsOW7Y5v9IFoO5cWIuQ8eZtFx689nRVZ7kZjCopkZWzW', 'usame@gmail.com', 'employee', 'active', '2025-04-11 03:35:48', '2025-05-31 04:09:20'),
(8, 'moha', '$2y$10$owvYBO1xkIj4L9A4OKhjx.ArbTEXqOjZjojq4LIHVunOb3sxDbrUK', 'moha@gmail.com', 'manager', 'active', '2025-04-14 14:29:49', '2025-04-25 14:00:41'),
(9, 'aden', '$2y$10$FG0tKRRxirbppwC7HTQMD.y70BDcmeUcTV8hM7fbCJfovC0SCsrRu', 'aden@gmail.com', 'manager', 'active', '2025-04-14 14:30:55', '2025-05-31 04:07:36'),
(11, 'abdi', '$2y$10$3ZaGFgM.ipeUk12sMaLO1.mrgs6vfWAMF3N9x5fz/TU1Pt6j9c2bS', 'abdi@g', 'employee', 'active', '2025-04-15 11:42:17', NULL),
(12, 'najiip', '$2y$10$vvhh5Q1aRH.WUntqYyhcAuoUsZT6Ih91PZmDo51CuO3KVj2/hmJdy', 'najiip@gmail.com', 'employee', 'active', '2025-04-22 06:51:59', '2025-05-03 05:39:58'),
(13, 'sadam', '$2y$10$MdJsgeEZXuEv5.YstE7Jz.IXbyCc5/C7ExOGKudhxjyJxaAHqSdCC', 'sadam@gmail.com', 'employee', 'active', '2025-04-22 06:57:25', '2025-04-22 07:00:50'),
(14, 'abdiqadir', '$2y$10$Mm3N4QMRz6VGYjZB6RIr9OFUhBiZBocNdEpBSTqYwFSCStonjBxJW', 'abdiqadir@gmail.com', 'employee', 'active', '2025-04-22 08:51:25', '2025-04-22 08:51:41'),
(15, 'cabdi', '$2y$10$UUM9ET4WdqquQX/yrj0SIOJVJLYSwQruFr/ENhBMAofeaxLkYMx8G', 'cabdi@gmail.com', 'employee', 'active', '2025-04-22 12:31:48', '2025-05-03 05:21:00'),
(16, 'axmed', '$2y$10$zdOahrkhsVqtvafErEsz3eQSAwG5YU0x1JTtegQCX41OqA2FSEtmC', 'axmed@gmail.com', 'employee', 'active', '2025-04-22 14:04:10', '2025-04-22 14:07:16'),
(17, 'luul', '$2y$10$V.GZPKW0Eit2wAbCZnNauenJtMOce/5LFwd2KmvK8tar3agVr060e', 'luul@gmail.com', 'manager', 'active', '2025-04-22 14:18:14', NULL),
(18, 'yusuf', '$2y$10$ptxhYGtvONIDJgq5AYk0suFChrjCGlxoKZlvFkFhwS15UgjMiLEWO', 'yusuf@gmail.com', 'employee', 'active', '2025-05-02 17:30:47', '2025-05-05 07:15:11'),
(19, 'oooo', '$2y$10$c2nboI/6Fb1SVjTnOpNtWO4gZMs7Fou0h67HaQCZ5cfDzS5OMhHjW', 'o@gmail.com', 'employee', 'active', '2025-05-02 17:42:33', '2025-05-02 17:42:54'),
(20, 'ismail', '$2y$10$MdldegyeMeimgz4kROopzeDAeANYcOHU8buiduRzB6rW5YO.oID1S', 'ismail@gmail.com', 'manager', 'active', '2025-05-05 07:21:14', '2025-05-05 07:21:57'),
(21, 'aasiyo', '$2y$10$9FEtXfMNPG8PIlUvI1dIx.gt03O5ZenKoerbF/nAmQzNfFCTLV126', 'aasiyo@gmail.com', 'employee', 'active', '2025-05-10 14:41:41', '2025-05-10 14:42:01'),
(22, 'abu', '$2y$10$B1PQqaQ57g2Lrne8YqReIO72AKeTQwYmKK5C/Ro4IFYMiT3Mj.pD2', 'abakardr123@gmail.com', 'manager', 'active', '2025-05-29 16:59:06', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD KEY `emp_id` (`emp_id`);

--
-- Indexes for table `attendance_bonus_config`
--
ALTER TABLE `attendance_bonus_config`
  ADD PRIMARY KEY (`config_id`);

--
-- Indexes for table `attendance_notifications`
--
ALTER TABLE `attendance_notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `emp_id` (`emp_id`);

--
-- Indexes for table `attendance_performance`
--
ALTER TABLE `attendance_performance`
  ADD PRIMARY KEY (`performance_id`),
  ADD UNIQUE KEY `unique_emp_month_year` (`emp_id`,`month`,`year`);

--
-- Indexes for table `attendance_policy`
--
ALTER TABLE `attendance_policy`
  ADD PRIMARY KEY (`policy_id`);

--
-- Indexes for table `attendance_reports`
--
ALTER TABLE `attendance_reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `emp_id` (`emp_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`dept_id`),
  ADD KEY `dept_head` (`dept_head`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`emp_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `dept_id` (`dept_id`);

--
-- Indexes for table `employee_leave_balance`
--
ALTER TABLE `employee_leave_balance`
  ADD PRIMARY KEY (`balance_id`),
  ADD KEY `emp_id` (`emp_id`),
  ADD KEY `leave_type_id` (`leave_type_id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`leave_id`),
  ADD KEY `emp_id` (`emp_id`),
  ADD KEY `leave_type_id` (`leave_type_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_requested_by_role` (`requested_by_role`);

--
-- Indexes for table `leave_types`
--
ALTER TABLE `leave_types`
  ADD PRIMARY KEY (`leave_type_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payroll`
--
ALTER TABLE `payroll`
  ADD PRIMARY KEY (`payroll_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `period_id` (`period_id`);

--
-- Indexes for table `payroll_adjustments`
--
ALTER TABLE `payroll_adjustments`
  ADD PRIMARY KEY (`adjustment_id`),
  ADD KEY `payroll_id` (`payroll_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `payroll_config`
--
ALTER TABLE `payroll_config`
  ADD PRIMARY KEY (`config_id`);

--
-- Indexes for table `payroll_periods`
--
ALTER TABLE `payroll_periods`
  ADD PRIMARY KEY (`period_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `payslips`
--
ALTER TABLE `payslips`
  ADD PRIMARY KEY (`payslip_id`),
  ADD KEY `payroll_id` (`payroll_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `period_id` (`period_id`),
  ADD KEY `generated_by` (`generated_by`);

--
-- Indexes for table `profile`
--
ALTER TABLE `profile`
  ADD PRIMARY KEY (`profile_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`project_id`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Indexes for table `project_assignments`
--
ALTER TABLE `project_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `emp_id` (`emp_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `attendance_bonus_config`
--
ALTER TABLE `attendance_bonus_config`
  MODIFY `config_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `attendance_notifications`
--
ALTER TABLE `attendance_notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `attendance_performance`
--
ALTER TABLE `attendance_performance`
  MODIFY `performance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `attendance_policy`
--
ALTER TABLE `attendance_policy`
  MODIFY `policy_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_reports`
--
ALTER TABLE `attendance_reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `dept_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `emp_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `employee_leave_balance`
--
ALTER TABLE `employee_leave_balance`
  MODIFY `balance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `leave_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `leave_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `payroll_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `payroll_adjustments`
--
ALTER TABLE `payroll_adjustments`
  MODIFY `adjustment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_config`
--
ALTER TABLE `payroll_config`
  MODIFY `config_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payroll_periods`
--
ALTER TABLE `payroll_periods`
  MODIFY `period_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `payslips`
--
ALTER TABLE `payslips`
  MODIFY `payslip_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `profile`
--
ALTER TABLE `profile`
  MODIFY `profile_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `project_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `project_assignments`
--
ALTER TABLE `project_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `employees` (`emp_id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance_notifications`
--
ALTER TABLE `attendance_notifications`
  ADD CONSTRAINT `attendance_notifications_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `employees` (`emp_id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance_performance`
--
ALTER TABLE `attendance_performance`
  ADD CONSTRAINT `attendance_performance_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `employees` (`emp_id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance_reports`
--
ALTER TABLE `attendance_reports`
  ADD CONSTRAINT `attendance_reports_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`dept_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `attendance_reports_ibfk_2` FOREIGN KEY (`emp_id`) REFERENCES `employees` (`emp_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `attendance_reports_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`dept_head`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`);

--
-- Constraints for table `employee_leave_balance`
--
ALTER TABLE `employee_leave_balance`
  ADD CONSTRAINT `employee_leave_balance_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `employees` (`emp_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_leave_balance_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`leave_type_id`);

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `employees` (`emp_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`leave_type_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_requests_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll`
--
ALTER TABLE `payroll`
  ADD CONSTRAINT `payroll_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`emp_id`),
  ADD CONSTRAINT `payroll_ibfk_2` FOREIGN KEY (`period_id`) REFERENCES `payroll_periods` (`period_id`);

--
-- Constraints for table `payroll_adjustments`
--
ALTER TABLE `payroll_adjustments`
  ADD CONSTRAINT `payroll_adjustments_ibfk_1` FOREIGN KEY (`payroll_id`) REFERENCES `payroll` (`payroll_id`),
  ADD CONSTRAINT `payroll_adjustments_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`emp_id`),
  ADD CONSTRAINT `payroll_adjustments_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `payroll_periods`
--
ALTER TABLE `payroll_periods`
  ADD CONSTRAINT `payroll_periods_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `payslips`
--
ALTER TABLE `payslips`
  ADD CONSTRAINT `payslips_ibfk_1` FOREIGN KEY (`payroll_id`) REFERENCES `payroll` (`payroll_id`),
  ADD CONSTRAINT `payslips_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`emp_id`),
  ADD CONSTRAINT `payslips_ibfk_3` FOREIGN KEY (`period_id`) REFERENCES `payroll_periods` (`period_id`),
  ADD CONSTRAINT `payslips_ibfk_4` FOREIGN KEY (`generated_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `profile`
--
ALTER TABLE `profile`
  ADD CONSTRAINT `profile_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `employees` (`emp_id`) ON DELETE SET NULL;

--
-- Constraints for table `project_assignments`
--
ALTER TABLE `project_assignments`
  ADD CONSTRAINT `project_assignments_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_assignments_ibfk_2` FOREIGN KEY (`emp_id`) REFERENCES `employees` (`emp_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
