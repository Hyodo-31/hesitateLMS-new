<?php

function student_feature_columns(): array
{
    return [
        'Time' => '解答時間',
        'distance' => '移動距離',
        'averageSpeed' => '平均速度',
        'maxSpeed' => '最大速度',
        'thinkingTime' => '第一ドラッグ前時間',
        'answeringTime' => '第一ドラッグ後時間',
        'totalStopTime' => '合計静止時間',
        'maxStopTime' => '最大静止時間',
        'totalDDIntervalTime' => '合計D&D間時間',
        'maxDDIntervalTime' => '最大D&D間時間',
        'maxDDTime' => '最大D&D時間',
        'minDDTime' => '最小D&D時間',
        'DDCount' => 'D&D回数',
        'groupingDDCount' => 'グループ化D&D回数',
        'groupingCountbool' => 'グループ化使用率',
        'xUTurnCount' => 'X軸Uターン回数',
        'yUTurnCount' => 'Y軸Uターン回数',
        'register_move_count1' => 'レジスタ間移動回数',
        'register_move_count2' => 'レジスタ外への移動回数',
        'register_move_count3' => 'レジスタ内への移動回数',
        'register_move_count4' => 'レジスタ関連移動回数4',
        'register01count1' => 'レジスタ間移動有無',
        'register01count2' => 'レジスタ外移動有無',
        'register01count3' => 'レジスタ内移動有無',
        'register01count4' => 'レジスタ関連移動有無4',
        'registerDDCount' => 'レジスタ内D&D回数',
        'stopcount' => '静止回数',
        'xUTurnCountDD' => 'X軸UターンD&D回数',
        'yUTurnCountDD' => 'Y軸UターンD&D回数',
        'FromlastdropToanswerTime' => '最終ドロップ後時間',
    ];
}

function student_feature_table_exists(mysqli $conn): bool
{
    static $exists = null;

    if ($exists !== null) {
        return $exists;
    }

    $result = $conn->query("SHOW TABLES LIKE 'test_featurevalue'");
    $exists = $result !== false && $result->num_rows > 0;

    if ($result instanceof mysqli_result) {
        $result->free();
    }

    return $exists;
}

function student_feature_average_select_sql(mysqli $conn, string $alias = 'feat'): string
{
    $selects = ['0 AS feature_record_count'];

    if (student_feature_table_exists($conn)) {
        $selects = ["COALESCE({$alias}.feature_record_count, 0) AS feature_record_count"];
        foreach (student_feature_columns() as $column => $_label) {
            $selects[] = "{$alias}.avg_{$column}";
        }
    } else {
        foreach (student_feature_columns() as $column => $_label) {
            $selects[] = "NULL AS avg_{$column}";
        }
    }

    return implode(",\n            ", $selects);
}

function student_feature_average_join_sql(mysqli $conn, string $alias = 'feat'): string
{
    if (!student_feature_table_exists($conn)) {
        return '';
    }

    $selects = ['UID', 'COUNT(*) AS feature_record_count'];
    foreach (student_feature_columns() as $column => $_label) {
        $selects[] = "AVG(`{$column}`) AS avg_{$column}";
    }

    return "LEFT JOIN (
            SELECT
                " . implode(",\n                ", $selects) . "
            FROM test_featurevalue
            GROUP BY UID
        ) {$alias} ON s.uid = {$alias}.UID";
}

function format_student_feature_value($value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    $number = round((float)$value, 2);
    return number_format($number, 2, '.', '');
}

function render_student_tooltip(array $row, string $accuracy_label, string $hesitation_label, string $answers_label): string
{
    $accuracy = htmlspecialchars(round((float)$row['accuracy'], 2), ENT_QUOTES, 'UTF-8');
    $hesitation_rate = htmlspecialchars(round((float)$row['hesitation_rate'], 2), ENT_QUOTES, 'UTF-8');
    $total_answers = htmlspecialchars($row['total_answers'], ENT_QUOTES, 'UTF-8');
    $feature_record_count = (int)($row['feature_record_count'] ?? 0);

    $html = "<span class='student-feature-popup' role='tooltip' hidden>
                <span>{$accuracy_label} {$accuracy}%</span>
                <span>{$hesitation_label} {$hesitation_rate}%</span>
                <span>{$answers_label} {$total_answers}</span>
                <span class='feature-tooltip-title'>特徴量平均 ({$feature_record_count}件)</span>";

    if ($feature_record_count === 0) {
        $html .= "<span class='feature-tooltip-empty'>特徴量データがありません</span>";
    } else {
        $html .= "<span class='feature-tooltip-grid'>";
        foreach (student_feature_columns() as $column => $label) {
            $value = htmlspecialchars(format_student_feature_value($row["avg_{$column}"] ?? null), ENT_QUOTES, 'UTF-8');
            $safe_label = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
            $html .= "<span class='feature-tooltip-label'>{$safe_label}</span><span class='feature-tooltip-value'>{$value}</span>";
        }
        $html .= "</span>";
    }

    $html .= "</span>";

    return $html;
}
