-- temporary_results_word: 単語単位の迷い推定結果ログ
-- UID: 学習者番号, WID: 問題番号, WWID: 問題内の単語番号

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

-- 既存環境向けの不足カラム補完
ALTER TABLE temporary_results_word
    ADD COLUMN IF NOT EXISTS WWID INT NOT NULL AFTER WID,
    ADD COLUMN IF NOT EXISTS attempt INT DEFAULT 1 AFTER Understand,
    ADD COLUMN IF NOT EXISTS teacher_id INT DEFAULT NULL AFTER attempt;
