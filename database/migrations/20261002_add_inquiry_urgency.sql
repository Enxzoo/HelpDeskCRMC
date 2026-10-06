-- One-time migration for an existing database. Do not run after the columns
-- and index/constraint below have already been added.
ALTER TABLE `inquiries`
  ADD COLUMN `ai_priority` enum('Critical/Urgent','High','Normal','Low') COLLATE utf8mb4_general_ci DEFAULT NULL AFTER `source`,
  ADD COLUMN `ai_priority_reason` varchar(280) COLLATE utf8mb4_general_ci DEFAULT NULL AFTER `ai_priority`,
  ADD COLUMN `ai_priority_confidence` decimal(4,3) DEFAULT NULL AFTER `ai_priority_reason`,
  ADD COLUMN `priority_override` enum('Critical/Urgent','High','Normal','Low') COLLATE utf8mb4_general_ci DEFAULT NULL AFTER `ai_priority_confidence`,
  ADD COLUMN `priority_override_by` int UNSIGNED DEFAULT NULL AFTER `priority_override`,
  ADD COLUMN `priority_override_at` timestamp NULL DEFAULT NULL AFTER `priority_override_by`,
  ADD KEY `idx_inquiries_priority_override_by` (`priority_override_by`),
  ADD KEY `idx_inquiries_urgency` (`status`,`ai_priority`,`priority_override`),
  ADD CONSTRAINT `fk_inquiries_priority_override_by`
    FOREIGN KEY (`priority_override_by`) REFERENCES `users` (`user_id`)
    ON DELETE SET NULL ON UPDATE CASCADE;
