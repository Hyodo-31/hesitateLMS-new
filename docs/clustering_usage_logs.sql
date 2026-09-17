SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `clustering_WIDselect` (
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
  KEY `idx_clustering_widselect_teacher_created` (`teacher_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `clustering_UIDselect` (
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
  KEY `idx_clustering_uidselect_teacher_created` (`teacher_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `clustering_result` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id` CHAR(8) NOT NULL,
  `selected_features` JSON NOT NULL,
  `clustering_method` ENUM('kmeans', 'xmeans', 'gmeans') NOT NULL,
  `requested_cluster_count` SMALLINT UNSIGNED DEFAULT NULL,
  `actual_cluster_count` SMALLINT UNSIGNED NOT NULL,
  `target_uids` JSON NOT NULL,
  `target_wids` JSON NOT NULL,
  `student_count` INT UNSIGNED NOT NULL,
  `from_feature_correlation` TINYINT(1) NOT NULL DEFAULT 0,
  `feature_correlation_transition_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `feature_correlation_understand_transition_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `feature_correlation_hesitation_degree_transition_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `feature_correlation_feature_pair_transition_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `ML` TINYINT(1) NOT NULL DEFAULT 0 CHECK (`ML` IN (0, 1)),
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_clustering_result_teacher_created` (`teacher_id`, `created_at`),
  KEY `idx_clustering_result_teacher_method_created` (`teacher_id`, `clustering_method`, `created_at`),
  KEY `idx_clustering_result_teacher_correlation_created` (`teacher_id`, `from_feature_correlation`, `created_at`),
  CONSTRAINT `chk_clustering_result_requested_count` CHECK (
    (`clustering_method` = 'kmeans' AND `requested_cluster_count` BETWEEN 2 AND 10)
    OR (`clustering_method` IN ('xmeans', 'gmeans') AND `requested_cluster_count` IS NULL)
  ),
  CONSTRAINT `chk_clustering_result_transition` CHECK (
    (`from_feature_correlation` = 0 AND `feature_correlation_transition_count` = 0)
    OR (`from_feature_correlation` = 1 AND `feature_correlation_transition_count` > 0)
  ),
  CONSTRAINT `chk_clustering_result_transition_modes` CHECK (
    `feature_correlation_understand_transition_count`
      + `feature_correlation_hesitation_degree_transition_count`
      + `feature_correlation_feature_pair_transition_count`
    <= `feature_correlation_transition_count`
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `clustering_WIDselect`
  ADD COLUMN IF NOT EXISTS `ML` TINYINT(1) NOT NULL DEFAULT 0 CHECK (`ML` IN (0, 1));

ALTER TABLE `clustering_UIDselect`
  ADD COLUMN IF NOT EXISTS `ML` TINYINT(1) NOT NULL DEFAULT 0 CHECK (`ML` IN (0, 1));

ALTER TABLE `clustering_result`
  ADD COLUMN IF NOT EXISTS `ML` TINYINT(1) NOT NULL DEFAULT 0 CHECK (`ML` IN (0, 1));

ALTER TABLE `clustering_result`
  ADD COLUMN IF NOT EXISTS `feature_correlation_understand_transition_count` INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `feature_correlation_hesitation_degree_transition_count` INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `feature_correlation_feature_pair_transition_count` INT UNSIGNED NOT NULL DEFAULT 0;

CREATE OR REPLACE VIEW `Clustering_usage_counts` AS
SELECT `teacher_id`, 'wid_apply' AS `function_name`, COUNT(*) AS `use_count`,
       MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `clustering_WIDselect`
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, 'uid_target_apply' AS `function_name`, COUNT(*) AS `use_count`,
       MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `clustering_UIDselect`
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, 'clustering_execute' AS `function_name`, COUNT(*) AS `use_count`,
       MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `clustering_result`
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, 'correlation_to_clustering_transition' AS `function_name`,
       SUM(`feature_correlation_transition_count`) AS `use_count`,
       MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `clustering_result`
WHERE `feature_correlation_transition_count` > 0
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, 'correlation_understand_to_clustering_transition' AS `function_name`,
       SUM(`feature_correlation_understand_transition_count`) AS `use_count`,
       MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `clustering_result`
WHERE `feature_correlation_understand_transition_count` > 0
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, 'correlation_hesitation_degree_to_clustering_transition' AS `function_name`,
       SUM(`feature_correlation_hesitation_degree_transition_count`) AS `use_count`,
       MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `clustering_result`
WHERE `feature_correlation_hesitation_degree_transition_count` > 0
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, 'correlation_feature_pair_to_clustering_transition' AS `function_name`,
       SUM(`feature_correlation_feature_pair_transition_count`) AS `use_count`,
       MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `clustering_result`
WHERE `feature_correlation_feature_pair_transition_count` > 0
GROUP BY `teacher_id`;
