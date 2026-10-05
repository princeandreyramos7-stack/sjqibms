-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 05, 2026 at 06:30 AM
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
-- Database: `sjqibms_migration_check`
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
  `audience` enum('public','all_residents','purok','selected_users') NOT NULL DEFAULT 'public',
  `target_purok` varchar(80) DEFAULT NULL,
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'draft',
  `published_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- --------------------------------------------------------

--
-- Table structure for table `barangay_personnel`
--

CREATE TABLE `barangay_personnel` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(180) NOT NULL,
  `position` varchar(120) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `barangay_personnel`
--

INSERT INTO `barangay_personnel` (`id`, `full_name`, `position`, `user_id`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(900, 'No Account', 'Test Position', NULL, 'active', NULL, '2026-09-24 15:01:55', '2026-09-24 15:01:55'),
(901, 'Also No Account', 'Test Position', NULL, 'active', NULL, '2026-09-24 15:01:55', '2026-09-24 15:01:55');

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

--
-- Dumping data for table `blotter_entries`
--

INSERT INTO `blotter_entries` (`id`, `blotter_number`, `complaint_id`, `incident_type`, `incident_at`, `incident_location`, `narrative`, `status`, `is_confidential`, `recorded_by`, `recorded_at`, `processing_started_by`, `processing_started_at`, `resolved_by`, `resolved_at`, `resolution_notes`, `closed_by`, `closed_at`, `closing_notes`, `created_at`, `updated_at`) VALUES
(900, 'TST-B-1', 900, 't', '2026-09-24 23:01:55', 'l', 'n', 'recorded', 1, NULL, '2026-09-24 15:01:55', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-24 15:01:55', '2026-09-24 15:01:55'),
(901, 'TST-B-2', 900, 't', '2026-09-24 23:01:55', 'l', 'n', 'recorded', 1, NULL, '2026-09-24 15:01:55', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-24 15:01:55', '2026-09-24 15:01:55'),
(902, 'TST-B-3', NULL, 't', '2026-09-24 23:01:55', 'l', 'n', 'recorded', 1, NULL, '2026-09-24 15:01:55', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-24 15:01:55', '2026-09-24 15:01:55');

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

--
-- Dumping data for table `case_hearings`
--

INSERT INTO `case_hearings` (`id`, `hearing_number`, `complaint_id`, `blotter_id`, `hearing_type`, `starts_at`, `ends_at`, `venue_id`, `status`, `notes`, `cancelled_by`, `cancelled_at`, `cancellation_reason`, `outcome_summary`, `outcome_recorded_by`, `outcome_recorded_at`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(900, 'TST-H-1', NULL, 900, 't', '2030-01-01 09:00:00', '2030-01-01 10:00:00', 900, 'scheduled', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-24 15:01:55', '2026-09-24 15:01:55');

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

--
-- Dumping data for table `case_hearing_personnel`
--

INSERT INTO `case_hearing_personnel` (`id`, `hearing_id`, `personnel_id`, `assignment_role`, `assigned_by`, `assigned_at`, `removed_by`, `removed_at`, `removal_reason`, `replaced_by_assignment_id`) VALUES
(900, 900, 900, 'r', NULL, '2026-09-24 15:01:55', NULL, '2026-09-24 23:02:19', NULL, NULL),
(902, 900, 900, 'r', NULL, '2026-09-24 15:01:55', NULL, NULL, NULL, NULL);

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

--
-- Dumping data for table `case_hearing_schedule_history`
--

INSERT INTO `case_hearing_schedule_history` (`id`, `hearing_id`, `change_type`, `previous_starts_at`, `previous_ends_at`, `previous_venue_id`, `new_starts_at`, `new_ends_at`, `new_venue_id`, `reason`, `changed_by`, `changed_at`) VALUES
(1, 900, 'initial', NULL, NULL, NULL, '2030-01-01 09:00:00', '2030-01-01 10:00:00', 900, NULL, NULL, '2026-09-24 15:01:55'),
(2, 900, 'initial', NULL, NULL, NULL, '2030-01-01 09:00:00', '2030-01-01 10:00:00', 900, NULL, NULL, '2026-09-24 15:02:19');

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
(900, 900, NULL, 'complainant', NULL, 'Non Resident', NULL, NULL, NULL, NULL, NULL, '2026-09-24 15:01:55', '2026-09-24 15:01:55');

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
(900, 'TST-C-1', NULL, NULL, NULL, NULL, 'fixture', NULL, 'settled', '2026-09-24 15:01:55', NULL, NULL, NULL, NULL, '2026-09-24 15:01:55', '2026-09-24 15:01:55', NULL, NULL, NULL, 'staff', 900, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `document_featured_templates`
--

CREATE TABLE `document_featured_templates` (
  `position` tinyint(3) UNSIGNED NOT NULL,
  `document_template_id` bigint(20) UNSIGNED NOT NULL,
  `featured_by` bigint(20) UNSIGNED DEFAULT NULL,
  `featured_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `document_releases`
