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
  `from_feature_correlation` TINYINT(1) NOT NULL DEFAULT 0,
  `feature_correlation_transition_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `feature_correlation_understand_transition_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `feature_correlation_hesitation_degree_transition_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `feature_correlation_feature_pair_transition_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `estimation_accuracy` DECIMAL(7,6) DEFAULT NULL COMMENT 'Cross-validation accuracy (0..1)',
  `ML` TINYINT(1) NOT NULL DEFAULT 0 CHECK (`ML` IN (0, 1)),
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_hesitate_estimate_pre_teacher_created` (`teacher_id`, `created_at`),
  KEY `idx_hesitate_estimate_pre_teacher_preset_created` (`teacher_id`, `classifier_preset`, `created_at`),
  KEY `idx_hesitate_estimate_pre_teacher_correlation_created` (`teacher_id`, `from_feature_correlation`, `created_at`),
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
  ),
  CONSTRAINT `chk_hesitate_estimate_pre_transition` CHECK (
    (`from_feature_correlation` = 0 AND `feature_correlation_transition_count` = 0)
    OR (`from_feature_correlation` = 1 AND `feature_correlation_transition_count` > 0)
  ),
  CONSTRAINT `chk_hesitate_estimate_pre_transition_modes` CHECK (
    `feature_correlation_understand_transition_count`
      + `feature_correlation_hesitation_degree_transition_count`
      + `feature_correlation_feature_pair_transition_count`
    <= `feature_correlation_transition_count`
  ),
  CONSTRAINT `chk_hesitate_estimate_pre_accuracy` CHECK (
    `estimation_accuracy` IS NULL
    OR (`estimation_accuracy` >= 0 AND `estimation_accuracy` <= 1)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW `hesitate_estimate_usage_counts` AS
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
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, 'correlation_to_machine_learning_transition' AS `function_name`,
       SUM(`feature_correlation_transition_count`) AS `use_count`,
       MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `hesitate_estimate_pre`
WHERE `feature_correlation_transition_count` > 0
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, 'correlation_understand_to_machine_learning_transition' AS `function_name`,
       SUM(`feature_correlation_understand_transition_count`) AS `use_count`,
       MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `hesitate_estimate_pre`
WHERE `feature_correlation_understand_transition_count` > 0
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, 'correlation_hesitation_degree_to_machine_learning_transition' AS `function_name`,
       SUM(`feature_correlation_hesitation_degree_transition_count`) AS `use_count`,
       MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `hesitate_estimate_pre`
WHERE `feature_correlation_hesitation_degree_transition_count` > 0
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, 'correlation_feature_pair_to_machine_learning_transition' AS `function_name`,
       SUM(`feature_correlation_feature_pair_transition_count`) AS `use_count`,
       MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `hesitate_estimate_pre`
WHERE `feature_correlation_feature_pair_transition_count` > 0
GROUP BY `teacher_id`;
