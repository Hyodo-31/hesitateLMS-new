SET NAMES utf8mb4;

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
