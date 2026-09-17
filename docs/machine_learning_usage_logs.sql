SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `hesitate_estimate_pre` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id` CHAR(8) NOT NULL,
  `training_data_source` ENUM('created_group', 'a_university_2019', 'default_all') NOT NULL,
  `training_group_id` INT DEFAULT NULL,
  `training_group_name` VARCHAR(255) DEFAULT NULL,
  `training_uids` JSON DEFAULT NULL,
  `classification_uids` JSON NOT NULL,
  `classification_groups` JSON NOT NULL,
  `selected_features` JSON NOT NULL,
  `classifier_preset_used` TINYINT(1) NOT NULL DEFAULT 0,
  `classifier_preset` ENUM('A', 'B', 'C') DEFAULT NULL,
  `classifier_preset_modified` TINYINT(1) NOT NULL DEFAULT 0,
  `ML` TINYINT(1) NOT NULL DEFAULT 0 CHECK (`ML` IN (0, 1)),
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_hesitate_estimate_pre_teacher_created` (`teacher_id`, `created_at`),
  KEY `idx_hesitate_estimate_pre_teacher_preset_created` (`teacher_id`, `classifier_preset`, `created_at`),
  CONSTRAINT `chk_hesitate_estimate_pre_training_group` CHECK (
    (`training_data_source` = 'created_group'
      AND `training_group_id` IS NOT NULL
      AND `training_group_name` IS NOT NULL
      AND `training_uids` IS NOT NULL)
    OR
    (`training_data_source` IN ('a_university_2019', 'default_all')
      AND `training_group_id` IS NULL
      AND `training_group_name` IS NULL
      AND `training_uids` IS NULL)
  ),
  CONSTRAINT `chk_hesitate_estimate_pre_preset` CHECK (
    (`classifier_preset_used` = 0
      AND `classifier_preset` IS NULL
      AND `classifier_preset_modified` = 0)
    OR
    (`classifier_preset_used` = 1
      AND `classifier_preset` IS NOT NULL
      AND `classifier_preset_modified` IN (0, 1))
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `hesitate_estimate_pre`
  ADD COLUMN IF NOT EXISTS `ML` TINYINT(1) NOT NULL DEFAULT 0 CHECK (`ML` IN (0, 1));

CREATE OR REPLACE VIEW `Hesitate_estimate_usage_counts` AS
SELECT `teacher_id`, 'machine_learning_execute' AS `function_name`, COUNT(*) AS `use_count`,
       MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `hesitate_estimate_pre`
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, CONCAT('classifier_', LOWER(`classifier_preset`), '_use') AS `function_name`,
       COUNT(*) AS `use_count`, MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `hesitate_estimate_pre`
WHERE `classifier_preset_used` = 1
GROUP BY `teacher_id`, `classifier_preset`
UNION ALL
SELECT `teacher_id`, 'manual_feature_selection' AS `function_name`, COUNT(*) AS `use_count`,
       MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `hesitate_estimate_pre`
WHERE `classifier_preset_used` = 0
GROUP BY `teacher_id`;