--

CREATE TABLE `document_releases` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `request_id` bigint(20) UNSIGNED NOT NULL,
  `claimant_name` varchar(150) NOT NULL,
  `claimant_type` enum('requester','representative') NOT NULL,
  `verification_result` enum('verified') NOT NULL,
  `verification_notes` varchar(255) DEFAULT NULL,
  `authorization_reference` varchar(150) DEFAULT NULL,
  `released_by` bigint(20) UNSIGNED NOT NULL,
  `released_at` datetime NOT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `document_requests`
--

CREATE TABLE `document_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `resident_id` bigint(20) UNSIGNED NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `document_type_id` bigint(20) UNSIGNED DEFAULT NULL,
  `document_template_id` bigint(20) UNSIGNED DEFAULT NULL,
  `purpose` text NOT NULL,
  `request_source` enum('online','staff') DEFAULT NULL,
  `requested_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('pending','approved','released','rejected','processing','ready_for_release','cancelled') NOT NULL DEFAULT 'pending',
  `reference_code` varchar(40) NOT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `reviewed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `rejected_by` bigint(20) UNSIGNED DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(500) DEFAULT NULL,
  `cancelled_by` bigint(20) UNSIGNED DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` varchar(500) DEFAULT NULL,
  `prepared_by` bigint(20) UNSIGNED DEFAULT NULL,
  `prepared_at` datetime DEFAULT NULL,
  `signing_confirmed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `signing_confirmed_at` datetime DEFAULT NULL,
  `ready_by` bigint(20) UNSIGNED DEFAULT NULL,
  `ready_at` datetime DEFAULT NULL,
  `fee_amount` decimal(10,2) DEFAULT NULL,
  `payment_status` enum('not_applicable','pending','verified','exempt') DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `payment_verified_by` bigint(20) UNSIGNED DEFAULT NULL,
  `payment_verified_at` datetime DEFAULT NULL,
  `exemption_reason` varchar(500) DEFAULT NULL,
  `exemption_by` bigint(20) UNSIGNED DEFAULT NULL,
  `exemption_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_signatories`
--

CREATE TABLE `document_signatories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `position` varchar(150) NOT NULL,
  `document_type_id` bigint(20) UNSIGNED DEFAULT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_templates`
--

CREATE TABLE `document_templates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `document_type_id` bigint(20) UNSIGNED NOT NULL,
  `version_no` int(10) UNSIGNED NOT NULL,
  `title` varchar(150) NOT NULL,
  `body` mediumtext NOT NULL,
  `page_size` enum('A4','Legal','Letter') NOT NULL DEFAULT 'A4',
  `margin_mm` decimal(4,1) NOT NULL DEFAULT 20.0,
  `status` enum('draft','for_review','approved','rejected','archived') NOT NULL DEFAULT 'draft',
  `is_development_sample` tinyint(1) NOT NULL DEFAULT 0,
  `rejection_reason` varchar(500) DEFAULT NULL,
  `content_hash` char(64) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `submitted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `archived_by` bigint(20) UNSIGNED DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_types`
--

