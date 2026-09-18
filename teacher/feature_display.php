<?php

function feature_display_time_features(): array
{
    return [
        'time' => true,
        'thinkingtime' => true,
        'answeringtime' => true,
        'totalstoptime' => true,
        'maxstoptime' => true,
        'totalddintervaltime' => true,
        'maxddintervaltime' => true,
        'totalddtime' => true,
        'maxddtime' => true,
        'minddtime' => true,
        'fromlastdroptoanswertime' => true,
    ];
}

function feature_display_speed_features(): array
{
    return [
        'averagespeed' => true,
        'maxspeed' => true,
    ];
}

function feature_display_distance_features(): array
{
    return [
        'distance' => true,
    ];
}

function feature_display_boolean_features(): array
{
    return [
        'groupingcountbool' => true,
    ];
}

function feature_display_register_presence_features(): array
{
    return [
        'register01count1' => true,
        'register01count2' => true,
        'register01count3' => true,
    ];
}

function feature_display_is_aggregate_context(string $context): bool
{
    return strtolower($context) === 'aggregate';
}

function feature_display_type(string $feature, string $context = 'raw'): string
{
    $key = strtolower($feature);

    if (isset(feature_display_register_presence_features()[$key])) {
        return feature_display_is_aggregate_context($context) ? 'percentage' : 'boolean';
    }

    if (isset(feature_display_time_features()[$key])) {
        return 'time';
    }

    if (isset(feature_display_speed_features()[$key])) {
        return 'speed';
    }

    if (isset(feature_display_distance_features()[$key])) {
        return 'distance';
    }

    if (isset(feature_display_boolean_features()[$key])) {
        return 'boolean';
    }

    if (str_contains($key, 'count')) {
        return 'count';
    }

    return 'number';
}

function feature_display_units(): array
{
    global $lang;

    if (($lang ?? 'ja') === 'en') {
        return [
            'time' => 's',
            'distance' => 'px',
            'speed' => 'px/s',
            'count' => 'times',
            'percentage' => '%',
        ];
    }

    return [
        'time' => '秒',
        'distance' => 'ピクセル',
        'speed' => 'ピクセル/秒',
        'count' => '回',
        'percentage' => '%',
    ];
}

function feature_display_unit(string $feature, string $context = 'raw'): string
{
    $units = feature_display_units();
    return $units[feature_display_type($feature, $context)] ?? '';
}

function feature_display_label_has_unit(string $label, string $type): bool
{
    $checks = [
        'time' => ['秒', '（秒）', '(秒)', '(s)', '（s）', ' sec', ' second'],
        'distance' => ['ピクセル', '（ピクセル）', '(ピクセル)', 'px', 'pixel'],
        'speed' => ['ピクセル/秒', 'px/s', 'px/sec', 'pixel/s', 'pixel/sec'],
        'count' => ['（回）', '(回)', '(times)', ' times'],
        'percentage' => ['%', '％', 'percent'],
    ];

    $lowerLabel = strtolower($label);
    foreach ($checks[$type] ?? [] as $needle) {
        if (str_contains($lowerLabel, strtolower($needle))) {
            return true;
        }
    }

    return false;
}

function feature_display_label(string $feature, ?string $label = null, string $context = 'raw'): string
{
    $label = $label ?? $feature;
    $type = feature_display_type($feature, $context);
    $unit = feature_display_unit($feature, $context);

    if ($unit === '' || feature_display_label_has_unit($label, $type)) {
        return $label;
    }

    return "{$label}（{$unit}）";
}

function feature_display_labels(array $featureLabels, string $context = 'raw'): array
{
    foreach ($featureLabels as $feature => $label) {
        $featureLabels[$feature] = feature_display_label((string)$feature, (string)$label, $context);
    }

    return $featureLabels;
}

function feature_display_numeric_value(string $feature, $value, string $context = 'raw'): ?float
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        return null;
    }

    $number = (float)$value;
    $type = feature_display_type($feature, $context);

    if ($type === 'time') {
        return $number / 1000;
    }

    if ($type === 'speed') {
        return $number * 1000;
    }

    if ($type === 'percentage') {
        return $number * 100;
    }

    return $number;
}

