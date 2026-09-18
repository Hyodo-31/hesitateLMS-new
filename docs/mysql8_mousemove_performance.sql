-- MySQL 8.0 migration for mousemove/mousemove.php lookup performance.
-- Run this file before deploying the PHP change. It is safe to run repeatedly.

USE `2019su1`;
SET NAMES utf8mb4;
SET @schema_name := DATABASE();

SET @has_covering_index := EXISTS(
  SELECT 1
  FROM (
    SELECT `INDEX_NAME`,
           GROUP_CONCAT(LOWER(`COLUMN_NAME`) ORDER BY `SEQ_IN_INDEX` SEPARATOR ',') AS `index_columns`
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE `TABLE_SCHEMA` = @schema_name
      AND `TABLE_NAME` = 'linedatamouse'
    GROUP BY `INDEX_NAME`
  ) AS `linedatamouse_indexes`
  WHERE `index_columns` = 'uid,wid,attempt,time'
     OR `index_columns` LIKE 'uid,wid,attempt,time,%'
);
SET @ddl := IF(
  @has_covering_index,
  'DO 0',
  'ALTER TABLE `linedatamouse` ADD INDEX `idx_linedatamouse_uid_wid_attempt_time` (`UID`, `WID`, `attempt`, `Time`)'
);
PREPARE mousemove_performance_migration FROM @ddl;
EXECUTE mousemove_performance_migration;
DEALLOCATE PREPARE mousemove_performance_migration;

SET @has_covering_index := EXISTS(
  SELECT 1
  FROM (
    SELECT `INDEX_NAME`,
           GROUP_CONCAT(LOWER(`COLUMN_NAME`) ORDER BY `SEQ_IN_INDEX` SEPARATOR ',') AS `index_columns`
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE `TABLE_SCHEMA` = @schema_name
      AND `TABLE_NAME` = 'temporary_results'
    GROUP BY `INDEX_NAME`
  ) AS `temporary_results_indexes`
  WHERE `index_columns` = 'teacher_id,uid,wid,attempt,created_at,id'
     OR `index_columns` LIKE 'teacher_id,uid,wid,attempt,created_at,id,%'
);
SET @ddl := IF(
  @has_covering_index,
  'DO 0',
  'ALTER TABLE `temporary_results` ADD INDEX `idx_temporary_results_teacher_attempt_latest` (`teacher_id`, `UID`, `WID`, `attempt`, `created_at`, `id`)'
);
PREPARE mousemove_performance_migration FROM @ddl;
EXECUTE mousemove_performance_migration;
DEALLOCATE PREPARE mousemove_performance_migration;

SET @has_covering_index := EXISTS(
  SELECT 1
  FROM (
    SELECT `INDEX_NAME`,
           GROUP_CONCAT(LOWER(`COLUMN_NAME`) ORDER BY `SEQ_IN_INDEX` SEPARATOR ',') AS `index_columns`
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE `TABLE_SCHEMA` = @schema_name
      AND `TABLE_NAME` = 'temporary_results_word'
    GROUP BY `INDEX_NAME`
  ) AS `temporary_results_word_indexes`
  WHERE `index_columns` = 'teacher_id,uid,wid,attempt,understand,wwid'
     OR `index_columns` LIKE 'teacher_id,uid,wid,attempt,understand,wwid,%'
);
SET @ddl := IF(
  @has_covering_index,
  'DO 0',
  'ALTER TABLE `temporary_results_word` ADD INDEX `idx_temporary_results_word_teacher_attempt_understand_wwid` (`teacher_id`, `UID`, `WID`, `attempt`, `Understand`, `WWID`)'
);
PREPARE mousemove_performance_migration FROM @ddl;
EXECUTE mousemove_performance_migration;
DEALLOCATE PREPARE mousemove_performance_migration;

-- Refresh optimizer statistics after adding or confirming the lookup indexes.
ANALYZE TABLE `linedatamouse`, `temporary_results`, `temporary_results_word`;

SELECT `TABLE_NAME`, `INDEX_NAME`,
       GROUP_CONCAT(`COLUMN_NAME` ORDER BY `SEQ_IN_INDEX` SEPARATOR ',') AS `index_columns`
FROM INFORMATION_SCHEMA.STATISTICS
WHERE `TABLE_SCHEMA` = @schema_name
  AND `TABLE_NAME` IN ('linedatamouse', 'temporary_results', 'temporary_results_word')
GROUP BY `TABLE_NAME`, `INDEX_NAME`
ORDER BY `TABLE_NAME`, `INDEX_NAME`;
