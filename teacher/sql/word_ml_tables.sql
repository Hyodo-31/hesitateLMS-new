-- 単語単位の特徴量生成/迷い度推定で利用するテーブル定義
-- UID: 学習者番号, WID: 問題番号, WWID: 問題内の単語番号

CREATE TABLE IF NOT EXISTS test_featurevalue_word (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    UID INT NOT NULL,
    WID INT NOT NULL,
    WWID INT NOT NULL,
    Understand INT DEFAULT 0,
    attempt INT DEFAULT 1,
    date BIGINT DEFAULT 0,
    `check` TINYINT DEFAULT 0,
    Time DOUBLE DEFAULT 0,
    distance DOUBLE DEFAULT 0,
    averageSpeed DOUBLE DEFAULT 0,
    maxSpeed DOUBLE DEFAULT 0,
    thinkingTime DOUBLE DEFAULT 0,
    answeringTime DOUBLE DEFAULT 0,
    totalStopTime DOUBLE DEFAULT 0,
    maxStopTime DOUBLE DEFAULT 0,
    totalDDIntervalTime DOUBLE DEFAULT 0,
    maxDDIntervalTime DOUBLE DEFAULT 0,
    maxDDTime DOUBLE DEFAULT 0,
    minDDTime DOUBLE DEFAULT 0,
    DDCount INT DEFAULT 0,
    groupingDDCount INT DEFAULT 0,
    groupingCountbool TINYINT DEFAULT 0,
    xUTurnCount INT DEFAULT 0,
    yUTurnCount INT DEFAULT 0,
    register_move_count1 INT DEFAULT 0,
    register_move_count2 INT DEFAULT 0,
    register_move_count3 INT DEFAULT 0,
    register_move_count4 INT DEFAULT 0,
    register01count1 TINYINT DEFAULT 0,
    register01count2 TINYINT DEFAULT 0,
    register01count3 TINYINT DEFAULT 0,
    register01count4 TINYINT DEFAULT 0,
    registerDDCount INT DEFAULT 0,
    stopcount INT DEFAULT 0,
    xUTurnCountDD INT DEFAULT 0,
    yUTurnCountDD INT DEFAULT 0,
    FromlastdropToanswerTime DOUBLE DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_word_attempt (UID, WID, WWID, attempt),
    INDEX idx_word_key (UID, WID, WWID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS temporary_results_word (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    UID INT NOT NULL,
    WID INT NOT NULL,
    WWID INT NOT NULL,
    Understand INT NOT NULL,
    attempt INT DEFAULT 1,
    teacher_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_word_teacher (teacher_id),
    INDEX idx_word_result_key (UID, WID, WWID, attempt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 既存テーブルで不足カラムがある場合に追加
ALTER TABLE test_featurevalue_word
    ADD COLUMN IF NOT EXISTS WWID INT NOT NULL AFTER WID,
    ADD COLUMN IF NOT EXISTS attempt INT DEFAULT 1 AFTER Understand;

ALTER TABLE temporary_results_word
    ADD COLUMN IF NOT EXISTS WWID INT NOT NULL AFTER WID,
    ADD COLUMN IF NOT EXISTS attempt INT DEFAULT 1 AFTER Understand,
    ADD COLUMN IF NOT EXISTS teacher_id INT DEFAULT NULL AFTER attempt;