function feature_storage_numeric_value(string $feature, $value, string $context = 'raw'): ?float
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        return null;
    }

    $number = (float)$value;
    $type = feature_display_type($feature, $context);

    if ($type === 'time') {
        return $number * 1000;
    }

    if ($type === 'speed') {
        return $number / 1000;
    }

    if ($type === 'percentage') {
        return $number / 100;
    }

    return $number;
}

function feature_display_value(
    string $feature,
    $value,
    int $decimals = 2,
    bool $withUnit = true,
    string $context = 'raw'
): string
{
    $displayValue = feature_display_numeric_value($feature, $value, $context);
    if ($displayValue === null) {
        return '-';
    }

    $formatted = number_format(round($displayValue, $decimals), $decimals, '.', '');
    $unit = $withUnit ? feature_display_unit($feature, $context) : '';

    return $unit === '' ? $formatted : "{$formatted}{$unit}";
}

function feature_display_metadata(array $features, string $context = 'raw'): array
{
    $metadata = [];
    foreach ($features as $feature) {
        $type = feature_display_type((string)$feature, $context);
        $displayScale = 1;
        $storageScale = 1;

        if ($type === 'time') {
            $displayScale = 0.001;
            $storageScale = 1000;
        } elseif ($type === 'speed') {
            $displayScale = 1000;
            $storageScale = 0.001;
        } elseif ($type === 'percentage') {
            $displayScale = 100;
            $storageScale = 0.01;
        }

        $metadata[(string)$feature] = [
            'type' => $type,
            'unit' => feature_display_unit((string)$feature, $context),
            'displayScale' => $displayScale,
            'storageScale' => $storageScale,
        ];
    }

    return $metadata;
}

function feature_display_name_map(string $context = 'raw'): array
{
    $names = [
        'Time' => '解答時間',
        'distance' => '移動距離',
        'averageSpeed' => '平均速度',
        'maxSpeed' => '最大速度',
        'thinkingTime' => '第1ドラッグ前時間',
        'answeringTime' => '第1ドラッグ後時間',
        'totalStopTime' => '合計静止時間',
        'maxStopTime' => '最大静止時間',
        'stopcount' => '静止回数',
        'totalDDIntervalTime' => '合計D&D間隔時間',
        'maxDDIntervalTime' => '最大D&D間隔時間',
        'totalDDTime' => '合計D&D時間',
        'maxDDTime' => '最大D&D時間',
        'minDDTime' => '最小D&D時間',
        'DDCount' => 'D&D回数',
        'groupingDDCount' => 'グループ化D&D回数',
        'groupingCountbool' => 'グループ化有無',
        'xUTurnCount' => 'X軸Uターン回数',
        'yUTurnCount' => 'Y軸Uターン回数',
        'xUTurnCountDD' => 'X軸UターンD&D回数',
        'yUTurnCountDD' => 'Y軸UターンD&D回数',
        'register_move_count1' => 'レジスタ➡レジスタへの移動回数',
        'register_move_count2' => 'レジスタ➡レジスタ外への移動回数',
        'register_move_count3' => 'レジスタ外➡レジスタへの移動回数',
        'register01count1' => 'レジスタ➡レジスタへの移動有無',
        'register01count2' => 'レジスタ➡レジスタ外への移動有無',
        'register01count3' => 'レジスタ外➡レジスタへの移動有無',
        'registerDDCount' => 'レジスタに関するD&D回数',
        'register_notDDCount' => 'レジスタ外のD&D回数',
        'register_fix_count1' => 'レジスタ1修正回数',
        'register_fix_count2' => 'レジスタ2修正回数',
        'register_fix_count3' => 'レジスタ3修正回数',
        'register_fix_count4' => 'レジスタ4修正回数',
        'register_delete_count1' => 'レジスタ1削除回数',
        'register_delete_count2' => 'レジスタ2削除回数',
        'register_delete_count3' => 'レジスタ3削除回数',
        'register_delete_count4' => 'レジスタ4削除回数',
        'register_allDelete_count1' => 'レジスタ1全削除回数',
        'register_allDelete_count2' => 'レジスタ2全削除回数',
        'register_allDelete_count3' => 'レジスタ3全削除回数',
        'register_allDelete_count4' => 'レジスタ4全削除回数',
        'register_notallDelete_count1' => 'レジスタ1部分削除回数',
        'register_notallDelete_count2' => 'レジスタ2部分削除回数',
        'register_notallDelete_count3' => 'レジスタ3部分削除回数',
        'register_notallDelete_count4' => 'レジスタ4部分削除回数',
        'FromlastdropToanswerTime' => '最終ドロップ後時間',
    ];

    if (feature_display_is_aggregate_context($context)) {
        $names['register01count1'] = 'レジスタ➡レジスタへの移動有無の割合';
        $names['register01count2'] = 'レジスタ➡レジスタ外への移動有無の割合';
        $names['register01count3'] = 'レジスタ外➡レジスタへの移動有無の割合';
    }

    return $names;
}

