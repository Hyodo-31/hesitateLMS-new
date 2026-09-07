<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

set_time_limit(0);
ini_set('session.save_path', sys_get_temp_dir());
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$options = getopt('', ['apply', 'help']);
if (isset($options['help'])) {
    echo "Usage:\n";
    echo "  php teacher/maintenance/recalculate_max_speed.php          # dry-run\n";
    echo "  php teacher/maintenance/recalculate_max_speed.php --apply  # backup and update\n";
    exit(0);
}

$apply = array_key_exists('apply', $options);

ob_start();
/** @var mysqli $conn */
$conn = require dirname(__DIR__, 2) . '/dbc.php';
ob_end_clean();
$conn->set_charset('utf8mb4');

function max_speed_stats(array $values): array
{
    $values = array_values(array_filter($values, static fn($value): bool => is_numeric($value)));
    sort($values, SORT_NUMERIC);
    $count = count($values);
    if ($count === 0) {
        return ['count' => 0, 'min' => null, 'median' => null, 'max' => null];
    }
    $middle = intdiv($count, 2);
    $median = $count % 2 === 0
        ? ((float)$values[$middle - 1] + (float)$values[$middle]) / 2
        : (float)$values[$middle];

    return [
        'count' => $count,
        'min' => (float)$values[0],
        'median' => $median,
        'max' => (float)$values[$count - 1],
    ];
}

function max_speed_print_stats(string $label, array $stats): void
{
    printf(
        "%s: 件数=%d / 最小=%.6fpx/ms (%.2fpx/s) / 中央値=%.6fpx/ms (%.2fpx/s) / 最大=%.6fpx/ms (%.2fpx/s)\n",
        $label,
        $stats['count'],
        $stats['min'],
        $stats['min'] * 1000,
        $stats['median'],
        $stats['median'] * 1000,
        $stats['max'],
        $stats['max'] * 1000
    );
}

