SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `Correlation_WIDselect` (
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
  KEY `idx_correlation_widselect_teacher_created` (`teacher_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Correlation_UIDselect` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id` CHAR(8) NOT NULL,
  `selection_method` ENUM('checkbox', 'histogram') NOT NULL,
  `selected_uids` JSON NOT NULL,
  `selected_wids` JSON NOT NULL,
  `group_condition_used` TINYINT(1) DEFAULT NULL,
  `group_expression` TEXT DEFAULT NULL,
  `group_expression_tokens` JSON DEFAULT NULL,
  `histogram_features` JSON DEFAULT NULL,
  `histogram_conditions` JSON DEFAULT NULL,
  `histogram_bin_width_changed` TINYINT(1) DEFAULT NULL,
  `ML` TINYINT(1) NOT NULL DEFAULT 0 CHECK (`ML` IN (0, 1)),
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_correlation_uidselect_teacher_created` (`teacher_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Correlation_2019select` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id` CHAR(8) NOT NULL,
  `ML` TINYINT(1) NOT NULL DEFAULT 0 CHECK (`ML` IN (0, 1)),
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_correlation_2019select_teacher_created` (`teacher_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Correlation_result` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id` CHAR(8) NOT NULL,
  `analysis_mode` ENUM('understand', 'hesitation_degree', 'feature_pair') NOT NULL,
  `display_trigger` VARCHAR(40) NOT NULL,
  `analysis_scope` ENUM('selection', 'a_university_2019') NOT NULL,
  `target_uids` JSON NOT NULL,
  `target_wids` JSON NOT NULL,
  `feature_x` VARCHAR(128) NOT NULL,
  `feature_y` VARCHAR(128) DEFAULT NULL,
  `prediction_filter` ENUM('all', 'hesitated', 'not_hesitated') NOT NULL DEFAULT 'all',
  `prediction_filter_used` TINYINT(1) NOT NULL DEFAULT 0,
  `correlation_value` DOUBLE DEFAULT NULL,
  `data_count` INT UNSIGNED NOT NULL,
  `ranking_clicked` TINYINT(1) NOT NULL DEFAULT 0,
  `ranking_position` INT UNSIGNED DEFAULT NULL,
  `ranking_feature` VARCHAR(128) DEFAULT NULL,
  `ranking_correlation` DOUBLE DEFAULT NULL,
  `ranking_data_count` INT UNSIGNED DEFAULT NULL,
  `ML` TINYINT(1) NOT NULL DEFAULT 0 CHECK (`ML` IN (0, 1)),
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_correlation_result_teacher_created` (`teacher_id`, `created_at`),
  KEY `idx_correlation_result_teacher_mode_created` (`teacher_id`, `analysis_mode`, `created_at`),
  KEY `idx_correlation_result_teacher_ranking_created` (`teacher_id`, `ranking_clicked`, `created_at`),
  CONSTRAINT `chk_correlation_result_ranking` CHECK (
    (`ranking_clicked` = 0
      AND `ranking_position` IS NULL
      AND `ranking_feature` IS NULL
      AND `ranking_correlation` IS NULL
      AND `ranking_data_count` IS NULL)
    OR
    (`ranking_clicked` = 1
      AND `ranking_position` IS NOT NULL
      AND `ranking_feature` IS NOT NULL
      AND `ranking_data_count` IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `Correlation_WIDselect`
  ADD COLUMN IF NOT EXISTS `ML` TINYINT(1) NOT NULL DEFAULT 0 CHECK (`ML` IN (0, 1));

ALTER TABLE `Correlation_UIDselect`
  ADD COLUMN IF NOT EXISTS `ML` TINYINT(1) NOT NULL DEFAULT 0 CHECK (`ML` IN (0, 1));

ALTER TABLE `Correlation_2019select`
  ADD COLUMN IF NOT EXISTS `ML` TINYINT(1) NOT NULL DEFAULT 0 CHECK (`ML` IN (0, 1));

ALTER TABLE `Correlation_result`
  ADD COLUMN IF NOT EXISTS `ML` TINYINT(1) NOT NULL DEFAULT 0 CHECK (`ML` IN (0, 1));

CREATE OR REPLACE VIEW `Correlation_usage_counts` AS
SELECT `teacher_id`, 'wid_apply' AS `function_name`, COUNT(*) AS `use_count`,
       MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `Correlation_WIDselect`
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, 'uid_correlation_display' AS `function_name`, COUNT(*) AS `use_count`,
       MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `Correlation_UIDselect`
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, 'a_university_2019_apply' AS `function_name`, COUNT(*) AS `use_count`,
       MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `Correlation_2019select`
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, CONCAT('correlation_', `analysis_mode`, '_display') AS `function_name`, COUNT(*) AS `use_count`,
       MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `Correlation_result`
GROUP BY `teacher_id`, `analysis_mode`
UNION ALL
SELECT `teacher_id`, CONCAT('ranking_', `analysis_mode`, '_click') AS `function_name`, COUNT(*) AS `use_count`,
       MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `Correlation_result`
WHERE `ranking_clicked` = 1
GROUP BY `teacher_id`, `analysis_mode`;