function feature_display_labels_for_features(array $features, string $context = 'raw'): array
{
    $names = feature_display_name_map($context);
    $labels = [];
    foreach ($features as $feature) {
        $feature = (string)$feature;
        $labels[$feature] = feature_display_label($feature, $names[$feature] ?? $feature, $context);
    }

    return $labels;
}

function feature_display_histogram_feature_labels(): array
{
    return [
        'Time' => '解答時間',
        'distance' => '移動距離',
        'averageSpeed' => '平均速度',
        'maxSpeed' => '最大速度',
        'thinkingTime' => '第1ドラッグ前時間',
        'answeringTime' => '第1ドラッグ後時間',
        'totalStopTime' => '合計静止時間',
        'maxStopTime' => '最大静止時間',
        'totalDDIntervalTime' => '合計D&D間隔時間',
        'maxDDIntervalTime' => '最大D&D間隔時間',
        'maxDDTime' => '最大D&D時間',
        'minDDTime' => '最小D&D時間',
        'DDCount' => 'D&D回数',
        'groupingDDCount' => 'グループ化D&D回数',
        'groupingCountbool' => 'グループ化使用率',
        'xUTurnCount' => 'X軸Uターン回数',
        'yUTurnCount' => 'Y軸Uターン回数',
        'register_move_count1' => 'レジスタ➡レジスタへの移動回数',
        'register_move_count2' => 'レジスタ➡レジスタ外への移動回数',
        'register_move_count3' => 'レジスタ外➡レジスタへの移動回数',
        'register01count1' => 'レジスタ➡レジスタへの移動有無の割合',
        'register01count2' => 'レジスタ➡レジスタ外への移動有無の割合',
        'register01count3' => 'レジスタ外➡レジスタへの移動有無の割合',
        'registerDDCount' => 'レジスタ内D&D回数',
        'stopcount' => '静止回数',
        'xUTurnCountDD' => 'X軸UターンD&D回数',
        'yUTurnCountDD' => 'Y軸UターンD&D回数',
        'FromlastdropToanswerTime' => '最終ドロップ後時間',
    ];
}

