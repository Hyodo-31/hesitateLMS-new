SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `Home_WIDselect` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id` CHAR(8) NOT NULL,
  `selection_method` ENUM('checkbox', 'histogram') NOT NULL,
  `selected_wids` JSON NOT NULL,
  `histogram_features` JSON DEFAULT NULL,
  `histogram_conditions` JSON DEFAULT NULL,
  `histogram_bin_width_changed` TINYINT(1) DEFAULT NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_home_widselect_teacher_created` (`teacher_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Home_UIDselect` (
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
  `correctness_filter` VARCHAR(32) NOT NULL,
  `correctness_filter_used` TINYINT(1) NOT NULL DEFAULT 0,
  `hesitation_filter` VARCHAR(32) NOT NULL,
  `hesitation_filter_used` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_home_uidselect_teacher_created` (`teacher_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Home_Person` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id` CHAR(8) NOT NULL,
  `selected_uid` INT NOT NULL,
  `selection_method` ENUM('checkbox', 'histogram') NOT NULL,
  `selected_wids` JSON NOT NULL,
  `histogram_features` JSON DEFAULT NULL,
  `histogram_conditions` JSON DEFAULT NULL,
  `histogram_bin_width_changed` TINYINT(1) DEFAULT NULL,
  `correctness_filter` VARCHAR(32) NOT NULL,
  `correctness_filter_used` TINYINT(1) NOT NULL DEFAULT 0,
  `hesitation_filter` VARCHAR(32) NOT NULL,
  `hesitation_filter_used` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_home_person_teacher_created` (`teacher_id`, `created_at`),
  KEY `idx_home_person_teacher_uid_created` (`teacher_id`, `selected_uid`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `To_mousemove` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id` CHAR(8) NOT NULL,
  `UID` INT NOT NULL,
  `WID` INT NOT NULL,
  `attempt` INT NOT NULL,
  `test_id` INT DEFAULT NULL,
  `from_class_results` TINYINT(1) DEFAULT NULL,
  `from_person_problem_results` TINYINT(1) DEFAULT NULL,
  `from_grammar_correct_hesitated` TINYINT(1) DEFAULT NULL,
  `from_grammar_incorrect_not_hesitated` TINYINT(1) DEFAULT NULL,
  `from_grammar_incorrect_hesitated` TINYINT(1) DEFAULT NULL,
  `grammar_name` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_to_mousemove_teacher_created` (`teacher_id`, `created_at`),
  KEY `idx_to_mousemove_attempt_created` (`teacher_id`, `UID`, `WID`, `attempt`, `created_at`),
  CONSTRAINT `chk_to_mousemove_single_source` CHECK (
    (`from_class_results` IS NOT NULL)
    + (`from_person_problem_results` IS NOT NULL)
    + (`from_grammar_correct_hesitated` IS NOT NULL)
    + (`from_grammar_incorrect_not_hesitated` IS NOT NULL)
    + (`from_grammar_incorrect_hesitated` IS NOT NULL) = 1
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Older revisions stored the grammar name directly in the three source columns.
-- Preserve that value in grammar_name before converting those columns to flags.
ALTER TABLE `To_mousemove`
  ADD COLUMN IF NOT EXISTS `grammar_name` VARCHAR(255) DEFAULT NULL
  AFTER `from_grammar_incorrect_hesitated`;

UPDATE `To_mousemove`
SET `grammar_name` = COALESCE(
  `grammar_name`,
  NULLIF(CAST(`from_grammar_correct_hesitated` AS CHAR), '1'),
  NULLIF(CAST(`from_grammar_incorrect_not_hesitated` AS CHAR), '1'),
  NULLIF(CAST(`from_grammar_incorrect_hesitated` AS CHAR), '1')
)
WHERE `grammar_name` IS NULL
  AND (
    `from_grammar_correct_hesitated` IS NOT NULL
    OR `from_grammar_incorrect_not_hesitated` IS NOT NULL
    OR `from_grammar_incorrect_hesitated` IS NOT NULL
  );

UPDATE `To_mousemove`
SET `from_grammar_correct_hesitated` = 1
WHERE `from_grammar_correct_hesitated` IS NOT NULL;

UPDATE `To_mousemove`
SET `from_grammar_incorrect_not_hesitated` = 1
WHERE `from_grammar_incorrect_not_hesitated` IS NOT NULL;

UPDATE `To_mousemove`
SET `from_grammar_incorrect_hesitated` = 1
WHERE `from_grammar_incorrect_hesitated` IS NOT NULL;

ALTER TABLE `To_mousemove`
  MODIFY COLUMN `from_grammar_correct_hesitated` TINYINT(1) DEFAULT NULL,
  MODIFY COLUMN `from_grammar_incorrect_not_hesitated` TINYINT(1) DEFAULT NULL,
  MODIFY COLUMN `from_grammar_incorrect_hesitated` TINYINT(1) DEFAULT NULL;

CREATE OR REPLACE VIEW `Home_usage_counts` AS
SELECT `teacher_id`, 'wid_apply' AS `function_name`, COUNT(*) AS `use_count`,
       MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `Home_WIDselect`
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, 'uid_result_display' AS `function_name`, COUNT(*) AS `use_count`,
       MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `Home_UIDselect`
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`, 'person_detail_display' AS `function_name`, COUNT(*) AS `use_count`,
       MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `Home_Person`
GROUP BY `teacher_id`
UNION ALL
SELECT `teacher_id`,
       CASE
         WHEN `from_class_results` IS NOT NULL THEN 'trajectory_class_results'
         WHEN `from_person_problem_results` IS NOT NULL THEN 'trajectory_person_problem'
         WHEN `from_grammar_correct_hesitated` IS NOT NULL THEN 'trajectory_grammar_correct_hesitated'
         WHEN `from_grammar_incorrect_not_hesitated` IS NOT NULL THEN 'trajectory_grammar_incorrect_not_hesitated'
         ELSE 'trajectory_grammar_incorrect_hesitated'
       END AS `function_name`,
       COUNT(*) AS `use_count`, MIN(`created_at`) AS `first_used_at`, MAX(`created_at`) AS `last_used_at`
FROM `To_mousemove`
GROUP BY `teacher_id`, `function_name`;