CREATE TABLE `document_types` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `name_normalized` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `fee_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `generated_documents`
--

CREATE TABLE `generated_documents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `request_id` bigint(20) UNSIGNED NOT NULL,
  `document_template_id` bigint(20) UNSIGNED NOT NULL,
  `template_version` int(10) UNSIGNED NOT NULL,
  `signatory_id` bigint(20) UNSIGNED DEFAULT NULL,
  `signatory_name` varchar(150) DEFAULT NULL,
  `signatory_position` varchar(150) DEFAULT NULL,
  `rendered_html` mediumtext NOT NULL,
  `content_hash` char(64) NOT NULL,
  `generated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(900, 'Test Hall', 'test hall', 1, NULL, '2026-09-24 15:01:55', '2026-09-24 15:01:55');

-- --------------------------------------------------------

--
-- Table structure for table `households`
--

CREATE TABLE `households` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `household_no` varchar(50) NOT NULL,
  `household_head_resident_id` bigint(20) UNSIGNED DEFAULT NULL,
  `address` text NOT NULL,
  `purok` varchar(80) NOT NULL,
  `housing_type` varchar(80) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `contact_number` varchar(30) DEFAULT NULL,
  `address` text NOT NULL,
  `purok` varchar(80) NOT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `status` enum('active','moved','deceased','inactive','pending') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `residents`
--

INSERT INTO `residents` (`id`, `user_id`, `household_no`, `first_name`, `middle_name`, `last_name`, `suffix`, `birth_date`, `sex`, `civil_status`, `contact_number`, `address`, `purok`, `photo_path`, `status`, `created_at`, `updated_at`) VALUES
(900, NULL, NULL, 'Fixture', NULL, 'Resident', NULL, NULL, NULL, NULL, NULL, 'n/a', 'n/a', NULL, 'active', '2026-09-24 15:01:55', '2026-09-24 15:01:55');

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
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('super_admin','secretary','treasurer','health_worker','official','resident') NOT NULL,
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

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `status`, `resident_id`, `approved_by`, `approved_at`, `last_login_at`, `created_at`, `updated_at`) VALUES
(900, 'Fixture Staff', 'fixture@test.invalid', 'x', 'secretary', 'active', 900, NULL, NULL, NULL, '2026-09-24 15:01:55', '2026-09-24 15:01:55');

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
-- Indexes for table `document_featured_templates`
--
ALTER TABLE `document_featured_templates`
  ADD PRIMARY KEY (`position`),
  ADD UNIQUE KEY `uq_document_featured_template` (`document_template_id`),
  ADD KEY `fk_document_featured_by` (`featured_by`);

--
-- Indexes for table `document_releases`
--
ALTER TABLE `document_releases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_document_release_request` (`request_id`),
  ADD KEY `fk_document_releases_released_by` (`released_by`);

--
-- Indexes for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_code` (`reference_code`),
  ADD KEY `fk_document_resident` (`resident_id`),
  ADD KEY `fk_document_approver` (`approved_by`),
  ADD KEY `idx_document_status` (`status`),
  ADD KEY `idx_document_requests_status_date` (`status`,`requested_at`),
  ADD KEY `idx_document_requests_type` (`document_type_id`),
  ADD KEY `idx_document_requests_requested_by` (`requested_by_user_id`),
  ADD KEY `fk_document_requests_template` (`document_template_id`),
  ADD KEY `fk_document_requests_reviewed_by` (`reviewed_by`),
  ADD KEY `fk_document_requests_rejected_by` (`rejected_by`),
  ADD KEY `fk_document_requests_cancelled_by` (`cancelled_by`),
  ADD KEY `fk_document_requests_prepared_by` (`prepared_by`),
  ADD KEY `fk_document_requests_signing_by` (`signing_confirmed_by`),
  ADD KEY `fk_document_requests_ready_by` (`ready_by`),
  ADD KEY `fk_document_requests_payment_by` (`payment_verified_by`),
  ADD KEY `fk_document_requests_exemption_by` (`exemption_by`);

--
-- Indexes for table `document_signatories`
--
ALTER TABLE `document_signatories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_document_signatories_active` (`is_active`,`effective_from`,`effective_to`),
  ADD KEY `fk_document_signatories_type` (`document_type_id`),
  ADD KEY `fk_document_signatories_approved_by` (`approved_by`);

--
-- Indexes for table `document_templates`
--
ALTER TABLE `document_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_document_template_version` (`document_type_id`,`version_no`),
  ADD KEY `idx_document_templates_status` (`document_type_id`,`status`),
  ADD KEY `fk_document_templates_created_by` (`created_by`),
  ADD KEY `fk_document_templates_submitted_by` (`submitted_by`),
  ADD KEY `fk_document_templates_approved_by` (`approved_by`),
  ADD KEY `fk_document_templates_archived_by` (`archived_by`);