function feature_display_description(string $feature, string $context = 'raw'): string
{
    $descriptions = [
        'Time' => '問題の解答開始から終了までにかかった時間です。',
        'distance' => '解答中にマウスカーソルが移動した距離の合計です。',
        'averageSpeed' => '解答中のマウスカーソルの平均移動速度です。',
        'maxSpeed' => '解答中のマウスカーソルの最大移動速度です。',
        'thinkingTime' => '解答開始から最初のドラッグまでの時間です。',
        'answeringTime' => '最初のドラッグから解答終了までの時間です。',
        'totalStopTime' => 'マウスカーソルが静止していた時間の合計です。',
        'maxStopTime' => '1回のマウスカーソル静止のうち、最も長かった時間です。',
        'stopcount' => '解答中にマウスカーソルが停止した回数です。',
        'totalDDIntervalTime' => 'ドラッグ＆ドロップから次のドラッグ開始までの間隔時間の合計です。',
        'maxDDIntervalTime' => 'ドラッグ＆ドロップ間の間隔時間の最大値です。',
        'totalDDTime' => 'ドラッグ＆ドロップにかかった時間の合計です。',
        'maxDDTime' => '1回のドラッグ＆ドロップにかかった時間の最大値です。',
        'minDDTime' => '1回のドラッグ＆ドロップにかかった時間の最小値です。',
        'DDCount' => 'ドラッグ＆ドロップを行った回数です。',
        'groupingDDCount' => 'グルーピングされた単語をドラッグ＆ドロップした回数です。',
        'groupingCountbool' => 'グルーピング機能を使用したかどうかを0または1で表します。',
        'xUTurnCount' => 'マウスが横方向に折り返した回数です。',
        'yUTurnCount' => 'マウスが縦方向に折り返した回数です。',
        'xUTurnCountDD' => 'ドラッグ中にマウスが横方向へ折り返した回数です。',
        'yUTurnCountDD' => 'ドラッグ中にマウスが縦方向へ折り返した回数です。',
        'register_move_count1' => 'マウスカーソルがレジスタからレジスタに移動した回数。多いほど、迷いに起因している可能性があります。これは迷いの際にレジスタを使用し、単語をチャンク単位で分割して考えている可能性があります。',
        'register_move_count2' => 'マウスカーソルがレジスタからレジスタ外に移動した回数。多いほど、迷いに起因している可能性があります。これは迷いの際にレジスタを使用し、単語をチャンク単位で分割して考えている可能性があります。',
        'register_move_count3' => 'マウスカーソルがレジスタ外からレジスタに移動した回数。多いほど、迷いに起因している可能性があります。これは迷いの際にレジスタを使用し、単語をチャンク単位で分割して考えている可能性があります。',
        'register01count1' => 'マウスカーソルがレジスタからレジスタに移動したかの有無。使用している場合、迷いに起因している可能性があります。これは迷いの際にレジスタを使用し、単語をチャンク単位で分割して考えている可能性があります。',
        'register01count2' => 'マウスカーソルがレジスタからレジスタ外に移動したかの有無。使用している場合、迷いに起因している可能性があります。これは迷いの際にレジスタを使用し、単語をチャンク単位で分割して考えている可能性があります。',
        'register01count3' => 'マウスカーソルがレジスタ外からレジスタに移動したかの有無。使用している場合、迷いに起因している可能性があります。これは迷いの際にレジスタを使用し、単語をチャンク単位で分割して考えている可能性があります。',
        'registerDDCount' => 'レジスタの内外をまたぐ、またはレジスタ間のドラッグ＆ドロップ回数の合計です。',
        'register_notDDCount' => 'レジスタ外で行われたドラッグ＆ドロップ回数です。',
        'FromlastdropToanswerTime' => '最後のドロップから解答終了までの時間です。',
    ];

    if (feature_display_is_aggregate_context($context)) {
        $descriptions['register01count1'] = 'マウスカーソルがレジスタからレジスタに移動したかの有無の割合。使用割合が高いほど、迷いに起因している可能性があります。これは迷いの際にレジスタを使用し、単語をチャンク単位で分割して考えている可能性があります。';
        $descriptions['register01count2'] = 'マウスカーソルがレジスタからレジスタ外に移動したかの有無の割合。使用割合が高いほど、迷いに起因している可能性があります。これは迷いの際にレジスタを使用し、単語をチャンク単位で分割して考えている可能性があります。';
        $descriptions['register01count3'] = 'マウスカーソルがレジスタ外からレジスタに移動したかの有無の割合。使用割合が高いほど、迷いに起因している可能性があります。これは迷いの際にレジスタを使用し、単語をチャンク単位で分割して考えている可能性があります。';
    }

    if (isset($descriptions[$feature])) {
        return $descriptions[$feature];
    }
    if (preg_match('/^register_fix_count([1-4])$/', $feature, $matches)) {
        return "レジスタ{$matches[1]}で単語の配置を修正した回数です。";
    }
    if (preg_match('/^register_delete_count([1-4])$/', $feature, $matches)) {
        return "レジスタ{$matches[1]}から単語を削除した回数です。";
    }
    if (preg_match('/^register_allDelete_count([1-4])$/', $feature, $matches)) {
        return "レジスタ{$matches[1]}の単語をすべて削除した回数です。";
    }
    if (preg_match('/^register_notallDelete_count([1-4])$/', $feature, $matches)) {
        return "レジスタ{$matches[1]}の単語を一部だけ削除した回数です。";
    }

    return (feature_display_name_map($context)[$feature] ?? $feature) . 'の計測値です。';
}

function feature_display_descriptions_for_features(array $features, string $context = 'raw'): array
{
    $descriptions = [];
    foreach ($features as $feature) {
        $descriptions[(string)$feature] = feature_display_description((string)$feature, $context);
    }

    return $descriptions;
}

