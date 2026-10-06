-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 29, 2026 at 01:58 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `helpdeskcrmc`
--

-- --------------------------------------------------------

--
-- Table structure for table `concern_types`
--

CREATE TABLE `concern_types` (
  `concern_type_id` int UNSIGNED NOT NULL,
  `office_id` int UNSIGNED NOT NULL,
  `type_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `feedback_id` int UNSIGNED NOT NULL,
  `inquiry_id` int UNSIGNED NOT NULL,
  `student_id` int UNSIGNED NOT NULL,
  `rating` tinyint UNSIGNED NOT NULL,
  `comment` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `inquiries`
--

CREATE TABLE `inquiries` (
  `inquiry_id` int UNSIGNED NOT NULL,
  `student_id` int UNSIGNED NOT NULL,
  `office_id` int UNSIGNED NOT NULL,
  `concern_type_id` int UNSIGNED DEFAULT NULL,
  `assigned_staff_id` int UNSIGNED DEFAULT NULL,
  `subject` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('Pending','In Progress','Resolved','On Hold') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pending',
  `source` enum('ai_escalation','general_inquiry') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'general_inquiry',
  `ai_priority` enum('Critical/Urgent','High','Normal','Low') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ai_priority_reason` varchar(280) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ai_priority_confidence` decimal(4,3) DEFAULT NULL,
  `priority_override` enum('Critical/Urgent','High','Normal','Low') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `priority_override_by` int UNSIGNED DEFAULT NULL,
  `priority_override_at` timestamp NULL DEFAULT NULL,
  `duplicate_of_inquiry_id` int UNSIGNED DEFAULT NULL,
  `duplicate_match_confidence` decimal(4,3) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inquiry_attachments`
--

CREATE TABLE `inquiry_attachments` (
  `attachment_id` int UNSIGNED NOT NULL,
  `inquiry_id` int UNSIGNED DEFAULT NULL,
  `response_id` int UNSIGNED DEFAULT NULL,
  `uploaded_by` int UNSIGNED NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_general_ci NOT NULL,
  `file_type` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `file_size` int UNSIGNED DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inquiry_responses`
--

CREATE TABLE `inquiry_responses` (
  `response_id` int UNSIGNED NOT NULL,
  `inquiry_id` int UNSIGNED NOT NULL,
  `staff_id` int UNSIGNED NOT NULL,
  `message` text COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `inquiry_id` int UNSIGNED DEFAULT NULL,
  `title` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `message` varchar(500) COLLATE utf8mb4_general_ci NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `offices`
--

CREATE TABLE `offices` (
  `office_id` int UNSIGNED NOT NULL,
  `office_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `offices`
--

INSERT INTO `offices` (`office_id`, `office_name`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Registrar', 'Handles enrollment, records, and academic documents', 1, '2026-09-13 12:49:38', '2026-09-13 12:49:38'),
(2, 'Cashier', 'Handles tuition, fees, and payment concerns', 1, '2026-09-13 12:49:38', '2026-09-13 12:49:38'),
(3, 'Guidance Office', 'Handles counseling and student welfare concerns', 1, '2026-09-13 12:49:38', '2026-09-13 12:49:38'),
(4, 'MIS / IT Office', 'Handles system access and technical concerns', 1, '2026-09-13 12:49:38', '2026-09-13 12:49:38'),
(6, 'SASO', 'Handles student affairs and student support concerns', 1, '2026-10-02 00:00:00', '2026-10-02 00:00:00'),
(7, 'CTE', 'College of Teacher Education', 1, '2026-10-02 00:00:00', '2026-10-02 00:00:00'),
(8, 'CBE', 'College of Business Education', 1, '2026-10-02 00:00:00', '2026-10-02 00:00:00'),
(9, 'CCS', 'College of Computer Studies', 1, '2026-10-02 00:00:00', '2026-10-02 00:00:00'),
(10, 'CCJE', 'College of Justice Education', 1, '2026-10-02 00:00:00', '2026-10-02 00:00:00'),
(11, 'Psychology Department', 'Handles Psychology Department concerns', 1, '2026-10-02 00:00:00', '2026-10-02 00:00:00'),
(12, 'Main Office', 'Handles general office concerns', 1, '2026-10-02 00:00:00', '2026-10-02 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int UNSIGNED NOT NULL,
  `role` enum('student','staff','admin') COLLATE utf8mb4_general_ci NOT NULL,
  `office_id` int UNSIGNED DEFAULT NULL,
  `student_number` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `role`, `office_id`, `student_number`, `first_name`, `last_name`, `email`, `password_hash`, `is_active`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'admin', NULL, NULL, 'System', 'Administrator', 'admin@crmc.edu.ph', '$2y$10$uqdL/FUXEBO5JAJ0kZc1e.sHFME9gB8e1kCp9FhHYyun0NCVlNhOW', 1, NULL, '2026-09-13 12:49:38', '2026-09-27 03:46:36'),
(3, 'student', NULL, '2023-00123', 'Juan', 'Dela Cruz', 'juan.delacruz@student.crmc.edu.ph', '$2y$10$uqdL/FUXEBO5JAJ0kZc1e.sHFME9gB8e1kCp9FhHYyun0NCVlNhOW', 1, NULL, '2026-09-13 12:52:42', '2026-09-13 14:18:58'),
(4, 'staff', 1, NULL, 'Maria', 'Santos', 'maria.santos@crmc.edu.ph', '$2y$10$uqdL/FUXEBO5JAJ0kZc1e.sHFME9gB8e1kCp9FhHYyun0NCVlNhOW', 1, NULL, '2026-09-13 12:52:42', '2026-09-27 03:47:18');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `concern_types`
--
ALTER TABLE `concern_types`
  ADD PRIMARY KEY (`concern_type_id`),
  ADD UNIQUE KEY `uq_office_type` (`office_id`,`type_name`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD UNIQUE KEY `inquiry_id` (`inquiry_id`),
  ADD KEY `fk_feedback_student` (`student_id`);

--
-- Indexes for table `inquiries`
--
ALTER TABLE `inquiries`
  ADD PRIMARY KEY (`inquiry_id`),
  ADD KEY `fk_inquiries_office` (`office_id`),
  ADD KEY `fk_inquiries_concern_type` (`concern_type_id`),
  ADD KEY `idx_inquiries_status` (`status`),
  ADD KEY `idx_inquiries_student` (`student_id`),
  ADD KEY `idx_inquiries_staff` (`assigned_staff_id`),
  ADD KEY `idx_inquiries_priority_override_by` (`priority_override_by`),
  ADD KEY `idx_inquiries_urgency` (`status`,`ai_priority`,`priority_override`),
  ADD KEY `idx_inquiries_student_office_created` (`student_id`,`office_id`,`created_at`),
  ADD KEY `idx_inquiries_duplicate_group` (`duplicate_of_inquiry_id`,`created_at`,`status`);

--
-- Indexes for table `inquiry_attachments`
--
ALTER TABLE `inquiry_attachments`
  ADD PRIMARY KEY (`attachment_id`),
  ADD KEY `fk_attach_inquiry` (`inquiry_id`),
  ADD KEY `fk_attach_response` (`response_id`),
  ADD KEY `fk_attach_uploader` (`uploaded_by`);

--
-- Indexes for table `inquiry_responses`
--
ALTER TABLE `inquiry_responses`
  ADD PRIMARY KEY (`response_id`),
  ADD KEY `fk_response_inquiry` (`inquiry_id`),
  ADD KEY `fk_response_staff` (`staff_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `fk_notif_inquiry` (`inquiry_id`),
  ADD KEY `idx_notif_user_unread` (`user_id`,`is_read`);

--
-- Indexes for table `offices`
--
ALTER TABLE `offices`
  ADD PRIMARY KEY (`office_id`),
  ADD UNIQUE KEY `office_name` (`office_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `student_number` (`student_number`),
  ADD KEY `fk_users_office` (`office_id`),
  ADD KEY `idx_users_role` (`role`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `concern_types`
--
ALTER TABLE `concern_types`
  MODIFY `concern_type_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `feedback_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inquiries`
--
ALTER TABLE `inquiries`
  MODIFY `inquiry_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inquiry_attachments`
--
ALTER TABLE `inquiry_attachments`
  MODIFY `attachment_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inquiry_responses`
--
ALTER TABLE `inquiry_responses`
  MODIFY `response_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `offices`
--
ALTER TABLE `offices`
  MODIFY `office_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `concern_types`
--
ALTER TABLE `concern_types`
  ADD CONSTRAINT `fk_concern_types_office` FOREIGN KEY (`office_id`) REFERENCES `offices` (`office_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `fk_feedback_inquiry` FOREIGN KEY (`inquiry_id`) REFERENCES `inquiries` (`inquiry_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_feedback_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `inquiries`
--
ALTER TABLE `inquiries`
  ADD CONSTRAINT `fk_inquiries_concern_type` FOREIGN KEY (`concern_type_id`) REFERENCES `concern_types` (`concern_type_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inquiries_office` FOREIGN KEY (`office_id`) REFERENCES `offices` (`office_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inquiries_staff` FOREIGN KEY (`assigned_staff_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inquiries_priority_override_by` FOREIGN KEY (`priority_override_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inquiries_duplicate_root` FOREIGN KEY (`duplicate_of_inquiry_id`) REFERENCES `inquiries` (`inquiry_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inquiries_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `inquiry_attachments`
--
ALTER TABLE `inquiry_attachments`
  ADD CONSTRAINT `fk_attach_inquiry` FOREIGN KEY (`inquiry_id`) REFERENCES `inquiries` (`inquiry_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_attach_response` FOREIGN KEY (`response_id`) REFERENCES `inquiry_responses` (`response_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_attach_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `inquiry_responses`
--
ALTER TABLE `inquiry_responses`
  ADD CONSTRAINT `fk_response_inquiry` FOREIGN KEY (`inquiry_id`) REFERENCES `inquiries` (`inquiry_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_response_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_inquiry` FOREIGN KEY (`inquiry_id`) REFERENCES `inquiries` (`inquiry_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_office` FOREIGN KEY (`office_id`) REFERENCES `offices` (`office_id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