try {
    $coverageSql = 'SELECT tf.UID, tf.WID, tf.attempt, COUNT(lm.Time) AS point_count
                    FROM test_featurevalue tf
                    LEFT JOIN linedatamouse lm
                      ON lm.UID = tf.UID AND lm.WID = tf.WID AND lm.attempt = tf.attempt
                    GROUP BY tf.UID, tf.WID, tf.attempt
                    HAVING point_count < 2';
    $coverageResult = $conn->query($coverageSql);
    $insufficient = $coverageResult->fetch_all(MYSQLI_ASSOC);
    $coverageResult->free();
    if ($insufficient) {
        fwrite(STDERR, "軌跡が2点未満の履歴が" . count($insufficient) . "件あるため、再計算を中止しました。\n");
        foreach (array_slice($insufficient, 0, 10) as $row) {
            fwrite(STDERR, sprintf(
                "  UID=%s WID=%s attempt=%s points=%s\n",
                $row['UID'], $row['WID'], $row['attempt'], $row['point_count']
            ));
        }
        exit(2);
    }

    $sql = 'SELECT tf.UID, tf.WID, tf.attempt, tf.maxSpeed AS old_max_speed,
                   lm.Time, lm.X, lm.Y
            FROM test_featurevalue tf
            JOIN linedatamouse lm
              ON lm.UID = tf.UID AND lm.WID = tf.WID AND lm.attempt = tf.attempt
            ORDER BY tf.UID, tf.WID, tf.attempt, lm.Time';
    $result = $conn->query($sql, MYSQLI_USE_RESULT);
    $records = [];
    $currentKey = null;
    $current = null;
    $previousPoint = null;

    $finishRecord = static function () use (&$records, &$current): void {
        if ($current !== null) {
            $records[] = $current;
        }
    };

    while ($row = $result->fetch_assoc()) {
        $key = $row['UID'] . ':' . $row['WID'] . ':' . $row['attempt'];
        if ($key !== $currentKey) {
            $finishRecord();
            $currentKey = $key;
            $current = [
                'uid' => (int)$row['UID'],
                'wid' => (int)$row['WID'],
                'attempt' => (int)$row['attempt'],
                'old' => $row['old_max_speed'] === null ? null : (float)$row['old_max_speed'],
                'new' => 0.0,
            ];
            $previousPoint = null;
        }

        $point = ['time' => (float)$row['Time'], 'x' => (float)$row['X'], 'y' => (float)$row['Y']];
        if ($previousPoint !== null) {
            $deltaTime = $point['time'] - $previousPoint['time'];
            if ($deltaTime > 0) {
                $deltaX = $point['x'] - $previousPoint['x'];
                $deltaY = $point['y'] - $previousPoint['y'];
                $speed = sqrt(($deltaX ** 2) + ($deltaY ** 2)) / $deltaTime;
                if ($speed > $current['new']) {
                    $current['new'] = $speed;
                }
            }
        }
        $previousPoint = $point;
    }
    $finishRecord();
    $result->free();

    $oldStats = max_speed_stats(array_column($records, 'old'));
    $newStats = max_speed_stats(array_column($records, 'new'));
    $changed = array_values(array_filter($records, static function (array $record): bool {
        $floatStorageTolerance = max(0.0001, abs($record['new']) * 0.000005);
        return $record['old'] === null || abs($record['old'] - $record['new']) > $floatStorageTolerance;
    }));
    usort($changed, static function (array $left, array $right): int {
        $leftDifference = abs((float)$left['old'] - $left['new']);
        $rightDifference = abs((float)$right['old'] - $right['new']);
        return $rightDifference <=> $leftDifference;
    });

    echo $apply ? "maxSpeed履歴補正（適用モード）\n" : "maxSpeed履歴補正（事前確認モード・DB更新なし）\n";
    max_speed_print_stats('補正前', $oldStats);
    max_speed_print_stats('補正後', $newStats);
    echo '変更対象: ' . count($changed) . ' / ' . count($records) . "件\n";
    echo "変更量が大きい例:\n";
    foreach (array_slice($changed, 0, 10) as $record) {
        printf(
            "  UID=%d WID=%d attempt=%d : %.6f -> %.6fpx/ms (%.2fpx/s)\n",
            $record['uid'], $record['wid'], $record['attempt'], (float)$record['old'],
            $record['new'], $record['new'] * 1000
        );
    }

    if (!$apply) {
        echo "\n更新する場合は --apply を付けて再実行してください。\n";
        exit(0);
    }

    $conn->query('CREATE TABLE IF NOT EXISTS test_featurevalue_maxspeed_backup (
        run_id VARCHAR(40) NOT NULL,
        UID INT NOT NULL,
        WID INT NOT NULL,
        attempt INT NOT NULL,
        old_maxSpeed DOUBLE NULL,
        new_maxSpeed DOUBLE NOT NULL,
        backed_up_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (run_id, UID, WID, attempt)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $runId = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    $conn->begin_transaction();
    try {
        $backup = $conn->prepare(
            'INSERT INTO test_featurevalue_maxspeed_backup
             (run_id, UID, WID, attempt, old_maxSpeed, new_maxSpeed)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $update = $conn->prepare(
            'UPDATE test_featurevalue SET maxSpeed = ? WHERE UID = ? AND WID = ? AND attempt = ?'
        );
        foreach ($records as $record) {
            $backup->bind_param(
                'siiidd',
                $runId,
                $record['uid'],
                $record['wid'],
                $record['attempt'],
                $record['old'],
                $record['new']
            );
            $backup->execute();
            $update->bind_param('diii', $record['new'], $record['uid'], $record['wid'], $record['attempt']);
            $update->execute();
        }
        $backup->close();
        $update->close();
        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }

    printf("\n%d件を更新しました。復元用バックアップrun_id: %s\n", count($records), $runId);
} catch (Throwable $error) {
    fwrite(STDERR, 'maxSpeed再計算に失敗しました: ' . $error->getMessage() . "\n");
    exit(1);
} finally {
    $conn->close();
}
