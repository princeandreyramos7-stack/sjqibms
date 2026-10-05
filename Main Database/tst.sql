-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 05, 2026 at 05:51 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sjqibms`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `author_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `audience` enum('public','all_residents','purok','selected_users','kagawads','all_staff') NOT NULL DEFAULT 'public',
  `category` enum('general','health') NOT NULL DEFAULT 'general',
  `target_purok` varchar(80) DEFAULT NULL,
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'draft',
  `published_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `author_id`, `title`, `body`, `attachment_path`, `audience`, `category`, `target_purok`, `status`, `published_at`, `created_at`) VALUES
(2, 1, 'SJQIBMS Notification Test', 'This is a test announcement for the SJQIBMS notification system. No actual barangay event is scheduled. Please disregard this announcement.', NULL, 'public', 'general', NULL, 'published', '2026-09-22 23:25:55', '2026-09-22 15:25:55'),
(3, 1, 'SJQIBMS Notification Test 2', 'This is a system test announcement to verify notification delivery and unread status. No actual barangay event is scheduled.', NULL, 'public', 'general', NULL, 'published', '2026-09-22 23:41:51', '2026-09-22 15:41:51'),
(4, 1, 'SJQIBMS Draft Notification Test', 'This is a test draft for verifying the SJQIBMS notification system. No actual barangay event is scheduled.', NULL, 'public', 'general', NULL, 'archived', '2026-09-22 23:47:24', '2026-09-22 15:46:33'),
(5, 1, 'SJQIBMS Draft Notification Test123', 'qwertyuiopfedkedgnei', NULL, 'public', 'general', NULL, 'archived', '2026-09-22 23:59:18', '2026-09-22 15:59:18'),
(13, 2, '0000000000', 'qwghcuv ubujbuibubub', NULL, 'public', 'general', NULL, 'published', '2026-09-24 23:54:34', '2026-09-24 15:54:34'),
(14, 2, '848448', 'gyb gtf vct', NULL, 'purok', 'general', '4', 'published', '2026-09-25 00:03:06', '2026-09-24 16:03:06'),
(15, 2, 'rcrr', 'ytvtv', NULL, 'public', 'general', NULL, 'published', '2026-09-25 10:28:14', '2026-09-25 02:28:14'),
(16, 1, 'Test', 'test', NULL, 'public', 'general', NULL, 'published', '2026-09-27 21:35:05', '2026-09-27 13:35:05'),
(17, 1, 'testing', 'test sms', NULL, 'public', 'general', NULL, 'published', '2026-09-27 21:40:12', '2026-09-27 13:40:12'),
(18, 1, 'testing', 'testing', NULL, 'public', 'general', NULL, 'published', '2026-09-27 21:45:48', '2026-09-27 13:45:48'),
(19, 1, 'test', 'test sms', NULL, 'public', 'general', NULL, 'published', '2026-09-27 21:50:24', '2026-09-27 13:50:24'),
(20, 1, 'test', 'tng in mon cj', NULL, 'public', 'general', NULL, 'published', '2026-09-27 21:51:33', '2026-09-27 13:51:33'),
(21, 1, 'testing', 'terstest', NULL, 'public', 'general', NULL, 'published', '2026-09-27 21:54:17', '2026-09-27 13:54:17'),
(22, 1, 'tst', 'tesatin', NULL, 'public', 'general', NULL, 'published', '2026-09-27 21:56:50', '2026-09-27 13:56:50'),
(23, 1, 'Test Title', 'Test Content', NULL, 'public', 'general', NULL, 'published', '2026-09-27 21:58:27', '2026-09-27 13:58:27'),
(24, 1, 'TESTING', 'WEHHHHHHHHHHHHHHH', NULL, 'public', 'general', NULL, 'published', '2026-09-27 22:19:47', '2026-09-27 14:19:47'),
(25, 1, 'testing', 'kinnam', NULL, 'public', 'general', NULL, 'archived', '2026-09-27 22:23:21', '2026-09-27 14:23:21'),
(26, 1, 'SJQIBMS Notification Test', 'ssfwfqwwss', NULL, 'public', 'general', NULL, 'published', '2026-09-29 13:53:14', '2026-09-29 05:53:14'),
(27, 1, 'SJQIBMS Notification Test', 'poiym,loi', NULL, 'public', 'general', NULL, 'published', '2026-09-29 14:02:49', '2026-09-29 06:02:49'),
(28, 1, 'Ayuda for Pdw', 'buksan ang link https://www.facebook.com/share/p/1FHNLE4ZyK/', NULL, 'public', 'general', NULL, 'published', '2026-10-03 12:53:41', '2026-10-03 04:53:41');

-- --------------------------------------------------------

--
-- Table structure for table `announcement_history`
--

CREATE TABLE `announcement_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `announcement_id` bigint(20) UNSIGNED NOT NULL,
  `action` enum('created','updated','published','unpublished','archived') NOT NULL,
  `actor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `previous_status` enum('draft','published','archived') DEFAULT NULL,
  `new_status` enum('draft','published','archived') DEFAULT NULL,
  `change_summary` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `announcement_history`
--

INSERT INTO `announcement_history` (`id`, `announcement_id`, `action`, `actor_id`, `previous_status`, `new_status`, `change_summary`, `created_at`) VALUES
(4, 2, 'created', 1, NULL, 'published', NULL, '2026-09-22 15:25:55'),
(6, 3, 'created', 1, NULL, 'published', NULL, '2026-09-22 15:41:51'),
(7, 4, 'created', 1, NULL, 'draft', NULL, '2026-09-22 15:46:33'),
(8, 4, 'published', 1, 'draft', 'published', NULL, '2026-09-22 15:47:24'),
(9, 2, 'archived', 1, 'published', 'archived', NULL, '2026-09-22 15:53:08'),
(10, 3, 'archived', 1, 'published', 'archived', NULL, '2026-09-22 15:53:13'),
(11, 5, 'created', 1, NULL, 'published', NULL, '2026-09-22 15:59:18'),
(12, 5, 'updated', 1, 'published', 'published', NULL, '2026-09-23 13:02:44'),
(16, 3, 'published', 1, 'archived', 'published', 'Unarchived', '2026-09-23 13:40:15'),
(18, 3, 'archived', 1, 'published', 'archived', NULL, '2026-09-23 13:40:32'),
(21, 5, 'archived', 1, 'published', 'archived', NULL, '2026-09-23 13:54:15'),
(23, 5, 'published', 1, 'archived', 'published', 'Unarchived', '2026-09-23 13:54:23'),
(24, 3, 'published', 1, 'archived', 'published', 'Unarchived', '2026-09-23 13:54:25'),
(26, 3, 'archived', 1, 'published', 'archived', NULL, '2026-09-23 13:54:39'),
(39, 3, 'published', 1, 'archived', 'published', 'Unarchived', '2026-09-23 14:19:16'),
(40, 2, 'published', 1, 'archived', 'published', 'Unarchived', '2026-09-23 14:19:26'),
(41, 5, 'archived', 1, 'published', 'archived', NULL, '2026-09-23 14:19:48'),
(42, 4, 'archived', 1, 'published', 'archived', NULL, '2026-09-23 14:19:52'),
(47, 13, 'created', 2, NULL, 'published', NULL, '2026-09-24 15:54:34'),
(48, 14, 'created', 2, NULL, 'published', NULL, '2026-09-24 16:03:06'),
(49, 15, 'created', 2, NULL, 'published', NULL, '2026-09-25 02:28:14'),
(50, 16, 'created', 1, NULL, 'published', NULL, '2026-09-27 13:35:05'),
(51, 17, 'created', 1, NULL, 'published', NULL, '2026-09-27 13:40:12'),
(52, 18, 'created', 1, NULL, 'published', NULL, '2026-09-27 13:45:48'),
(53, 19, 'created', 1, NULL, 'published', NULL, '2026-09-27 13:50:24'),
(54, 20, 'created', 1, NULL, 'published', NULL, '2026-09-27 13:51:33'),
(55, 21, 'created', 1, NULL, 'published', NULL, '2026-09-27 13:54:17'),
(56, 22, 'created', 1, NULL, 'published', NULL, '2026-09-27 13:56:50'),
(57, 23, 'created', 1, NULL, 'published', NULL, '2026-09-27 13:58:27'),
(58, 24, 'created', 1, NULL, 'published', NULL, '2026-09-27 14:19:47'),
(59, 25, 'created', 1, NULL, 'published', NULL, '2026-09-27 14:23:21'),
(60, 25, 'archived', 1, 'published', 'archived', NULL, '2026-09-29 05:52:25'),
(61, 26, 'created', 1, NULL, 'published', NULL, '2026-09-29 05:53:14'),
(62, 27, 'created', 1, NULL, 'published', NULL, '2026-09-29 06:02:49'),
(63, 28, 'created', 1, NULL, 'published', NULL, '2026-10-03 04:53:42');

-- --------------------------------------------------------

--
-- Table structure for table `announcement_notifications`
--

CREATE TABLE `announcement_notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `recipient_user_id` bigint(20) UNSIGNED NOT NULL,
  `announcement_id` bigint(20) UNSIGNED NOT NULL,
  `notification_type` varchar(60) NOT NULL DEFAULT 'announcement_published',
  `title` varchar(200) NOT NULL,
  `message` varchar(500) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `announcement_notifications`
--

