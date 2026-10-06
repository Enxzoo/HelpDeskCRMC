-- These changes preserve existing rows and can be applied more than once.
ALTER TABLE `inquiries`
  MODIFY COLUMN `status` enum('Pending','In Progress','Resolved','On Hold')
    COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pending';

ALTER TABLE `inquiry_attachments`
  MODIFY COLUMN `inquiry_id` int UNSIGNED DEFAULT NULL;
