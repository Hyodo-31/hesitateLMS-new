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

function feature_display_type(string $feature): string
{
    $key = strtolower($feature);

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
        ];
    }

    return [
        'time' => '秒',
        'distance' => 'ピクセル',
        'speed' => 'ピクセル/秒',
        'count' => '回',
    ];
}

function feature_display_unit(string $feature): string
{
    $units = feature_display_units();
    return $units[feature_display_type($feature)] ?? '';
}

function feature_display_label_has_unit(string $label, string $type): bool
{
    $checks = [
        'time' => ['秒', '（秒）', '(秒)', '(s)', '（s）', ' sec', ' second'],
        'distance' => ['ピクセル', '（ピクセル）', '(ピクセル)', 'px', 'pixel'],
        'speed' => ['ピクセル/秒', 'px/s', 'px/sec', 'pixel/s', 'pixel/sec'],
        'count' => ['（回）', '(回)', '(times)', ' times'],
    ];

    $lowerLabel = strtolower($label);
    foreach ($checks[$type] ?? [] as $needle) {
        if (str_contains($lowerLabel, strtolower($needle))) {
            return true;
        }
    }

    return false;
}

function feature_display_label(string $feature, ?string $label = null): string
{
    $label = $label ?? $feature;
    $type = feature_display_type($feature);
    $unit = feature_display_unit($feature);

    if ($unit === '' || feature_display_label_has_unit($label, $type)) {
        return $label;
    }

    return "{$label}（{$unit}）";
}

function feature_display_labels(array $featureLabels): array
{
    foreach ($featureLabels as $feature => $label) {
        $featureLabels[$feature] = feature_display_label((string)$feature, (string)$label);
    }

    return $featureLabels;
}

function feature_display_numeric_value(string $feature, $value): ?float
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        return null;
    }

    $number = (float)$value;
    $type = feature_display_type($feature);

    if ($type === 'time') {
        return $number / 1000;
    }

    if ($type === 'speed') {
        return $number * 1000;
    }

    return $number;
}

function feature_storage_numeric_value(string $feature, $value): ?float
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        return null;
    }

    $number = (float)$value;
    $type = feature_display_type($feature);

    if ($type === 'time') {
        return $number * 1000;
    }

    if ($type === 'speed') {
        return $number / 1000;
    }

    return $number;
}

function feature_display_value(string $feature, $value, int $decimals = 2, bool $withUnit = true): string
{
    $displayValue = feature_display_numeric_value($feature, $value);
    if ($displayValue === null) {
        return '-';
    }

    $formatted = number_format(round($displayValue, $decimals), $decimals, '.', '');
    $unit = $withUnit ? feature_display_unit($feature) : '';

    return $unit === '' ? $formatted : "{$formatted}{$unit}";
}

function feature_display_metadata(array $features): array
{
    $metadata = [];
    foreach ($features as $feature) {
        $type = feature_display_type((string)$feature);
        $displayScale = 1;
        $storageScale = 1;

        if ($type === 'time') {
            $displayScale = 0.001;
            $storageScale = 1000;
        } elseif ($type === 'speed') {
            $displayScale = 1000;
            $storageScale = 0.001;
        }

        $metadata[(string)$feature] = [
            'type' => $type,
            'unit' => feature_display_unit((string)$feature),
            'displayScale' => $displayScale,
            'storageScale' => $storageScale,
        ];
    }

    return $metadata;
}

