<?php

/**
 * 教師向けの学習者分析画面で選択・集計対象にする問題(WID)。
 *
 * @return int[]
 */
function teacher_analysis_target_wids(): array
{
    return [32, 45, 54, 58, 59, 60, 61, 127, 129, 138, 141, 143, 147, 176, 191];
}

function teacher_analysis_wid_is_allowed($wid): bool
{
    if (!is_scalar($wid)) {
        return false;
    }

    $normalizedWid = filter_var($wid, FILTER_VALIDATE_INT);
    if ($normalizedWid === false) {
        return false;
    }

    static $lookup = null;
    if ($lookup === null) {
        $lookup = array_fill_keys(teacher_analysis_target_wids(), true);
    }

    return isset($lookup[$normalizedWid]);
}

/**
 * 入力順を維持しながら、対象WIDだけに絞り込む。
 *
 * @return int[]
 */
function teacher_analysis_filter_wids(array $wids): array
{
    $filtered = [];
    foreach ($wids as $wid) {
        if (!teacher_analysis_wid_is_allowed($wid)) {
            continue;
        }
        $filtered[(int)$wid] = (int)$wid;
    }

    return array_values($filtered);
}

/**
 * WIDを持つ取得行を対象問題だけに制限する。
 */
function teacher_analysis_filter_wid_rows(array $rows, string $widKey = 'WID'): array
{
    return array_values(array_filter(
        $rows,
        static fn(array $row): bool => teacher_analysis_wid_is_allowed($row[$widKey] ?? null)
    ));
}