--
-- Indexes for table `document_types`
--
ALTER TABLE `document_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_document_types_name` (`name_normalized`),
  ADD KEY `idx_document_types_active` (`is_active`),
  ADD KEY `fk_document_types_created_by` (`created_by`),
  ADD KEY `fk_document_types_updated_by` (`updated_by`);

--
-- Indexes for table `generated_documents`
--
ALTER TABLE `generated_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_generated_documents_request` (`request_id`,`generated_at`),
  ADD KEY `fk_generated_documents_template` (`document_template_id`),
  ADD KEY `fk_generated_documents_signatory` (`signatory_id`),
  ADD KEY `fk_generated_documents_generated_by` (`generated_by`);

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
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `uq_users_resident` (`resident_id`),
  ADD KEY `idx_users_status` (`status`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `fk_users_resident` (`resident_id`);

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `announcement_history`
--
ALTER TABLE `announcement_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `announcement_notifications`
--
ALTER TABLE `announcement_notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT for table `barangay_personnel`
--
ALTER TABLE `barangay_personnel`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=902;

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=905;

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
-- AUTO_INCREMENT for table `document_releases`
--
ALTER TABLE `document_releases`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_requests`
--
ALTER TABLE `document_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `document_signatories`
--
ALTER TABLE `document_signatories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_templates`
--
ALTER TABLE `document_templates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_types`
--
ALTER TABLE `document_types`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `generated_documents`
--
ALTER TABLE `generated_documents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hearing_venues`
--
ALTER TABLE `hearing_venues`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `households`
--
ALTER TABLE `households`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `registration_applications`
--
ALTER TABLE `registration_applications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `registration_approvals`
--
ALTER TABLE `registration_approvals`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `residents`
--
ALTER TABLE `residents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=901;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=903;

--
-- AUTO_INCREMENT for table `user_notifications`
--
ALTER TABLE `user_notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

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
-- Constraints for table `document_featured_templates`
--
ALTER TABLE `document_featured_templates`
  ADD CONSTRAINT `fk_document_featured_by` FOREIGN KEY (`featured_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_document_featured_template` FOREIGN KEY (`document_template_id`) REFERENCES `document_templates` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_releases`
--
ALTER TABLE `document_releases`
  ADD CONSTRAINT `fk_document_releases_released_by` FOREIGN KEY (`released_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_document_releases_request` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`);

--
-- Constraints for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD CONSTRAINT `fk_document_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_document_requests_cancelled_by` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_document_requests_exemption_by` FOREIGN KEY (`exemption_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_document_requests_payment_by` FOREIGN KEY (`payment_verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_document_requests_prepared_by` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_document_requests_ready_by` FOREIGN KEY (`ready_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_document_requests_rejected_by` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_document_requests_requested_by` FOREIGN KEY (`requested_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_document_requests_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_document_requests_signing_by` FOREIGN KEY (`signing_confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_document_requests_template` FOREIGN KEY (`document_template_id`) REFERENCES `document_templates` (`id`),
  ADD CONSTRAINT `fk_document_requests_type` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`),
  ADD CONSTRAINT `fk_document_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`);

--
-- Constraints for table `document_signatories`
--
ALTER TABLE `document_signatories`
  ADD CONSTRAINT `fk_document_signatories_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_document_signatories_type` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `document_templates`
--
ALTER TABLE `document_templates`
  ADD CONSTRAINT `fk_document_templates_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_document_templates_archived_by` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_document_templates_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_document_templates_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_document_templates_type` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`);

--
-- Constraints for table `document_types`
--
ALTER TABLE `document_types`
  ADD CONSTRAINT `fk_document_types_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_document_types_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `generated_documents`
--
ALTER TABLE `generated_documents`
  ADD CONSTRAINT `fk_generated_documents_generated_by` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_generated_documents_request` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`),
  ADD CONSTRAINT `fk_generated_documents_signatory` FOREIGN KEY (`signatory_id`) REFERENCES `document_signatories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_generated_documents_template` FOREIGN KEY (`document_template_id`) REFERENCES `document_templates` (`id`);

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
