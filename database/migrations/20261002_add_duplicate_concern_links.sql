-- One-time migration for an existing database. Do not run after the column,
-- index, and self-referencing constraint below have already been added.
ALTER TABLE `inquiries`
  ADD COLUMN `duplicate_of_inquiry_id` int UNSIGNED DEFAULT NULL AFTER `priority_override_at`,
  ADD COLUMN `duplicate_match_confidence` decimal(4,3) DEFAULT NULL AFTER `duplicate_of_inquiry_id`,
  ADD KEY `idx_inquiries_student_office_created` (`student_id`,`office_id`,`created_at`),
  ADD KEY `idx_inquiries_duplicate_group` (`duplicate_of_inquiry_id`,`created_at`,`status`),
  ADD CONSTRAINT `fk_inquiries_duplicate_root`
    FOREIGN KEY (`duplicate_of_inquiry_id`) REFERENCES `inquiries` (`inquiry_id`)
    ON DELETE SET NULL ON UPDATE CASCADE;