INSERT INTO `announcement_notifications` (`id`, `recipient_user_id`, `announcement_id`, `notification_type`, `title`, `message`, `created_at`, `read_at`) VALUES
(1, 1, 3, 'announcement_published', 'SJQIBMS Notification Test 2', 'This is a system test announcement to verify notification delivery and unread status. No actual barangay event is sch...', '2026-09-22 15:41:51', '2026-09-22 23:42:53'),
(2, 1, 4, 'announcement_published', 'SJQIBMS Draft Notification Test', 'This is a test draft for verifying the SJQIBMS notification system. No actual barangay event is scheduled.', '2026-09-22 15:47:24', '2026-09-22 23:50:27'),
(3, 1, 5, 'announcement_published', 'SJQIBMS Draft Notification Test123', 'qwertyuiop', '2026-09-22 15:59:18', '2026-09-22 23:59:23'),
(19, 4, 13, 'announcement_published', '0000000000', 'qwghcuv ubujbuibubub', '2026-09-24 15:54:34', NULL),
(20, 5, 13, 'announcement_published', '0000000000', 'qwghcuv ubujbuibubub', '2026-09-24 15:54:34', NULL),
(23, 1, 13, 'announcement_published', '0000000000', 'qwghcuv ubujbuibubub', '2026-09-24 15:54:34', NULL),
(24, 2, 13, 'announcement_published', '0000000000', 'qwghcuv ubujbuibubub', '2026-09-24 15:54:34', '2026-09-24 23:54:44'),
(26, 1, 14, 'announcement_published', '848448', 'gyb gtf vct', '2026-09-24 16:03:06', NULL),
(27, 2, 14, 'announcement_published', '848448', 'gyb gtf vct', '2026-09-24 16:03:06', NULL),
(28, 4, 15, 'announcement_published', 'rcrr', 'ytvtv', '2026-09-25 02:28:14', NULL),
(29, 5, 15, 'announcement_published', 'rcrr', 'ytvtv', '2026-09-25 02:28:14', NULL),
(32, 1, 15, 'announcement_published', 'rcrr', 'ytvtv', '2026-09-25 02:28:14', NULL),
(33, 2, 15, 'announcement_published', 'rcrr', 'ytvtv', '2026-09-25 02:28:14', NULL),
(34, 4, 16, 'announcement_published', 'Test', 'test', '2026-09-27 13:35:05', NULL),
(35, 5, 16, 'announcement_published', 'Test', 'test', '2026-09-27 13:35:05', NULL),
(38, 1, 16, 'announcement_published', 'Test', 'test', '2026-09-27 13:35:05', NULL),
(39, 2, 16, 'announcement_published', 'Test', 'test', '2026-09-27 13:35:05', NULL),
(40, 4, 17, 'announcement_published', 'testing', 'test sms', '2026-09-27 13:40:12', NULL),
(41, 5, 17, 'announcement_published', 'testing', 'test sms', '2026-09-27 13:40:12', NULL),
(44, 1, 17, 'announcement_published', 'testing', 'test sms', '2026-09-27 13:40:12', NULL),
(45, 2, 17, 'announcement_published', 'testing', 'test sms', '2026-09-27 13:40:12', NULL),
(46, 4, 18, 'announcement_published', 'testing', 'testing', '2026-09-27 13:45:48', NULL),
(47, 5, 18, 'announcement_published', 'testing', 'testing', '2026-09-27 13:45:48', NULL),
(50, 1, 18, 'announcement_published', 'testing', 'testing', '2026-09-27 13:45:48', NULL),
(51, 2, 18, 'announcement_published', 'testing', 'testing', '2026-09-27 13:45:48', NULL),
(52, 4, 19, 'announcement_published', 'test', 'test sms', '2026-09-27 13:50:24', NULL),
(53, 5, 19, 'announcement_published', 'test', 'test sms', '2026-09-27 13:50:24', NULL),
(56, 1, 19, 'announcement_published', 'test', 'test sms', '2026-09-27 13:50:24', NULL),
(57, 2, 19, 'announcement_published', 'test', 'test sms', '2026-09-27 13:50:24', NULL),
(58, 4, 20, 'announcement_published', 'test', 'tng in mon cj', '2026-09-27 13:51:33', NULL),
(59, 5, 20, 'announcement_published', 'test', 'tng in mon cj', '2026-09-27 13:51:33', NULL),
(62, 1, 20, 'announcement_published', 'test', 'tng in mon cj', '2026-09-27 13:51:33', NULL),
(63, 2, 20, 'announcement_published', 'test', 'tng in mon cj', '2026-09-27 13:51:33', NULL),
(64, 4, 21, 'announcement_published', 'testing', 'terstest', '2026-09-27 13:54:17', NULL),
(65, 5, 21, 'announcement_published', 'testing', 'terstest', '2026-09-27 13:54:17', NULL),
(68, 1, 21, 'announcement_published', 'testing', 'terstest', '2026-09-27 13:54:17', NULL),
(69, 2, 21, 'announcement_published', 'testing', 'terstest', '2026-09-27 13:54:17', NULL),
(70, 4, 22, 'announcement_published', 'tst', 'tesatin', '2026-09-27 13:56:50', NULL),
(71, 5, 22, 'announcement_published', 'tst', 'tesatin', '2026-09-27 13:56:50', NULL),
(74, 1, 22, 'announcement_published', 'tst', 'tesatin', '2026-09-27 13:56:50', NULL),
(75, 2, 22, 'announcement_published', 'tst', 'tesatin', '2026-09-27 13:56:50', NULL),
(76, 4, 23, 'announcement_published', 'Test Title', 'Test Content', '2026-09-27 13:58:27', NULL),
(77, 5, 23, 'announcement_published', 'Test Title', 'Test Content', '2026-09-27 13:58:27', NULL),
(80, 1, 23, 'announcement_published', 'Test Title', 'Test Content', '2026-09-27 13:58:27', NULL),
(81, 2, 23, 'announcement_published', 'Test Title', 'Test Content', '2026-09-27 13:58:27', NULL),
(82, 4, 24, 'announcement_published', 'TESTING', 'WEHHHHHHHHHHHHHHH', '2026-09-27 14:19:47', NULL),
(83, 5, 24, 'announcement_published', 'TESTING', 'WEHHHHHHHHHHHHHHH', '2026-09-27 14:19:47', NULL),
(86, 1, 24, 'announcement_published', 'TESTING', 'WEHHHHHHHHHHHHHHH', '2026-09-27 14:19:47', NULL),
(87, 2, 24, 'announcement_published', 'TESTING', 'WEHHHHHHHHHHHHHHH', '2026-09-27 14:19:47', NULL),
(88, 4, 25, 'announcement_published', 'testing', 'kinnam', '2026-09-27 14:23:21', NULL),
(89, 5, 25, 'announcement_published', 'testing', 'kinnam', '2026-09-27 14:23:21', NULL),
(92, 1, 25, 'announcement_published', 'testing', 'kinnam', '2026-09-27 14:23:21', NULL),
(93, 2, 25, 'announcement_published', 'testing', 'kinnam', '2026-09-27 14:23:21', NULL),
(94, 4, 26, 'announcement_published', 'SJQIBMS Notification Test', 'ssfwfqwwss', '2026-09-29 05:53:14', NULL),
(95, 5, 26, 'announcement_published', 'SJQIBMS Notification Test', 'ssfwfqwwss', '2026-09-29 05:53:14', NULL),
(99, 1, 26, 'announcement_published', 'SJQIBMS Notification Test', 'ssfwfqwwss', '2026-09-29 05:53:14', NULL),
(100, 2, 26, 'announcement_published', 'SJQIBMS Notification Test', 'ssfwfqwwss', '2026-09-29 05:53:14', NULL),
(101, 4, 27, 'announcement_published', 'SJQIBMS Notification Test', 'poiym,loi', '2026-09-29 06:02:49', NULL),
(102, 5, 27, 'announcement_published', 'SJQIBMS Notification Test', 'poiym,loi', '2026-09-29 06:02:49', NULL),
(107, 1, 27, 'announcement_published', 'SJQIBMS Notification Test', 'poiym,loi', '2026-09-29 06:02:49', NULL),
(108, 2, 27, 'announcement_published', 'SJQIBMS Notification Test', 'poiym,loi', '2026-09-29 06:02:49', NULL),
(109, 4, 28, 'announcement_published', 'Ayuda for Pdw', 'buksan ang link https://www.facebook.com/share/p/1FHNLE4ZyK/', '2026-10-03 04:53:42', NULL),
(110, 5, 28, 'announcement_published', 'Ayuda for Pdw', 'buksan ang link https://www.facebook.com/share/p/1FHNLE4ZyK/', '2026-10-03 04:53:42', NULL),
(120, 1, 28, 'announcement_published', 'Ayuda for Pdw', 'buksan ang link https://www.facebook.com/share/p/1FHNLE4ZyK/', '2026-10-03 04:53:42', NULL),
(121, 2, 28, 'announcement_published', 'Ayuda for Pdw', 'buksan ang link https://www.facebook.com/share/p/1FHNLE4ZyK/', '2026-10-03 04:53:42', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `assistance_distributions`
--

CREATE TABLE `assistance_distributions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference_no` varchar(20) NOT NULL,
  `ref_year` smallint(5) UNSIGNED DEFAULT NULL,
  `ref_seq` int(10) UNSIGNED DEFAULT NULL,
  `beneficiary_type` enum('resident','household') NOT NULL DEFAULT 'resident',
  `resident_id` bigint(20) UNSIGNED DEFAULT NULL,
  `household_id` bigint(20) UNSIGNED DEFAULT NULL,
  `priority_groups` set('pwd','solo_parent') NOT NULL DEFAULT '',
  `incident_id` bigint(20) UNSIGNED DEFAULT NULL,
  `assistance_type` enum('food','medical','educational','financial','burial','other','cash','rice','hygiene','medicine') NOT NULL,
  `assistance_form` enum('cash','in_kind') DEFAULT NULL,
  `source` enum('barangay_fund','barangay_inventory','donation','municipal_government','provincial_government','national_agency','ngo','other') DEFAULT NULL,
  `source_details` varchar(150) DEFAULT NULL,
  `purpose` varchar(255) NOT NULL,
  `cash_amount` decimal(12,2) DEFAULT NULL,
  `finance_transaction_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('scheduled','given','void') NOT NULL DEFAULT 'given',
  `received_by` varchar(150) NOT NULL,
  `receiver_relationship` varchar(60) DEFAULT NULL,
  `document_no` varchar(60) DEFAULT NULL,
  `given_on` date NOT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `archived_by` bigint(20) UNSIGNED DEFAULT NULL,
  `archive_reason` varchar(255) DEFAULT NULL
) ;

-- --------------------------------------------------------

--
-- Table structure for table `assistance_items`
--

CREATE TABLE `assistance_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `distribution_id` bigint(20) UNSIGNED NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `unit` varchar(30) NOT NULL,
  `inventory_movement_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(80) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `ip_address`, `user_agent`, `details`, `created_at`) VALUES
(1, 1, 'announcement_created', 'announcement', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{\"status\":\"draft\"}', '2026-09-22 13:58:54'),
(2, 1, 'announcement_updated', 'announcement', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-22 14:24:45'),
(3, 1, 'announcement_published', 'announcement', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-22 14:25:55'),
(4, 1, 'announcement_created', 'announcement', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-22 15:25:55'),
(5, 1, 'announcement_archived', 'announcement', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-22 15:36:58'),
(6, 1, 'announcement_created', 'announcement', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-22 15:41:51'),
(7, 1, 'announcement_created', 'announcement', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-22 15:46:33'),
(8, 1, 'announcement_published', 'announcement', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-22 15:47:24'),
(9, 1, 'announcement_archived', 'announcement', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-22 15:53:08'),
(10, 1, 'announcement_archived', 'announcement', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-22 15:53:13'),
(11, 1, 'announcement_created', 'announcement', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-22 15:59:18'),
(12, 1, 'announcement_updated', 'announcement', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-23 13:02:44'),
(13, 1, 'announcement_created', 'announcement', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-23 13:17:19'),
(14, 1, 'announcement_published', 'announcement', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-23 13:17:40'),
(15, 1, 'announcement_updated', 'announcement', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-23 13:18:01'),
(16, 1, 'announcement_unarchived', 'announcement', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{\"restored_status\":\"published\"}', '2026-09-23 13:40:15'),
(17, 1, 'announcement_unarchived', 'announcement', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{\"restored_status\":\"published\"}', '2026-09-23 13:40:25'),
(18, 1, 'announcement_archived', 'announcement', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-23 13:40:32'),
(19, 1, 'announcement_archived', 'announcement', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-23 13:40:41'),
(20, 1, 'announcement_archived', 'announcement', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-23 13:51:22'),
(21, 1, 'announcement_archived', 'announcement', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-23 13:54:15'),
(22, 1, 'announcement_unarchived', 'announcement', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{\"restored_status\":\"published\"}', '2026-09-23 13:54:20'),
(23, 1, 'announcement_unarchived', 'announcement', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{\"restored_status\":\"published\"}', '2026-09-23 13:54:23'),
(24, 1, 'announcement_unarchived', 'announcement', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{\"restored_status\":\"published\"}', '2026-09-23 13:54:25'),
(25, 1, 'announcement_archived', 'announcement', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-23 13:54:32'),
(26, 1, 'announcement_archived', 'announcement', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-23 13:54:39'),
(27, 1, 'announcement_deleted', 'announcement', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{\"title\":\"Barangay Medical Mission\",\"status\":\"archived\",\"published_at\":\"2026-09-22 22:25:55\"}', '2026-09-23 13:54:52'),
(28, 1, 'announcement_created', 'announcement', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-23 14:04:51'),
(29, 1, 'announcement_published', 'announcement', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-23 14:05:09'),
(30, 1, 'announcement_created', 'announcement', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-23 14:07:26'),
(31, 1, 'announcement_created', 'announcement', 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-23 14:15:48'),
(32, 1, 'announcement_published', 'announcement', 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-23 14:16:12'),
(33, 1, 'announcement_archived', 'announcement', 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-23 14:16:17'),
(34, 1, 'announcement_deleted', 'announcement', 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{\"title\":\"8905\",\"status\":\"archived\",\"published_at\":\"2026-09-23 22:16:12\"}', '2026-09-23 14:16:21'),
(35, 1, 'announcement_archived', 'announcement', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-23 14:16:34'),
(36, 1, 'announcement_deleted', 'announcement', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{\"title\":\"123456789\",\"status\":\"archived\",\"published_at\":\"2026-09-23 22:05:09\"}', '2026-09-23 14:16:38'),
(37, 1, 'announcement_created', 'announcement', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-23 14:16:50'),
(38, 1, 'announcement_published', 'announcement', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-23 14:17:01'),
(39, 1, 'announcement_published', 'announcement', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-23 14:17:09'),
(40, 1, 'announcement_archived', 'announcement', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-23 14:17:19'),
(41, 1, 'announcement_archived', 'announcement', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-23 14:17:21'),
(42, 1, 'announcement_deleted', 'announcement', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{\"title\":\"321045\",\"status\":\"archived\",\"published_at\":\"2026-09-23 22:17:01\"}', '2026-09-23 14:17:24'),
(43, 1, 'announcement_deleted', 'announcement', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{\"title\":\"fsfjnfieuf\",\"status\":\"archived\",\"published_at\":\"2026-09-23 22:17:09\"}', '2026-09-23 14:17:26'),
(44, 1, 'announcement_deleted', 'announcement', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{\"title\":\"qwertyuiop\",\"status\":\"archived\",\"published_at\":\"2026-09-23 21:17:40\"}', '2026-09-23 14:17:28'),
(45, 1, 'announcement_unarchived', 'announcement', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{\"restored_status\":\"published\"}', '2026-09-23 14:19:16'),
(46, 1, 'announcement_unarchived', 'announcement', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{\"restored_status\":\"published\"}', '2026-09-23 14:19:26'),
(47, 1, 'announcement_archived', 'announcement', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-23 14:19:48'),
(48, 1, 'announcement_archived', 'announcement', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{}', '2026-09-23 14:19:52'),
(49, 1, 'resident_created', 'resident', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{\"status\":\"active\",\"household_option\":\"later\"}', '2026-09-23 15:45:03'),
(50, 1, 'household_created', 'household', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{\"household_no\":\"147\",\"first_member_resident_id\":1}', '2026-09-23 15:54:16'),
(51, 1, 'resident_household_assigned', 'resident', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{\"household_id\":1,\"relationship_to_head\":\"Father\"}', '2026-09-23 15:54:16'),
(52, 1, 'household_head_changed', 'household', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{\"previous_head_resident_id\":null,\"new_head_resident_id\":1}', '2026-09-23 15:56:17'),
(53, 1, 'household_created', 'household', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{\"household_no\":\"148\"}', '2026-09-23 17:14:15'),
(54, 1, 'resident_household_transferred', 'resident', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{\"household_id\":2,\"relationship_to_head\":null,\"previous_household_id\":1,\"cleared_head_household_ids\":[1]}', '2026-09-23 17:19:32'),
(55, 1, 'household_head_changed', 'household', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '{\"previous_head_resident_id\":null,\"new_head_resident_id\":1}', '2026-09-23 17:23:16'),
(56, NULL, 'document_requested', 'document', 1, '::1', 'curl/8.21.0', '{\"reference_code\":\"DOC-2026-9F1ED2\",\"document_type\":\"Barangay Clearance\",\"resident_id\":1}', '2026-09-24 06:15:29'),
(57, 2, 'document_approved', 'document', 1, '::1', 'curl/8.21.0', '{\"reference_code\":\"DOC-2026-9F1ED2\",\"from\":\"pending\",\"to\":\"approved\"}', '2026-09-24 06:15:31'),
(58, 2, 'document_released', 'document', 1, '::1', 'curl/8.21.0', '{\"reference_code\":\"DOC-2026-9F1ED2\",\"from\":\"approved\",\"to\":\"released\"}', '2026-09-24 06:15:31'),
(59, 1, 'document_requested', 'document', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference_code\":\"DOC-2026-4D4B0F\",\"document_type\":\"Certificate of Indigency\",\"resident_id\":1,\"source\":\"staff\"}', '2026-09-24 11:40:16'),
(60, 2, 'document_requested', 'document', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference_code\":\"DOC-2026-4080A3\",\"document_type\":\"Business Clearance\",\"resident_id\":1,\"source\":\"staff\"}', '2026-09-24 11:41:42'),
(61, 2, 'document_approved', 'document', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference_code\":\"DOC-2026-4D4B0F\",\"from\":\"pending\",\"to\":\"approved\"}', '2026-09-24 11:41:55'),
(62, 2, 'document_requested', 'document', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference_code\":\"DOC-2026-4CA4BC\",\"document_type\":\"Certificate of Residency\",\"resident_id\":1,\"source\":\"staff\"}', '2026-09-24 11:48:01'),
(63, 2, 'document_rejected', 'document', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference_code\":\"DOC-2026-4CA4BC\",\"from\":\"pending\",\"to\":\"rejected\",\"reason\":\"not needed\"}', '2026-09-24 11:48:28'),
(64, 2, 'document_approved', 'document', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference_code\":\"DOC-2026-4080A3\",\"from\":\"pending\",\"to\":\"approved\"}', '2026-09-24 12:27:29'),
(65, 2, 'document_requested', 'document', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference_code\":\"DOC-2026-F9B836\",\"document_type\":\"Barangay Clearance\",\"resident_id\":1,\"source\":\"staff\"}', '2026-09-24 12:29:03'),
(66, 2, 'document_approved', 'document', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference_code\":\"DOC-2026-F9B836\",\"from\":\"pending\",\"to\":\"approved\"}', '2026-09-24 12:35:57'),
(67, 2, 'document_requested', 'document', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference_code\":\"DOC-2026-9028BC\",\"document_type\":\"Certificate of Good Moral Character\",\"resident_id\":1,\"source\":\"staff\"}', '2026-09-24 12:48:12'),
(68, 2, 'announcement_created', 'announcement', 11, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-24 13:28:37'),
(69, 2, 'announcement_archived', 'announcement', 11, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-24 13:28:49'),
(70, 2, 'announcement_deleted', 'announcement', 11, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"title\":\"123456789\",\"status\":\"archived\",\"published_at\":\"2026-09-24 21:28:37\"}', '2026-09-24 13:28:54'),
(71, 2, 'resident_created', 'resident', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"status\":\"pending\",\"household_option\":\"existing\"}', '2026-09-24 13:37:04'),
(72, 2, 'resident_household_assigned', 'resident', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"household_id\":2,\"relationship_to_head\":\"Daughter\"}', '2026-09-24 13:37:04'),
(73, 2, 'resident_status_changed', 'resident', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"from\":\"pending\",\"to\":\"active\",\"reason\":\"An active Resident of San Jose Quirino Isabela\"}', '2026-09-24 13:38:21'),
(74, 1, 'announcement_created', 'announcement', 12, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-24 14:10:03'),
(75, 1, 'announcement_archived', 'announcement', 12, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-24 14:11:05'),
(76, 1, 'announcement_deleted', 'announcement', 12, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"title\":\"ythtrh6rh6\",\"status\":\"archived\",\"published_at\":\"2026-09-24 22:10:03\"}', '2026-09-24 14:11:10'),
(77, 2, 'announcement_created', 'announcement', 13, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-24 15:54:34'),
(78, 2, 'resident_updated', 'resident', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"changed_fields\":[\"purok\"]}', '2026-09-24 16:02:39'),
(79, 2, 'announcement_created', 'announcement', 14, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-24 16:03:06'),
(80, NULL, 'complaint_submitted', 'case', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference\":\"CMP-2026-436C1A\",\"source\":\"online\"}', '2026-09-25 00:22:29'),
(81, 2, 'evidence_uploaded', 'case', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference\":\"CMP-2026-436C1A\"}', '2026-09-25 00:39:20'),
(82, 2, 'complaint_review_started', 'case', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference\":\"CMP-2026-436C1A\",\"from\":\"pending_review\",\"to\":\"under_review\"}', '2026-09-25 00:39:34'),
(83, 2, 'venue_created', 'case', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-25 00:40:32'),
(84, 2, 'personnel_created', 'case', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-25 00:41:16'),
(85, 2, 'announcement_created', 'announcement', 15, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-25 02:28:14'),
(86, NULL, 'document_requested', 'document', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference_code\":\"DOC-2026-854095\",\"document_type\":\"Certificate of Indigency\",\"resident_id\":2,\"source\":\"online\"}', '2026-09-25 02:29:56'),
(87, 2, 'resident_created', 'resident', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"status\":\"active\",\"household_option\":\"later\"}', '2026-09-25 06:44:20'),
(88, 2, 'document_requested', 'document', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference_code\":\"DOC-2026-2AF6E3\",\"document_type\":\"Barangay Clearance\",\"resident_id\":3,\"source\":\"staff\"}', '2026-09-25 07:49:23'),
(89, 2, 'resident_updated', 'resident', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"changed_fields\":[\"years_of_residency\"]}', '2026-09-25 08:10:54'),
(90, 2, 'document_rejected', 'document', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference_code\":\"DOC-2026-2AF6E3\",\"from\":\"pending\",\"to\":\"rejected\",\"reason\":\"wrong information\"}', '2026-09-25 08:11:40'),
(91, 2, 'resident_updated', 'resident', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"changed_fields\":[\"years_of_residency\"]}', '2026-09-25 08:12:12'),
(92, 2, 'document_rejected', 'document', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference_code\":\"DOC-2026-9028BC\",\"from\":\"pending\",\"to\":\"rejected\",\"reason\":\"hehehe\"}', '2026-09-25 08:13:39'),
(93, 2, 'document_requested', 'document', 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference_code\":\"DOC-2026-E4A4A0\",\"document_type\":\"Barangay Clearance\",\"resident_id\":3,\"source\":\"staff\"}', '2026-09-25 08:15:05'),
(94, 2, 'document_approved', 'document', 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference_code\":\"DOC-2026-E4A4A0\",\"from\":\"pending\",\"to\":\"approved\"}', '2026-09-25 08:20:56'),
(95, 1, 'account_profile_updated', 'user', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"changed_fields\":[\"name\",\"email\"]}', '2026-09-25 09:00:35'),
(96, 1, 'account_password_changed', 'user', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-25 09:02:16'),
(97, 1, 'audit_log_exported', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"rows\":96,\"filters\":[]}', '2026-09-25 09:32:19'),
(98, 1, 'document_print_opened', 'document', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference\":\"DOC-2026-854095\",\"document_type\":\"Certificate of Indigency\",\"layout\":\"official format\"}', '2026-09-25 10:18:24'),
(99, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"portal\":\"admin\"}', '2026-09-25 11:44:05'),
(100, 2, 'auth_login_failed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"portal\":\"admin\",\"email\":\"secretary@san-jose.local\",\"reason\":\"wrong_portal\"}', '2026-09-25 11:44:10'),
(101, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-09-25 11:44:15'),
(102, 1, 'inventory_created', 'inventory', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"item_code\":\"INV-001\",\"item_type\":\"equipment\",\"quantity\":2,\"status\":\"in_use\"}', '2026-09-25 12:00:07'),
(103, 1, 'inventory_updated', 'inventory', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"item_code\":\"INV-001\",\"changed_fields\":[\"status\"],\"status_from\":\"in_use\",\"status_to\":\"borrowed\"}', '2026-09-25 12:00:30'),
(104, 1, 'inventory_borrowed', 'inventory', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"item_code\":\"INV-001\",\"reference\":\"BR-2026-0001\",\"quantity\":1}', '2026-09-25 12:02:48'),
(105, 1, 'inventory_borrowed', 'inventory', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"item_code\":\"INV-001\",\"reference\":\"BR-2026-0002\",\"quantity\":1}', '2026-09-25 12:03:55'),
(106, 1, 'health_record_created', 'health', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"record_no\":\"HLT-0001\",\"service\":\"check_up\",\"status\":\"completed\"}', '2026-09-25 14:00:19'),
(107, 4, 'disaster_records_printed', 'disaster', 0, '127.0.0.1', '', '{\"rows\":0,\"filters\":\"All records\"}', '2026-09-25 17:16:12'),
(108, NULL, 'access_denied', 'security', NULL, '127.0.0.1', NULL, '{\"page\":\"live_get.php\",\"method\":\"GET\",\"role\":\"resident\"}', '2026-09-25 17:16:12'),
(109, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"portal\":\"admin\"}', '2026-09-25 17:20:06'),
(110, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-09-25 17:20:09'),
(111, 1, 'disaster_record_created', 'disaster', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference\":\"DRR-2026-01\",\"record_type\":\"incident\",\"status\":\"monitoring\"}', '2026-09-25 17:22:56'),
(112, 1, 'access_denied', 'security', NULL, '127.0.0.1', NULL, '{\"page\":\"live_get.php\",\"method\":\"GET\",\"role\":\"super_admin\"}', '2026-09-25 17:40:40'),
(113, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"portal\":\"admin\"}', '2026-09-25 18:30:34'),
(114, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-09-25 18:30:37'),
(115, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"role\":\"super_admin\"}', '2026-09-25 18:34:03'),
(116, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"portal\":\"treasurer\"}', '2026-09-25 18:34:09'),
(117, 3, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"portal\":\"treasurer\",\"role\":\"treasurer\"}', '2026-09-25 18:34:16'),
(118, 3, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"role\":\"treasurer\"}', '2026-09-25 18:38:15'),
(119, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"portal\":\"admin\"}', '2026-09-25 18:38:25'),
(120, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-09-25 18:38:30'),
(121, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"role\":\"super_admin\"}', '2026-09-25 19:01:03'),
(122, NULL, 'admin_gate_failed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"portal\":\"admin\",\"locked\":false}', '2026-09-26 00:20:38'),
(123, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"portal\":\"admin\"}', '2026-09-26 00:20:42'),
(124, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-09-26 00:20:43'),
(125, 1, 'document_approved', 'document', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference_code\":\"DOC-2026-854095\",\"from\":\"pending\",\"to\":\"approved\"}', '2026-09-26 00:39:59'),
(126, 1, 'document_print_opened', 'document', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference\":\"DOC-2026-9F1ED2\",\"document_type\":\"Barangay Clearance\",\"layout\":\"official format\"}', '2026-09-26 00:40:35'),
(127, 1, 'document_released', 'document', 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference_code\":\"DOC-2026-E4A4A0\",\"from\":\"approved\",\"to\":\"released\",\"received_by\":\"john doe\"}', '2026-09-26 00:46:17'),
(128, 1, 'document_released', 'document', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference_code\":\"DOC-2026-F9B836\",\"from\":\"approved\",\"to\":\"released\",\"received_by\":\"benny balimbin\"}', '2026-09-26 00:46:29'),
(129, 1, 'document_released', 'document', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference_code\":\"DOC-2026-4080A3\",\"from\":\"approved\",\"to\":\"released\",\"received_by\":\"benny balimbin\"}', '2026-09-26 00:46:40'),
(130, 1, 'document_released', 'document', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference_code\":\"DOC-2026-4D4B0F\",\"from\":\"approved\",\"to\":\"released\",\"received_by\":\"benny balimbin\"}', '2026-09-26 00:46:49'),
(131, 1, 'document_requested', 'document', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference_code\":\"DOC-2026-66DBCD\",\"document_type\":\"Barangay Clearance\",\"resident_id\":1,\"source\":\"staff\"}', '2026-09-26 00:47:07'),
(132, 1, 'document_approved', 'document', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference_code\":\"DOC-2026-66DBCD\",\"from\":\"pending\",\"to\":\"approved\"}', '2026-09-26 00:47:14'),
(133, 1, 'inventory_labels_printed', 'inventory', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"labels\":1}', '2026-09-26 00:51:29'),
(134, 1, 'inventory_labels_printed', 'inventory', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"labels\":1}', '2026-09-26 00:52:10'),
(135, 1, 'inventory_report_generated', 'inventory', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"rows\":1,\"filters\":\"All active items\"}', '2026-09-26 00:52:18'),
(136, 1, 'inventory_report_generated', 'inventory', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"rows\":1,\"filters\":\"All active items\"}', '2026-09-26 00:52:25'),
(137, 1, 'inventory_exported', 'inventory', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"format\":\"xlsx\",\"rows\":1,\"filters\":\"All active items\"}', '2026-09-26 00:52:32'),
(138, 1, 'inventory_labels_printed', 'inventory', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"labels\":1}', '2026-09-26 00:52:42'),
(139, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"role\":\"super_admin\"}', '2026-09-26 00:56:39'),
(140, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"portal\":\"treasurer\"}', '2026-09-26 00:56:46'),
(141, 3, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"portal\":\"treasurer\",\"role\":\"treasurer\"}', '2026-09-26 00:56:51'),
(142, 3, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"role\":\"treasurer\"}', '2026-09-26 00:57:54'),
(143, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"portal\":\"admin\"}', '2026-09-26 00:58:00'),
(144, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-09-26 00:58:04'),
(145, 1, 'personnel_updated', 'case', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"changed_fields\":[\"service_type\",\"term_start_year\",\"term_end_year\"]}', '2026-09-26 01:05:40'),
(146, 1, 'personnel_created', 'case', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-26 01:07:10'),
(147, 1, 'personnel_created', 'case', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-26 01:08:02'),
(148, 1, 'personnel_created', 'case', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-26 01:08:43'),
(149, 1, 'personnel_created', 'case', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-26 01:09:24'),
(150, 1, 'personnel_created', 'case', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-26 01:09:57'),
(151, 1, 'personnel_created', 'case', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-26 01:10:44'),
(152, 1, 'personnel_created', 'case', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-26 01:11:38'),
(153, 1, 'personnel_created', 'case', 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-26 01:12:27'),
(154, 1, 'personnel_created', 'case', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-26 01:13:05'),
(155, 1, 'account_updated', 'user', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"name\":\"Jamil R. Tabuyo\",\"changed_fields\":[\"name\"],\"changes\":{\"name\":{\"old\":\"Test Secretary\",\"new\":\"Jamil R. Tabuyo\"}}}', '2026-09-26 01:14:44'),
(156, 1, 'resident_created', 'resident', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"status\":\"active\",\"household_option\":\"later\"}', '2026-09-26 01:18:41'),
(157, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"role\":\"super_admin\"}', '2026-09-26 03:46:10'),
(158, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"portal\":\"admin\"}', '2026-09-26 06:30:46'),
(159, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-09-26 06:30:56'),
(160, 1, 'resident_updated', 'resident', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"changed_fields\":[\"contact_number\"]}', '2026-09-27 13:34:19'),
(161, 1, 'announcement_created', 'announcement', 16, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-27 13:35:05'),
(162, 1, 'announcement_created', 'announcement', 17, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-27 13:40:12'),
(163, 1, 'announcement_created', 'announcement', 18, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-27 13:45:48'),
(164, 1, 'announcement_created', 'announcement', 19, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-27 13:50:24'),
(165, 1, 'resident_updated', 'resident', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"changed_fields\":[\"contact_number\"]}', '2026-09-27 13:50:56'),
(166, 1, 'resident_updated', 'resident', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"changed_fields\":[\"contact_number\"]}', '2026-09-27 13:51:17'),
(167, 1, 'announcement_created', 'announcement', 20, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-27 13:51:33'),
(168, 1, 'document_print_opened', 'document', 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference\":\"DOC-2026-E4A4A0\",\"document_type\":\"Barangay Clearance\",\"layout\":\"official format\"}', '2026-09-27 13:53:38'),
(169, 1, 'document_print_opened', 'document', 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference\":\"DOC-2026-E4A4A0\",\"document_type\":\"Barangay Clearance\",\"layout\":\"official format\"}', '2026-09-27 13:53:39'),
(170, 1, 'announcement_created', 'announcement', 21, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-27 13:54:17'),
(171, 1, 'resident_created', 'resident', 5, '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '{\"status\":\"pending\",\"household_option\":\"later\"}', '2026-09-27 13:56:32'),
(172, 1, 'announcement_created', 'announcement', 22, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-27 13:56:50'),
(173, 1, 'resident_status_changed', 'resident', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"from\":\"pending\",\"to\":\"active\",\"reason\":\"bastafdsgety55yg\"}', '2026-09-27 13:57:56'),
(174, 1, 'announcement_created', 'announcement', 23, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-27 13:58:27'),
(175, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"role\":\"super_admin\"}', '2026-09-27 14:16:30'),
(176, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"portal\":\"treasurer\"}', '2026-09-27 14:16:39'),
(177, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"portal\":\"admin\"}', '2026-09-27 14:16:51'),
(178, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-09-27 14:16:56'),
(179, 1, 'resident_updated', 'resident', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"changed_fields\":[\"contact_number\"]}', '2026-09-27 14:19:11'),
(180, 1, 'announcement_created', 'announcement', 24, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-27 14:19:47'),
(181, 1, 'resident_updated', 'resident', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"changed_fields\":[\"contact_number\"]}', '2026-09-27 14:23:08'),
(182, 1, 'announcement_created', 'announcement', 25, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{}', '2026-09-27 14:23:21'),
(183, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"role\":\"super_admin\"}', '2026-09-27 14:30:48'),
(184, NULL, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"portal\":\"resident\",\"role\":\"resident\"}', '2026-09-27 14:31:03'),
(185, NULL, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"role\":\"resident\"}', '2026-09-27 14:31:14'),
(186, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"portal\":\"admin\"}', '2026-09-27 14:31:24'),
(187, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-09-27 14:31:32'),
(188, 1, 'resident_created', 'resident', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"status\":\"active\",\"household_option\":\"later\"}', '2026-09-27 14:41:43'),
(189, 1, 'account_created', 'user', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"name\":\"Test gammad TST\",\"account_role\":\"resident\",\"linked_resident_id\":6}', '2026-09-27 14:41:43'),
(190, 1, 'document_print_opened', 'document', 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '{\"reference\":\"DOC-2026-E4A4A0\",\"document_type\":\"Barangay Clearance\",\"layout\":\"official format\"}', '2026-09-27 14:44:54'),
(191, NULL, 'admin_gate_failed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"secretary\",\"locked\":false}', '2026-09-29 01:17:16'),
(192, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-09-29 05:51:15'),
(193, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-09-29 05:51:19'),
(194, 1, 'resident_updated', 'resident', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"changed_fields\":[\"first_name\",\"last_name\",\"contact_number\"]}', '2026-09-29 05:52:13'),
(195, 1, 'announcement_archived', 'announcement', 25, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{}', '2026-09-29 05:52:25'),
(196, 1, 'announcement_created', 'announcement', 26, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{}', '2026-09-29 05:53:14'),
(197, 1, 'resident_updated', 'resident', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"changed_fields\":[\"contact_number\"]}', '2026-09-29 05:57:11'),
(198, 1, 'resident_created', 'resident', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"status\":\"active\",\"household_option\":\"later\"}', '2026-09-29 06:02:22'),
(199, 1, 'account_created', 'user', 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"name\":\"John P Doe\",\"account_role\":\"resident\",\"linked_resident_id\":7}', '2026-09-29 06:02:23'),
(200, 1, 'announcement_created', 'announcement', 27, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{}', '2026-09-29 06:02:49'),
(201, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"super_admin\"}', '2026-09-29 06:32:11'),
(202, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-09-29 06:35:55'),
(203, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-09-29 06:35:58');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `ip_address`, `user_agent`, `details`, `created_at`) VALUES
(204, 1, 'document_print_opened', 'document', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"reference\":\"DOC-2026-66DBCD\",\"document_type\":\"Barangay Clearance\",\"layout\":\"official format\"}', '2026-09-29 06:39:04'),
(205, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"super_admin\"}', '2026-09-29 06:41:23'),
(206, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-09-29 06:41:55'),
(207, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-09-29 06:41:57'),
(208, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"super_admin\"}', '2026-09-29 06:47:19'),
(209, NULL, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"resident\",\"role\":\"resident\"}', '2026-09-29 06:47:32'),
(210, NULL, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"resident\"}', '2026-09-29 06:50:40'),
(211, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"secretary\"}', '2026-09-29 06:50:48'),
(212, 2, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"secretary\",\"role\":\"secretary\"}', '2026-09-29 06:50:57'),
(213, 2, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"secretary\"}', '2026-09-29 06:51:30'),
(214, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"secretary\"}', '2026-09-29 06:52:36'),
(215, NULL, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"resident\",\"role\":\"resident\"}', '2026-09-29 06:53:27'),
(216, NULL, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"resident\"}', '2026-09-29 06:54:27'),
(217, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-09-29 06:54:48'),
(218, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-09-29 06:54:59'),
(219, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"super_admin\"}', '2026-09-29 06:55:32'),
(220, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"treasurer\"}', '2026-09-29 06:55:39'),
(221, 3, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"treasurer\",\"role\":\"treasurer\"}', '2026-09-29 06:55:49'),
(222, 3, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"treasurer\"}', '2026-09-29 06:57:02'),
(223, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"secretary\"}', '2026-09-29 06:57:13'),
(224, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"treasurer\"}', '2026-09-29 06:57:26'),
(225, 3, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"treasurer\",\"role\":\"treasurer\"}', '2026-09-29 06:57:29'),
(226, 3, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"treasurer\"}', '2026-09-29 07:05:31'),
(227, NULL, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"resident\",\"role\":\"resident\"}', '2026-09-29 07:05:42'),
(228, NULL, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"resident\"}', '2026-09-29 07:06:48'),
(229, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-09-29 07:06:56'),
(230, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-09-29 07:07:02'),
(231, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"treasurer\"}', '2026-09-29 11:54:34'),
(232, 3, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"treasurer\",\"role\":\"treasurer\"}', '2026-09-29 11:54:53'),
(233, 3, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"treasurer\"}', '2026-09-29 13:08:30'),
(234, NULL, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"resident\",\"role\":\"resident\"}', '2026-09-29 13:08:45'),
(235, NULL, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"resident\"}', '2026-09-29 13:09:41'),
(236, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-09-29 13:09:48'),
(237, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-09-29 13:10:07'),
(238, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"super_admin\"}', '2026-09-29 13:12:41'),
(239, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"treasurer\"}', '2026-09-29 13:12:48'),
(240, 3, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"treasurer\",\"role\":\"treasurer\"}', '2026-09-29 13:12:53'),
(241, 3, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"treasurer\"}', '2026-09-29 13:19:54'),
(242, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-09-29 13:20:30'),
(243, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-09-29 13:20:36'),
(244, 1, 'resident_created', 'resident', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"status\":\"active\",\"household_option\":\"later\"}', '2026-09-29 13:24:43'),
(245, 1, 'account_created', 'user', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"name\":\"King gammad Terrenal\",\"account_role\":\"resident\",\"linked_resident_id\":8}', '2026-09-29 13:24:43'),
(246, 1, 'resident_status_changed', 'resident', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"from\":\"active\",\"to\":\"inactive\",\"reason\":\"waal alang\"}', '2026-09-29 13:25:36'),
(247, 1, 'resident_status_changed', 'resident', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"from\":\"inactive\",\"to\":\"active\",\"reason\":\"asdasfa\\r\\nfasfdas\"}', '2026-09-29 13:25:52'),
(248, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"super_admin\"}', '2026-09-29 13:26:18'),
(249, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"treasurer\"}', '2026-09-29 13:26:25'),
(250, 3, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"treasurer\",\"role\":\"treasurer\"}', '2026-09-29 13:26:30'),
(251, 3, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"treasurer\"}', '2026-09-29 13:26:59'),
(252, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-09-29 13:27:08'),
(253, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-09-29 13:27:11'),
(254, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"super_admin\"}', '2026-09-29 13:29:06'),
(255, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"treasurer\"}', '2026-09-29 13:39:16'),
(256, 3, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"treasurer\",\"role\":\"treasurer\"}', '2026-09-29 13:39:21'),
(257, 3, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"treasurer\"}', '2026-09-29 13:56:24'),
(258, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-09-29 13:56:31'),
(259, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-09-29 13:56:35'),
(260, 1, 'assistance_scheduled', 'assistance', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"reference\":\"AST-2026-0001\",\"name\":\"Benny Hernandez Balimbin\",\"category\":\"Food Assistance\",\"form\":\"cash\",\"given\":\"₱1,000.00 cash\",\"source\":\"Donation\",\"incident_id\":1,\"group\":\"residents\",\"repeat\":\"no\"}', '2026-09-29 17:02:45'),
(261, 1, 'assistance_scheduled', 'assistance', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"reference\":\"AST-2026-0002\",\"name\":\"Glyza Martinez Balimbin\",\"category\":\"Food Assistance\",\"form\":\"cash\",\"given\":\"₱1,000.00 cash\",\"source\":\"Donation\",\"incident_id\":1,\"group\":\"residents\",\"repeat\":\"no\"}', '2026-09-29 17:02:45'),
(262, 1, 'assistance_scheduled', 'assistance', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"reference\":\"AST-2026-0003\",\"name\":\"John P Doe\",\"category\":\"Food Assistance\",\"form\":\"cash\",\"given\":\"₱1,000.00 cash\",\"source\":\"Donation\",\"incident_id\":1,\"group\":\"residents\",\"repeat\":\"no\"}', '2026-09-29 17:02:45'),
(263, 1, 'assistance_scheduled', 'assistance', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"reference\":\"AST-2026-0004\",\"name\":\"Jay el Pogi Lamag\",\"category\":\"Food Assistance\",\"form\":\"cash\",\"given\":\"₱1,000.00 cash\",\"source\":\"Donation\",\"incident_id\":1,\"group\":\"residents\",\"repeat\":\"no\"}', '2026-09-29 17:02:45'),
(264, 1, 'assistance_scheduled', 'assistance', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"reference\":\"AST-2026-0005\",\"name\":\"John lloyd P. Lamag\",\"category\":\"Food Assistance\",\"form\":\"cash\",\"given\":\"₱1,000.00 cash\",\"source\":\"Donation\",\"incident_id\":1,\"group\":\"residents\",\"repeat\":\"no\"}', '2026-09-29 17:02:45'),
(265, 1, 'assistance_scheduled', 'assistance', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"reference\":\"AST-2026-0006\",\"name\":\"King gammad Terrenal\",\"category\":\"Food Assistance\",\"form\":\"cash\",\"given\":\"₱1,000.00 cash\",\"source\":\"Donation\",\"incident_id\":1,\"group\":\"residents\",\"repeat\":\"no\"}', '2026-09-29 17:02:45'),
(266, 1, 'assistance_scheduled', 'assistance', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"reference\":\"AST-2026-0007\",\"name\":\"Test gammad TST\",\"category\":\"Food Assistance\",\"form\":\"cash\",\"given\":\"₱1,000.00 cash\",\"source\":\"Donation\",\"incident_id\":1,\"group\":\"residents\",\"repeat\":\"no\"}', '2026-09-29 17:02:45'),
(267, 1, 'assistance_scheduled', 'assistance', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"reference\":\"AST-2026-0008\",\"name\":\"ella joy gammad vicente\",\"category\":\"Food Assistance\",\"form\":\"cash\",\"given\":\"₱1,000.00 cash\",\"source\":\"Donation\",\"incident_id\":1,\"group\":\"residents\",\"repeat\":\"no\"}', '2026-09-29 17:02:45'),
(268, 1, 'inventory_labels_printed', 'inventory', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"labels\":1}', '2026-09-29 17:13:51'),
(269, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"super_admin\"}', '2026-09-29 17:27:53'),
(270, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"secretary\"}', '2026-09-29 17:28:01'),
(271, 2, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"secretary\",\"role\":\"secretary\"}', '2026-09-29 17:28:14'),
(272, 2, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"secretary\"}', '2026-09-29 17:52:13'),
(273, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-09-29 17:52:20'),
(274, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-09-29 17:52:25'),
(275, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-09-30 05:40:27'),
(276, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-09-30 05:40:30'),
(277, 1, 'document_requested', 'document', 11, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"reference_code\":\"DOC-2026-E3A119\",\"document_type\":\"Certificate of Indigency\",\"resident_id\":3,\"source\":\"staff\"}', '2026-09-30 05:47:12'),
(278, 1, 'document_print_opened', 'document', 11, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"reference\":\"DOC-2026-E3A119\",\"document_type\":\"Certificate of Indigency\",\"layout\":\"official format\"}', '2026-09-30 05:48:15'),
(279, 1, 'household_updated', 'household', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"changed_fields\":[\"purok\"]}', '2026-09-30 06:01:47'),
(280, 1, 'disaster_alert_issued', 'disaster_alert', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"name\":\"pardas\",\"level\":\"red\",\"areas\":\"All Puroks\",\"recipients\":10}', '2026-09-30 06:06:26'),
(281, 1, 'disaster_center_created', 'disaster_center', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"name\":\"court\"}', '2026-09-30 06:09:32'),
(282, 1, 'disaster_evacuee_checked_in', 'disaster_evacuation', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"name\":\"148 · Head: Benny Hernandez Balimbin\",\"center\":\"court\",\"persons\":2}', '2026-09-30 06:12:17'),
(283, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-10-02 02:39:30'),
(284, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-10-02 02:39:32'),
(285, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"super_admin\"}', '2026-10-02 02:48:11'),
(286, NULL, 'sms_subscriber_registered', 'sms_subscriber', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"purok\":\"1\",\"mobile\":\"0991•••8709\"}', '2026-10-02 03:07:09'),
(287, 1, 'auth_login_failed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"resident\",\"email\":\"bj123@gmail.com\",\"reason\":\"wrong_portal\"}', '2026-10-02 04:03:33'),
(288, NULL, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"resident\",\"role\":\"resident\"}', '2026-10-02 04:03:48'),
(289, NULL, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"resident\"}', '2026-10-02 04:16:01'),
(290, NULL, 'resident_self_registered', 'user', 11, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"name\":\"Ryco Pacelo Gammad\",\"resident_id\":9,\"resident_record\":\"new\",\"login\":\"email\",\"purok\":\"1\",\"mobile\":\"0991•••8709\"}', '2026-10-02 04:19:28'),
(291, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-10-02 04:19:49'),
(292, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-10-02 04:19:53'),
(293, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-10-02 04:40:24'),
(294, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-10-02 04:40:29'),
(295, 1, 'auth_login_failed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"resident\",\"email\":\"bj123@gmail.com\",\"reason\":\"wrong_portal\"}', '2026-10-02 14:17:11'),
(296, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-10-02 14:17:27'),
(297, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-10-02 14:17:29'),
(298, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"super_admin\"}', '2026-10-02 14:34:57'),
(299, NULL, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"resident\",\"role\":\"resident\"}', '2026-10-02 14:35:05'),
(300, NULL, 'household_member_requested', 'household_request', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"resident_id\":10,\"relationship_to_requester\":\"Brother\",\"possible_matches\":0}', '2026-10-02 14:38:57'),
(301, NULL, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"resident\"}', '2026-10-02 14:39:08'),
(302, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-10-02 14:39:17'),
(303, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-10-02 14:39:21'),
(304, 1, 'resident_status_changed', 'resident', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"from\":\"pending\",\"to\":\"active\",\"reason\":\"Family member request FAM-2026-0001 approved\"}', '2026-10-02 14:40:07'),
(305, 1, 'resident_household_assigned', 'resident', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"household_id\":2,\"relationship_to_head\":\"Son\",\"source\":\"FAM-2026-0001\"}', '2026-10-02 14:40:07'),
(306, 1, 'household_member_approved', 'household_request', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"resident_id\":10}', '2026-10-02 14:40:07'),
(307, 1, 'account_status_changed', 'user', 11, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"name\":\"Ryco Pacelo Gammad\",\"status_from\":\"pending\",\"status_to\":\"active\"}', '2026-10-02 14:40:17'),
(308, 1, 'resident_status_changed', 'resident', 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"from\":\"pending\",\"to\":\"active\",\"reason\":\"Online registration REG-2026-0001 approved\"}', '2026-10-02 14:40:17'),
(309, 1, 'registration_approved', 'registration', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{}', '2026-10-02 14:40:17'),
(310, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"super_admin\"}', '2026-10-02 14:40:27'),
(311, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-10-02 14:40:41'),
(312, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-10-02 14:40:44'),
(313, 1, 'household_created', 'household', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"household_no\":\"P1-0001\"}', '2026-10-02 15:12:37'),
(314, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"super_admin\"}', '2026-10-02 15:13:13'),
(315, NULL, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"resident\",\"role\":\"resident\"}', '2026-10-02 15:13:23'),
(316, NULL, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"resident\"}', '2026-10-02 15:13:35'),
(317, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-10-02 15:13:41'),
(318, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-10-02 15:13:45'),
(319, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"super_admin\"}', '2026-10-02 15:16:35'),
(320, NULL, 'resident_self_registered', 'user', 12, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"name\":\"Jayson Torres Vicente\",\"application_id\":2,\"resident_id\":11,\"resident_record\":\"new\",\"login\":\"email\",\"purok\":\"1\",\"mobile\":\"0991•••8708\"}', '2026-10-02 15:56:08'),
(321, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-10-02 15:57:01'),
(322, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-10-02 15:57:03'),
(323, 1, 'account_status_changed', 'user', 12, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"name\":\"Jayson Torres Vicente\",\"status_from\":\"pending\",\"status_to\":\"active\"}', '2026-10-02 16:04:43'),
(324, 1, 'resident_status_changed', 'resident', 11, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"from\":\"pending\",\"to\":\"active\",\"reason\":\"Online registration REG-2026-0002 approved\"}', '2026-10-02 16:04:43'),
(325, 1, 'household_created', 'household', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"household_no\":\"P1-0002\",\"first_member_resident_id\":11}', '2026-10-02 16:04:43'),
(326, 1, 'resident_household_assigned', 'resident', 11, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"household_id\":4,\"relationship_to_head\":null,\"source\":\"REG-2026-0002\"}', '2026-10-02 16:04:44'),
(327, 1, 'household_head_changed', 'household', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"previous_head_resident_id\":null,\"new_head_resident_id\":11}', '2026-10-02 16:04:44'),
(328, 1, 'registration_approved', 'registration', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{}', '2026-10-02 16:04:44'),
(329, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"super_admin\"}', '2026-10-02 16:04:56'),
(330, NULL, 'resident_self_registered', 'user', 13, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"name\":\"Marrieta gammad Vicente\",\"application_id\":3,\"resident_id\":12,\"resident_record\":\"new\",\"login\":\"email\",\"purok\":\"1\",\"mobile\":\"0991•••8707\"}', '2026-10-02 16:13:26'),
(331, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-10-02 16:13:36'),
(332, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-10-02 16:13:38'),
(333, 1, 'account_status_changed', 'user', 13, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"name\":\"Marrieta gammad Vicente\",\"status_from\":\"pending\",\"status_to\":\"active\"}', '2026-10-02 16:13:57'),
(334, 1, 'resident_status_changed', 'resident', 12, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"from\":\"pending\",\"to\":\"active\",\"reason\":\"Online registration REG-2026-0003 approved\"}', '2026-10-02 16:13:57'),
(335, 1, 'resident_household_assigned', 'resident', 12, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"household_id\":4,\"relationship_to_head\":\"Spouse\",\"source\":\"REG-2026-0003\"}', '2026-10-02 16:13:57'),
(336, 1, 'registration_approved', 'registration', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{}', '2026-10-02 16:13:57'),
(337, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"super_admin\"}', '2026-10-02 16:15:25'),
(338, NULL, 'auth_login_failed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"resident\",\"email\":\"vicentejayson@gmail.com\",\"reason\":\"wrong_password\"}', '2026-10-02 16:16:02'),
(339, NULL, 'auth_login_failed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"resident\",\"email\":\"vicentejayson@gmail.com\",\"reason\":\"wrong_password\"}', '2026-10-02 16:16:11'),
(340, NULL, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"resident\",\"role\":\"resident\"}', '2026-10-02 16:16:16'),
(341, NULL, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"resident\"}', '2026-10-02 16:16:59'),
(342, NULL, 'resident_self_registered', 'user', 14, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"name\":\"Jhon Lenard gammad Vicente\",\"application_id\":4,\"resident_id\":13,\"resident_record\":\"new\",\"login\":\"email\",\"purok\":\"1\",\"mobile\":\"0991•••8706\"}', '2026-10-02 16:19:41'),
(343, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-10-02 16:19:52'),
(344, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-10-02 16:20:00'),
(345, 1, 'account_status_changed', 'user', 14, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"name\":\"Jhon Lenard gammad Vicente\",\"status_from\":\"pending\",\"status_to\":\"active\"}', '2026-10-02 16:20:28'),
(346, 1, 'resident_status_changed', 'resident', 13, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"from\":\"pending\",\"to\":\"active\",\"reason\":\"Online registration REG-2026-0004 approved\"}', '2026-10-02 16:20:28'),
(347, 1, 'resident_household_assigned', 'resident', 13, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"household_id\":4,\"relationship_to_head\":\"Son\",\"source\":\"REG-2026-0004\"}', '2026-10-02 16:20:28'),
(348, 1, 'registration_approved', 'registration', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{}', '2026-10-02 16:20:28'),
(349, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"super_admin\"}', '2026-10-02 16:38:19'),
(350, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-10-02 16:38:34'),
(351, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-10-02 16:38:36'),
(352, 1, 'document_requested', 'document', 12, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"reference_code\":\"DOC-2026-88163C\",\"document_type\":\"Barangay Clearance\",\"resident_id\":13,\"source\":\"staff\"}', '2026-10-02 16:47:58'),
(353, 1, 'health_record_created', 'health', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"record_no\":\"HLT-0002\",\"service\":\"check_up\",\"status\":\"scheduled\"}', '2026-10-02 16:49:56'),
(354, 1, 'disaster_hazard_created', 'disaster_hazard', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"name\":\"Purok 1 — Flood\"}', '2026-10-02 16:53:46'),
(355, 1, 'disaster_contact_created', 'disaster_contact', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"name\":\"MDRRMO\"}', '2026-10-02 16:57:08'),
(356, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"super_admin\"}', '2026-10-02 17:01:50'),
(357, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"treasurer\"}', '2026-10-02 17:01:57'),
(358, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"secretary\"}', '2026-10-02 17:02:09'),
(359, 2, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"secretary\",\"role\":\"secretary\"}', '2026-10-02 17:02:15'),
(360, 2, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"secretary\"}', '2026-10-02 17:02:36'),
(361, NULL, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"resident\",\"role\":\"resident\"}', '2026-10-02 17:02:50'),
(362, NULL, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"resident\"}', '2026-10-02 17:03:42'),
(363, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-10-02 17:03:47'),
(364, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-10-02 17:03:50'),
(365, NULL, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"resident\",\"role\":\"resident\"}', '2026-10-03 04:00:52'),
(366, NULL, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"resident\"}', '2026-10-03 04:01:08'),
(367, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-10-03 04:01:15'),
(368, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-10-03 04:01:19'),
(369, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-10-03 04:38:48'),
(370, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-10-03 04:38:52'),
(371, 1, 'access_denied', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"page\":\"module.php\",\"method\":\"GET\",\"role\":\"super_admin\"}', '2026-10-03 04:47:25'),
(372, 1, 'access_denied', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"page\":\"module.php\",\"method\":\"GET\",\"role\":\"super_admin\"}', '2026-10-03 04:47:27'),
(373, 1, 'announcement_created', 'announcement', 28, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{}', '2026-10-03 04:53:42'),
(374, 1, 'access_denied', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"page\":\"health.php\",\"method\":\"GET\",\"role\":\"super_admin\"}', '2026-10-03 05:52:41'),
(375, 1, 'access_denied', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"page\":\"health.php\",\"method\":\"GET\",\"role\":\"super_admin\"}', '2026-10-03 05:52:43'),
(376, 1, 'access_denied', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"page\":\"health.php\",\"method\":\"GET\",\"role\":\"super_admin\"}', '2026-10-03 05:52:44'),
(377, 1, 'access_denied', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"page\":\"health.php\",\"method\":\"GET\",\"role\":\"super_admin\"}', '2026-10-03 05:52:44'),
(378, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"super_admin\"}', '2026-10-03 05:53:00'),
(379, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"health\"}', '2026-10-03 05:53:07'),
(380, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-10-03 05:56:34'),
(381, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-10-03 05:56:36'),
(382, 1, 'account_created', 'user', 15, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"name\":\"Rynel May Gammad\",\"account_role\":\"health_worker\",\"assigned_puroks\":[\"1\"]}', '2026-10-03 06:01:25'),
(383, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"super_admin\"}', '2026-10-03 06:01:28'),
(384, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"health\"}', '2026-10-03 06:01:39'),
(385, 15, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"health\",\"role\":\"health_worker\"}', '2026-10-03 06:01:48'),
(386, 15, 'health_list_printed', 'health', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"rows\":1}', '2026-10-03 06:11:45'),
(387, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"health\"}', '2026-10-03 13:37:30'),
(388, 15, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"health\",\"role\":\"health_worker\"}', '2026-10-03 13:37:32'),
(389, 15, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"health_worker\"}', '2026-10-03 13:55:29'),
(390, NULL, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"resident\",\"role\":\"resident\"}', '2026-10-03 13:55:36'),
(391, NULL, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"resident\"}', '2026-10-03 13:57:16'),
(392, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"health\"}', '2026-10-03 13:57:22'),
(393, 15, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"health\",\"role\":\"health_worker\"}', '2026-10-03 13:57:45');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `ip_address`, `user_agent`, `details`, `created_at`) VALUES
(394, 15, 'health_record_created', 'health', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"record_no\":\"HLT-0003\",\"service\":\"check_up\",\"status\":\"completed\"}', '2026-10-03 14:03:19'),
(395, 15, 'health_record_viewed', 'health', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"record_no\":\"HLT-0003\"}', '2026-10-03 14:03:19'),
(396, 15, 'health_list_printed', 'health', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"rows\":1}', '2026-10-03 14:07:07'),
(397, 15, 'health_list_printed', 'health', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"rows\":2}', '2026-10-03 14:07:18'),
(398, 15, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"health_worker\"}', '2026-10-03 15:39:46'),
(399, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-10-03 15:39:52'),
(400, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-10-03 15:40:01'),
(401, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"health\"}', '2026-10-04 01:16:36'),
(402, 15, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"health\",\"role\":\"health_worker\"}', '2026-10-04 01:16:48'),
(403, 15, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"health_worker\"}', '2026-10-04 01:40:45'),
(404, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-10-04 01:40:52'),
(405, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-10-04 01:40:56'),
(406, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"super_admin\"}', '2026-10-04 02:07:10'),
(407, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"health\"}', '2026-10-04 02:07:15'),
(408, 15, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"health\",\"role\":\"health_worker\"}', '2026-10-04 02:07:20'),
(409, 15, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"health_worker\"}', '2026-10-04 02:08:15'),
(410, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-10-04 02:08:21'),
(411, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-10-04 02:08:27'),
(412, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-10-04 09:15:51'),
(413, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-10-04 09:15:54'),
(414, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"super_admin\"}', '2026-10-04 09:33:58'),
(415, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"health\"}', '2026-10-04 09:34:04'),
(416, 3, 'auth_login_failed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"health\",\"email\":\"treasurer@san-jose.local\",\"reason\":\"wrong_portal\"}', '2026-10-04 09:34:10'),
(417, 15, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"health\",\"role\":\"health_worker\"}', '2026-10-04 09:34:19'),
(418, 15, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"health_worker\"}', '2026-10-04 09:39:37'),
(419, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-10-04 09:39:46'),
(420, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-10-04 09:39:53'),
(421, 1, 'household_deleted', 'household', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"household_no\":\"147\",\"purok\":\"4\",\"address\":\"147 Purok 3 San Jose Quirino Isabela\"}', '2026-10-04 09:58:08'),
(422, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"super_admin\"}', '2026-10-04 10:04:47'),
(423, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"treasurer\"}', '2026-10-04 10:04:52'),
(424, 3, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"treasurer\",\"role\":\"treasurer\"}', '2026-10-04 10:04:59'),
(425, 3, 'access_denied', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"page\":\"disaster.php\",\"method\":\"GET\",\"role\":\"treasurer\"}', '2026-10-04 10:11:11'),
(426, 3, 'access_denied', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"page\":\"disaster.php\",\"method\":\"GET\",\"role\":\"treasurer\"}', '2026-10-04 10:11:13'),
(427, 3, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"treasurer\"}', '2026-10-04 10:11:25'),
(428, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-10-04 10:11:32'),
(429, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-10-04 10:11:36'),
(430, 1, 'account_created', 'user', 16, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"name\":\"Manjonel Gammad\",\"account_role\":\"official\"}', '2026-10-04 10:13:48'),
(431, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"super_admin\"}', '2026-10-04 10:13:53'),
(432, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"kagawad\"}', '2026-10-04 10:14:00'),
(433, 16, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"kagawad\",\"role\":\"official\"}', '2026-10-04 10:14:02'),
(434, 16, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"official\"}', '2026-10-04 10:14:25'),
(435, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-10-04 10:22:00'),
(436, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-10-04 10:22:04'),
(437, 1, 'account_created', 'user', 17, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"name\":\"Dine Dine Caronan Gammad\",\"account_role\":\"health_worker\",\"assigned_puroks\":[\"1\"]}', '2026-10-04 10:23:40'),
(438, 1, 'resident_created', 'resident', 14, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"source\":\"Health Worker account\",\"user_id\":17}', '2026-10-04 10:23:40'),
(439, 1, 'resident_account_linked', 'resident', 14, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"user_id\":17,\"source\":\"Health Worker account\"}', '2026-10-04 10:23:40'),
(440, 1, 'account_updated', 'user', 17, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"name\":\"Dine Dine Caronan Gammad\",\"changed_fields\":[\"linked_resident\"],\"resident_id\":14,\"resident_profile\":\"created\"}', '2026-10-04 10:23:40'),
(441, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"super_admin\"}', '2026-10-04 10:27:48'),
(442, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"health\"}', '2026-10-04 10:27:55'),
(443, 17, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"health\",\"role\":\"health_worker\"}', '2026-10-04 10:27:57'),
(444, 17, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"health_worker\"}', '2026-10-04 11:14:11'),
(445, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-10-04 11:14:18'),
(446, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-10-04 11:14:24'),
(447, 1, 'auth_logout', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"role\":\"super_admin\"}', '2026-10-04 11:15:10'),
(448, 17, 'auth_login_failed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"resident\",\"email\":\"dinedine@gmail.com\",\"reason\":\"wrong_portal\"}', '2026-10-04 11:15:17'),
(449, NULL, 'admin_gate_passed', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\"}', '2026-10-04 11:17:06'),
(450, 1, 'auth_login', 'security', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '{\"portal\":\"admin\",\"role\":\"super_admin\"}', '2026-10-04 11:17:13');

-- --------------------------------------------------------

--
-- Table structure for table `barangay_personnel`
--

CREATE TABLE `barangay_personnel` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(180) NOT NULL,
  `position` varchar(120) NOT NULL,
  `committee` varchar(120) DEFAULT NULL,
  `service_type` enum('elected','appointed') DEFAULT NULL,
  `term_start_year` smallint(5) UNSIGNED DEFAULT NULL,
  `term_end_year` smallint(5) UNSIGNED DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('active','inactive','on_leave') NOT NULL DEFAULT 'active',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `barangay_personnel`
--

INSERT INTO `barangay_personnel` (`id`, `full_name`, `position`, `committee`, `service_type`, `term_start_year`, `term_end_year`, `contact_number`, `user_id`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Wilson Tabuyo', 'Barangay Captain', NULL, 'elected', 2020, 2028, NULL, NULL, 'active', 2, '2026-09-25 00:41:16', '2026-09-26 01:05:40'),
(2, 'Jackson E. Tulay', 'Barangay Kagawad', NULL, 'elected', 2022, 2028, NULL, NULL, 'active', 1, '2026-09-26 01:07:10', '2026-09-26 01:07:10'),
(3, 'Junel L. Realista', 'Barangay Kagawad', NULL, 'elected', 2020, 2028, NULL, NULL, 'active', 1, '2026-09-26 01:08:02', '2026-09-26 01:08:02'),
(4, 'Hermogenes R. Serna', 'Barangay Kagawad', NULL, 'elected', 2022, 2028, NULL, NULL, 'active', 1, '2026-09-26 01:08:43', '2026-09-26 01:08:43'),
(5, 'Merson B. Calle', 'Barangay Kagawad', NULL, 'elected', 2020, 2028, NULL, NULL, 'active', 1, '2026-09-26 01:09:24', '2026-09-26 01:09:24'),
(6, 'Joel B. Este', 'Barangay Kagawad', NULL, 'elected', 2020, 2028, NULL, NULL, 'active', 1, '2026-09-26 01:09:57', '2026-09-26 01:09:57'),
(7, 'Jofriz Glenn A. Gilo', 'Barangay Kagawad', NULL, 'elected', 2020, 2028, NULL, NULL, 'active', 1, '2026-09-26 01:10:44', '2026-09-26 01:10:44'),
(8, 'Marry Ann B. Vidal', 'Barangay Treasurer', NULL, 'appointed', 2020, 2028, NULL, NULL, 'active', 1, '2026-09-26 01:11:38', '2026-09-26 01:11:38'),
(9, 'Alvin B. Tabuyo', 'Barangay Kagawad', NULL, 'elected', 2020, 2028, NULL, NULL, 'active', 1, '2026-09-26 01:12:27', '2026-09-26 01:12:27'),
(10, 'Jamil R. Tabuyo', 'Barangay Secretary', NULL, 'appointed', 2022, 2025, NULL, NULL, 'active', 1, '2026-09-26 01:13:05', '2026-09-26 01:13:05');

-- --------------------------------------------------------

--
-- Table structure for table `barangay_projects`
--

CREATE TABLE `barangay_projects` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `project_code` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('planned','ongoing','completed','suspended','cancelled') NOT NULL DEFAULT 'planned',
  `start_date` date DEFAULT NULL,
  `target_end_date` date DEFAULT NULL,
  `completed_at` date DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blotter_entries`
--

CREATE TABLE `blotter_entries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `blotter_number` varchar(50) NOT NULL,
  `complaint_id` bigint(20) UNSIGNED DEFAULT NULL,
  `incident_type` varchar(80) NOT NULL,
  `incident_at` datetime NOT NULL,
  `incident_location` varchar(255) NOT NULL,
  `narrative` text NOT NULL,
  `status` enum('recorded','active','resolved','closed') NOT NULL DEFAULT 'recorded',
  `is_confidential` tinyint(1) NOT NULL DEFAULT 1,
  `recorded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processing_started_by` bigint(20) UNSIGNED DEFAULT NULL,
  `processing_started_at` datetime DEFAULT NULL,
  `resolved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `closed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `closing_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `case_attachments`
--

CREATE TABLE `case_attachments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `complaint_id` bigint(20) UNSIGNED DEFAULT NULL,
  `blotter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_filename` varchar(100) NOT NULL,
  `storage_path` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` bigint(20) UNSIGNED NOT NULL,
  `sha256_checksum` char(64) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `uploaded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `removed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `removed_at` datetime DEFAULT NULL,
  `removal_reason` varchar(255) DEFAULT NULL
) ;

