<?php

if (!function_exists('get_teacher_base_group')) {
    function get_teacher_base_group(mysqli $conn, $teacherId): ?array
    {
        $stmt = $conn->prepare(
            "SELECT c.ClassID, c.ClassName
             FROM classes c
             LEFT JOIN classteacher ct ON c.ClassID = ct.ClassID
             WHERE c.TID = ? OR ct.TID = ?
             ORDER BY c.ClassID ASC
             LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('ss', $teacherId, $teacherId);
        $stmt->execute();
        $result = $stmt->get_result();
        $baseGroup = $result->fetch_assoc() ?: null;
        $stmt->close();

        return $baseGroup;
    }
}

if (!function_exists('ensure_teacher_base_group_link')) {
    function ensure_teacher_base_group_link(mysqli $conn, $teacherId, $baseGroupId): void
    {
        $stmt = $conn->prepare("SELECT 1 FROM classteacher WHERE TID = ? AND ClassID = ? LIMIT 1");
        if (!$stmt) {
            return;
        }

        $stmt->bind_param('si', $teacherId, $baseGroupId);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();

        if ($exists) {
            return;
        }

        $stmt = $conn->prepare("INSERT INTO classteacher (TID, ClassID) VALUES (?, ?)");
        if ($stmt) {
            $stmt->bind_param('si', $teacherId, $baseGroupId);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('teacher_owns_base_group')) {
    function teacher_owns_base_group(mysqli $conn, $teacherId, $baseGroupId): bool
    {
        $stmt = $conn->prepare(
            "SELECT 1
             FROM classes c
             LEFT JOIN classteacher ct ON c.ClassID = ct.ClassID
             WHERE c.ClassID = ? AND (c.TID = ? OR ct.TID = ?)
             LIMIT 1"
        );
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('iss', $baseGroupId, $teacherId, $teacherId);
        $stmt->execute();
        $owns = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();

        return $owns;
    }
}
