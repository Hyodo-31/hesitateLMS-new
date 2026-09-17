<?php

if (!function_exists('usage_log_insert_table')) {
    function usage_log_insert_table(string $sql): string
    {
        if (preg_match('/\bINSERT\s+INTO\s+`?([a-z0-9_]+)`?/i', $sql, $matches) === 1) {
            return strtolower($matches[1]);
        }
        return 'unknown';
    }
}

if (!function_exists('usage_log_report_database_error')) {
    function usage_log_report_database_error(
        string $feature,
        string $table,
        Throwable $error,
        ?mysqli $connection = null
    ): void {
        $errno = (int)$error->getCode();
        $sqlState = 'unknown';
        if ($error instanceof mysqli_sql_exception) {
            $sqlState = $error->getSqlState();
        } elseif ($connection !== null) {
            $errno = $connection->errno;
            $sqlState = $connection->sqlstate;
        }

        error_log(sprintf(
            '[Usage log DB] feature=%s table=%s sqlstate=%s errno=%d message=%s',
            $feature,
            $table,
            $sqlState,
            $errno,
            $error->getMessage()
        ));
    }
}

if (!function_exists('usage_log_throw_database_error')) {
    function usage_log_throw_database_error(
        string $feature,
        string $table,
        Throwable $error,
        ?mysqli $connection = null
    ): void {
        usage_log_report_database_error($feature, $table, $error, $connection);
        throw new RuntimeException('ログ保存に失敗しました。', 0, $error);
    }
}

if (!function_exists('usage_log_throw_statement_error')) {
    function usage_log_throw_statement_error(
        string $feature,
        string $table,
        int $errno,
        string $sqlState,
        string $message
    ): void {
        error_log(sprintf(
            '[Usage log DB] feature=%s table=%s sqlstate=%s errno=%d message=%s',
            $feature,
            $table,
            $sqlState,
            $errno,
            $message
        ));
        throw new RuntimeException('ログ保存に失敗しました。');
    }
}
