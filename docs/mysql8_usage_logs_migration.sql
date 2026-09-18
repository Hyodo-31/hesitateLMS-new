-- Existing-server migration for MySQL 8.0.
-- Run this file before deploying the PHP changes.
-- It is safe to run more than once.

USE `2019su1`;
SET NAMES utf8mb4;

SET @schema_name := DATABASE();

CREATE TABLE IF NOT EXISTS `feacherml` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id` CHAR(8) NOT NULL,
  `group_id` INT NOT NULL,
  `group_name` VARCHAR(255) NOT NULL,
  `selected_features` JSON NOT NULL,
  `displayed_student_count` INT UNSIGNED NOT NULL,
  `ML` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_feacherml_teacher_created` (`teacher_id`, `created_at`),
  KEY `idx_feacherml_group_created` (`group_id`, `created_at`),
  CONSTRAINT `chk_feacherml_selected_features` CHECK (
    JSON_TYPE(`selected_features`) = 'ARRAY'
    AND JSON_LENGTH(`selected_features`) = 2
  ),
  CONSTRAINT `chk_feacherml_displayed_student_count` CHECK (`displayed_student_count` > 0),
  CONSTRAINT `chk_feacherml_ml` CHECK (`ML` IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @ddl := IF(
  EXISTS(
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'clustering_result'
      AND COLUMN_NAME = 'feature_correlation_understand_transition_count'
  ),
  'DO 0',
  'ALTER TABLE `clustering_result` ADD COLUMN `feature_correlation_understand_transition_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `feature_correlation_transition_count`'
);
PREPARE usage_log_migration FROM @ddl;
EXECUTE usage_log_migration;
DEALLOCATE PREPARE usage_log_migration;

SET @ddl := IF(
  EXISTS(
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'clustering_result'
      AND COLUMN_NAME = 'feature_correlation_hesitation_degree_transition_count'
  ),
  'DO 0',
  'ALTER TABLE `clustering_result` ADD COLUMN `feature_correlation_hesitation_degree_transition_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `feature_correlation_understand_transition_count`'
);
PREPARE usage_log_migration FROM @ddl;
EXECUTE usage_log_migration;
DEALLOCATE PREPARE usage_log_migration;

SET @ddl := IF(
  EXISTS(
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'clustering_result'
      AND COLUMN_NAME = 'feature_correlation_feature_pair_transition_count'
  ),
  'DO 0',
  'ALTER TABLE `clustering_result` ADD COLUMN `feature_correlation_feature_pair_transition_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `feature_correlation_hesitation_degree_transition_count`'
);
PREPARE usage_log_migration FROM @ddl;
EXECUTE usage_log_migration;
DEALLOCATE PREPARE usage_log_migration;

SET @ddl := IF(
  EXISTS(
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'hesitate_estimate_pre'
      AND COLUMN_NAME = 'from_feature_correlation'
  ),
  'DO 0',
  'ALTER TABLE `hesitate_estimate_pre` ADD COLUMN `from_feature_correlation` TINYINT(1) NOT NULL DEFAULT 0 AFTER `classifier_preset_modified`'
);
PREPARE usage_log_migration FROM @ddl;
EXECUTE usage_log_migration;
DEALLOCATE PREPARE usage_log_migration;

SET @ddl := IF(
  EXISTS(
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'hesitate_estimate_pre'
      AND COLUMN_NAME = 'feature_correlation_transition_count'
  ),
  'DO 0',
  'ALTER TABLE `hesitate_estimate_pre` ADD COLUMN `feature_correlation_transition_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `from_feature_correlation`'
);
PREPARE usage_log_migration FROM @ddl;
EXECUTE usage_log_migration;
DEALLOCATE PREPARE usage_log_migration;

SET @ddl := IF(
  EXISTS(
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'hesitate_estimate_pre'
      AND COLUMN_NAME = 'feature_correlation_understand_transition_count'
  ),
  'DO 0',
  'ALTER TABLE `hesitate_estimate_pre` ADD COLUMN `feature_correlation_understand_transition_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `feature_correlation_transition_count`'
);
PREPARE usage_log_migration FROM @ddl;
EXECUTE usage_log_migration;
DEALLOCATE PREPARE usage_log_migration;

SET @ddl := IF(
  EXISTS(
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'hesitate_estimate_pre'
      AND COLUMN_NAME = 'feature_correlation_hesitation_degree_transition_count'
  ),
  'DO 0',
  'ALTER TABLE `hesitate_estimate_pre` ADD COLUMN `feature_correlation_hesitation_degree_transition_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `feature_correlation_understand_transition_count`'
);
PREPARE usage_log_migration FROM @ddl;
EXECUTE usage_log_migration;
DEALLOCATE PREPARE usage_log_migration;

SET @ddl := IF(
  EXISTS(
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'hesitate_estimate_pre'
      AND COLUMN_NAME = 'feature_correlation_feature_pair_transition_count'
  ),
  'DO 0',
  'ALTER TABLE `hesitate_estimate_pre` ADD COLUMN `feature_correlation_feature_pair_transition_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `feature_correlation_hesitation_degree_transition_count`'
);
PREPARE usage_log_migration FROM @ddl;
EXECUTE usage_log_migration;
DEALLOCATE PREPARE usage_log_migration;

SET @ddl := IF(
  EXISTS(
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'hesitate_estimate_pre'
      AND COLUMN_NAME = 'estimation_accuracy'
  ),
  'DO 0',
  'ALTER TABLE `hesitate_estimate_pre` ADD COLUMN `estimation_accuracy` DECIMAL(7,6) NULL COMMENT ''Cross-validation accuracy (0..1)'' AFTER `feature_correlation_feature_pair_transition_count`'
);
PREPARE usage_log_migration FROM @ddl;
EXECUTE usage_log_migration;
DEALLOCATE PREPARE usage_log_migration;

SET @ddl := IF(
  EXISTS(
    SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'hesitate_estimate_pre'
      AND INDEX_NAME = 'idx_hesitate_estimate_pre_teacher_correlation_created'
  ),
  'DO 0',
  'ALTER TABLE `hesitate_estimate_pre` ADD INDEX `idx_hesitate_estimate_pre_teacher_correlation_created` (`teacher_id`, `from_feature_correlation`, `created_at`)'
);
PREPARE usage_log_migration FROM @ddl;
EXECUTE usage_log_migration;
DEALLOCATE PREPARE usage_log_migration;

SET @ddl := IF(
  EXISTS(
    SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = @schema_name
      AND TABLE_NAME = 'hesitate_estimate_pre'
      AND CONSTRAINT_NAME = 'chk_hesitate_estimate_pre_accuracy'
  ),
  'DO 0',
  'ALTER TABLE `hesitate_estimate_pre` ADD CONSTRAINT `chk_hesitate_estimate_pre_accuracy` CHECK (`estimation_accuracy` IS NULL OR (`estimation_accuracy` >= 0 AND `estimation_accuracy` <= 1))'
);
PREPARE usage_log_migration FROM @ddl;
EXECUTE usage_log_migration;
DEALLOCATE PREPARE usage_log_migration;

SET @ddl := IF(
  EXISTS(
    SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = @schema_name
      AND TABLE_NAME = 'clustering_result'
      AND CONSTRAINT_NAME = 'chk_clustering_result_transition_modes'
  ),
  'DO 0',
  'ALTER TABLE `clustering_result` ADD CONSTRAINT `chk_clustering_result_transition_modes` CHECK (`feature_correlation_understand_transition_count` + `feature_correlation_hesitation_degree_transition_count` + `feature_correlation_feature_pair_transition_count` <= `feature_correlation_transition_count`)'
);
PREPARE usage_log_migration FROM @ddl;
EXECUTE usage_log_migration;
DEALLOCATE PREPARE usage_log_migration;

SET @ddl := IF(
  EXISTS(
    SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = @schema_name
      AND TABLE_NAME = 'hesitate_estimate_pre'
      AND CONSTRAINT_NAME = 'chk_hesitate_estimate_pre_transition'
  ),
  'DO 0',
  'ALTER TABLE `hesitate_estimate_pre` ADD CONSTRAINT `chk_hesitate_estimate_pre_transition` CHECK ((`from_feature_correlation` = 0 AND `feature_correlation_transition_count` = 0) OR (`from_feature_correlation` = 1 AND `feature_correlation_transition_count` > 0))'
);
PREPARE usage_log_migration FROM @ddl;
EXECUTE usage_log_migration;
DEALLOCATE PREPARE usage_log_migration;

SET @ddl := IF(
  EXISTS(
    SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = @schema_name
      AND TABLE_NAME = 'hesitate_estimate_pre'
      AND CONSTRAINT_NAME = 'chk_hesitate_estimate_pre_transition_modes'
  ),
  'DO 0',
  'ALTER TABLE `hesitate_estimate_pre` ADD CONSTRAINT `chk_hesitate_estimate_pre_transition_modes` CHECK (`feature_correlation_understand_transition_count` + `feature_correlation_hesitation_degree_transition_count` + `feature_correlation_feature_pair_transition_count` <= `feature_correlation_transition_count`)'
);
PREPARE usage_log_migration FROM @ddl;
EXECUTE usage_log_migration;
DEALLOCATE PREPARE usage_log_migration;

CREATE OR REPLACE VIEW `clustering_usage_counts` AS
SELECT `teacher_id`, 'wid_apply' AS `function_name`, COUNT(*) AS `use_count`,
       MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `clustering_widselect`
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, 'uid_target_apply', COUNT(*), MIN(`created_at`), MAX(`created_at`)
FROM `clustering_uidselect`
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, 'clustering_execute', COUNT(*), MIN(`created_at`), MAX(`created_at`)
FROM `clustering_result`
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, 'correlation_to_clustering_transition',
       SUM(`feature_correlation_transition_count`), MIN(`created_at`), MAX(`created_at`)
FROM `clustering_result`
WHERE `feature_correlation_transition_count` > 0
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, 'correlation_understand_to_clustering_transition',
       SUM(`feature_correlation_understand_transition_count`), MIN(`created_at`), MAX(`created_at`)
FROM `clustering_result`
WHERE `feature_correlation_understand_transition_count` > 0
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, 'correlation_hesitation_degree_to_clustering_transition',
       SUM(`feature_correlation_hesitation_degree_transition_count`), MIN(`created_at`), MAX(`created_at`)
FROM `clustering_result`
WHERE `feature_correlation_hesitation_degree_transition_count` > 0
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, 'correlation_feature_pair_to_clustering_transition',
       SUM(`feature_correlation_feature_pair_transition_count`), MIN(`created_at`), MAX(`created_at`)
FROM `clustering_result`
WHERE `feature_correlation_feature_pair_transition_count` > 0
GROUP BY `teacher_id`;

CREATE OR REPLACE VIEW `hesitate_estimate_usage_counts` AS
SELECT `teacher_id`, 'machine_learning_execute' AS `function_name`, COUNT(*) AS `use_count`,
       MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `hesitate_estimate_pre`
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, CONCAT('classifier_', LOWER(`classifier_preset`), '_use'),
       COUNT(*), MIN(`created_at`), MAX(`created_at`)
FROM `hesitate_estimate_pre`
WHERE `classifier_preset_used` = 1
GROUP BY `teacher_id`, `classifier_preset`
UNION ALL
SELECT `teacher_id`, 'manual_feature_selection', COUNT(*), MIN(`created_at`), MAX(`created_at`)
FROM `hesitate_estimate_pre`
WHERE `classifier_preset_used` = 0
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, 'correlation_to_machine_learning_transition',
       SUM(`feature_correlation_transition_count`), MIN(`created_at`), MAX(`created_at`)
FROM `hesitate_estimate_pre`
WHERE `feature_correlation_transition_count` > 0
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, 'correlation_understand_to_machine_learning_transition',
       SUM(`feature_correlation_understand_transition_count`), MIN(`created_at`), MAX(`created_at`)
FROM `hesitate_estimate_pre`
WHERE `feature_correlation_understand_transition_count` > 0
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, 'correlation_hesitation_degree_to_machine_learning_transition',
       SUM(`feature_correlation_hesitation_degree_transition_count`), MIN(`created_at`), MAX(`created_at`)
FROM `hesitate_estimate_pre`
WHERE `feature_correlation_hesitation_degree_transition_count` > 0
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, 'correlation_feature_pair_to_machine_learning_transition',
       SUM(`feature_correlation_feature_pair_transition_count`), MIN(`created_at`), MAX(`created_at`)
FROM `hesitate_estimate_pre`
WHERE `feature_correlation_feature_pair_transition_count` > 0
GROUP BY `teacher_id`;

SELECT
  `TABLE_NAME`,
  MAX(`COLUMN_NAME` = 'ML') AS `has_ml`,
  MAX(`COLUMN_NAME` = 'teacher_id') AS `has_teacher_id`,
  MAX(`COLUMN_NAME` = 'from_feature_correlation') AS `has_from_correlation`,
  MAX(`COLUMN_NAME` = 'feature_correlation_transition_count') AS `has_transition_total`,
  MAX(`COLUMN_NAME` = 'feature_correlation_understand_transition_count') AS `has_transition_understand`,
  MAX(`COLUMN_NAME` = 'feature_correlation_hesitation_degree_transition_count') AS `has_transition_hesitation`,
  MAX(`COLUMN_NAME` = 'feature_correlation_feature_pair_transition_count') AS `has_transition_pair`,
  MAX(`COLUMN_NAME` = 'estimation_accuracy') AS `has_estimation_accuracy`
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @schema_name
  AND TABLE_NAME IN ('clustering_result', 'feacherml', 'hesitate_estimate_pre')
GROUP BY `TABLE_NAME`
ORDER BY `TABLE_NAME`;