--
-- Dumping data for table `case_attachments`
--

INSERT INTO `case_attachments` (`id`, `complaint_id`, `blotter_id`, `original_filename`, `stored_filename`, `storage_path`, `mime_type`, `file_size`, `sha256_checksum`, `description`, `uploaded_by`, `uploaded_at`, `removed_by`, `removed_at`, `removal_reason`) VALUES
(1, 1, NULL, '2d259e91-250e-4da1-959c-e8fa98ce43e3.jpg', '44fadc0b0932e9afdda109471a555019', '2026/09', 'image/jpeg', 189579, 'b2165b55a3516ecd54a894aea9a1b1b05822b214390eb91927a7a027f4506df6', 'qwertyuiop', 2, '2026-09-25 00:39:20', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `case_attachment_access_log`
--

CREATE TABLE `case_attachment_access_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `attachment_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `access_type` enum('view','download') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `accessed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `case_attachment_access_log`
--

INSERT INTO `case_attachment_access_log` (`id`, `attachment_id`, `user_id`, `access_type`, `ip_address`, `accessed_at`) VALUES
(1, 1, 2, 'download', '::1', '2026-09-25 00:39:25'),
(2, 1, 2, 'download', '::1', '2026-09-25 06:11:33'),
(3, 1, 2, 'download', '::1', '2026-09-29 06:51:09');

-- --------------------------------------------------------

--
-- Table structure for table `case_hearings`
--

CREATE TABLE `case_hearings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `hearing_number` varchar(50) NOT NULL,
  `complaint_id` bigint(20) UNSIGNED DEFAULT NULL,
  `blotter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `hearing_type` varchar(80) NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `venue_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('scheduled','rescheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `cancelled_by` bigint(20) UNSIGNED DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `outcome_summary` text DEFAULT NULL,
  `outcome_recorded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `outcome_recorded_at` datetime DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `case_hearing_participants`
--

CREATE TABLE `case_hearing_participants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `hearing_id` bigint(20) UNSIGNED NOT NULL,
  `case_person_id` bigint(20) UNSIGNED DEFAULT NULL,
  `participant_name` varchar(180) DEFAULT NULL,
  `participant_role` enum('complainant','respondent','witness','representative','other') NOT NULL,
  `resident_id` bigint(20) UNSIGNED DEFAULT NULL,
  `attendance_status` enum('not_recorded','present','absent','excused') NOT NULL DEFAULT 'not_recorded',
  `attendance_recorded_at` datetime DEFAULT NULL,
  `attendance_recorded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `attendance_notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `case_hearing_personnel`
--

CREATE TABLE `case_hearing_personnel` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `hearing_id` bigint(20) UNSIGNED NOT NULL,
  `personnel_id` bigint(20) UNSIGNED NOT NULL,
  `assignment_role` varchar(80) NOT NULL,
  `assigned_by` bigint(20) UNSIGNED DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `removed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `removed_at` datetime DEFAULT NULL,
  `removal_reason` varchar(255) DEFAULT NULL,
  `replaced_by_assignment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `active_key` tinyint(4) GENERATED ALWAYS AS (if(`removed_at` is null,1,NULL)) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `case_hearing_schedule_history`
--

CREATE TABLE `case_hearing_schedule_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `hearing_id` bigint(20) UNSIGNED NOT NULL,
  `change_type` enum('initial','rescheduled') NOT NULL,
  `previous_starts_at` datetime DEFAULT NULL,
  `previous_ends_at` datetime DEFAULT NULL,
  `previous_venue_id` bigint(20) UNSIGNED DEFAULT NULL,
  `new_starts_at` datetime NOT NULL,
  `new_ends_at` datetime NOT NULL,
  `new_venue_id` bigint(20) UNSIGNED NOT NULL,
  `reason` text DEFAULT NULL,
  `changed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `case_persons`
--

CREATE TABLE `case_persons` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `complaint_id` bigint(20) UNSIGNED DEFAULT NULL,
  `blotter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `person_role` enum('complainant','respondent','witness','other') NOT NULL,
  `resident_id` bigint(20) UNSIGNED DEFAULT NULL,
  `full_name` varchar(180) NOT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `identifying_details` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `case_persons`
--

INSERT INTO `case_persons` (`id`, `complaint_id`, `blotter_id`, `person_role`, `resident_id`, `full_name`, `contact_number`, `address`, `identifying_details`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'complainant', NULL, 'Glyza Martinez Balimbin', '09243546899', '#147 Purok 3 San Jose Quirino Isabela', NULL, NULL, NULL, '2026-09-25 00:22:29', '2026-09-25 00:22:29'),
(2, 1, NULL, 'respondent', NULL, 'Elle', '09243546899', '#147 Purok 3 San Jose Quirino Isabela', NULL, NULL, NULL, '2026-09-25 00:22:29', '2026-09-25 00:22:29');

-- --------------------------------------------------------

--
-- Table structure for table `case_status_history`
--

CREATE TABLE `case_status_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `complaint_id` bigint(20) UNSIGNED DEFAULT NULL,
  `blotter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `from_status` varchar(30) DEFAULT NULL,
  `to_status` varchar(30) NOT NULL,
  `notes` text DEFAULT NULL,
  `actor_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `acted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `case_status_history`
--

INSERT INTO `case_status_history` (`id`, `complaint_id`, `blotter_id`, `action`, `from_status`, `to_status`, `notes`, `actor_user_id`, `acted_at`) VALUES
(1, 1, NULL, 'submitted', NULL, 'pending_review', NULL, NULL, '2026-09-25 00:22:29'),
(2, 1, NULL, 'review_started', 'pending_review', 'under_review', NULL, 2, '2026-09-25 00:39:34');

-- --------------------------------------------------------

--
-- Table structure for table `complaint_cases`
--

CREATE TABLE `complaint_cases` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `case_number` varchar(50) NOT NULL,
  `complainant_resident_id` bigint(20) UNSIGNED DEFAULT NULL,
  `respondent_resident_id` bigint(20) UNSIGNED DEFAULT NULL,
  `complainant_name` varchar(180) DEFAULT NULL,
  `respondent_name` varchar(180) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `confidential_details` text DEFAULT NULL,
  `status` enum('open','under_review','for_hearing','settled','dismissed','closed','pending_review','resolved') NOT NULL DEFAULT 'pending_review',
  `filed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `hearing_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `category` varchar(80) DEFAULT NULL,
  `incident_at` datetime DEFAULT NULL,
  `incident_location` varchar(255) DEFAULT NULL,
  `submission_source` enum('staff','online') DEFAULT NULL,
  `submitted_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_confidential` tinyint(1) NOT NULL DEFAULT 1,
  `reviewed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `resolved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `closed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `closing_notes` text DEFAULT NULL
) ;

--
-- Dumping data for table `complaint_cases`
--

INSERT INTO `complaint_cases` (`id`, `case_number`, `complainant_resident_id`, `respondent_resident_id`, `complainant_name`, `respondent_name`, `subject`, `confidential_details`, `status`, `filed_at`, `hearing_at`, `resolved_at`, `created_by`, `updated_by`, `created_at`, `updated_at`, `category`, `incident_at`, `incident_location`, `submission_source`, `submitted_by_user_id`, `is_confidential`, `reviewed_by`, `reviewed_at`, `resolved_by`, `resolution_notes`, `closed_by`, `closed_at`, `closing_notes`) VALUES
(1, 'CMP-2026-436C1A', NULL, NULL, 'Glyza Martinez Balimbin', 'Elle', 'Loud noise at night', 'loud music/videoke was being played late at night, causing excessive noise and disturbance to nearby resident', 'under_review', '2026-09-25 00:22:29', NULL, NULL, NULL, 2, '2026-09-25 00:22:29', '2026-09-25 00:39:34', 'noise', '2026-09-23 22:36:00', 'Purok 3', 'online', NULL, 1, 2, '2026-09-25 08:39:34', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `document_requests`
--

CREATE TABLE `document_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `resident_id` bigint(20) UNSIGNED NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `purpose` text NOT NULL,
  `status` enum('pending','approved','released','rejected') NOT NULL DEFAULT 'pending',
  `reference_code` varchar(40) NOT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `drr_alerts`
--

CREATE TABLE `drr_alerts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `alert_level` enum('advisory','yellow','orange','red') NOT NULL,
  `incident_id` bigint(20) UNSIGNED DEFAULT NULL,
  `recipients` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `lifted_at` datetime DEFAULT NULL,
  `lifted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `archived_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `drr_alerts`
--

INSERT INTO `drr_alerts` (`id`, `title`, `message`, `alert_level`, `incident_id`, `recipients`, `lifted_at`, `lifted_by`, `created_by`, `created_at`, `updated_at`, `archived_at`, `archived_by`) VALUES
(1, 'pardas', 'jkg6yjikolp;[poiytrfwssdrftgyjklop;lokijygtfrdswrtjkiolp;', 'red', 1, 10, NULL, NULL, 1, '2026-09-30 06:06:26', '2026-09-30 06:06:26', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `drr_alert_areas`
--

CREATE TABLE `drr_alert_areas` (
  `alert_id` bigint(20) UNSIGNED NOT NULL,
  `area_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `drr_alert_areas`
--

INSERT INTO `drr_alert_areas` (`alert_id`, `area_id`) VALUES
(1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `drr_areas`
--

CREATE TABLE `drr_areas` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `area_type` enum('all_puroks','purok','place') NOT NULL DEFAULT 'place',
  `purok` varchar(10) DEFAULT NULL,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 100,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `drr_areas`
--

INSERT INTO `drr_areas` (`id`, `name`, `area_type`, `purok`, `sort_order`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'All Puroks', 'all_puroks', NULL, 1, 1, NULL, '2026-09-25 16:38:12', '2026-09-25 16:38:12'),
(2, 'Purok 1', 'purok', '1', 11, 1, NULL, '2026-09-25 16:38:12', '2026-09-25 16:38:12'),
(3, 'Purok 2', 'purok', '2', 12, 1, NULL, '2026-09-25 16:38:12', '2026-09-25 16:38:12'),
(4, 'Purok 3', 'purok', '3', 13, 1, NULL, '2026-09-25 16:38:12', '2026-09-25 16:38:12'),
(5, 'Purok 4', 'purok', '4', 14, 1, NULL, '2026-09-25 16:38:12', '2026-09-25 16:38:12'),
(6, 'Barangay Hall', 'place', NULL, 100, 1, NULL, '2026-09-25 16:38:12', '2026-09-25 16:38:12'),
(7, 'Community Center', 'place', NULL, 100, 1, NULL, '2026-09-25 16:38:12', '2026-09-25 16:38:12'),
(8, 'San Jose Elementary School', 'place', NULL, 100, 1, NULL, '2026-09-25 16:38:12', '2026-09-25 16:38:12');

-- --------------------------------------------------------

--
-- Table structure for table `drr_contacts`
--

CREATE TABLE `drr_contacts` (
  `id` int(10) UNSIGNED NOT NULL,
  `contact_type` enum('member','hotline') NOT NULL,
  `name` varchar(150) NOT NULL,
  `member_role` enum('rescue','relief','medical','communication','security','other') DEFAULT NULL,
  `hotline_category` enum('mdrrmo','bfp','pnp','hospital','other') DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `contact_number` varchar(30) NOT NULL,
  `alternate_number` varchar(30) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `archived_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `drr_contacts`
--

INSERT INTO `drr_contacts` (`id`, `contact_type`, `name`, `member_role`, `hotline_category`, `position`, `contact_number`, `alternate_number`, `notes`, `created_by`, `updated_by`, `created_at`, `updated_at`, `archived_at`, `archived_by`) VALUES
(1, 'hotline', 'MDRRMO', NULL, 'mdrrmo', NULL, '09171124429', '911', NULL, 1, 1, '2026-10-02 16:57:08', '2026-10-02 16:57:08', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `drr_damage_assessments`
--

CREATE TABLE `drr_damage_assessments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `incident_id` bigint(20) UNSIGNED NOT NULL,
  `area_id` int(10) UNSIGNED NOT NULL,
  `assessed_on` date NOT NULL,
  `houses_partial` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `houses_total` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `roads` varchar(255) DEFAULT NULL,
  `bridges` varchar(255) DEFAULT NULL,
  `crops` varchar(255) DEFAULT NULL,
  `public_facilities` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `photo_path` varchar(100) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `archived_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `drr_evacuations`
--

CREATE TABLE `drr_evacuations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `incident_id` bigint(20) UNSIGNED NOT NULL,
  `center_id` int(10) UNSIGNED NOT NULL,
  `household_id` bigint(20) UNSIGNED DEFAULT NULL,
  `resident_id` bigint(20) UNSIGNED DEFAULT NULL,
  `family_members` smallint(5) UNSIGNED NOT NULL,
  `arrived_at` datetime NOT NULL,
  `departed_at` datetime DEFAULT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `archived_by` bigint(20) UNSIGNED DEFAULT NULL,
  `open_family_key` varchar(30) GENERATED ALWAYS AS (case when `departed_at` is null and `archived_at` is null then concat(if(`household_id` is null,'r','h'),coalesce(`household_id`,`resident_id`)) end) STORED
) ;

-- --------------------------------------------------------

--
-- Table structure for table `drr_evacuation_centers`
--

CREATE TABLE `drr_evacuation_centers` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `address` varchar(255) NOT NULL,
  `area_id` int(10) UNSIGNED NOT NULL,
  `capacity` int(10) UNSIGNED NOT NULL,
  `has_toilets` tinyint(1) NOT NULL DEFAULT 0,
  `has_water` tinyint(1) NOT NULL DEFAULT 0,
  `has_electricity` tinyint(1) NOT NULL DEFAULT 0,
  `has_kitchen` tinyint(1) NOT NULL DEFAULT 0,
  `contact_person` varchar(150) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `status` enum('open','closed','full') NOT NULL DEFAULT 'closed',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `archived_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `drr_evacuation_centers`
--

INSERT INTO `drr_evacuation_centers` (`id`, `name`, `address`, `area_id`, `capacity`, `has_toilets`, `has_water`, `has_electricity`, `has_kitchen`, `contact_person`, `contact_number`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`, `archived_at`, `archived_by`) VALUES
(1, 'court', 'San Jose', 1, 100, 1, 1, 1, 0, 'jamil tabuyo', '09707112132', 'open', 1, 1, '2026-09-30 06:09:32', '2026-09-30 06:09:32', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `drr_hazard_areas`
--

CREATE TABLE `drr_hazard_areas` (
  `id` int(10) UNSIGNED NOT NULL,
  `area_id` int(10) UNSIGNED NOT NULL,
  `hazard_type` enum('flood','landslide','storm_surge','fire','other') NOT NULL,
  `hazard_other` varchar(100) DEFAULT NULL,
  `risk_level` enum('low','medium','high') NOT NULL,
  `families_at_risk` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `archived_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `drr_hazard_areas`
--

INSERT INTO `drr_hazard_areas` (`id`, `area_id`, `hazard_type`, `hazard_other`, `risk_level`, `families_at_risk`, `notes`, `created_by`, `updated_by`, `created_at`, `updated_at`, `archived_at`, `archived_by`) VALUES
(1, 2, 'flood', NULL, 'medium', 3, NULL, 1, 1, '2026-10-02 16:53:46', '2026-10-02 16:53:46', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `drr_records`
--

CREATE TABLE `drr_records` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference_no` varchar(20) NOT NULL,
  `ref_year` smallint(5) UNSIGNED NOT NULL,
  `ref_seq` int(10) UNSIGNED NOT NULL,
  `title` varchar(150) NOT NULL,
  `record_type` enum('preparedness','incident','response','drill','mitigation') NOT NULL,
  `area_id` int(10) UNSIGNED NOT NULL,
  `record_date` date NOT NULL,
  `status` enum('planned','ongoing','monitoring','completed','cancelled') NOT NULL DEFAULT 'planned',
  `description` text DEFAULT NULL,
  `affected_families` int(10) UNSIGNED DEFAULT NULL,
  `affected_persons` int(10) UNSIGNED DEFAULT NULL,
  `alert_level` enum('advisory','yellow','orange','red') DEFAULT NULL,
  `incident_details` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `archived_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `drr_records`
--

INSERT INTO `drr_records` (`id`, `reference_no`, `ref_year`, `ref_seq`, `title`, `record_type`, `area_id`, `record_date`, `status`, `description`, `affected_families`, `affected_persons`, `alert_level`, `incident_details`, `created_by`, `updated_by`, `created_at`, `updated_at`, `archived_at`, `archived_by`) VALUES
(1, 'DRR-2026-01', 2026, 1, 'typhoon', 'incident', 4, '2026-09-23', 'monitoring', 'puting some sacks of sand', 3, 9, 'yellow', 'break or crack wall of flood control', 1, 1, '2026-09-25 17:22:56', '2026-09-25 17:22:56', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `drr_relief_distributions`
--

CREATE TABLE `drr_relief_distributions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference_no` varchar(20) NOT NULL,
  `incident_id` bigint(20) UNSIGNED NOT NULL,
  `household_id` bigint(20) UNSIGNED DEFAULT NULL,
  `resident_id` bigint(20) UNSIGNED DEFAULT NULL,
  `received_by` varchar(150) NOT NULL,
  `distributed_on` date NOT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `archived_by` bigint(20) UNSIGNED DEFAULT NULL,
  `archive_reason` varchar(255) DEFAULT NULL
) ;

-- --------------------------------------------------------

--
-- Table structure for table `drr_relief_items`
--

CREATE TABLE `drr_relief_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `distribution_id` bigint(20) UNSIGNED NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `unit` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `finance_attachments`
--

CREATE TABLE `finance_attachments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `transaction_id` bigint(20) UNSIGNED NOT NULL,
  `original_name` varchar(150) NOT NULL,
  `stored_name` varchar(60) NOT NULL,
  `mime_type` varchar(50) NOT NULL,
  `file_size` int(10) UNSIGNED NOT NULL,
  `uploaded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `archived_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `finance_budgets`
--

CREATE TABLE `finance_budgets` (
  `id` int(10) UNSIGNED NOT NULL,
  `budget_year` smallint(5) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `appropriated` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `finance_categories`
--

CREATE TABLE `finance_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `is_allotment` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 100,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `finance_categories`
--

INSERT INTO `finance_categories` (`id`, `name`, `type`, `is_allotment`, `is_active`, `sort_order`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Service Fees', 'income', 0, 1, 10, NULL, '2026-09-25 17:58:27', '2026-09-25 17:58:27'),
(2, 'Allotment', 'income', 1, 1, 20, NULL, '2026-09-25 17:58:27', '2026-09-25 17:58:27'),
(3, 'Donations', 'income', 0, 1, 30, NULL, '2026-09-25 17:58:27', '2026-09-25 17:58:27'),
(4, 'Other Income', 'income', 0, 1, 90, NULL, '2026-09-25 17:58:27', '2026-09-25 17:58:27'),
(5, 'Personnel', 'expense', 0, 1, 10, NULL, '2026-09-25 17:58:27', '2026-09-25 17:58:27'),
(6, 'Supplies', 'expense', 0, 1, 20, NULL, '2026-09-25 17:58:27', '2026-09-25 17:58:27'),
(7, 'Infrastructure', 'expense', 0, 1, 30, NULL, '2026-09-25 17:58:27', '2026-09-25 17:58:27'),
(8, 'Utilities', 'expense', 0, 1, 40, NULL, '2026-09-25 17:58:27', '2026-09-25 17:58:27'),
(9, 'Programs/Activities', 'expense', 0, 1, 50, NULL, '2026-09-25 17:58:27', '2026-09-25 17:58:27'),
(10, 'Other Expense', 'expense', 0, 1, 90, NULL, '2026-09-25 17:58:27', '2026-09-25 17:58:27');

-- --------------------------------------------------------

--
-- Table structure for table `finance_opening_balances`
--

CREATE TABLE `finance_opening_balances` (
  `id` int(10) UNSIGNED NOT NULL,
  `as_of_date` date NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` bigint(20) UNSIGNED DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `current_flag` tinyint(1) GENERATED ALWAYS AS (if(`cancelled_at` is null,1,NULL)) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `finance_transactions`
--

CREATE TABLE `finance_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference_no` varchar(30) NOT NULL,
  `ref_kind` enum('or','nta','dv') NOT NULL,
  `ref_year` smallint(5) UNSIGNED DEFAULT NULL,
  `ref_seq` int(10) UNSIGNED DEFAULT NULL,
  `type` enum('income','expense') NOT NULL,
  `transaction_date` date NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `payor_or_payee` varchar(150) NOT NULL,
  `resident_id` bigint(20) UNSIGNED DEFAULT NULL,
  `payment_mode` enum('cash','check','bank_transfer','other') DEFAULT NULL,
  `check_no` varchar(40) DEFAULT NULL,
  `status` enum('posted','pending_approval','approved','rejected','released','cancelled') NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected_by` bigint(20) UNSIGNED DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `reject_reason` varchar(255) DEFAULT NULL,
  `released_by` bigint(20) UNSIGNED DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `cancelled_by` bigint(20) UNSIGNED DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `status_before_cancel` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `health_chronic_cases`
--

CREATE TABLE `health_chronic_cases` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `resident_id` bigint(20) UNSIGNED NOT NULL,
  `condition_type` enum('hypertension','diabetes','tb') NOT NULL,
  `diagnosed_on` date DEFAULT NULL,
  `status` enum('active','completed','inactive') NOT NULL DEFAULT 'active',
  `maintenance_medicines` varchar(500) DEFAULT NULL,
  `is_serious` tinyint(1) NOT NULL DEFAULT 0,
  `remarks` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `archived_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `health_conditions`
--

CREATE TABLE `health_conditions` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `health_conditions`
--

INSERT INTO `health_conditions` (`id`, `name`, `is_active`, `created_by`, `created_at`) VALUES
(1, 'Fever', 1, NULL, '2026-10-03 13:31:55'),
(2, 'Cough and Colds (URTI)', 1, NULL, '2026-10-03 13:31:55'),
(3, 'Hypertension', 1, NULL, '2026-10-03 13:31:55'),
(4, 'Diabetes Mellitus', 1, NULL, '2026-10-03 13:31:55'),
(5, 'Diarrhea / Acute Gastroenteritis', 1, NULL, '2026-10-03 13:31:55'),
(6, 'Pneumonia', 1, NULL, '2026-10-03 13:31:55'),
(7, 'Urinary Tract Infection', 1, NULL, '2026-10-03 13:31:55'),
(8, 'Skin Infection / Wound', 1, NULL, '2026-10-03 13:31:55'),
(9, 'Asthma', 1, NULL, '2026-10-03 13:31:55'),
(10, 'Dengue (suspected)', 1, NULL, '2026-10-03 13:31:55'),
(11, 'Animal Bite', 1, NULL, '2026-10-03 13:31:55'),
(12, 'Headache', 1, NULL, '2026-10-03 13:31:55'),
(13, 'Muscle / Joint Pain', 1, NULL, '2026-10-03 13:31:55'),
(14, 'Toothache', 1, NULL, '2026-10-03 13:31:55'),
(15, 'Influenza-like Illness', 1, NULL, '2026-10-03 13:31:55');

-- --------------------------------------------------------

--
-- Table structure for table `health_growth_reference`
--

CREATE TABLE `health_growth_reference` (
  `id` int(10) UNSIGNED NOT NULL,
  `indicator` enum('wfa','lhfa','wfl','wfh') NOT NULL,
  `sex` enum('male','female') NOT NULL,
  `x_value` decimal(6,1) NOT NULL,
  `l_value` decimal(10,6) NOT NULL,
  `m_value` decimal(10,6) NOT NULL,
  `s_value` decimal(10,6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `health_growth_reference`
--

INSERT INTO `health_growth_reference` (`id`, `indicator`, `sex`, `x_value`, `l_value`, `m_value`, `s_value`) VALUES
(1, 'wfa', 'male', 0.0, 0.348700, 3.346400, 0.146020),
(2, 'wfa', 'male', 1.0, 0.229700, 4.470900, 0.133950),
(3, 'wfa', 'male', 2.0, 0.197000, 5.567500, 0.123850),
(4, 'wfa', 'male', 3.0, 0.173800, 6.376200, 0.117270),
(5, 'wfa', 'male', 4.0, 0.155300, 7.002300, 0.113160),
(6, 'wfa', 'male', 5.0, 0.139500, 7.510500, 0.110800),
(7, 'wfa', 'male', 6.0, 0.125700, 7.934000, 0.109580),
(8, 'wfa', 'male', 7.0, 0.113400, 8.297000, 0.109020),
(9, 'wfa', 'male', 8.0, 0.102100, 8.615100, 0.108820),
(10, 'wfa', 'male', 9.0, 0.091700, 8.901400, 0.108810),
(11, 'wfa', 'male', 10.0, 0.082000, 9.164900, 0.108910),
(12, 'wfa', 'male', 11.0, 0.073000, 9.412200, 0.109060),
(13, 'wfa', 'male', 12.0, 0.064400, 9.647900, 0.109250),
(14, 'wfa', 'male', 13.0, 0.056300, 9.874900, 0.109490),
(15, 'wfa', 'male', 14.0, 0.048700, 10.095300, 0.109760),
(16, 'wfa', 'male', 15.0, 0.041300, 10.310800, 0.110070),
(17, 'wfa', 'male', 16.0, 0.034300, 10.522800, 0.110410),
(18, 'wfa', 'male', 17.0, 0.027500, 10.731900, 0.110790),
(19, 'wfa', 'male', 18.0, 0.021100, 10.938500, 0.111190),
(20, 'wfa', 'male', 19.0, 0.014800, 11.143000, 0.111640),
(21, 'wfa', 'male', 20.0, 0.008700, 11.346200, 0.112110),
(22, 'wfa', 'male', 21.0, 0.002900, 11.548600, 0.112610),
(23, 'wfa', 'male', 22.0, -0.002800, 11.750400, 0.113140),
(24, 'wfa', 'male', 23.0, -0.008300, 11.951400, 0.113690),
(25, 'wfa', 'male', 24.0, -0.013700, 12.151500, 0.114260),
(26, 'wfa', 'male', 25.0, -0.018900, 12.350200, 0.114850),
(27, 'wfa', 'male', 26.0, -0.024000, 12.546600, 0.115440),
(28, 'wfa', 'male', 27.0, -0.028900, 12.740100, 0.116040),
(29, 'wfa', 'male', 28.0, -0.033700, 12.930300, 0.116640),
(30, 'wfa', 'male', 29.0, -0.038500, 13.116900, 0.117230),
(31, 'wfa', 'male', 30.0, -0.043100, 13.300000, 0.117810),
(32, 'wfa', 'male', 31.0, -0.047600, 13.479800, 0.118390),
(33, 'wfa', 'male', 32.0, -0.052000, 13.656700, 0.118960),
(34, 'wfa', 'male', 33.0, -0.056400, 13.830900, 0.119530),
(35, 'wfa', 'male', 34.0, -0.060600, 14.003100, 0.120080),
(36, 'wfa', 'male', 35.0, -0.064800, 14.173600, 0.120620),
(37, 'wfa', 'male', 36.0, -0.068900, 14.342900, 0.121160),
(38, 'wfa', 'male', 37.0, -0.072900, 14.511300, 0.121680),
(39, 'wfa', 'male', 38.0, -0.076900, 14.679100, 0.122200),
(40, 'wfa', 'male', 39.0, -0.080800, 14.846600, 0.122710),
(41, 'wfa', 'male', 40.0, -0.084600, 15.014000, 0.123220),
(42, 'wfa', 'male', 41.0, -0.088300, 15.181300, 0.123730),
(43, 'wfa', 'male', 42.0, -0.092000, 15.348600, 0.124250),
(44, 'wfa', 'male', 43.0, -0.095700, 15.515800, 0.124780),
(45, 'wfa', 'male', 44.0, -0.099300, 15.682800, 0.125310),
(46, 'wfa', 'male', 45.0, -0.102800, 15.849700, 0.125860),
(47, 'wfa', 'male', 46.0, -0.106300, 16.016300, 0.126430),
(48, 'wfa', 'male', 47.0, -0.109700, 16.182700, 0.127000),
(49, 'wfa', 'male', 48.0, -0.113100, 16.348900, 0.127590),
(50, 'wfa', 'male', 49.0, -0.116500, 16.515000, 0.128190),
(51, 'wfa', 'male', 50.0, -0.119800, 16.681100, 0.128800),
(52, 'wfa', 'male', 51.0, -0.123000, 16.847100, 0.129430),
(53, 'wfa', 'male', 52.0, -0.126200, 17.013200, 0.130050),
(54, 'wfa', 'male', 53.0, -0.129400, 17.179200, 0.130690),
(55, 'wfa', 'male', 54.0, -0.132500, 17.345200, 0.131330),
(56, 'wfa', 'male', 55.0, -0.135600, 17.511100, 0.131970),
(57, 'wfa', 'male', 56.0, -0.138700, 17.676800, 0.132610),
(58, 'wfa', 'male', 57.0, -0.141700, 17.842200, 0.133250),
(59, 'wfa', 'male', 58.0, -0.144700, 18.007300, 0.133890),
(60, 'wfa', 'male', 59.0, -0.147700, 18.172200, 0.134530),
(61, 'wfa', 'male', 60.0, -0.150600, 18.336600, 0.135170),
(62, 'wfa', 'female', 0.0, 0.380900, 3.232200, 0.141710),
(63, 'wfa', 'female', 1.0, 0.171400, 4.187300, 0.137240),
(64, 'wfa', 'female', 2.0, 0.096200, 5.128200, 0.130000),
(65, 'wfa', 'female', 3.0, 0.040200, 5.845800, 0.126190),
(66, 'wfa', 'female', 4.0, -0.005000, 6.423700, 0.124020),
(67, 'wfa', 'female', 5.0, -0.043000, 6.898500, 0.122740),
(68, 'wfa', 'female', 6.0, -0.075600, 7.297000, 0.122040),
(69, 'wfa', 'female', 7.0, -0.103900, 7.642200, 0.121780),
(70, 'wfa', 'female', 8.0, -0.128800, 7.948700, 0.121810),
(71, 'wfa', 'female', 9.0, -0.150700, 8.225400, 0.121990),
(72, 'wfa', 'female', 10.0, -0.170000, 8.480000, 0.122230),
(73, 'wfa', 'female', 11.0, -0.187200, 8.719200, 0.122470),
(74, 'wfa', 'female', 12.0, -0.202400, 8.948100, 0.122680),
(75, 'wfa', 'female', 13.0, -0.215800, 9.169900, 0.122830),
(76, 'wfa', 'female', 14.0, -0.227800, 9.387000, 0.122940),
(77, 'wfa', 'female', 15.0, -0.238400, 9.600800, 0.122990),
(78, 'wfa', 'female', 16.0, -0.247800, 9.812400, 0.123030),
(79, 'wfa', 'female', 17.0, -0.256200, 10.022600, 0.123060),
(80, 'wfa', 'female', 18.0, -0.263700, 10.231500, 0.123090),
(81, 'wfa', 'female', 19.0, -0.270300, 10.439300, 0.123150),
(82, 'wfa', 'female', 20.0, -0.276200, 10.646400, 0.123230),
(83, 'wfa', 'female', 21.0, -0.281500, 10.853400, 0.123350),
(84, 'wfa', 'female', 22.0, -0.286200, 11.060800, 0.123500),
(85, 'wfa', 'female', 23.0, -0.290300, 11.268800, 0.123690),
(86, 'wfa', 'female', 24.0, -0.294100, 11.477500, 0.123900),
(87, 'wfa', 'female', 25.0, -0.297500, 11.686400, 0.124140),
(88, 'wfa', 'female', 26.0, -0.300500, 11.894700, 0.124410),
(89, 'wfa', 'female', 27.0, -0.303200, 12.101500, 0.124720),
(90, 'wfa', 'female', 28.0, -0.305700, 12.305900, 0.125060),
(91, 'wfa', 'female', 29.0, -0.308000, 12.507300, 0.125450),
(92, 'wfa', 'female', 30.0, -0.310100, 12.705500, 0.125870),
(93, 'wfa', 'female', 31.0, -0.312000, 12.900600, 0.126330),
(94, 'wfa', 'female', 32.0, -0.313800, 13.093000, 0.126830),
(95, 'wfa', 'female', 33.0, -0.315500, 13.283700, 0.127370),
(96, 'wfa', 'female', 34.0, -0.317100, 13.473100, 0.127940),
(97, 'wfa', 'female', 35.0, -0.318600, 13.661800, 0.128550),
(98, 'wfa', 'female', 36.0, -0.320100, 13.850300, 0.129190),
(99, 'wfa', 'female', 37.0, -0.321600, 14.038500, 0.129880),
(100, 'wfa', 'female', 38.0, -0.323000, 14.226500, 0.130590),
(101, 'wfa', 'female', 39.0, -0.324300, 14.414000, 0.131350),
(102, 'wfa', 'female', 40.0, -0.325700, 14.601000, 0.132130),
(103, 'wfa', 'female', 41.0, -0.327000, 14.787300, 0.132930),
(104, 'wfa', 'female', 42.0, -0.328300, 14.972700, 0.133760),
(105, 'wfa', 'female', 43.0, -0.329600, 15.157300, 0.134600),
(106, 'wfa', 'female', 44.0, -0.330900, 15.341000, 0.135450),
(107, 'wfa', 'female', 45.0, -0.332200, 15.524000, 0.136300),
(108, 'wfa', 'female', 46.0, -0.333500, 15.706400, 0.137160),
(109, 'wfa', 'female', 47.0, -0.334800, 15.888200, 0.138000),
(110, 'wfa', 'female', 48.0, -0.336100, 16.069700, 0.138840),
(111, 'wfa', 'female', 49.0, -0.337400, 16.251100, 0.139680),
(112, 'wfa', 'female', 50.0, -0.338700, 16.432200, 0.140510),
(113, 'wfa', 'female', 51.0, -0.340000, 16.613300, 0.141320),
(114, 'wfa', 'female', 52.0, -0.341400, 16.794200, 0.142130),
(115, 'wfa', 'female', 53.0, -0.342700, 16.974800, 0.142930),
(116, 'wfa', 'female', 54.0, -0.344000, 17.155100, 0.143710),
(117, 'wfa', 'female', 55.0, -0.345300, 17.334700, 0.144480),
(118, 'wfa', 'female', 56.0, -0.346600, 17.513600, 0.145250),
(119, 'wfa', 'female', 57.0, -0.347900, 17.691600, 0.146000),
(120, 'wfa', 'female', 58.0, -0.349200, 17.868600, 0.146750),
(121, 'wfa', 'female', 59.0, -0.350500, 18.044500, 0.147480),
(122, 'wfa', 'female', 60.0, -0.351800, 18.219300, 0.148210),
(123, 'lhfa', 'male', 0.0, 1.000000, 49.884200, 0.037950),
(124, 'lhfa', 'male', 1.0, 1.000000, 54.724400, 0.035570),
(125, 'lhfa', 'male', 2.0, 1.000000, 58.424900, 0.034240),
(126, 'lhfa', 'male', 3.0, 1.000000, 61.429200, 0.033280),
(127, 'lhfa', 'male', 4.0, 1.000000, 63.886000, 0.032570),
(128, 'lhfa', 'male', 5.0, 1.000000, 65.902600, 0.032040),
(129, 'lhfa', 'male', 6.0, 1.000000, 67.623600, 0.031650),
(130, 'lhfa', 'male', 7.0, 1.000000, 69.164500, 0.031390),
(131, 'lhfa', 'male', 8.0, 1.000000, 70.599400, 0.031240),
(132, 'lhfa', 'male', 9.0, 1.000000, 71.968700, 0.031170),
(133, 'lhfa', 'male', 10.0, 1.000000, 73.281200, 0.031180),
(134, 'lhfa', 'male', 11.0, 1.000000, 74.538800, 0.031250),
(135, 'lhfa', 'male', 12.0, 1.000000, 75.748800, 0.031370),
(136, 'lhfa', 'male', 13.0, 1.000000, 76.918600, 0.031540),
(137, 'lhfa', 'male', 14.0, 1.000000, 78.049700, 0.031740),
(138, 'lhfa', 'male', 15.0, 1.000000, 79.145800, 0.031970),
(139, 'lhfa', 'male', 16.0, 1.000000, 80.211300, 0.032220),
(140, 'lhfa', 'male', 17.0, 1.000000, 81.248700, 0.032500),
(141, 'lhfa', 'male', 18.0, 1.000000, 82.258700, 0.032790),
(142, 'lhfa', 'male', 19.0, 1.000000, 83.241800, 0.033100),
(143, 'lhfa', 'male', 20.0, 1.000000, 84.199600, 0.033420),
(144, 'lhfa', 'male', 21.0, 1.000000, 85.134800, 0.033760),
(145, 'lhfa', 'male', 22.0, 1.000000, 86.047700, 0.034100),
(146, 'lhfa', 'male', 23.0, 1.000000, 86.941000, 0.034450),
(147, 'lhfa', 'male', 24.0, 1.000000, 87.116100, 0.035070),
(148, 'lhfa', 'male', 25.0, 1.000000, 87.972000, 0.035420),
(149, 'lhfa', 'male', 26.0, 1.000000, 88.806500, 0.035760),
(150, 'lhfa', 'male', 27.0, 1.000000, 89.619700, 0.036100),
(151, 'lhfa', 'male', 28.0, 1.000000, 90.412000, 0.036420),
(152, 'lhfa', 'male', 29.0, 1.000000, 91.182800, 0.036740),
(153, 'lhfa', 'male', 30.0, 1.000000, 91.932700, 0.037040),
(154, 'lhfa', 'male', 31.0, 1.000000, 92.663100, 0.037330),
(155, 'lhfa', 'male', 32.0, 1.000000, 93.375300, 0.037610),
(156, 'lhfa', 'male', 33.0, 1.000000, 94.071100, 0.037870),
(157, 'lhfa', 'male', 34.0, 1.000000, 94.753200, 0.038120),
(158, 'lhfa', 'male', 35.0, 1.000000, 95.423600, 0.038360),
(159, 'lhfa', 'male', 36.0, 1.000000, 96.083500, 0.038580),
(160, 'lhfa', 'male', 37.0, 1.000000, 96.733700, 0.038790),
(161, 'lhfa', 'male', 38.0, 1.000000, 97.374900, 0.039000),
(162, 'lhfa', 'male', 39.0, 1.000000, 98.007300, 0.039190),
(163, 'lhfa', 'male', 40.0, 1.000000, 98.631000, 0.039370),
(164, 'lhfa', 'male', 41.0, 1.000000, 99.245900, 0.039540),
(165, 'lhfa', 'male', 42.0, 1.000000, 99.851500, 0.039710),
(166, 'lhfa', 'male', 43.0, 1.000000, 100.448500, 0.039860),
(167, 'lhfa', 'male', 44.0, 1.000000, 101.037400, 0.040020),
(168, 'lhfa', 'male', 45.0, 1.000000, 101.618600, 0.040160),
(169, 'lhfa', 'male', 46.0, 1.000000, 102.193300, 0.040310),
(170, 'lhfa', 'male', 47.0, 1.000000, 102.762500, 0.040450),
(171, 'lhfa', 'male', 48.0, 1.000000, 103.327300, 0.040590),
(172, 'lhfa', 'male', 49.0, 1.000000, 103.888600, 0.040730),
(173, 'lhfa', 'male', 50.0, 1.000000, 104.447300, 0.040860),
(174, 'lhfa', 'male', 51.0, 1.000000, 105.004100, 0.041000),
(175, 'lhfa', 'male', 52.0, 1.000000, 105.559600, 0.041130),
(176, 'lhfa', 'male', 53.0, 1.000000, 106.113800, 0.041260),
(177, 'lhfa', 'male', 54.0, 1.000000, 106.666800, 0.041390),
(178, 'lhfa', 'male', 55.0, 1.000000, 107.218800, 0.041520),
(179, 'lhfa', 'male', 56.0, 1.000000, 107.769700, 0.041650),
(180, 'lhfa', 'male', 57.0, 1.000000, 108.319800, 0.041770),
(181, 'lhfa', 'male', 58.0, 1.000000, 108.868900, 0.041900),
(182, 'lhfa', 'male', 59.0, 1.000000, 109.417000, 0.042020),
(183, 'lhfa', 'male', 60.0, 1.000000, 109.963800, 0.042140),
(184, 'lhfa', 'female', 0.0, 1.000000, 49.147700, 0.037900),
(185, 'lhfa', 'female', 1.0, 1.000000, 53.687200, 0.036400),
(186, 'lhfa', 'female', 2.0, 1.000000, 57.067300, 0.035680),
(187, 'lhfa', 'female', 3.0, 1.000000, 59.802900, 0.035200),
(188, 'lhfa', 'female', 4.0, 1.000000, 62.089900, 0.034860),
(189, 'lhfa', 'female', 5.0, 1.000000, 64.030100, 0.034630),
(190, 'lhfa', 'female', 6.0, 1.000000, 65.731100, 0.034480),
(191, 'lhfa', 'female', 7.0, 1.000000, 67.287300, 0.034410),
(192, 'lhfa', 'female', 8.0, 1.000000, 68.749800, 0.034400),
(193, 'lhfa', 'female', 9.0, 1.000000, 70.143500, 0.034440),
(194, 'lhfa', 'female', 10.0, 1.000000, 71.481800, 0.034520),
(195, 'lhfa', 'female', 11.0, 1.000000, 72.771000, 0.034640),
(196, 'lhfa', 'female', 12.0, 1.000000, 74.015000, 0.034790),
(197, 'lhfa', 'female', 13.0, 1.000000, 75.217600, 0.034960),
(198, 'lhfa', 'female', 14.0, 1.000000, 76.381700, 0.035140),
(199, 'lhfa', 'female', 15.0, 1.000000, 77.509900, 0.035340),
(200, 'lhfa', 'female', 16.0, 1.000000, 78.605500, 0.035550),
(201, 'lhfa', 'female', 17.0, 1.000000, 79.671000, 0.035760),
(202, 'lhfa', 'female', 18.0, 1.000000, 80.707900, 0.035980),
(203, 'lhfa', 'female', 19.0, 1.000000, 81.718200, 0.036200),
(204, 'lhfa', 'female', 20.0, 1.000000, 82.703600, 0.036430),
(205, 'lhfa', 'female', 21.0, 1.000000, 83.665400, 0.036660),
(206, 'lhfa', 'female', 22.0, 1.000000, 84.604000, 0.036880),
(207, 'lhfa', 'female', 23.0, 1.000000, 85.520200, 0.037110),
(208, 'lhfa', 'female', 24.0, 1.000000, 85.715300, 0.037640),
(209, 'lhfa', 'female', 25.0, 1.000000, 86.590400, 0.037860),
(210, 'lhfa', 'female', 26.0, 1.000000, 87.446200, 0.038080),
(211, 'lhfa', 'female', 27.0, 1.000000, 88.283000, 0.038300),
(212, 'lhfa', 'female', 28.0, 1.000000, 89.100400, 0.038510),
(213, 'lhfa', 'female', 29.0, 1.000000, 89.899100, 0.038720),
(214, 'lhfa', 'female', 30.0, 1.000000, 90.679700, 0.038930),
(215, 'lhfa', 'female', 31.0, 1.000000, 91.443000, 0.039130),
(216, 'lhfa', 'female', 32.0, 1.000000, 92.190600, 0.039330),
(217, 'lhfa', 'female', 33.0, 1.000000, 92.923900, 0.039520),
(218, 'lhfa', 'female', 34.0, 1.000000, 93.644400, 0.039710),
(219, 'lhfa', 'female', 35.0, 1.000000, 94.353300, 0.039890),
(220, 'lhfa', 'female', 36.0, 1.000000, 95.051500, 0.040060),
(221, 'lhfa', 'female', 37.0, 1.000000, 95.739900, 0.040240),
(222, 'lhfa', 'female', 38.0, 1.000000, 96.418700, 0.040410),
(223, 'lhfa', 'female', 39.0, 1.000000, 97.088500, 0.040570),
(224, 'lhfa', 'female', 40.0, 1.000000, 97.749300, 0.040730),
(225, 'lhfa', 'female', 41.0, 1.000000, 98.401500, 0.040890),
(226, 'lhfa', 'female', 42.0, 1.000000, 99.044800, 0.041050),
(227, 'lhfa', 'female', 43.0, 1.000000, 99.679500, 0.041200),
(228, 'lhfa', 'female', 44.0, 1.000000, 100.305800, 0.041350),
(229, 'lhfa', 'female', 45.0, 1.000000, 100.923800, 0.041500),
(230, 'lhfa', 'female', 46.0, 1.000000, 101.533700, 0.041640),
(231, 'lhfa', 'female', 47.0, 1.000000, 102.136000, 0.041790),
(232, 'lhfa', 'female', 48.0, 1.000000, 102.731200, 0.041930),
(233, 'lhfa', 'female', 49.0, 1.000000, 103.319700, 0.042060),
(234, 'lhfa', 'female', 50.0, 1.000000, 103.902100, 0.042200),
(235, 'lhfa', 'female', 51.0, 1.000000, 104.478600, 0.042330),
(236, 'lhfa', 'female', 52.0, 1.000000, 105.049400, 0.042460),
(237, 'lhfa', 'female', 53.0, 1.000000, 105.614800, 0.042590),
(238, 'lhfa', 'female', 54.0, 1.000000, 106.174800, 0.042720),
(239, 'lhfa', 'female', 55.0, 1.000000, 106.729500, 0.042850),
(240, 'lhfa', 'female', 56.0, 1.000000, 107.278800, 0.042980),
(241, 'lhfa', 'female', 57.0, 1.000000, 107.822700, 0.043100),
(242, 'lhfa', 'female', 58.0, 1.000000, 108.361300, 0.043220),
(243, 'lhfa', 'female', 59.0, 1.000000, 108.894800, 0.043340),
(244, 'lhfa', 'female', 60.0, 1.000000, 109.423300, 0.043470),
(245, 'wfl', 'male', 45.0, -0.352100, 2.441000, 0.091820),
(246, 'wfl', 'male', 45.5, -0.352100, 2.524400, 0.091530),
(247, 'wfl', 'male', 46.0, -0.352100, 2.607700, 0.091240),
(248, 'wfl', 'male', 46.5, -0.352100, 2.691300, 0.090940),
(249, 'wfl', 'male', 47.0, -0.352100, 2.775500, 0.090650),
(250, 'wfl', 'male', 47.5, -0.352100, 2.860900, 0.090360),
(251, 'wfl', 'male', 48.0, -0.352100, 2.948000, 0.090070),
(252, 'wfl', 'male', 48.5, -0.352100, 3.037700, 0.089770),
(253, 'wfl', 'male', 49.0, -0.352100, 3.130800, 0.089480),
(254, 'wfl', 'male', 49.5, -0.352100, 3.227600, 0.089190),
(255, 'wfl', 'male', 50.0, -0.352100, 3.327800, 0.088900),
(256, 'wfl', 'male', 50.5, -0.352100, 3.431100, 0.088610),
(257, 'wfl', 'male', 51.0, -0.352100, 3.537600, 0.088310),
(258, 'wfl', 'male', 51.5, -0.352100, 3.647700, 0.088010),
(259, 'wfl', 'male', 52.0, -0.352100, 3.762000, 0.087710),
(260, 'wfl', 'male', 52.5, -0.352100, 3.881400, 0.087410),
(261, 'wfl', 'male', 53.0, -0.352100, 4.006000, 0.087110),
(262, 'wfl', 'male', 53.5, -0.352100, 4.135400, 0.086810),
(263, 'wfl', 'male', 54.0, -0.352100, 4.269300, 0.086510),
(264, 'wfl', 'male', 54.5, -0.352100, 4.406600, 0.086210),
(265, 'wfl', 'male', 55.0, -0.352100, 4.546700, 0.085920),
(266, 'wfl', 'male', 55.5, -0.352100, 4.689200, 0.085630),
(267, 'wfl', 'male', 56.0, -0.352100, 4.833800, 0.085350),
(268, 'wfl', 'male', 56.5, -0.352100, 4.979600, 0.085070),
(269, 'wfl', 'male', 57.0, -0.352100, 5.125900, 0.084810),
(270, 'wfl', 'male', 57.5, -0.352100, 5.272100, 0.084550),
(271, 'wfl', 'male', 58.0, -0.352100, 5.418000, 0.084300),
(272, 'wfl', 'male', 58.5, -0.352100, 5.563200, 0.084060),
(273, 'wfl', 'male', 59.0, -0.352100, 5.707400, 0.083830),
(274, 'wfl', 'male', 59.5, -0.352100, 5.850100, 0.083620),
(275, 'wfl', 'male', 60.0, -0.352100, 5.990700, 0.083420),
(276, 'wfl', 'male', 60.5, -0.352100, 6.128400, 0.083240),
(277, 'wfl', 'male', 61.0, -0.352100, 6.263200, 0.083080),
(278, 'wfl', 'male', 61.5, -0.352100, 6.395400, 0.082920),
(279, 'wfl', 'male', 62.0, -0.352100, 6.525100, 0.082790),
(280, 'wfl', 'male', 62.5, -0.352100, 6.652700, 0.082660),
(281, 'wfl', 'male', 63.0, -0.352100, 6.778600, 0.082550),
(282, 'wfl', 'male', 63.5, -0.352100, 6.902800, 0.082450),
(283, 'wfl', 'male', 64.0, -0.352100, 7.025500, 0.082360),
(284, 'wfl', 'male', 64.5, -0.352100, 7.146700, 0.082290),
(285, 'wfl', 'male', 65.0, -0.352100, 7.266600, 0.082230),
(286, 'wfl', 'male', 65.5, -0.352100, 7.385400, 0.082180),
(287, 'wfl', 'male', 66.0, -0.352100, 7.503400, 0.082150),
(288, 'wfl', 'male', 66.5, -0.352100, 7.620600, 0.082130),
(289, 'wfl', 'male', 67.0, -0.352100, 7.737000, 0.082120),
(290, 'wfl', 'male', 67.5, -0.352100, 7.852600, 0.082120),
(291, 'wfl', 'male', 68.0, -0.352100, 7.967400, 0.082140),
(292, 'wfl', 'male', 68.5, -0.352100, 8.081600, 0.082160),
(293, 'wfl', 'male', 69.0, -0.352100, 8.195500, 0.082190),
(294, 'wfl', 'male', 69.5, -0.352100, 8.309200, 0.082240),
(295, 'wfl', 'male', 70.0, -0.352100, 8.422700, 0.082290),
(296, 'wfl', 'male', 70.5, -0.352100, 8.535800, 0.082350),
(297, 'wfl', 'male', 71.0, -0.352100, 8.648000, 0.082410),
(298, 'wfl', 'male', 71.5, -0.352100, 8.759400, 0.082480),
(299, 'wfl', 'male', 72.0, -0.352100, 8.869700, 0.082540),
(300, 'wfl', 'male', 72.5, -0.352100, 8.978800, 0.082620),
(301, 'wfl', 'male', 73.0, -0.352100, 9.086500, 0.082690),
(302, 'wfl', 'male', 73.5, -0.352100, 9.192700, 0.082760),
(303, 'wfl', 'male', 74.0, -0.352100, 9.297400, 0.082830),
(304, 'wfl', 'male', 74.5, -0.352100, 9.401000, 0.082890),
(305, 'wfl', 'male', 75.0, -0.352100, 9.503200, 0.082950),
(306, 'wfl', 'male', 75.5, -0.352100, 9.604100, 0.083010),
(307, 'wfl', 'male', 76.0, -0.352100, 9.703300, 0.083070),
(308, 'wfl', 'male', 76.5, -0.352100, 9.800700, 0.083110),
(309, 'wfl', 'male', 77.0, -0.352100, 9.896300, 0.083140),
(310, 'wfl', 'male', 77.5, -0.352100, 9.990200, 0.083170),
(311, 'wfl', 'male', 78.0, -0.352100, 10.082700, 0.083180),
(312, 'wfl', 'male', 78.5, -0.352100, 10.174100, 0.083180),
(313, 'wfl', 'male', 79.0, -0.352100, 10.264900, 0.083160),
(314, 'wfl', 'male', 79.5, -0.352100, 10.355800, 0.083130),
(315, 'wfl', 'male', 80.0, -0.352100, 10.447500, 0.083080),
(316, 'wfl', 'male', 80.5, -0.352100, 10.540500, 0.083010),
(317, 'wfl', 'male', 81.0, -0.352100, 10.635200, 0.082930),
(318, 'wfl', 'male', 81.5, -0.352100, 10.732200, 0.082840),
(319, 'wfl', 'male', 82.0, -0.352100, 10.832100, 0.082730),
(320, 'wfl', 'male', 82.5, -0.352100, 10.935000, 0.082600),
(321, 'wfl', 'male', 83.0, -0.352100, 11.041500, 0.082460),
(322, 'wfl', 'male', 83.5, -0.352100, 11.151600, 0.082310),
(323, 'wfl', 'male', 84.0, -0.352100, 11.265100, 0.082150),
(324, 'wfl', 'male', 84.5, -0.352100, 11.381700, 0.081980),
(325, 'wfl', 'male', 85.0, -0.352100, 11.500700, 0.081810),
(326, 'wfl', 'male', 85.5, -0.352100, 11.621800, 0.081630),
(327, 'wfl', 'male', 86.0, -0.352100, 11.744400, 0.081450),
(328, 'wfl', 'male', 86.5, -0.352100, 11.867800, 0.081280),
(329, 'wfl', 'male', 87.0, -0.352100, 11.991600, 0.081110),
(330, 'wfl', 'male', 87.5, -0.352100, 12.115200, 0.080960),
(331, 'wfl', 'male', 88.0, -0.352100, 12.238200, 0.080820),
(332, 'wfl', 'male', 88.5, -0.352100, 12.360300, 0.080690),
(333, 'wfl', 'male', 89.0, -0.352100, 12.481500, 0.080580),
(334, 'wfl', 'male', 89.5, -0.352100, 12.601700, 0.080480),
(335, 'wfl', 'male', 90.0, -0.352100, 12.720900, 0.080410),
(336, 'wfl', 'male', 90.5, -0.352100, 12.839200, 0.080340),
(337, 'wfl', 'male', 91.0, -0.352100, 12.956900, 0.080300),
(338, 'wfl', 'male', 91.5, -0.352100, 13.074200, 0.080260),
(339, 'wfl', 'male', 92.0, -0.352100, 13.191000, 0.080250),
(340, 'wfl', 'male', 92.5, -0.352100, 13.307500, 0.080250),
(341, 'wfl', 'male', 93.0, -0.352100, 13.423900, 0.080260),
(342, 'wfl', 'male', 93.5, -0.352100, 13.540400, 0.080290),
(343, 'wfl', 'male', 94.0, -0.352100, 13.657200, 0.080340),
(344, 'wfl', 'male', 94.5, -0.352100, 13.774600, 0.080400),
(345, 'wfl', 'male', 95.0, -0.352100, 13.892800, 0.080470),
(346, 'wfl', 'male', 95.5, -0.352100, 14.012000, 0.080560),
(347, 'wfl', 'male', 96.0, -0.352100, 14.132500, 0.080670),
(348, 'wfl', 'male', 96.5, -0.352100, 14.254400, 0.080780),
(349, 'wfl', 'male', 97.0, -0.352100, 14.378200, 0.080920),
(350, 'wfl', 'male', 97.5, -0.352100, 14.503800, 0.081060),
(351, 'wfl', 'male', 98.0, -0.352100, 14.631600, 0.081220),
(352, 'wfl', 'male', 98.5, -0.352100, 14.761400, 0.081390),
(353, 'wfl', 'male', 99.0, -0.352100, 14.893400, 0.081570),
(354, 'wfl', 'male', 99.5, -0.352100, 15.027500, 0.081770),
(355, 'wfl', 'male', 100.0, -0.352100, 15.163700, 0.081980),
(356, 'wfl', 'male', 100.5, -0.352100, 15.301800, 0.082200),
(357, 'wfl', 'male', 101.0, -0.352100, 15.441900, 0.082430),
(358, 'wfl', 'male', 101.5, -0.352100, 15.583800, 0.082670),
(359, 'wfl', 'male', 102.0, -0.352100, 15.727600, 0.082920),
(360, 'wfl', 'male', 102.5, -0.352100, 15.873200, 0.083170),
(361, 'wfl', 'male', 103.0, -0.352100, 16.020600, 0.083430),
(362, 'wfl', 'male', 103.5, -0.352100, 16.169700, 0.083700),
(363, 'wfl', 'male', 104.0, -0.352100, 16.320400, 0.083970),
(364, 'wfl', 'male', 104.5, -0.352100, 16.472800, 0.084250),
(365, 'wfl', 'male', 105.0, -0.352100, 16.626800, 0.084530),
(366, 'wfl', 'male', 105.5, -0.352100, 16.782600, 0.084810),
(367, 'wfl', 'male', 106.0, -0.352100, 16.940100, 0.085100),
(368, 'wfl', 'male', 106.5, -0.352100, 17.099500, 0.085390),
(369, 'wfl', 'male', 107.0, -0.352100, 17.260700, 0.085680),
(370, 'wfl', 'male', 107.5, -0.352100, 17.423700, 0.085990),
(371, 'wfl', 'male', 108.0, -0.352100, 17.588500, 0.086290),
(372, 'wfl', 'male', 108.5, -0.352100, 17.755300, 0.086600),
(373, 'wfl', 'male', 109.0, -0.352100, 17.924200, 0.086910),
(374, 'wfl', 'male', 109.5, -0.352100, 18.095400, 0.087230),
(375, 'wfl', 'male', 110.0, -0.352100, 18.268900, 0.087550),
(376, 'wfl', 'female', 45.0, -0.383300, 2.460700, 0.090290),
(377, 'wfl', 'female', 45.5, -0.383300, 2.545700, 0.090330),
(378, 'wfl', 'female', 46.0, -0.383300, 2.630600, 0.090370),
(379, 'wfl', 'female', 46.5, -0.383300, 2.715500, 0.090400),
(380, 'wfl', 'female', 47.0, -0.383300, 2.800700, 0.090440),
(381, 'wfl', 'female', 47.5, -0.383300, 2.886700, 0.090480),
(382, 'wfl', 'female', 48.0, -0.383300, 2.974100, 0.090520),
(383, 'wfl', 'female', 48.5, -0.383300, 3.063600, 0.090560),
(384, 'wfl', 'female', 49.0, -0.383300, 3.156000, 0.090600),
(385, 'wfl', 'female', 49.5, -0.383300, 3.252000, 0.090640),
(386, 'wfl', 'female', 50.0, -0.383300, 3.351800, 0.090680),
(387, 'wfl', 'female', 50.5, -0.383300, 3.455700, 0.090720),
(388, 'wfl', 'female', 51.0, -0.383300, 3.563600, 0.090760),
(389, 'wfl', 'female', 51.5, -0.383300, 3.675400, 0.090800),
(390, 'wfl', 'female', 52.0, -0.383300, 3.791100, 0.090850),
(391, 'wfl', 'female', 52.5, -0.383300, 3.910500, 0.090890),
(392, 'wfl', 'female', 53.0, -0.383300, 4.033200, 0.090930),
(393, 'wfl', 'female', 53.5, -0.383300, 4.159100, 0.090980),
(394, 'wfl', 'female', 54.0, -0.383300, 4.287500, 0.091020),
(395, 'wfl', 'female', 54.5, -0.383300, 4.417900, 0.091060),
(396, 'wfl', 'female', 55.0, -0.383300, 4.549800, 0.091100),
(397, 'wfl', 'female', 55.5, -0.383300, 4.682700, 0.091140),
(398, 'wfl', 'female', 56.0, -0.383300, 4.816200, 0.091180),
(399, 'wfl', 'female', 56.5, -0.383300, 4.950000, 0.091210),
(400, 'wfl', 'female', 57.0, -0.383300, 5.083700, 0.091250),
(401, 'wfl', 'female', 57.5, -0.383300, 5.217300, 0.091280),
(402, 'wfl', 'female', 58.0, -0.383300, 5.350700, 0.091300),
(403, 'wfl', 'female', 58.5, -0.383300, 5.483400, 0.091320),
(404, 'wfl', 'female', 59.0, -0.383300, 5.615100, 0.091340),
(405, 'wfl', 'female', 59.5, -0.383300, 5.745400, 0.091350),
(406, 'wfl', 'female', 60.0, -0.383300, 5.874200, 0.091360),
(407, 'wfl', 'female', 60.5, -0.383300, 6.001400, 0.091370),
(408, 'wfl', 'female', 61.0, -0.383300, 6.127000, 0.091370),
(409, 'wfl', 'female', 61.5, -0.383300, 6.251100, 0.091360),
(410, 'wfl', 'female', 62.0, -0.383300, 6.373800, 0.091350),
(411, 'wfl', 'female', 62.5, -0.383300, 6.494800, 0.091330),
(412, 'wfl', 'female', 63.0, -0.383300, 6.614400, 0.091310),
(413, 'wfl', 'female', 63.5, -0.383300, 6.732800, 0.091290),
(414, 'wfl', 'female', 64.0, -0.383300, 6.850100, 0.091260),
(415, 'wfl', 'female', 64.5, -0.383300, 6.966200, 0.091230),
(416, 'wfl', 'female', 65.0, -0.383300, 7.081200, 0.091190),
(417, 'wfl', 'female', 65.5, -0.383300, 7.195000, 0.091150),
(418, 'wfl', 'female', 66.0, -0.383300, 7.307600, 0.091100),
(419, 'wfl', 'female', 66.5, -0.383300, 7.418900, 0.091060),
(420, 'wfl', 'female', 67.0, -0.383300, 7.528800, 0.091010),
(421, 'wfl', 'female', 67.5, -0.383300, 7.637500, 0.090960),
(422, 'wfl', 'female', 68.0, -0.383300, 7.744800, 0.090900),
(423, 'wfl', 'female', 68.5, -0.383300, 7.850900, 0.090850),
(424, 'wfl', 'female', 69.0, -0.383300, 7.955900, 0.090790),
(425, 'wfl', 'female', 69.5, -0.383300, 8.059900, 0.090740),
(426, 'wfl', 'female', 70.0, -0.383300, 8.163000, 0.090680),
(427, 'wfl', 'female', 70.5, -0.383300, 8.265100, 0.090620),
(428, 'wfl', 'female', 71.0, -0.383300, 8.366600, 0.090560),
(429, 'wfl', 'female', 71.5, -0.383300, 8.467600, 0.090500),
(430, 'wfl', 'female', 72.0, -0.383300, 8.567900, 0.090430),
(431, 'wfl', 'female', 72.5, -0.383300, 8.667400, 0.090370),
(432, 'wfl', 'female', 73.0, -0.383300, 8.766100, 0.090310),
(433, 'wfl', 'female', 73.5, -0.383300, 8.863800, 0.090250),
(434, 'wfl', 'female', 74.0, -0.383300, 8.960100, 0.090180),
(435, 'wfl', 'female', 74.5, -0.383300, 9.055200, 0.090120),
(436, 'wfl', 'female', 75.0, -0.383300, 9.149000, 0.090050),
(437, 'wfl', 'female', 75.5, -0.383300, 9.241800, 0.089990),
(438, 'wfl', 'female', 76.0, -0.383300, 9.333700, 0.089920),
(439, 'wfl', 'female', 76.5, -0.383300, 9.425200, 0.089850),
(440, 'wfl', 'female', 77.0, -0.383300, 9.516600, 0.089790),
(441, 'wfl', 'female', 77.5, -0.383300, 9.608600, 0.089720),
(442, 'wfl', 'female', 78.0, -0.383300, 9.701500, 0.089650),
(443, 'wfl', 'female', 78.5, -0.383300, 9.795700, 0.089590),
(444, 'wfl', 'female', 79.0, -0.383300, 9.891500, 0.089520),
(445, 'wfl', 'female', 79.5, -0.383300, 9.989200, 0.089460),
(446, 'wfl', 'female', 80.0, -0.383300, 10.089100, 0.089400),
(447, 'wfl', 'female', 80.5, -0.383300, 10.191600, 0.089340),
(448, 'wfl', 'female', 81.0, -0.383300, 10.296500, 0.089280),
(449, 'wfl', 'female', 81.5, -0.383300, 10.404100, 0.089230),
(450, 'wfl', 'female', 82.0, -0.383300, 10.514000, 0.089180),
(451, 'wfl', 'female', 82.5, -0.383300, 10.626300, 0.089140),
(452, 'wfl', 'female', 83.0, -0.383300, 10.741000, 0.089100),
(453, 'wfl', 'female', 83.5, -0.383300, 10.857800, 0.089060),
(454, 'wfl', 'female', 84.0, -0.383300, 10.976700, 0.089030),
(455, 'wfl', 'female', 84.5, -0.383300, 11.097400, 0.089000),
(456, 'wfl', 'female', 85.0, -0.383300, 11.219800, 0.088980),
(457, 'wfl', 'female', 85.5, -0.383300, 11.343500, 0.088970),
(458, 'wfl', 'female', 86.0, -0.383300, 11.468400, 0.088950),
(459, 'wfl', 'female', 86.5, -0.383300, 11.594000, 0.088950),
(460, 'wfl', 'female', 87.0, -0.383300, 11.720100, 0.088950),
(461, 'wfl', 'female', 87.5, -0.383300, 11.846100, 0.088950),
(462, 'wfl', 'female', 88.0, -0.383300, 11.972000, 0.088960),
(463, 'wfl', 'female', 88.5, -0.383300, 12.097600, 0.088980),
(464, 'wfl', 'female', 89.0, -0.383300, 12.222900, 0.089000),
(465, 'wfl', 'female', 89.5, -0.383300, 12.347700, 0.089030),
(466, 'wfl', 'female', 90.0, -0.383300, 12.472300, 0.089060),
(467, 'wfl', 'female', 90.5, -0.383300, 12.596500, 0.089090),
(468, 'wfl', 'female', 91.0, -0.383300, 12.720500, 0.089130),
(469, 'wfl', 'female', 91.5, -0.383300, 12.844300, 0.089180),
(470, 'wfl', 'female', 92.0, -0.383300, 12.968100, 0.089230),
(471, 'wfl', 'female', 92.5, -0.383300, 13.092000, 0.089280),
(472, 'wfl', 'female', 93.0, -0.383300, 13.215800, 0.089340),
(473, 'wfl', 'female', 93.5, -0.383300, 13.339900, 0.089410),
(474, 'wfl', 'female', 94.0, -0.383300, 13.464300, 0.089480),
(475, 'wfl', 'female', 94.5, -0.383300, 13.589200, 0.089550),
(476, 'wfl', 'female', 95.0, -0.383300, 13.714600, 0.089630),
(477, 'wfl', 'female', 95.5, -0.383300, 13.840800, 0.089720),
(478, 'wfl', 'female', 96.0, -0.383300, 13.967600, 0.089810),
(479, 'wfl', 'female', 96.5, -0.383300, 14.095300, 0.089900),
(480, 'wfl', 'female', 97.0, -0.383300, 14.223900, 0.090000),
(481, 'wfl', 'female', 97.5, -0.383300, 14.353700, 0.090100),
(482, 'wfl', 'female', 98.0, -0.383300, 14.484800, 0.090210),
(483, 'wfl', 'female', 98.5, -0.383300, 14.617400, 0.090330),
(484, 'wfl', 'female', 99.0, -0.383300, 14.751900, 0.090440),
(485, 'wfl', 'female', 99.5, -0.383300, 14.888200, 0.090570),
(486, 'wfl', 'female', 100.0, -0.383300, 15.026700, 0.090690),
(487, 'wfl', 'female', 100.5, -0.383300, 15.167600, 0.090830),
(488, 'wfl', 'female', 101.0, -0.383300, 15.310800, 0.090960),
(489, 'wfl', 'female', 101.5, -0.383300, 15.456400, 0.091100),
(490, 'wfl', 'female', 102.0, -0.383300, 15.604600, 0.091250),
(491, 'wfl', 'female', 102.5, -0.383300, 15.755300, 0.091390),
(492, 'wfl', 'female', 103.0, -0.383300, 15.908700, 0.091550),
(493, 'wfl', 'female', 103.5, -0.383300, 16.064500, 0.091700),
(494, 'wfl', 'female', 104.0, -0.383300, 16.222900, 0.091860),
(495, 'wfl', 'female', 104.5, -0.383300, 16.383700, 0.092030),
(496, 'wfl', 'female', 105.0, -0.383300, 16.547000, 0.092190),
(497, 'wfl', 'female', 105.5, -0.383300, 16.712900, 0.092360),
(498, 'wfl', 'female', 106.0, -0.383300, 16.881400, 0.092540),
(499, 'wfl', 'female', 106.5, -0.383300, 17.052700, 0.092710),
(500, 'wfl', 'female', 107.0, -0.383300, 17.226900, 0.092890),
(501, 'wfl', 'female', 107.5, -0.383300, 17.403900, 0.093070),
(502, 'wfl', 'female', 108.0, -0.383300, 17.583900, 0.093260),
(503, 'wfl', 'female', 108.5, -0.383300, 17.766800, 0.093440),
(504, 'wfl', 'female', 109.0, -0.383300, 17.952600, 0.093630),
(505, 'wfl', 'female', 109.5, -0.383300, 18.141200, 0.093820),
(506, 'wfl', 'female', 110.0, -0.383300, 18.332400, 0.094010),
(507, 'wfh', 'male', 65.0, -0.352100, 7.432700, 0.082170),
(508, 'wfh', 'male', 65.5, -0.352100, 7.550400, 0.082140),
(509, 'wfh', 'male', 66.0, -0.352100, 7.667300, 0.082120),
(510, 'wfh', 'male', 66.5, -0.352100, 7.783400, 0.082120),
(511, 'wfh', 'male', 67.0, -0.352100, 7.898600, 0.082130),
(512, 'wfh', 'male', 67.5, -0.352100, 8.013200, 0.082140),
(513, 'wfh', 'male', 68.0, -0.352100, 8.127200, 0.082170),
(514, 'wfh', 'male', 68.5, -0.352100, 8.241000, 0.082210),
(515, 'wfh', 'male', 69.0, -0.352100, 8.354700, 0.082260),
(516, 'wfh', 'male', 69.5, -0.352100, 8.468000, 0.082310),
(517, 'wfh', 'male', 70.0, -0.352100, 8.580800, 0.082370),
(518, 'wfh', 'male', 70.5, -0.352100, 8.692700, 0.082430),
(519, 'wfh', 'male', 71.0, -0.352100, 8.803600, 0.082500),
(520, 'wfh', 'male', 71.5, -0.352100, 8.913500, 0.082570),
(521, 'wfh', 'male', 72.0, -0.352100, 9.022100, 0.082640),
(522, 'wfh', 'male', 72.5, -0.352100, 9.129200, 0.082720),
(523, 'wfh', 'male', 73.0, -0.352100, 9.234700, 0.082780),
(524, 'wfh', 'male', 73.5, -0.352100, 9.339000, 0.082850),
(525, 'wfh', 'male', 74.0, -0.352100, 9.442000, 0.082920),
(526, 'wfh', 'male', 74.5, -0.352100, 9.543800, 0.082980),
(527, 'wfh', 'male', 75.0, -0.352100, 9.644000, 0.083030),
(528, 'wfh', 'male', 75.5, -0.352100, 9.742500, 0.083080),
(529, 'wfh', 'male', 76.0, -0.352100, 9.839200, 0.083120),
(530, 'wfh', 'male', 76.5, -0.352100, 9.934100, 0.083150),
(531, 'wfh', 'male', 77.0, -0.352100, 10.027400, 0.083170),
(532, 'wfh', 'male', 77.5, -0.352100, 10.119400, 0.083180),
(533, 'wfh', 'male', 78.0, -0.352100, 10.210500, 0.083170),
(534, 'wfh', 'male', 78.5, -0.352100, 10.301200, 0.083150),
(535, 'wfh', 'male', 79.0, -0.352100, 10.392300, 0.083110),
(536, 'wfh', 'male', 79.5, -0.352100, 10.484500, 0.083050),
(537, 'wfh', 'male', 80.0, -0.352100, 10.578100, 0.082980),
(538, 'wfh', 'male', 80.5, -0.352100, 10.673700, 0.082900),
(539, 'wfh', 'male', 81.0, -0.352100, 10.771800, 0.082790),
(540, 'wfh', 'male', 81.5, -0.352100, 10.872800, 0.082680),
(541, 'wfh', 'male', 82.0, -0.352100, 10.977200, 0.082550),
(542, 'wfh', 'male', 82.5, -0.352100, 11.085100, 0.082410),
(543, 'wfh', 'male', 83.0, -0.352100, 11.196600, 0.082250),
(544, 'wfh', 'male', 83.5, -0.352100, 11.311400, 0.082090),
(545, 'wfh', 'male', 84.0, -0.352100, 11.429000, 0.081910),
(546, 'wfh', 'male', 84.5, -0.352100, 11.549000, 0.081740),
(547, 'wfh', 'male', 85.0, -0.352100, 11.670700, 0.081560),
(548, 'wfh', 'male', 85.5, -0.352100, 11.793700, 0.081380),
(549, 'wfh', 'male', 86.0, -0.352100, 11.917300, 0.081210),
(550, 'wfh', 'male', 86.5, -0.352100, 12.041100, 0.081050),
(551, 'wfh', 'male', 87.0, -0.352100, 12.164500, 0.080900),
(552, 'wfh', 'male', 87.5, -0.352100, 12.287100, 0.080760),
(553, 'wfh', 'male', 88.0, -0.352100, 12.408900, 0.080640),
(554, 'wfh', 'male', 88.5, -0.352100, 12.529800, 0.080540),
(555, 'wfh', 'male', 89.0, -0.352100, 12.649500, 0.080450),
(556, 'wfh', 'male', 89.5, -0.352100, 12.768300, 0.080380),
(557, 'wfh', 'male', 90.0, -0.352100, 12.886400, 0.080320),
(558, 'wfh', 'male', 90.5, -0.352100, 13.003800, 0.080280),
(559, 'wfh', 'male', 91.0, -0.352100, 13.120900, 0.080250),
(560, 'wfh', 'male', 91.5, -0.352100, 13.237600, 0.080240),
(561, 'wfh', 'male', 92.0, -0.352100, 13.354100, 0.080250),
(562, 'wfh', 'male', 92.5, -0.352100, 13.470500, 0.080270),
(563, 'wfh', 'male', 93.0, -0.352100, 13.587000, 0.080310),
(564, 'wfh', 'male', 93.5, -0.352100, 13.704100, 0.080360),
(565, 'wfh', 'male', 94.0, -0.352100, 13.821700, 0.080430),
(566, 'wfh', 'male', 94.5, -0.352100, 13.940300, 0.080510),
(567, 'wfh', 'male', 95.0, -0.352100, 14.060000, 0.080600),
(568, 'wfh', 'male', 95.5, -0.352100, 14.181100, 0.080710),
(569, 'wfh', 'male', 96.0, -0.352100, 14.303700, 0.080830),
(570, 'wfh', 'male', 96.5, -0.352100, 14.428200, 0.080970),
(571, 'wfh', 'male', 97.0, -0.352100, 14.554700, 0.081120),
(572, 'wfh', 'male', 97.5, -0.352100, 14.683200, 0.081290),
(573, 'wfh', 'male', 98.0, -0.352100, 14.814000, 0.081460),
(574, 'wfh', 'male', 98.5, -0.352100, 14.946800, 0.081650),
(575, 'wfh', 'male', 99.0, -0.352100, 15.081800, 0.081850),
(576, 'wfh', 'male', 99.5, -0.352100, 15.218700, 0.082060),
(577, 'wfh', 'male', 100.0, -0.352100, 15.357600, 0.082290),
(578, 'wfh', 'male', 100.5, -0.352100, 15.498500, 0.082520),
(579, 'wfh', 'male', 101.0, -0.352100, 15.641200, 0.082770),
(580, 'wfh', 'male', 101.5, -0.352100, 15.785700, 0.083020),
(581, 'wfh', 'male', 102.0, -0.352100, 15.932000, 0.083280),
(582, 'wfh', 'male', 102.5, -0.352100, 16.080100, 0.083540),
(583, 'wfh', 'male', 103.0, -0.352100, 16.229800, 0.083810),
(584, 'wfh', 'male', 103.5, -0.352100, 16.381200, 0.084080),
(585, 'wfh', 'male', 104.0, -0.352100, 16.534200, 0.084360),
(586, 'wfh', 'male', 104.5, -0.352100, 16.688900, 0.084640),
(587, 'wfh', 'male', 105.0, -0.352100, 16.845400, 0.084930),
(588, 'wfh', 'male', 105.5, -0.352100, 17.003600, 0.085210),
(589, 'wfh', 'male', 106.0, -0.352100, 17.163700, 0.085510),
(590, 'wfh', 'male', 106.5, -0.352100, 17.325600, 0.085800),
(591, 'wfh', 'male', 107.0, -0.352100, 17.489400, 0.086110),
(592, 'wfh', 'male', 107.5, -0.352100, 17.655000, 0.086410),
(593, 'wfh', 'male', 108.0, -0.352100, 17.822600, 0.086730),
(594, 'wfh', 'male', 108.5, -0.352100, 17.992400, 0.087040),
(595, 'wfh', 'male', 109.0, -0.352100, 18.164500, 0.087360),
(596, 'wfh', 'male', 109.5, -0.352100, 18.339000, 0.087680),
(597, 'wfh', 'male', 110.0, -0.352100, 18.515800, 0.088000),
(598, 'wfh', 'male', 110.5, -0.352100, 18.694800, 0.088320),
(599, 'wfh', 'male', 111.0, -0.352100, 18.875900, 0.088640),
(600, 'wfh', 'male', 111.5, -0.352100, 19.059000, 0.088960),
(601, 'wfh', 'male', 112.0, -0.352100, 19.243900, 0.089280),
(602, 'wfh', 'male', 112.5, -0.352100, 19.430400, 0.089600),
(603, 'wfh', 'male', 113.0, -0.352100, 19.618500, 0.089910),
(604, 'wfh', 'male', 113.5, -0.352100, 19.808100, 0.090220),
(605, 'wfh', 'male', 114.0, -0.352100, 19.999000, 0.090540),
(606, 'wfh', 'male', 114.5, -0.352100, 20.191200, 0.090850),
(607, 'wfh', 'male', 115.0, -0.352100, 20.384600, 0.091160),
(608, 'wfh', 'male', 115.5, -0.352100, 20.578900, 0.091470),
(609, 'wfh', 'male', 116.0, -0.352100, 20.774100, 0.091770),
(610, 'wfh', 'male', 116.5, -0.352100, 20.970000, 0.092080),
(611, 'wfh', 'male', 117.0, -0.352100, 21.166600, 0.092390),
(612, 'wfh', 'male', 117.5, -0.352100, 21.363600, 0.092700),
(613, 'wfh', 'male', 118.0, -0.352100, 21.561100, 0.093000),
(614, 'wfh', 'male', 118.5, -0.352100, 21.758800, 0.093310),
(615, 'wfh', 'male', 119.0, -0.352100, 21.956800, 0.093620),
(616, 'wfh', 'male', 119.5, -0.352100, 22.154900, 0.093930),
(617, 'wfh', 'male', 120.0, -0.352100, 22.353000, 0.094240),
(618, 'wfh', 'female', 65.0, -0.383300, 7.240200, 0.091130),
(619, 'wfh', 'female', 65.5, -0.383300, 7.352300, 0.091090),
(620, 'wfh', 'female', 66.0, -0.383300, 7.463000, 0.091040),
(621, 'wfh', 'female', 66.5, -0.383300, 7.572400, 0.090990),
(622, 'wfh', 'female', 67.0, -0.383300, 7.680600, 0.090940),
(623, 'wfh', 'female', 67.5, -0.383300, 7.787400, 0.090880),
(624, 'wfh', 'female', 68.0, -0.383300, 7.893000, 0.090830),
(625, 'wfh', 'female', 68.5, -0.383300, 7.997600, 0.090770),
(626, 'wfh', 'female', 69.0, -0.383300, 8.101200, 0.090710),
(627, 'wfh', 'female', 69.5, -0.383300, 8.203900, 0.090650),
(628, 'wfh', 'female', 70.0, -0.383300, 8.305800, 0.090590),
(629, 'wfh', 'female', 70.5, -0.383300, 8.407100, 0.090530),
(630, 'wfh', 'female', 71.0, -0.383300, 8.507800, 0.090470),
(631, 'wfh', 'female', 71.5, -0.383300, 8.607800, 0.090410),
(632, 'wfh', 'female', 72.0, -0.383300, 8.707000, 0.090350),
(633, 'wfh', 'female', 72.5, -0.383300, 8.805300, 0.090280),
(634, 'wfh', 'female', 73.0, -0.383300, 8.902500, 0.090220),
(635, 'wfh', 'female', 73.5, -0.383300, 8.998300, 0.090160),
(636, 'wfh', 'female', 74.0, -0.383300, 9.092800, 0.090090),
(637, 'wfh', 'female', 74.5, -0.383300, 9.186200, 0.090030),
(638, 'wfh', 'female', 75.0, -0.383300, 9.278600, 0.089960),
(639, 'wfh', 'female', 75.5, -0.383300, 9.370300, 0.089890),
(640, 'wfh', 'female', 76.0, -0.383300, 9.461700, 0.089830),
(641, 'wfh', 'female', 76.5, -0.383300, 9.553300, 0.089760),
(642, 'wfh', 'female', 77.0, -0.383300, 9.645600, 0.089690),
(643, 'wfh', 'female', 77.5, -0.383300, 9.739000, 0.089630),
(644, 'wfh', 'female', 78.0, -0.383300, 9.833800, 0.089560),
(645, 'wfh', 'female', 78.5, -0.383300, 9.930300, 0.089500),
(646, 'wfh', 'female', 79.0, -0.383300, 10.028900, 0.089430),
(647, 'wfh', 'female', 79.5, -0.383300, 10.129800, 0.089370),
(648, 'wfh', 'female', 80.0, -0.383300, 10.233200, 0.089320),
(649, 'wfh', 'female', 80.5, -0.383300, 10.339300, 0.089260),
(650, 'wfh', 'female', 81.0, -0.383300, 10.447700, 0.089210),
(651, 'wfh', 'female', 81.5, -0.383300, 10.558600, 0.089160),
(652, 'wfh', 'female', 82.0, -0.383300, 10.671900, 0.089120),
(653, 'wfh', 'female', 82.5, -0.383300, 10.787400, 0.089080),
(654, 'wfh', 'female', 83.0, -0.383300, 10.905100, 0.089050),
(655, 'wfh', 'female', 83.5, -0.383300, 11.024800, 0.089020),
(656, 'wfh', 'female', 84.0, -0.383300, 11.146200, 0.088990),
(657, 'wfh', 'female', 84.5, -0.383300, 11.269100, 0.088970),
(658, 'wfh', 'female', 85.0, -0.383300, 11.393400, 0.088960),
(659, 'wfh', 'female', 85.5, -0.383300, 11.518600, 0.088950),
(660, 'wfh', 'female', 86.0, -0.383300, 11.644400, 0.088950),
(661, 'wfh', 'female', 86.5, -0.383300, 11.770500, 0.088950),
(662, 'wfh', 'female', 87.0, -0.383300, 11.896500, 0.088960),
(663, 'wfh', 'female', 87.5, -0.383300, 12.022300, 0.088970),
(664, 'wfh', 'female', 88.0, -0.383300, 12.147800, 0.088990),
(665, 'wfh', 'female', 88.5, -0.383300, 12.272900, 0.089010),
(666, 'wfh', 'female', 89.0, -0.383300, 12.397600, 0.089040),
(667, 'wfh', 'female', 89.5, -0.383300, 12.522000, 0.089070),
(668, 'wfh', 'female', 90.0, -0.383300, 12.646100, 0.089110),
(669, 'wfh', 'female', 90.5, -0.383300, 12.770000, 0.089150),
(670, 'wfh', 'female', 91.0, -0.383300, 12.893900, 0.089200),
(671, 'wfh', 'female', 91.5, -0.383300, 13.017700, 0.089250),
(672, 'wfh', 'female', 92.0, -0.383300, 13.141500, 0.089310),
(673, 'wfh', 'female', 92.5, -0.383300, 13.265400, 0.089370),
(674, 'wfh', 'female', 93.0, -0.383300, 13.389600, 0.089440),
(675, 'wfh', 'female', 93.5, -0.383300, 13.514200, 0.089510),
(676, 'wfh', 'female', 94.0, -0.383300, 13.639300, 0.089590),
(677, 'wfh', 'female', 94.5, -0.383300, 13.765000, 0.089670),
(678, 'wfh', 'female', 95.0, -0.383300, 13.891400, 0.089750),
(679, 'wfh', 'female', 95.5, -0.383300, 14.018600, 0.089840),
(680, 'wfh', 'female', 96.0, -0.383300, 14.146600, 0.089940),
(681, 'wfh', 'female', 96.5, -0.383300, 14.275700, 0.090040),
(682, 'wfh', 'female', 97.0, -0.383300, 14.405900, 0.090150),
(683, 'wfh', 'female', 97.5, -0.383300, 14.537600, 0.090260),
(684, 'wfh', 'female', 98.0, -0.383300, 14.671000, 0.090370),
(685, 'wfh', 'female', 98.5, -0.383300, 14.806200, 0.090490),
(686, 'wfh', 'female', 99.0, -0.383300, 14.943400, 0.090620),
(687, 'wfh', 'female', 99.5, -0.383300, 15.082800, 0.090750),
(688, 'wfh', 'female', 100.0, -0.383300, 15.224600, 0.090880),
(689, 'wfh', 'female', 100.5, -0.383300, 15.368700, 0.091020),
(690, 'wfh', 'female', 101.0, -0.383300, 15.515400, 0.091160),
(691, 'wfh', 'female', 101.5, -0.383300, 15.664600, 0.091310),
(692, 'wfh', 'female', 102.0, -0.383300, 15.816400, 0.091460),
(693, 'wfh', 'female', 102.5, -0.383300, 15.970700, 0.091610),
(694, 'wfh', 'female', 103.0, -0.383300, 16.127600, 0.091770),
(695, 'wfh', 'female', 103.5, -0.383300, 16.287000, 0.091930),
(696, 'wfh', 'female', 104.0, -0.383300, 16.448800, 0.092090),
(697, 'wfh', 'female', 104.5, -0.383300, 16.613100, 0.092260),
(698, 'wfh', 'female', 105.0, -0.383300, 16.780000, 0.092430),
(699, 'wfh', 'female', 105.5, -0.383300, 16.949600, 0.092610),
(700, 'wfh', 'female', 106.0, -0.383300, 17.122000, 0.092780),
(701, 'wfh', 'female', 106.5, -0.383300, 17.297300, 0.092960),
(702, 'wfh', 'female', 107.0, -0.383300, 17.475500, 0.093150),
(703, 'wfh', 'female', 107.5, -0.383300, 17.656700, 0.093330),
(704, 'wfh', 'female', 108.0, -0.383300, 17.840700, 0.093520),
(705, 'wfh', 'female', 108.5, -0.383300, 18.027700, 0.093710),
(706, 'wfh', 'female', 109.0, -0.383300, 18.217400, 0.093900),
(707, 'wfh', 'female', 109.5, -0.383300, 18.409600, 0.094090),
(708, 'wfh', 'female', 110.0, -0.383300, 18.604300, 0.094280),
(709, 'wfh', 'female', 110.5, -0.383300, 18.801500, 0.094480),
(710, 'wfh', 'female', 111.0, -0.383300, 19.000900, 0.094670),
(711, 'wfh', 'female', 111.5, -0.383300, 19.202400, 0.094870),
(712, 'wfh', 'female', 112.0, -0.383300, 19.406000, 0.095070),
(713, 'wfh', 'female', 112.5, -0.383300, 19.611600, 0.095270),
(714, 'wfh', 'female', 113.0, -0.383300, 19.819000, 0.095460),
(715, 'wfh', 'female', 113.5, -0.383300, 20.028000, 0.095660),
(716, 'wfh', 'female', 114.0, -0.383300, 20.238500, 0.095860),
(717, 'wfh', 'female', 114.5, -0.383300, 20.450200, 0.096060),
(718, 'wfh', 'female', 115.0, -0.383300, 20.662900, 0.096260),
(719, 'wfh', 'female', 115.5, -0.383300, 20.876600, 0.096460),
(720, 'wfh', 'female', 116.0, -0.383300, 21.090900, 0.096660),
(721, 'wfh', 'female', 116.5, -0.383300, 21.305900, 0.096860),
(722, 'wfh', 'female', 117.0, -0.383300, 21.521300, 0.097070),
(723, 'wfh', 'female', 117.5, -0.383300, 21.737000, 0.097270),
(724, 'wfh', 'female', 118.0, -0.383300, 21.952900, 0.097470),
(725, 'wfh', 'female', 118.5, -0.383300, 22.169000, 0.097670),
(726, 'wfh', 'female', 119.0, -0.383300, 22.385100, 0.097880),
(727, 'wfh', 'female', 119.5, -0.383300, 22.601200, 0.098080),
(728, 'wfh', 'female', 120.0, -0.383300, 22.817300, 0.098280);

-- --------------------------------------------------------

--
-- Table structure for table `health_immunizations`
--

CREATE TABLE `health_immunizations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `resident_id` bigint(20) UNSIGNED NOT NULL,
  `vaccine_id` int(10) UNSIGNED NOT NULL,
  `date_given` date NOT NULL,
  `given_by` varchar(150) NOT NULL,
  `lot_no` varchar(50) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `archived_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `health_nutrition`
--

CREATE TABLE `health_nutrition` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `resident_id` bigint(20) UNSIGNED NOT NULL,
  `weigh_date` date NOT NULL,
  `age_months` smallint(5) UNSIGNED NOT NULL,
  `weight_kg` decimal(5,2) NOT NULL,
  `height_cm` decimal(5,1) NOT NULL,
  `measured_lying` tinyint(1) NOT NULL DEFAULT 0,
  `wfa_status` enum('severely_underweight','underweight','normal','overweight') DEFAULT NULL,
  `hfa_status` enum('severely_stunted','stunted','normal','tall') DEFAULT NULL,
  `wfh_status` enum('severely_wasted','wasted','normal','overweight','obese') DEFAULT NULL,
  `status_source` enum('who_auto','manual') NOT NULL DEFAULT 'manual',
  `remarks` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `archived_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `health_pregnancies`
--

CREATE TABLE `health_pregnancies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `resident_id` bigint(20) UNSIGNED NOT NULL,
  `lmp_date` date DEFAULT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `gravida` tinyint(3) UNSIGNED DEFAULT NULL,
  `para` tinyint(3) UNSIGNED DEFAULT NULL,
  `status` enum('active','delivered','ended') NOT NULL DEFAULT 'active',
  `delivery_date` date DEFAULT NULL,
  `delivery_outcome` enum('live_birth','stillbirth','miscarriage','other') DEFAULT NULL,
  `delivery_place` enum('health_facility','home','other') DEFAULT NULL,
  `delivery_place_name` varchar(150) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `archived_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `health_records`
--

CREATE TABLE `health_records` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `record_no` varchar(20) NOT NULL,
  `resident_id` bigint(20) UNSIGNED NOT NULL,
  `service` enum('check_up','prenatal','postnatal','immunization','bp_monitoring','blood_sugar_monitoring','deworming','family_planning','tb_dots_monitoring','other') NOT NULL,
  `service_details` varchar(150) DEFAULT NULL,
  `bp_systolic` smallint(5) UNSIGNED DEFAULT NULL,
  `bp_diastolic` smallint(5) UNSIGNED DEFAULT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `height_cm` decimal(5,1) DEFAULT NULL,
  `temperature_c` decimal(4,1) DEFAULT NULL,
  `blood_sugar_mgdl` decimal(5,1) DEFAULT NULL,
  `chief_complaint` varchar(255) DEFAULT NULL,
  `findings` text DEFAULT NULL,
  `condition_id` int(10) UNSIGNED DEFAULT NULL,
  `health_worker` varchar(150) NOT NULL,
  `service_date` date NOT NULL,
  `status` enum('scheduled','completed','follow_up','cancelled') NOT NULL DEFAULT 'scheduled',
  `follow_up_date` date DEFAULT NULL,
  `referred_rhu` tinyint(1) NOT NULL DEFAULT 0,
  `referral_reason` varchar(255) DEFAULT NULL,
  `referral_status` enum('pending','completed') DEFAULT NULL,
  `referral_completed_on` date DEFAULT NULL,
  `referral_outcome` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `archived_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `health_record_medicines`
--

CREATE TABLE `health_record_medicines` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `health_record_id` bigint(20) UNSIGNED NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `unit` varchar(30) DEFAULT NULL,
  `inventory_movement_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `health_vaccines`
--

CREATE TABLE `health_vaccines` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(30) NOT NULL,
  `name` varchar(120) NOT NULL,
  `dose_no` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `due_age_days` smallint(5) UNSIGNED NOT NULL,
  `late_after_days` smallint(5) UNSIGNED NOT NULL DEFAULT 28,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `source_note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `health_worker_puroks`
--

CREATE TABLE `health_worker_puroks` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `purok` varchar(80) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `health_worker_puroks`
--

INSERT INTO `health_worker_puroks` (`user_id`, `purok`, `created_at`) VALUES
(15, '1', '2026-10-03 06:01:25'),
(17, '1', '2026-10-04 10:23:40');

-- --------------------------------------------------------

--
-- Table structure for table `hearing_venues`
--

CREATE TABLE `hearing_venues` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `name_normalized` varchar(150) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `hearing_venues`
--

INSERT INTO `hearing_venues` (`id`, `name`, `name_normalized`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Barangay Hall', 'barangay hall', 1, 2, '2026-09-25 00:40:32', '2026-09-25 00:40:32');

-- --------------------------------------------------------

--
-- Table structure for table `households`
--

CREATE TABLE `households` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `household_no` varchar(50) NOT NULL,
  `household_head_resident_id` bigint(20) UNSIGNED DEFAULT NULL,
  `address` text NOT NULL,
  `house_no` varchar(20) DEFAULT NULL,
  `street` varchar(150) DEFAULT NULL,
  `zone` varchar(40) DEFAULT NULL,
  `purok` varchar(80) NOT NULL,
  `housing_type` varchar(80) DEFAULT NULL,
  `house_ownership` varchar(40) DEFAULT NULL,
  `water_source` varchar(60) DEFAULT NULL,
  `toilet_facility` varchar(40) DEFAULT NULL,
  `has_electricity` tinyint(1) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `household_member_requests`
--

CREATE TABLE `household_member_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `requested_by` bigint(20) UNSIGNED NOT NULL,
  `resident_id` bigint(20) UNSIGNED DEFAULT NULL,
  `relationship_to_requester` varchar(50) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `decided_by` bigint(20) UNSIGNED DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `review_notes` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_borrow_records`
--

CREATE TABLE `inventory_borrow_records` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `borrow_code` varchar(20) NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `borrower_name` varchar(150) NOT NULL,
  `borrower_contact` varchar(30) DEFAULT NULL,
  `borrower_address` varchar(255) NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `date_borrowed` date NOT NULL,
  `expected_return_date` date NOT NULL,
  `actual_return_date` date DEFAULT NULL,
  `returned_quantity` int(10) UNSIGNED DEFAULT NULL,
  `damaged_quantity` int(10) UNSIGNED DEFAULT NULL,
  `missing_quantity` int(10) UNSIGNED DEFAULT NULL,
  `return_condition` enum('good','fair','poor','damaged') DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `return_remarks` text DEFAULT NULL,
  `processed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `returned_to` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `inventory_borrow_records`
--

INSERT INTO `inventory_borrow_records` (`id`, `borrow_code`, `item_id`, `borrower_name`, `borrower_contact`, `borrower_address`, `quantity`, `purpose`, `date_borrowed`, `expected_return_date`, `actual_return_date`, `returned_quantity`, `damaged_quantity`, `missing_quantity`, `return_condition`, `remarks`, `return_remarks`, `processed_by`, `returned_to`, `created_at`, `updated_at`) VALUES
(1, 'BR-2026-0001', 1, 'ella joy', NULL, 'purok 2', 1, 'contest', '2026-09-25', '2026-09-27', NULL, NULL, NULL, NULL, NULL, 'thank you', NULL, 1, NULL, '2026-09-25 12:02:48', '2026-09-25 12:02:48'),
(2, 'BR-2026-0002', 1, 'benny', '09919488709', 'purok 3', 1, 'gusto ko lang', '2026-09-25', '2026-09-28', NULL, NULL, NULL, NULL, NULL, 'hehehe', NULL, 1, NULL, '2026-09-25 12:03:55', '2026-09-25 12:03:55');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_categories`
--

CREATE TABLE `inventory_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `inventory_categories`
--

INSERT INTO `inventory_categories` (`id`, `name`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Furniture', 1, NULL, '2026-09-25 11:46:09', '2026-09-25 11:46:09'),
(2, 'IT Equipment', 1, NULL, '2026-09-25 11:46:09', '2026-09-25 11:46:09'),
(3, 'Equipment', 1, NULL, '2026-09-25 11:46:09', '2026-09-25 11:46:09'),
(4, 'Supplies', 1, NULL, '2026-09-25 11:46:09', '2026-09-25 11:46:09'),
(5, 'Medical', 1, NULL, '2026-09-25 11:46:09', '2026-09-25 11:46:09'),
(6, 'Disaster Equipment', 1, NULL, '2026-09-25 11:46:09', '2026-09-25 11:46:09'),
(7, 'Communication', 1, NULL, '2026-09-25 11:46:09', '2026-09-25 11:46:09'),
(8, 'Vehicle', 1, NULL, '2026-09-25 11:46:09', '2026-09-25 11:46:09');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `item_code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `item_type` enum('equipment','supply') NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `location_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `unit` varchar(30) NOT NULL,
  `status` enum('available','in_use','borrowed','for_repair','low_stock','unserviceable') NOT NULL DEFAULT 'available',
  `item_condition` enum('good','fair','poor','unserviceable') NOT NULL DEFAULT 'good',
  `custodian` varchar(150) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `property_number` varchar(100) DEFAULT NULL,
  `date_acquired` date DEFAULT NULL,
  `unit_cost` decimal(12,2) DEFAULT NULL,
  `source_of_funds` enum('barangay_fund','donation','lgu_grant','other') DEFAULT NULL,
  `reorder_level` int(10) UNSIGNED DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `photo_filename` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `archived_by` bigint(20) UNSIGNED DEFAULT NULL
) ;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`id`, `item_code`, `name`, `description`, `item_type`, `category_id`, `location_id`, `quantity`, `unit`, `status`, `item_condition`, `custodian`, `serial_number`, `property_number`, `date_acquired`, `unit_cost`, `source_of_funds`, `reorder_level`, `expiry_date`, `photo_filename`, `remarks`, `created_by`, `updated_by`, `created_at`, `updated_at`, `archived_at`, `archived_by`) VALUES
(1, 'INV-001', 'MONITOR', 'PARA SA PC', 'equipment', 2, 1, 0, 'pcs', 'borrowed', 'good', 'EWAN', '2335466', NULL, NULL, NULL, 'lgu_grant', NULL, NULL, NULL, NULL, 1, 1, '2026-09-25 12:00:07', '2026-09-25 12:03:55', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `inventory_locations`
--

CREATE TABLE `inventory_locations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `inventory_locations`
--

INSERT INTO `inventory_locations` (`id`, `name`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Barangay Hall', 1, NULL, '2026-09-25 11:46:09', '2026-09-25 11:46:09'),
(2, 'Community Center', 1, NULL, '2026-09-25 11:46:09', '2026-09-25 11:46:09'),
(3, 'Storage Room', 1, NULL, '2026-09-25 11:46:09', '2026-09-25 11:46:09'),
(4, 'Supply Cabinet', 1, NULL, '2026-09-25 11:46:09', '2026-09-25 11:46:09'),
(5, 'Health Center', 1, NULL, '2026-09-25 11:46:09', '2026-09-25 11:46:09'),
(6, 'DRRM Storage', 1, NULL, '2026-09-25 11:46:09', '2026-09-25 11:46:09'),
(7, 'Tanod Outpost', 1, NULL, '2026-09-25 11:46:09', '2026-09-25 11:46:09');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_movements`
--

CREATE TABLE `inventory_movements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `movement_type` enum('added','issued','borrowed','returned','adjusted','repaired','status_changed','archived','restored') NOT NULL,
  `quantity_change` int(11) NOT NULL DEFAULT 0,
  `quantity_after` int(10) UNSIGNED NOT NULL,
  `status_from` varchar(20) DEFAULT NULL,
  `status_to` varchar(20) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `recipient` varchar(150) DEFAULT NULL,
  `borrow_id` bigint(20) UNSIGNED DEFAULT NULL,
  `performed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_movements`
--

INSERT INTO `inventory_movements` (`id`, `item_id`, `movement_type`, `quantity_change`, `quantity_after`, `status_from`, `status_to`, `reason`, `recipient`, `borrow_id`, `performed_by`, `created_at`) VALUES
(1, 1, 'added', 2, 2, NULL, 'in_use', 'New item recorded', NULL, NULL, 1, '2026-09-25 12:00:07'),
(2, 1, 'status_changed', 0, 2, 'in_use', 'borrowed', 'Status changed in the item form', NULL, NULL, 1, '2026-09-25 12:00:30'),
(3, 1, 'borrowed', -1, 1, 'borrowed', 'available', 'BR-2026-0001 — contest', 'ella joy', 1, 1, '2026-09-25 12:02:48'),
(4, 1, 'borrowed', -1, 0, 'available', 'borrowed', 'BR-2026-0002 — gusto ko lang', 'benny', 2, 1, '2026-09-25 12:03:55');

-- --------------------------------------------------------

--
-- Table structure for table `push_subscriptions`
--

CREATE TABLE `push_subscriptions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `endpoint` text NOT NULL,
  `endpoint_hash` char(64) NOT NULL,
  `subscription_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`subscription_json`)),
  `user_agent` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_used_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `registration_applications`
--

CREATE TABLE `registration_applications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `application_type` enum('personnel','resident') NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `middle_name` varchar(80) DEFAULT NULL,
  `last_name` varchar(80) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `sex` enum('male','female','other','unspecified') NOT NULL DEFAULT 'unspecified',
  `civil_status` enum('single','married','widowed','separated','other','unspecified') NOT NULL DEFAULT 'unspecified',
  `contact_number` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `purok` varchar(80) DEFAULT NULL,
  `household_no` varchar(50) DEFAULT NULL,
  `household_role` enum('head','member','unsure') DEFAULT NULL,
  `household_head_name` varchar(150) DEFAULT NULL,
  `household_relationship` varchar(50) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `resident_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('submitted','verified','awaiting_final_approval','approved','rejected','cancelled') NOT NULL DEFAULT 'submitted',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified_by` bigint(20) UNSIGNED DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `decided_by` bigint(20) UNSIGNED DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `review_notes` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `registration_applications`
--

INSERT INTO `registration_applications` (`id`, `application_type`, `first_name`, `middle_name`, `last_name`, `suffix`, `email`, `birth_date`, `sex`, `civil_status`, `contact_number`, `address`, `purok`, `household_no`, `household_role`, `household_head_name`, `household_relationship`, `user_id`, `resident_id`, `status`, `submitted_at`, `verified_by`, `verified_at`, `decided_by`, `decided_at`, `review_notes`) VALUES
(1, 'resident', 'Ryco', 'Pacelo', 'Gammad', NULL, 'vicenteellajoy37@gmail.com', '2004-06-22', 'male', 'single', '09919488709', 'Purok 3, 1, Balimbin Street, Purok 1, San Jose, Quirino, Isabela', '1', NULL, NULL, NULL, NULL, NULL, NULL, 'approved', '2026-10-02 04:19:28', NULL, NULL, 1, '2026-10-02 22:40:17', NULL),
(2, 'resident', 'Jayson', 'Torres', 'Vicente', NULL, 'vicentejayson@gmail.com', '1976-10-22', 'male', 'married', '09919488708', 'Centro, Purok 1, San Jose, Quirino, Isabela', '1', NULL, 'head', NULL, NULL, NULL, NULL, 'approved', '2026-10-02 15:56:08', NULL, NULL, 1, '2026-10-03 00:04:44', NULL),
(3, 'resident', 'Marrieta', 'gammad', 'Vicente', NULL, 'vicentemarrieta@gmail.com', '1976-10-18', 'female', 'married', '09919488707', 'Centro, Purok 1, San Jose, Quirino, Isabela', '1', 'P1-0002', 'member', 'Jayson Torres Vicente', 'Spouse', NULL, NULL, 'approved', '2026-10-02 16:13:26', NULL, NULL, 1, '2026-10-03 00:13:57', NULL),
(4, 'resident', 'Jhon Lenard', 'gammad', 'Vicente', NULL, 'vicentelenard@gmail.com', '2002-10-11', 'male', 'single', '09919488706', 'Centro, Purok 1, San Jose, Quirino, Isabela', '1', 'P1-0002', 'member', 'Jayson Torres Vicente', 'Son', NULL, NULL, 'approved', '2026-10-02 16:19:40', NULL, NULL, 1, '2026-10-03 00:20:28', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `registration_approvals`
--

CREATE TABLE `registration_approvals` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `application_id` bigint(20) UNSIGNED NOT NULL,
  `action` enum('verified','approved','rejected','cancelled') NOT NULL,
  `from_status` enum('submitted','verified','awaiting_final_approval','approved','rejected','cancelled') DEFAULT NULL,
  `to_status` enum('submitted','verified','awaiting_final_approval','approved','rejected','cancelled') NOT NULL,
  `actor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `registration_approvals`
--

INSERT INTO `registration_approvals` (`id`, `application_id`, `action`, `from_status`, `to_status`, `actor_id`, `notes`, `created_at`) VALUES
(1, 1, 'approved', 'submitted', 'approved', 1, NULL, '2026-10-02 14:40:17'),
(2, 2, 'approved', 'submitted', 'approved', 1, NULL, '2026-10-02 16:04:44'),
(3, 3, 'approved', 'submitted', 'approved', 1, NULL, '2026-10-02 16:13:57'),
(4, 4, 'approved', 'submitted', 'approved', 1, NULL, '2026-10-02 16:20:28');

-- --------------------------------------------------------

--
-- Table structure for table `residents`
--

CREATE TABLE `residents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `household_no` varchar(50) DEFAULT NULL,
  `first_name` varchar(80) NOT NULL,
  `middle_name` varchar(80) DEFAULT NULL,
  `last_name` varchar(80) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `sex` enum('male','female','other') DEFAULT NULL,
  `civil_status` enum('single','married','widowed','separated','other') DEFAULT NULL,
  `is_pwd` tinyint(1) NOT NULL DEFAULT 0,
  `is_solo_parent` tinyint(1) NOT NULL DEFAULT 0,
  `contact_number` varchar(30) DEFAULT NULL,
  `address` text NOT NULL,
  `purok` varchar(80) NOT NULL,
  `residency_start_year` smallint(5) UNSIGNED DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `status` enum('active','moved','deceased','inactive','pending') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `residents`
--

INSERT INTO `residents` (`id`, `user_id`, `household_no`, `first_name`, `middle_name`, `last_name`, `suffix`, `birth_date`, `sex`, `civil_status`, `is_pwd`, `is_solo_parent`, `contact_number`, `address`, `purok`, `residency_start_year`, `photo_path`, `status`, `created_at`, `updated_at`) VALUES
(14, 17, NULL, 'Dine Dine', 'Caronan', 'Gammad', NULL, '1986-08-13', 'female', 'single', 0, 0, '09925753521', 'Balimbin Street', '1', NULL, NULL, 'active', '2026-10-04 10:23:40', '2026-10-04 10:23:40');

-- --------------------------------------------------------

--
-- Table structure for table `resident_households`
--

CREATE TABLE `resident_households` (
  `resident_id` bigint(20) UNSIGNED NOT NULL,
  `household_id` bigint(20) UNSIGNED NOT NULL,
  `relationship_to_head` varchar(60) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 1,
  `joined_at` date DEFAULT NULL,
  `left_at` date DEFAULT NULL,
  `current_primary_resident_id` bigint(20) UNSIGNED GENERATED ALWAYS AS (case when `is_primary` = 1 and `left_at` is null then `resident_id` else NULL end) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_otp_requests`
--

CREATE TABLE `sms_otp_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `mobile` char(12) NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `request_token` char(64) NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `requested_ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sms_otp_requests`
--

INSERT INTO `sms_otp_requests` (`id`, `mobile`, `code_hash`, `request_token`, `attempts`, `expires_at`, `verified_at`, `used_at`, `requested_ip`, `created_at`) VALUES
(1, '639919488709', '$2y$10$fOIdWwTgNsTB4bGL7PBPU.uK41Kq6yGNjTE1rhjGKQBZuPXvh/8Ci', 'a144026a2ae97b700619f31cc5a5971eb2d313b2a0ce73f0e49fb846d8d77251', 0, '2026-10-02 11:11:56', '2026-10-02 11:07:07', '2026-10-02 11:07:09', '::1', '2026-10-02 03:06:56'),
(2, '639919488709', '$2y$10$JxKSG8z2jGzgsN5R3X5cuOYIyO/1nfMDwniWa0QMxarNoubAzzS7q', '4e37d288038291434859f5077986352d950ead09f760241d70292ffa1902ff36', 0, '2026-10-02 12:23:51', '2026-10-02 12:19:09', '2026-10-02 12:19:28', '::1', '2026-10-02 04:18:51'),
(3, '639919488708', '$2y$10$nvHWQB15JKtp.hztC1PUYuZOgSkjNwa5rlQ./tWekDvw8Gosjm6eG', 'e1e5320d3c59caacdc7a2d64cadd2826628477deb039e25207e37aa06b987dd4', 0, '2026-10-03 00:00:26', '2026-10-02 23:55:37', '2026-10-02 23:56:08', '::1', '2026-10-02 15:55:26'),
(4, '639919488707', '$2y$10$EKle5YVrQqUEBk8M6kWjT.zspsDNdsoEIOmRcmaYsi26xivwLhBnC', 'c1c798a6207280f4ce7d25019b60c269a691c86cdfa0540343559b726a9881b6', 0, '2026-10-03 00:17:41', '2026-10-03 00:12:49', '2026-10-03 00:13:26', '::1', '2026-10-02 16:12:41'),
(5, '639919488706', '$2y$10$J5TYa4oqwGVZ./nu9miZ9.8UpQvxUdyzb35IePltR95CypNt8OH0e', 'a485b6c7c5e675a1fe947db4d92951b1a493318207988a969ae5c8209e1b8cdc', 0, '2026-10-03 00:24:03', '2026-10-03 00:19:14', '2026-10-03 00:19:41', '::1', '2026-10-02 16:19:03');

-- --------------------------------------------------------

--
-- Table structure for table `sms_subscribers`
--

CREATE TABLE `sms_subscribers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `purok` varchar(80) NOT NULL,
  `street` varchar(150) DEFAULT NULL,
  `mobile` char(12) NOT NULL,
  `consent_text` varchar(500) NOT NULL,
  `consent_at` datetime NOT NULL,
  `verified_at` datetime NOT NULL,
  `status` enum('active','unsubscribed') NOT NULL DEFAULT 'active',
  `registered_ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `role` enum('super_admin','secretary','treasurer','health_worker','official','resident','punong_barangay') NOT NULL,
  `status` enum('pending','active','suspended') NOT NULL DEFAULT 'pending',
  `resident_id` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `username`, `password_hash`, `must_change_password`, `role`, `status`, `resident_id`, `approved_by`, `approved_at`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'Benny Balimbin', 'bj123@gmail.com', NULL, '$2y$10$Uj8LkgooRE6uojC.wRuba.AD9A0gRIlQtq15qhPrLtNfmbE5.3JES', 0, 'super_admin', 'active', NULL, NULL, NULL, '2026-10-04 19:17:13', '2026-09-22 03:39:16', '2026-10-04 11:17:13'),
(2, 'Jamil R. Tabuyo', 'secretary@san-jose.local', NULL, '$2y$10$rQo/evWHIUSdk4xzTMZE1.N4PH7MwWyfUqOczpCZarb21rdwoYfWG', 0, 'secretary', 'active', NULL, NULL, '2026-09-24 13:52:39', '2026-10-03 01:02:15', '2026-09-24 05:52:39', '2026-10-02 17:02:15'),
(3, 'Test Treasurer', 'treasurer@san-jose.local', NULL, '$2y$10$8CnykgYi.W1pG6ei/gH8bOwcas8cy1bYlikmvdDHiFS9u2n5ud9k.', 0, 'treasurer', 'active', NULL, NULL, '2026-09-24 13:52:39', '2026-10-04 18:04:59', '2026-09-24 05:52:39', '2026-10-04 10:04:59'),
(4, 'Test Kagawad', 'kagawad@san-jose.local', NULL, '$2y$10$ObwcO/nIoGXY6Bumx6arzuNNVzHbMuWr6jQYPt4JMjAoQDNa5wM/G', 0, 'official', 'active', NULL, NULL, '2026-09-24 13:52:39', NULL, '2026-09-24 05:52:39', '2026-09-24 05:52:39'),
(5, 'Test Health Worker', 'healthworker@san-jose.local', NULL, '$2y$10$T2dm28NYC46ToHk8IQUtDukCCdC23j2i1DEMcA994fIfdIVH2Q2FW', 0, 'health_worker', 'active', NULL, NULL, '2026-09-24 13:52:39', NULL, '2026-09-24 05:52:39', '2026-09-24 05:52:39'),
(15, 'Rynel May Gammad', 'rynel@gmail.com', NULL, '$2y$10$8sxVu1M8B103fl7PpXejSuabvSVXYarQMilGRvqo2vh9S.5wcWu12', 0, 'health_worker', 'active', NULL, 1, '2026-10-03 14:01:25', '2026-10-04 17:34:19', '2026-10-03 06:01:25', '2026-10-04 09:34:19'),
(16, 'Manjonel Gammad', 'manjonel@gmail.com', NULL, '$2y$10$UxOn8mN81ADLEUzJqm1lm.WGIeVEC98h9tQrzbU2fOFfzKBBGO.K.', 0, 'official', 'active', NULL, 1, '2026-10-04 18:13:48', '2026-10-04 18:14:02', '2026-10-04 10:13:48', '2026-10-04 10:14:02'),
(17, 'Dine Dine Caronan Gammad', 'dinedine@gmail.com', NULL, '$2y$10$UDZRz4TvawjRR29JG1HszONVm0zRciUMFEFy2JlJKlMp03Dq1Cltq', 0, 'health_worker', 'active', 14, 1, '2026-10-04 18:23:40', '2026-10-04 18:27:57', '2026-10-04 10:23:40', '2026-10-04 10:27:57');

-- --------------------------------------------------------

--
-- Table structure for table `user_notifications`
--

CREATE TABLE `user_notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `recipient_user_id` bigint(20) UNSIGNED NOT NULL,
  `category` varchar(40) NOT NULL,
  `entity_type` varchar(40) NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` varchar(500) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_notifications`
--

INSERT INTO `user_notifications` (`id`, `recipient_user_id`, `category`, `entity_type`, `entity_id`, `title`, `message`, `created_at`, `read_at`) VALUES
(1, 1, 'complaint_submitted', 'complaint', 1, 'New complaint received', 'Complaint CMP-2026-436C1A was submitted and is waiting for review.', '2026-09-25 00:22:29', NULL),
(2, 2, 'complaint_submitted', 'complaint', 1, 'New complaint received', 'Complaint CMP-2026-436C1A was submitted and is waiting for review.', '2026-09-25 00:22:29', NULL),
(4, 1, 'disaster_alert', 'disaster_alert', 1, 'Red Warning: pardas', 'Areas: All Puroks. jkg6yjikolp;[poiytrfwssdrftgyjklop;lokijygtfrdswrtjkiolp;', '2026-09-30 06:06:26', NULL),
(5, 2, 'disaster_alert', 'disaster_alert', 1, 'Red Warning: pardas', 'Areas: All Puroks. jkg6yjikolp;[poiytrfwssdrftgyjklop;lokijygtfrdswrtjkiolp;', '2026-09-30 06:06:26', NULL),
(6, 3, 'disaster_alert', 'disaster_alert', 1, 'Red Warning: pardas', 'Areas: All Puroks. jkg6yjikolp;[poiytrfwssdrftgyjklop;lokijygtfrdswrtjkiolp;', '2026-09-30 06:06:26', NULL),
(7, 4, 'disaster_alert', 'disaster_alert', 1, 'Red Warning: pardas', 'Areas: All Puroks. jkg6yjikolp;[poiytrfwssdrftgyjklop;lokijygtfrdswrtjkiolp;', '2026-09-30 06:06:26', NULL),
(8, 5, 'disaster_alert', 'disaster_alert', 1, 'Red Warning: pardas', 'Areas: All Puroks. jkg6yjikolp;[poiytrfwssdrftgyjklop;lokijygtfrdswrtjkiolp;', '2026-09-30 06:06:26', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_announcement_author` (`author_id`),
  ADD KEY `idx_announcement_status` (`status`);

--
-- Indexes for table `announcement_history`
--
ALTER TABLE `announcement_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_announcement_history_announcement` (`announcement_id`,`created_at`),
  ADD KEY `idx_announcement_history_actor` (`actor_id`);

--
-- Indexes for table `announcement_notifications`
--
ALTER TABLE `announcement_notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_announcement_notification_event` (`recipient_user_id`,`announcement_id`,`notification_type`),
  ADD KEY `idx_announcement_notifications_recipient_read` (`recipient_user_id`,`read_at`,`created_at`),
  ADD KEY `idx_announcement_notifications_announcement` (`announcement_id`,`created_at`);

--
-- Indexes for table `assistance_distributions`
--
ALTER TABLE `assistance_distributions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_assistance_reference` (`reference_no`),
  ADD UNIQUE KEY `uq_assistance_sequence` (`ref_year`,`ref_seq`),
  ADD KEY `idx_assistance_resident` (`resident_id`,`archived_at`),
  ADD KEY `idx_assistance_date` (`given_on`),
  ADD KEY `idx_assistance_type` (`assistance_type`),
  ADD KEY `fk_assistance_created_by` (`created_by`),
  ADD KEY `fk_assistance_archived_by` (`archived_by`),
  ADD KEY `idx_assistance_household` (`household_id`,`archived_at`),
  ADD KEY `idx_assistance_incident` (`incident_id`,`status`),
  ADD KEY `idx_assistance_status` (`status`,`given_on`),
  ADD KEY `idx_assistance_finance` (`finance_transaction_id`),
  ADD KEY `fk_assistance_updated_by` (`updated_by`);

--
-- Indexes for table `assistance_items`
--
ALTER TABLE `assistance_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_assistance_items` (`distribution_id`,`item_id`),
  ADD KEY `idx_assistance_items_item` (`item_id`),
  ADD KEY `idx_assistance_items_movement` (`inventory_movement_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_audit_user` (`user_id`),
  ADD KEY `idx_audit_created` (`created_at`),
  ADD KEY `idx_audit_entity` (`entity_type`,`entity_id`);

--
-- Indexes for table `barangay_personnel`
--
ALTER TABLE `barangay_personnel`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_barangay_personnel_user` (`user_id`),
  ADD KEY `idx_barangay_personnel_status_name` (`status`,`full_name`),
  ADD KEY `fk_barangay_personnel_created_by` (`created_by`);

--
-- Indexes for table `barangay_projects`
--
ALTER TABLE `barangay_projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_code` (`project_code`),
  ADD KEY `fk_projects_created_by` (`created_by`),
  ADD KEY `fk_projects_updated_by` (`updated_by`),
  ADD KEY `idx_projects_status` (`status`),
  ADD KEY `idx_projects_dates` (`start_date`,`target_end_date`);

--
-- Indexes for table `blotter_entries`
--
ALTER TABLE `blotter_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_blotter_number` (`blotter_number`),
  ADD KEY `idx_blotter_complaint` (`complaint_id`),
  ADD KEY `idx_blotter_status_recorded` (`status`,`recorded_at`),
  ADD KEY `idx_blotter_type_incident` (`incident_type`,`incident_at`),
  ADD KEY `idx_blotter_recorded` (`recorded_at`),
  ADD KEY `fk_blotter_recorded_by` (`recorded_by`),
  ADD KEY `fk_blotter_started_by` (`processing_started_by`),
  ADD KEY `fk_blotter_resolved_by` (`resolved_by`),
  ADD KEY `fk_blotter_closed_by` (`closed_by`);

--
-- Indexes for table `case_attachments`
--
ALTER TABLE `case_attachments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_case_attachments_stored` (`stored_filename`),
  ADD KEY `idx_case_attachments_complaint` (`complaint_id`,`removed_at`),
  ADD KEY `idx_case_attachments_blotter` (`blotter_id`,`removed_at`),
  ADD KEY `idx_case_attachments_checksum` (`sha256_checksum`),
  ADD KEY `fk_case_attachments_uploaded_by` (`uploaded_by`),
  ADD KEY `fk_case_attachments_removed_by` (`removed_by`);

--
-- Indexes for table `case_attachment_access_log`
--
ALTER TABLE `case_attachment_access_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attachment_access_attachment` (`attachment_id`,`accessed_at`),
  ADD KEY `idx_attachment_access_user` (`user_id`,`accessed_at`);

--
-- Indexes for table `case_hearings`
--
ALTER TABLE `case_hearings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_hearing_number` (`hearing_number`),
  ADD KEY `idx_hearings_venue_time` (`venue_id`,`starts_at`,`ends_at`),
  ADD KEY `idx_hearings_status_time` (`status`,`starts_at`),
  ADD KEY `idx_hearings_complaint` (`complaint_id`,`starts_at`),
  ADD KEY `idx_hearings_blotter` (`blotter_id`,`starts_at`),
  ADD KEY `fk_hearings_cancelled_by` (`cancelled_by`),
  ADD KEY `fk_hearings_outcome_by` (`outcome_recorded_by`),
  ADD KEY `fk_hearings_created_by` (`created_by`),
  ADD KEY `fk_hearings_updated_by` (`updated_by`);

--
-- Indexes for table `case_hearing_participants`
--
ALTER TABLE `case_hearing_participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_hearing_participant_person` (`hearing_id`,`case_person_id`),
  ADD KEY `idx_hearing_participants_attendance` (`hearing_id`,`attendance_status`),
  ADD KEY `idx_hearing_participants_person` (`case_person_id`),
  ADD KEY `idx_hearing_participants_resident` (`resident_id`),
  ADD KEY `fk_hearing_participants_recorded_by` (`attendance_recorded_by`),
  ADD KEY `fk_hearing_participants_created_by` (`created_by`);

--
-- Indexes for table `case_hearing_personnel`
--
ALTER TABLE `case_hearing_personnel`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_hearing_personnel_active` (`hearing_id`,`personnel_id`,`active_key`),
  ADD KEY `idx_hearing_personnel_hearing` (`hearing_id`),
  ADD KEY `idx_hearing_personnel_conflict` (`personnel_id`,`removed_at`,`hearing_id`),
  ADD KEY `fk_hearing_personnel_assigned_by` (`assigned_by`),
  ADD KEY `fk_hearing_personnel_removed_by` (`removed_by`),
  ADD KEY `fk_hearing_personnel_replaced_by` (`replaced_by_assignment_id`);

--
-- Indexes for table `case_hearing_schedule_history`
--
ALTER TABLE `case_hearing_schedule_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_schedule_history_hearing` (`hearing_id`,`changed_at`),
  ADD KEY `fk_schedule_history_prev_venue` (`previous_venue_id`),
  ADD KEY `fk_schedule_history_new_venue` (`new_venue_id`),
  ADD KEY `fk_schedule_history_changed_by` (`changed_by`);

--
-- Indexes for table `case_persons`
--
ALTER TABLE `case_persons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_case_persons_complaint` (`complaint_id`,`person_role`),
  ADD KEY `idx_case_persons_blotter` (`blotter_id`,`person_role`),
  ADD KEY `idx_case_persons_resident` (`resident_id`),
  ADD KEY `fk_case_persons_created_by` (`created_by`);

--
-- Indexes for table `case_status_history`
--
ALTER TABLE `case_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_case_history_complaint` (`complaint_id`,`acted_at`),
  ADD KEY `idx_case_history_blotter` (`blotter_id`,`acted_at`),
  ADD KEY `idx_case_history_actor` (`actor_user_id`,`acted_at`);

--
-- Indexes for table `complaint_cases`
--
ALTER TABLE `complaint_cases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `case_number` (`case_number`),
  ADD KEY `fk_complaints_created_by` (`created_by`),
  ADD KEY `fk_complaints_updated_by` (`updated_by`),
  ADD KEY `idx_complaints_status_filed` (`status`,`filed_at`),
  ADD KEY `idx_complaints_complainant` (`complainant_resident_id`),
  ADD KEY `idx_complaints_respondent` (`respondent_resident_id`),
  ADD KEY `idx_complaints_category_filed` (`category`,`filed_at`),
  ADD KEY `idx_complaints_source_filed` (`submission_source`,`filed_at`),
  ADD KEY `idx_complaints_submitted_by` (`submitted_by_user_id`,`filed_at`),
  ADD KEY `idx_complaints_filed` (`filed_at`),
  ADD KEY `fk_complaints_reviewed_by` (`reviewed_by`),
  ADD KEY `fk_complaints_resolved_by` (`resolved_by`),
  ADD KEY `fk_complaints_closed_by` (`closed_by`);

--
-- Indexes for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_code` (`reference_code`),
  ADD KEY `fk_document_resident` (`resident_id`),
  ADD KEY `fk_document_approver` (`approved_by`),
  ADD KEY `idx_document_status` (`status`);

--
-- Indexes for table `drr_alerts`
--
ALTER TABLE `drr_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_drr_alerts_active` (`archived_at`,`lifted_at`,`created_at`),
  ADD KEY `idx_drr_alerts_incident` (`incident_id`),
  ADD KEY `fk_drr_alerts_lifted_by` (`lifted_by`),
  ADD KEY `fk_drr_alerts_created_by` (`created_by`),
  ADD KEY `fk_drr_alerts_archived_by` (`archived_by`);

--
-- Indexes for table `drr_alert_areas`
--
ALTER TABLE `drr_alert_areas`
  ADD PRIMARY KEY (`alert_id`,`area_id`),
  ADD KEY `idx_drr_alert_areas_area` (`area_id`);

--
-- Indexes for table `drr_areas`
--
ALTER TABLE `drr_areas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_drr_areas_name` (`name`),
  ADD KEY `idx_drr_areas_active` (`is_active`,`sort_order`),
  ADD KEY `fk_drr_areas_created_by` (`created_by`);

--
-- Indexes for table `drr_contacts`
--
ALTER TABLE `drr_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_drr_contacts_type` (`contact_type`,`archived_at`),
  ADD KEY `fk_drr_contacts_created_by` (`created_by`),
  ADD KEY `fk_drr_contacts_updated_by` (`updated_by`),
  ADD KEY `fk_drr_contacts_archived_by` (`archived_by`);

--
-- Indexes for table `drr_damage_assessments`
--
ALTER TABLE `drr_damage_assessments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_drr_damage_incident` (`incident_id`,`archived_at`),
  ADD KEY `idx_drr_damage_area` (`area_id`),
  ADD KEY `fk_drr_damage_created_by` (`created_by`),
  ADD KEY `fk_drr_damage_updated_by` (`updated_by`),
  ADD KEY `fk_drr_damage_archived_by` (`archived_by`);

--
-- Indexes for table `drr_evacuations`
--
ALTER TABLE `drr_evacuations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_drr_evacuations_open_family` (`open_family_key`),
  ADD KEY `idx_drr_evacuations_incident` (`incident_id`),
  ADD KEY `idx_drr_evacuations_center` (`center_id`,`departed_at`,`archived_at`),
  ADD KEY `idx_drr_evacuations_household` (`household_id`),
  ADD KEY `idx_drr_evacuations_resident` (`resident_id`),
  ADD KEY `fk_drr_evacuations_created_by` (`created_by`),
  ADD KEY `fk_drr_evacuations_updated_by` (`updated_by`),
  ADD KEY `fk_drr_evacuations_archived_by` (`archived_by`);

--
-- Indexes for table `drr_evacuation_centers`
--
ALTER TABLE `drr_evacuation_centers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_drr_centers_name` (`name`),
  ADD KEY `idx_drr_centers_area` (`area_id`),
  ADD KEY `idx_drr_centers_status` (`status`,`archived_at`),
  ADD KEY `fk_drr_centers_created_by` (`created_by`),
  ADD KEY `fk_drr_centers_updated_by` (`updated_by`),
  ADD KEY `fk_drr_centers_archived_by` (`archived_by`);

--
-- Indexes for table `drr_hazard_areas`
--
ALTER TABLE `drr_hazard_areas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_drr_hazards_area` (`area_id`),
  ADD KEY `idx_drr_hazards_type` (`hazard_type`,`risk_level`),
  ADD KEY `idx_drr_hazards_archived` (`archived_at`),
  ADD KEY `fk_drr_hazards_created_by` (`created_by`),
  ADD KEY `fk_drr_hazards_updated_by` (`updated_by`),
  ADD KEY `fk_drr_hazards_archived_by` (`archived_by`);

--
-- Indexes for table `drr_records`
--
ALTER TABLE `drr_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_drr_records_reference` (`reference_no`),
  ADD UNIQUE KEY `uq_drr_records_year_seq` (`ref_year`,`ref_seq`),
  ADD KEY `idx_drr_records_type` (`record_type`),
  ADD KEY `idx_drr_records_status` (`status`),
  ADD KEY `idx_drr_records_area` (`area_id`),
  ADD KEY `idx_drr_records_date` (`record_date`),
  ADD KEY `idx_drr_records_archived` (`archived_at`),
  ADD KEY `fk_drr_records_created_by` (`created_by`),
  ADD KEY `fk_drr_records_updated_by` (`updated_by`),
  ADD KEY `fk_drr_records_archived_by` (`archived_by`);

--
-- Indexes for table `drr_relief_distributions`
--
ALTER TABLE `drr_relief_distributions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_drr_relief_reference` (`reference_no`),
  ADD KEY `idx_drr_relief_incident` (`incident_id`,`archived_at`),
  ADD KEY `idx_drr_relief_household` (`household_id`),
  ADD KEY `idx_drr_relief_resident` (`resident_id`),
  ADD KEY `idx_drr_relief_date` (`distributed_on`),
  ADD KEY `fk_drr_relief_created_by` (`created_by`),
  ADD KEY `fk_drr_relief_archived_by` (`archived_by`);

--
-- Indexes for table `drr_relief_items`
--
ALTER TABLE `drr_relief_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_drr_relief_items` (`distribution_id`,`item_id`),
  ADD KEY `idx_drr_relief_items_item` (`item_id`);

--
-- Indexes for table `finance_attachments`
--
ALTER TABLE `finance_attachments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_finance_attachments_stored` (`stored_name`),
  ADD KEY `idx_finance_attachments_transaction` (`transaction_id`,`archived_at`),
  ADD KEY `fk_finance_attachments_uploaded_by` (`uploaded_by`),
  ADD KEY `fk_finance_attachments_archived_by` (`archived_by`);

--
-- Indexes for table `finance_budgets`
--
ALTER TABLE `finance_budgets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_finance_budgets_year_category` (`budget_year`,`category_id`),
  ADD KEY `idx_finance_budgets_category` (`category_id`),
  ADD KEY `fk_finance_budgets_created_by` (`created_by`),
  ADD KEY `fk_finance_budgets_updated_by` (`updated_by`);

--
-- Indexes for table `finance_categories`
--
ALTER TABLE `finance_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_finance_categories_name` (`name`),
  ADD KEY `idx_finance_categories_type` (`type`,`is_active`),
  ADD KEY `fk_finance_categories_created_by` (`created_by`);

--
-- Indexes for table `finance_opening_balances`
--
ALTER TABLE `finance_opening_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_finance_opening_current` (`current_flag`),
  ADD KEY `fk_finance_opening_created_by` (`created_by`),
  ADD KEY `fk_finance_opening_cancelled_by` (`cancelled_by`);

--
-- Indexes for table `finance_transactions`
--
ALTER TABLE `finance_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_finance_transactions_reference` (`reference_no`),
  ADD UNIQUE KEY `uq_finance_transactions_sequence` (`ref_kind`,`ref_year`,`ref_seq`),
  ADD KEY `idx_finance_transactions_type_status` (`type`,`status`),
  ADD KEY `idx_finance_transactions_date` (`transaction_date`),
  ADD KEY `idx_finance_transactions_release` (`release_date`),
  ADD KEY `idx_finance_transactions_category` (`category_id`),
  ADD KEY `idx_finance_transactions_resident` (`resident_id`),
  ADD KEY `fk_finance_transactions_created_by` (`created_by`),
  ADD KEY `fk_finance_transactions_updated_by` (`updated_by`),
  ADD KEY `fk_finance_transactions_approved_by` (`approved_by`),
  ADD KEY `fk_finance_transactions_rejected_by` (`rejected_by`),
  ADD KEY `fk_finance_transactions_released_by` (`released_by`),
  ADD KEY `fk_finance_transactions_cancelled_by` (`cancelled_by`);

--
-- Indexes for table `health_chronic_cases`
--
ALTER TABLE `health_chronic_cases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hcc_resident` (`resident_id`,`condition_type`),
  ADD KEY `idx_hcc_status` (`status`),
  ADD KEY `fk_hcc_created_by` (`created_by`),
  ADD KEY `fk_hcc_updated_by` (`updated_by`),
  ADD KEY `fk_hcc_archived_by` (`archived_by`);

--
-- Indexes for table `health_conditions`
--
ALTER TABLE `health_conditions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_health_conditions_name` (`name`),
  ADD KEY `fk_health_conditions_creator` (`created_by`);

--
-- Indexes for table `health_growth_reference`
--
ALTER TABLE `health_growth_reference`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_hgr` (`indicator`,`sex`,`x_value`);

--
-- Indexes for table `health_immunizations`
--
ALTER TABLE `health_immunizations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hi_resident` (`resident_id`,`vaccine_id`),
  ADD KEY `idx_hi_date` (`date_given`),
  ADD KEY `fk_hi_vaccine` (`vaccine_id`),
  ADD KEY `fk_hi_created_by` (`created_by`),
  ADD KEY `fk_hi_archived_by` (`archived_by`);

--
-- Indexes for table `health_nutrition`
--
ALTER TABLE `health_nutrition`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hn_resident` (`resident_id`,`weigh_date`),
  ADD KEY `idx_hn_date` (`weigh_date`),
  ADD KEY `fk_hn_created_by` (`created_by`),
  ADD KEY `fk_hn_archived_by` (`archived_by`);

--
-- Indexes for table `health_pregnancies`
--
ALTER TABLE `health_pregnancies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hp_resident` (`resident_id`),
  ADD KEY `idx_hp_status_edd` (`status`,`expected_delivery_date`),
  ADD KEY `fk_hp_created_by` (`created_by`),
  ADD KEY `fk_hp_updated_by` (`updated_by`),
  ADD KEY `fk_hp_archived_by` (`archived_by`);

--
-- Indexes for table `health_records`
--
ALTER TABLE `health_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_health_records_no` (`record_no`),
  ADD KEY `idx_health_records_resident` (`resident_id`),
  ADD KEY `idx_health_records_date` (`service_date`),
  ADD KEY `idx_health_records_follow_up` (`status`,`follow_up_date`),
  ADD KEY `idx_health_records_archived` (`archived_at`),
  ADD KEY `fk_health_records_created_by` (`created_by`),
  ADD KEY `fk_health_records_updated_by` (`updated_by`),
  ADD KEY `fk_health_records_archived_by` (`archived_by`),
  ADD KEY `idx_health_records_condition` (`condition_id`,`service_date`),
  ADD KEY `idx_health_records_referral` (`referred_rhu`,`referral_status`);

--
-- Indexes for table `health_record_medicines`
--
ALTER TABLE `health_record_medicines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hrm_record` (`health_record_id`),
  ADD KEY `idx_hrm_item` (`item_id`),
  ADD KEY `fk_hrm_movement` (`inventory_movement_id`);

--
-- Indexes for table `health_vaccines`
--
ALTER TABLE `health_vaccines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_health_vaccines_code_dose` (`code`,`dose_no`);

--
-- Indexes for table `health_worker_puroks`
--
ALTER TABLE `health_worker_puroks`
  ADD PRIMARY KEY (`user_id`,`purok`);

--
-- Indexes for table `hearing_venues`
--
ALTER TABLE `hearing_venues`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_hearing_venues_name` (`name_normalized`),
  ADD KEY `fk_hearing_venues_created_by` (`created_by`);

--
-- Indexes for table `households`
--
ALTER TABLE `households`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `household_no` (`household_no`),
  ADD KEY `idx_households_purok` (`purok`),
  ADD KEY `idx_households_head` (`household_head_resident_id`);

--
-- Indexes for table `household_member_requests`
--
ALTER TABLE `household_member_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hmr_requester` (`requested_by`,`status`),
  ADD KEY `idx_hmr_status` (`status`,`created_at`),
  ADD KEY `fk_hmr_resident` (`resident_id`),
  ADD KEY `fk_hmr_decider` (`decided_by`);

--
-- Indexes for table `inventory_borrow_records`
--
ALTER TABLE `inventory_borrow_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inventory_borrow_code` (`borrow_code`),
  ADD KEY `idx_inventory_borrow_item` (`item_id`),
  ADD KEY `idx_inventory_borrow_open` (`actual_return_date`,`expected_return_date`),
  ADD KEY `fk_inventory_borrow_processed_by` (`processed_by`),
  ADD KEY `fk_inventory_borrow_returned_to` (`returned_to`);

--
-- Indexes for table `inventory_categories`
--
ALTER TABLE `inventory_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inventory_categories_name` (`name`),
  ADD KEY `fk_inventory_categories_created_by` (`created_by`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inventory_items_code` (`item_code`),
  ADD KEY `idx_inventory_items_type` (`item_type`),
  ADD KEY `idx_inventory_items_status` (`status`),
  ADD KEY `idx_inventory_items_archived` (`archived_at`),
  ADD KEY `idx_inventory_items_name` (`name`),
  ADD KEY `fk_inventory_items_category` (`category_id`),
  ADD KEY `fk_inventory_items_location` (`location_id`),
  ADD KEY `fk_inventory_items_created_by` (`created_by`),
  ADD KEY `fk_inventory_items_updated_by` (`updated_by`),
  ADD KEY `fk_inventory_items_archived_by` (`archived_by`);

--
-- Indexes for table `inventory_locations`
--
ALTER TABLE `inventory_locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inventory_locations_name` (`name`),
  ADD KEY `fk_inventory_locations_created_by` (`created_by`);

--
-- Indexes for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory_movements_item` (`item_id`,`created_at`),
  ADD KEY `idx_inventory_movements_type` (`movement_type`),
  ADD KEY `fk_inventory_movements_borrow` (`borrow_id`),
  ADD KEY `fk_inventory_movements_user` (`performed_by`);

--
-- Indexes for table `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `endpoint_hash` (`endpoint_hash`),
  ADD KEY `idx_push_user_active` (`user_id`,`is_active`);

--
-- Indexes for table `registration_applications`
--
ALTER TABLE `registration_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_registration_application_verifier` (`verified_by`),
  ADD KEY `fk_registration_application_decider` (`decided_by`),
  ADD KEY `idx_registration_application_queue` (`application_type`,`status`,`submitted_at`),
  ADD KEY `idx_registration_application_user` (`user_id`),
  ADD KEY `idx_registration_application_resident` (`resident_id`),
  ADD KEY `idx_registration_application_email` (`email`);

--
-- Indexes for table `registration_approvals`
--
ALTER TABLE `registration_approvals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_registration_approval_application` (`application_id`,`created_at`),
  ADD KEY `idx_registration_approval_actor` (`actor_id`),
  ADD KEY `idx_registration_approval_action` (`action`,`created_at`);

--
-- Indexes for table `residents`
--
ALTER TABLE `residents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_residents_name` (`last_name`,`first_name`),
  ADD KEY `idx_residents_purok` (`purok`),
  ADD KEY `fk_residents_user` (`user_id`);

--
-- Indexes for table `resident_households`
--
ALTER TABLE `resident_households`
  ADD PRIMARY KEY (`resident_id`,`household_id`),
  ADD UNIQUE KEY `uq_resident_current_primary` (`current_primary_resident_id`),
  ADD KEY `idx_resident_households_household` (`household_id`),
  ADD KEY `idx_resident_households_primary` (`resident_id`,`is_primary`);

--
-- Indexes for table `sms_otp_requests`
--
ALTER TABLE `sms_otp_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sms_otp_token` (`request_token`),
  ADD KEY `idx_sms_otp_mobile` (`mobile`,`created_at`),
  ADD KEY `idx_sms_otp_ip` (`requested_ip`,`created_at`);

--
-- Indexes for table `sms_subscribers`
--
ALTER TABLE `sms_subscribers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sms_subscribers_mobile` (`mobile`),
  ADD KEY `idx_sms_subscribers_status` (`status`,`purok`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `uq_users_resident` (`resident_id`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD KEY `idx_users_status` (`status`),
  ADD KEY `idx_users_role` (`role`);

--
-- Indexes for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_notification_event` (`recipient_user_id`,`entity_type`,`entity_id`,`category`),
  ADD KEY `idx_user_notifications_unread` (`recipient_user_id`,`read_at`,`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `announcement_history`
--
ALTER TABLE `announcement_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `announcement_notifications`
--
ALTER TABLE `announcement_notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=122;

--
-- AUTO_INCREMENT for table `assistance_distributions`
--
ALTER TABLE `assistance_distributions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assistance_items`
--
ALTER TABLE `assistance_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=451;

--
-- AUTO_INCREMENT for table `barangay_personnel`
--
ALTER TABLE `barangay_personnel`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `barangay_projects`
--
ALTER TABLE `barangay_projects`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blotter_entries`
--
ALTER TABLE `blotter_entries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `case_attachments`
--
ALTER TABLE `case_attachments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `case_attachment_access_log`
--
ALTER TABLE `case_attachment_access_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `case_hearings`
--
ALTER TABLE `case_hearings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `case_hearing_participants`
--
ALTER TABLE `case_hearing_participants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `case_hearing_personnel`
--
ALTER TABLE `case_hearing_personnel`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `case_hearing_schedule_history`
--
ALTER TABLE `case_hearing_schedule_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `case_persons`
--
ALTER TABLE `case_persons`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `case_status_history`
--
ALTER TABLE `case_status_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaint_cases`
--
ALTER TABLE `complaint_cases`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_requests`
--
ALTER TABLE `document_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `drr_alerts`
--
ALTER TABLE `drr_alerts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `drr_areas`
--
ALTER TABLE `drr_areas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `drr_contacts`
--
ALTER TABLE `drr_contacts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `drr_damage_assessments`
--
ALTER TABLE `drr_damage_assessments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `drr_evacuations`
--
ALTER TABLE `drr_evacuations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `drr_evacuation_centers`
--
ALTER TABLE `drr_evacuation_centers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `drr_hazard_areas`
--
ALTER TABLE `drr_hazard_areas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `drr_records`
--
ALTER TABLE `drr_records`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `drr_relief_distributions`
--
ALTER TABLE `drr_relief_distributions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `drr_relief_items`
--
ALTER TABLE `drr_relief_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `finance_attachments`
--
ALTER TABLE `finance_attachments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `finance_budgets`
--
ALTER TABLE `finance_budgets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `finance_categories`
--
ALTER TABLE `finance_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `finance_opening_balances`
--
ALTER TABLE `finance_opening_balances`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `finance_transactions`
--
ALTER TABLE `finance_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `health_chronic_cases`
--
ALTER TABLE `health_chronic_cases`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `health_conditions`
--
ALTER TABLE `health_conditions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `health_growth_reference`
--
ALTER TABLE `health_growth_reference`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=729;

--
-- AUTO_INCREMENT for table `health_immunizations`
--
ALTER TABLE `health_immunizations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `health_nutrition`
--
ALTER TABLE `health_nutrition`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `health_pregnancies`
--
ALTER TABLE `health_pregnancies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `health_records`
--
ALTER TABLE `health_records`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `health_record_medicines`
--
ALTER TABLE `health_record_medicines`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `health_vaccines`
--
ALTER TABLE `health_vaccines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hearing_venues`
--
ALTER TABLE `hearing_venues`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `households`
--
ALTER TABLE `households`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `household_member_requests`
--
ALTER TABLE `household_member_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `inventory_borrow_records`
--
ALTER TABLE `inventory_borrow_records`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_categories`
--
ALTER TABLE `inventory_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_locations`
--
ALTER TABLE `inventory_locations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `registration_applications`
--
ALTER TABLE `registration_applications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `registration_approvals`
--
ALTER TABLE `registration_approvals`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `residents`
--
ALTER TABLE `residents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `sms_otp_requests`
--
ALTER TABLE `sms_otp_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sms_subscribers`
--
ALTER TABLE `sms_subscribers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `user_notifications`
--
ALTER TABLE `user_notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_announcement_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `announcement_history`
--
ALTER TABLE `announcement_history`
  ADD CONSTRAINT `fk_announcement_history_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_announcement_history_announcement` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `announcement_notifications`
--
ALTER TABLE `announcement_notifications`
  ADD CONSTRAINT `fk_announcement_notifications_announcement` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_announcement_notifications_recipient` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assistance_distributions`
--
ALTER TABLE `assistance_distributions`
  ADD CONSTRAINT `fk_assistance_archived_by` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_assistance_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_assistance_finance` FOREIGN KEY (`finance_transaction_id`) REFERENCES `finance_transactions` (`id`),
  ADD CONSTRAINT `fk_assistance_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`),
  ADD CONSTRAINT `fk_assistance_incident` FOREIGN KEY (`incident_id`) REFERENCES `drr_records` (`id`),
  ADD CONSTRAINT `fk_assistance_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`),
  ADD CONSTRAINT `fk_assistance_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `assistance_items`
--
ALTER TABLE `assistance_items`
  ADD CONSTRAINT `fk_assistance_items_distribution` FOREIGN KEY (`distribution_id`) REFERENCES `assistance_distributions` (`id`),
  ADD CONSTRAINT `fk_assistance_items_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  ADD CONSTRAINT `fk_assistance_items_movement` FOREIGN KEY (`inventory_movement_id`) REFERENCES `inventory_movements` (`id`);

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `barangay_personnel`
--
ALTER TABLE `barangay_personnel`
  ADD CONSTRAINT `fk_barangay_personnel_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_barangay_personnel_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `barangay_projects`
--
ALTER TABLE `barangay_projects`
  ADD CONSTRAINT `fk_projects_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_projects_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `blotter_entries`
--
ALTER TABLE `blotter_entries`
  ADD CONSTRAINT `fk_blotter_closed_by` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_blotter_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaint_cases` (`id`),
  ADD CONSTRAINT `fk_blotter_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_blotter_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_blotter_started_by` FOREIGN KEY (`processing_started_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `case_attachments`
--
ALTER TABLE `case_attachments`
  ADD CONSTRAINT `fk_case_attachments_blotter` FOREIGN KEY (`blotter_id`) REFERENCES `blotter_entries` (`id`),
  ADD CONSTRAINT `fk_case_attachments_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaint_cases` (`id`),
  ADD CONSTRAINT `fk_case_attachments_removed_by` FOREIGN KEY (`removed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_case_attachments_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `case_attachment_access_log`
--
ALTER TABLE `case_attachment_access_log`
  ADD CONSTRAINT `fk_attachment_access_attachment` FOREIGN KEY (`attachment_id`) REFERENCES `case_attachments` (`id`),
  ADD CONSTRAINT `fk_attachment_access_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `case_hearings`
--
ALTER TABLE `case_hearings`
  ADD CONSTRAINT `fk_hearings_blotter` FOREIGN KEY (`blotter_id`) REFERENCES `blotter_entries` (`id`),
  ADD CONSTRAINT `fk_hearings_cancelled_by` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_hearings_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaint_cases` (`id`),
  ADD CONSTRAINT `fk_hearings_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_hearings_outcome_by` FOREIGN KEY (`outcome_recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_hearings_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_hearings_venue` FOREIGN KEY (`venue_id`) REFERENCES `hearing_venues` (`id`);

--
-- Constraints for table `case_hearing_participants`
--
ALTER TABLE `case_hearing_participants`
  ADD CONSTRAINT `fk_hearing_participants_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_hearing_participants_hearing` FOREIGN KEY (`hearing_id`) REFERENCES `case_hearings` (`id`),
  ADD CONSTRAINT `fk_hearing_participants_person` FOREIGN KEY (`case_person_id`) REFERENCES `case_persons` (`id`),
  ADD CONSTRAINT `fk_hearing_participants_recorded_by` FOREIGN KEY (`attendance_recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_hearing_participants_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `case_hearing_personnel`
--
ALTER TABLE `case_hearing_personnel`
  ADD CONSTRAINT `fk_hearing_personnel_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_hearing_personnel_hearing` FOREIGN KEY (`hearing_id`) REFERENCES `case_hearings` (`id`),
  ADD CONSTRAINT `fk_hearing_personnel_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `barangay_personnel` (`id`),
  ADD CONSTRAINT `fk_hearing_personnel_removed_by` FOREIGN KEY (`removed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_hearing_personnel_replaced_by` FOREIGN KEY (`replaced_by_assignment_id`) REFERENCES `case_hearing_personnel` (`id`);

--
-- Constraints for table `case_hearing_schedule_history`
--
ALTER TABLE `case_hearing_schedule_history`
  ADD CONSTRAINT `fk_schedule_history_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_schedule_history_hearing` FOREIGN KEY (`hearing_id`) REFERENCES `case_hearings` (`id`),
  ADD CONSTRAINT `fk_schedule_history_new_venue` FOREIGN KEY (`new_venue_id`) REFERENCES `hearing_venues` (`id`),
  ADD CONSTRAINT `fk_schedule_history_prev_venue` FOREIGN KEY (`previous_venue_id`) REFERENCES `hearing_venues` (`id`);

--
-- Constraints for table `case_persons`
--
ALTER TABLE `case_persons`
  ADD CONSTRAINT `fk_case_persons_blotter` FOREIGN KEY (`blotter_id`) REFERENCES `blotter_entries` (`id`),
  ADD CONSTRAINT `fk_case_persons_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaint_cases` (`id`),
  ADD CONSTRAINT `fk_case_persons_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_case_persons_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `case_status_history`
--
ALTER TABLE `case_status_history`
  ADD CONSTRAINT `fk_case_history_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_case_history_blotter` FOREIGN KEY (`blotter_id`) REFERENCES `blotter_entries` (`id`),
  ADD CONSTRAINT `fk_case_history_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaint_cases` (`id`);

--
-- Constraints for table `complaint_cases`
--
ALTER TABLE `complaint_cases`
  ADD CONSTRAINT `fk_complaints_closed_by` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_complaints_complainant` FOREIGN KEY (`complainant_resident_id`) REFERENCES `residents` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_complaints_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_complaints_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_complaints_respondent` FOREIGN KEY (`respondent_resident_id`) REFERENCES `residents` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_complaints_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_complaints_submitted_by` FOREIGN KEY (`submitted_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_complaints_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD CONSTRAINT `fk_document_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_document_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`);

--
-- Constraints for table `drr_alerts`
--
ALTER TABLE `drr_alerts`
  ADD CONSTRAINT `fk_drr_alerts_archived_by` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_drr_alerts_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_drr_alerts_incident` FOREIGN KEY (`incident_id`) REFERENCES `drr_records` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_drr_alerts_lifted_by` FOREIGN KEY (`lifted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `drr_alert_areas`
--
ALTER TABLE `drr_alert_areas`
  ADD CONSTRAINT `fk_drr_alert_areas_alert` FOREIGN KEY (`alert_id`) REFERENCES `drr_alerts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_drr_alert_areas_area` FOREIGN KEY (`area_id`) REFERENCES `drr_areas` (`id`);

--
-- Constraints for table `drr_areas`
--
ALTER TABLE `drr_areas`
  ADD CONSTRAINT `fk_drr_areas_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `drr_contacts`
--
ALTER TABLE `drr_contacts`
  ADD CONSTRAINT `fk_drr_contacts_archived_by` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_drr_contacts_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_drr_contacts_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `drr_damage_assessments`
--
ALTER TABLE `drr_damage_assessments`
  ADD CONSTRAINT `fk_drr_damage_archived_by` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_drr_damage_area` FOREIGN KEY (`area_id`) REFERENCES `drr_areas` (`id`),
  ADD CONSTRAINT `fk_drr_damage_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_drr_damage_incident` FOREIGN KEY (`incident_id`) REFERENCES `drr_records` (`id`),
  ADD CONSTRAINT `fk_drr_damage_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `drr_evacuations`
--
ALTER TABLE `drr_evacuations`
  ADD CONSTRAINT `fk_drr_evacuations_archived_by` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_drr_evacuations_center` FOREIGN KEY (`center_id`) REFERENCES `drr_evacuation_centers` (`id`),
  ADD CONSTRAINT `fk_drr_evacuations_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_drr_evacuations_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`),
  ADD CONSTRAINT `fk_drr_evacuations_incident` FOREIGN KEY (`incident_id`) REFERENCES `drr_records` (`id`),
  ADD CONSTRAINT `fk_drr_evacuations_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`),
  ADD CONSTRAINT `fk_drr_evacuations_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `drr_evacuation_centers`
--
ALTER TABLE `drr_evacuation_centers`
  ADD CONSTRAINT `fk_drr_centers_archived_by` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_drr_centers_area` FOREIGN KEY (`area_id`) REFERENCES `drr_areas` (`id`),
  ADD CONSTRAINT `fk_drr_centers_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_drr_centers_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `drr_hazard_areas`
--
ALTER TABLE `drr_hazard_areas`
  ADD CONSTRAINT `fk_drr_hazards_archived_by` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_drr_hazards_area` FOREIGN KEY (`area_id`) REFERENCES `drr_areas` (`id`),
  ADD CONSTRAINT `fk_drr_hazards_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_drr_hazards_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `drr_records`
--
ALTER TABLE `drr_records`
  ADD CONSTRAINT `fk_drr_records_archived_by` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_drr_records_area` FOREIGN KEY (`area_id`) REFERENCES `drr_areas` (`id`),
  ADD CONSTRAINT `fk_drr_records_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_drr_records_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `drr_relief_distributions`
--
ALTER TABLE `drr_relief_distributions`
  ADD CONSTRAINT `fk_drr_relief_archived_by` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_drr_relief_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_drr_relief_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`),
  ADD CONSTRAINT `fk_drr_relief_incident` FOREIGN KEY (`incident_id`) REFERENCES `drr_records` (`id`),
  ADD CONSTRAINT `fk_drr_relief_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`);

--
-- Constraints for table `drr_relief_items`
--
ALTER TABLE `drr_relief_items`
  ADD CONSTRAINT `fk_drr_relief_items_distribution` FOREIGN KEY (`distribution_id`) REFERENCES `drr_relief_distributions` (`id`),
  ADD CONSTRAINT `fk_drr_relief_items_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`);

--
-- Constraints for table `finance_attachments`
--
ALTER TABLE `finance_attachments`
  ADD CONSTRAINT `fk_finance_attachments_archived_by` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_finance_attachments_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `finance_transactions` (`id`),
  ADD CONSTRAINT `fk_finance_attachments_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `finance_budgets`
--
ALTER TABLE `finance_budgets`
  ADD CONSTRAINT `fk_finance_budgets_category` FOREIGN KEY (`category_id`) REFERENCES `finance_categories` (`id`),
  ADD CONSTRAINT `fk_finance_budgets_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_finance_budgets_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `finance_categories`
--
ALTER TABLE `finance_categories`
  ADD CONSTRAINT `fk_finance_categories_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `finance_opening_balances`
--
ALTER TABLE `finance_opening_balances`
  ADD CONSTRAINT `fk_finance_opening_cancelled_by` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_finance_opening_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `finance_transactions`
--
ALTER TABLE `finance_transactions`
  ADD CONSTRAINT `fk_finance_transactions_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_finance_transactions_cancelled_by` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_finance_transactions_category` FOREIGN KEY (`category_id`) REFERENCES `finance_categories` (`id`),
  ADD CONSTRAINT `fk_finance_transactions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_finance_transactions_rejected_by` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_finance_transactions_released_by` FOREIGN KEY (`released_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_finance_transactions_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`),
  ADD CONSTRAINT `fk_finance_transactions_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `health_chronic_cases`
--
ALTER TABLE `health_chronic_cases`
  ADD CONSTRAINT `fk_hcc_archived_by` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_hcc_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_hcc_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`),
  ADD CONSTRAINT `fk_hcc_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `health_conditions`
--
ALTER TABLE `health_conditions`
  ADD CONSTRAINT `fk_health_conditions_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `health_immunizations`
--
ALTER TABLE `health_immunizations`
  ADD CONSTRAINT `fk_hi_archived_by` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_hi_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_hi_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`),
  ADD CONSTRAINT `fk_hi_vaccine` FOREIGN KEY (`vaccine_id`) REFERENCES `health_vaccines` (`id`);

--
-- Constraints for table `health_nutrition`
--
ALTER TABLE `health_nutrition`
  ADD CONSTRAINT `fk_hn_archived_by` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_hn_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_hn_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`);

--
-- Constraints for table `health_pregnancies`
--
ALTER TABLE `health_pregnancies`
  ADD CONSTRAINT `fk_hp_archived_by` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_hp_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_hp_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`),
  ADD CONSTRAINT `fk_hp_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `health_records`
--
ALTER TABLE `health_records`
  ADD CONSTRAINT `fk_health_records_archived_by` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_health_records_condition` FOREIGN KEY (`condition_id`) REFERENCES `health_conditions` (`id`),
  ADD CONSTRAINT `fk_health_records_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_health_records_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`),
  ADD CONSTRAINT `fk_health_records_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `health_record_medicines`
--
ALTER TABLE `health_record_medicines`
  ADD CONSTRAINT `fk_hrm_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  ADD CONSTRAINT `fk_hrm_movement` FOREIGN KEY (`inventory_movement_id`) REFERENCES `inventory_movements` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_hrm_record` FOREIGN KEY (`health_record_id`) REFERENCES `health_records` (`id`);

--
-- Constraints for table `health_worker_puroks`
--
ALTER TABLE `health_worker_puroks`
  ADD CONSTRAINT `fk_hwp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hearing_venues`
--
ALTER TABLE `hearing_venues`
  ADD CONSTRAINT `fk_hearing_venues_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `households`
--
ALTER TABLE `households`
  ADD CONSTRAINT `fk_households_head` FOREIGN KEY (`household_head_resident_id`) REFERENCES `residents` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `household_member_requests`
--
ALTER TABLE `household_member_requests`
  ADD CONSTRAINT `fk_hmr_decider` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_hmr_requester` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_hmr_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_borrow_records`
--
ALTER TABLE `inventory_borrow_records`
  ADD CONSTRAINT `fk_inventory_borrow_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  ADD CONSTRAINT `fk_inventory_borrow_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inventory_borrow_returned_to` FOREIGN KEY (`returned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_categories`
--
ALTER TABLE `inventory_categories`
  ADD CONSTRAINT `fk_inventory_categories_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD CONSTRAINT `fk_inventory_items_archived_by` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inventory_items_category` FOREIGN KEY (`category_id`) REFERENCES `inventory_categories` (`id`),
  ADD CONSTRAINT `fk_inventory_items_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inventory_items_location` FOREIGN KEY (`location_id`) REFERENCES `inventory_locations` (`id`),
  ADD CONSTRAINT `fk_inventory_items_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_locations`
--
ALTER TABLE `inventory_locations`
  ADD CONSTRAINT `fk_inventory_locations_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD CONSTRAINT `fk_inventory_movements_borrow` FOREIGN KEY (`borrow_id`) REFERENCES `inventory_borrow_records` (`id`),
  ADD CONSTRAINT `fk_inventory_movements_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  ADD CONSTRAINT `fk_inventory_movements_user` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  ADD CONSTRAINT `fk_push_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `registration_applications`
--
ALTER TABLE `registration_applications`
  ADD CONSTRAINT `fk_registration_application_decider` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_registration_application_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_registration_application_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_registration_application_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `registration_approvals`
--
ALTER TABLE `registration_approvals`
  ADD CONSTRAINT `fk_registration_approval_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_registration_approval_application` FOREIGN KEY (`application_id`) REFERENCES `registration_applications` (`id`);

--
-- Constraints for table `residents`
--
ALTER TABLE `residents`
  ADD CONSTRAINT `fk_residents_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `resident_households`
--
ALTER TABLE `resident_households`
  ADD CONSTRAINT `fk_resident_households_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_resident_households_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD CONSTRAINT `fk_user_notifications_recipient` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
