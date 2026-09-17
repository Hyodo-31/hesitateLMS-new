SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `grouping_widselect` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id` CHAR(8) NOT NULL,
  `selection_method` ENUM('checkbox', 'histogram') NOT NULL,
  `selected_wids` JSON NOT NULL,
  `histogram_features` JSON DEFAULT NULL,
  `histogram_conditions` JSON DEFAULT NULL,
  `histogram_bin_width_changed` TINYINT(1) DEFAULT NULL,
  `ML` TINYINT(1) NOT NULL DEFAULT 0 CHECK (`ML` IN (0, 1)),
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_grouping_widselect_teacher_created` (`teacher_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `grouping_uidselect` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id` CHAR(8) NOT NULL,
  `selection_method` ENUM('checkbox', 'histogram') NOT NULL,
  `selected_uids` JSON NOT NULL,
  `reflected_candidate_uids` JSON NOT NULL,
  `selected_wids` JSON NOT NULL,
  `group_condition_used` TINYINT(1) DEFAULT NULL,
  `group_expression` TEXT DEFAULT NULL,
  `group_expression_tokens` JSON DEFAULT NULL,
  `histogram_features` JSON DEFAULT NULL,
  `histogram_conditions` JSON DEFAULT NULL,
  `histogram_bin_width_changed` TINYINT(1) DEFAULT NULL,
  `correctness_filter` VARCHAR(32) NOT NULL,
  `correctness_filter_used` TINYINT(1) NOT NULL DEFAULT 0,
  `hesitation_filter` VARCHAR(32) NOT NULL,
  `hesitation_filter_used` TINYINT(1) NOT NULL DEFAULT 0,
  `ML` TINYINT(1) NOT NULL DEFAULT 0 CHECK (`ML` IN (0, 1)),
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_grouping_uidselect_teacher_created` (`teacher_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `delete_groups` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id` CHAR(8) NOT NULL,
  `group_id` INT NOT NULL,
  `group_name` VARCHAR(255) NOT NULL,
  `ML` TINYINT(1) NOT NULL DEFAULT 0 CHECK (`ML` IN (0, 1)),
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_delete_groups_teacher_created` (`teacher_id`, `created_at`),
  KEY `idx_delete_groups_teacher_group_created` (`teacher_id`, `group_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW `grouping_usage_counts` AS
SELECT `teacher_id`, 'wid_apply' AS `function_name`, COUNT(*) AS `use_count`,
       MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `grouping_widselect`
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, 'uid_candidate_apply' AS `function_name`, COUNT(*) AS `use_count`,
       MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `grouping_uidselect`
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, 'group_delete' AS `function_name`, COUNT(*) AS `use_count`,
       MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `delete_groups`
GROUP BY `teacher_id`;
