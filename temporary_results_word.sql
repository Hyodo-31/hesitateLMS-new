CREATE TABLE IF NOT EXISTS temporary_results_word (
    UID VARCHAR(64) NOT NULL,
    WID INT NOT NULL,
    WWID INT NOT NULL,
    Understand INT NOT NULL,
    teacher_id VARCHAR(64) NOT NULL,
    attempt INT NOT NULL,
    predicted_probability DOUBLE NULL,
    model_name VARCHAR(128) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (UID, WID, WWID, teacher_id, attempt),
    KEY idx_teacher (teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
