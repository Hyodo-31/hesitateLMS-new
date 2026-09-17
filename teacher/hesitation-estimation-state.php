<?php

/**
 * 迷い推定結果の一時的な表示状態を管理する。
 *
 * 推定結果そのものは変更せず、ログイン中のセッションでだけ分析用の
 * temporary_results / temporary_results_word を空のデータ集合として扱う。
 */

if (!function_exists('teacher_hesitation_is_suppressed')) {
    function teacher_hesitation_is_suppressed(): bool
    {
        return ($_SESSION['teacher_hesitation_suppressed'] ?? false) === true;
    }
}

if (!function_exists('teacher_hesitation_set_suppressed')) {
    function teacher_hesitation_set_suppressed(bool $suppressed): void
    {
        $_SESSION['teacher_hesitation_suppressed'] = $suppressed;
    }
}

if (!function_exists('teacher_hesitation_ml_flag')) {
    function teacher_hesitation_ml_flag(mysqli $conn, string $teacherId): int
    {
        if (teacher_hesitation_is_suppressed()) {
            return 0;
        }

        $teacherId = trim($teacherId);
        if ($teacherId === '' || !preg_match('/^\d+$/', $teacherId)) {
            throw new InvalidArgumentException('教師IDが不正です。');
        }

        $numericTeacherId = (int)$teacherId;
        $stmt = $conn->prepare(
            'SELECT 1 FROM temporary_results WHERE teacher_id = ? LIMIT 1'
        );
        if (!$stmt) {
            throw new RuntimeException('迷い推定状態の確認準備に失敗しました。');
        }
        $stmt->bind_param('i', $numericTeacherId);
        if (!$stmt->execute()) {
            $message = $stmt->error;
            $stmt->close();
            throw new RuntimeException('迷い推定状態の確認に失敗しました: ' . $message);
        }
        $stmt->store_result();
        $hasResults = $stmt->num_rows > 0;
        $stmt->close();

        return $hasResults ? 1 : 0;
    }
}

if (!function_exists('teacher_hesitation_results_source')) {
    function teacher_hesitation_results_source(string $alias = ''): string
    {
        $source = teacher_hesitation_is_suppressed()
            ? '(SELECT * FROM temporary_results WHERE 1 = 0)'
            : 'temporary_results';

        return $source . teacher_hesitation_safe_alias($alias);
    }
}

if (!function_exists('teacher_hesitation_word_results_source')) {
    function teacher_hesitation_word_results_source(string $alias = ''): string
    {
        $source = teacher_hesitation_is_suppressed()
            ? '(SELECT * FROM temporary_results_word WHERE 1 = 0)'
            : 'temporary_results_word';

        return $source . teacher_hesitation_safe_alias($alias);
    }
}

if (!function_exists('teacher_hesitation_safe_alias')) {
    function teacher_hesitation_safe_alias(string $alias): string
    {
        if ($alias === '') {
            return '';
        }
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) {
            throw new InvalidArgumentException('迷い推定結果のSQL別名が不正です。');
        }
        return ' ' . $alias;
    }
}
