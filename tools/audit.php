<?php
/**
 * Audit logging helper
 * Usage: include 'tools/audit.php'; then call audit_log(...) or helpers below
 *
 * This file provides small helpers to record INSERT/UPDATE/DELETE events
 * into a centralized `audit_log` table.
 */

if (!function_exists('audit_log')) {
    /**
     * Allowed tables and their primary key column name
     */
    function audit_allowed_tables(): array {
        return [
            'patients' => 'id',
            'consultants' => 'id',
            'billing_items' => 'id',
            'billings' => 'bill_id',
            'work_tracker' => 'id',
            'roles' => 'id',
            'users' => 'id',
            'credential' => 'id',
            'user_session' => 'id'
        ];
    }

    /**
     * Insert a row into audit_log
     * @param mysqli $conn
     * @param string $table
     * @param int $recordId
     * @param string $action one of INSERT, UPDATE, DELETE
     * @param array|null $oldData
     * @param array|null $newData
     * @param int|null $changedBy
     * @return bool
     */
    function audit_log(mysqli $conn, string $table, int $recordId, string $action, ?array $oldData = null, ?array $newData = null, ?int $changedBy = null): bool {
        $allowed = audit_allowed_tables();
        if (!array_key_exists($table, $allowed)) {
            return false; // not allowed table
        }

        $action = strtoupper($action);
        if (!in_array($action, ['INSERT','UPDATE','DELETE'], true)) return false;

        // encode JSON safely (allow partial output on error)
        $oldJson = $oldData === null ? null : json_encode($oldData, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        $newJson = $newData === null ? null : json_encode($newData, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        $sql = "INSERT INTO audit_log (table_name, record_id, action_type, old_data, new_data, changed_by, changed_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            error_log('[audit] prepare failed: ' . mysqli_error($conn) . " -- SQL: $sql");
            return false;
        }

        // variables for bind (must be variables, not expressions)
        $tbl = $table;
        $rid = (int)$recordId;
        $act = $action;
        $old = $oldJson;
        $new = $newJson;
        $chg = $changedBy === null ? null : (int)$changedBy;

        // types: s = table_name, i = record_id, s = action, s = old_data, s = new_data, i = changed_by
        $types = 'sisssi';

        // bind params - handle potential nulls by using variables
        if (!mysqli_stmt_bind_param($stmt, $types, $tbl, $rid, $act, $old, $new, $chg)) {
            error_log('[audit] bind_param failed: ' . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return false;
        }

        $ok = mysqli_stmt_execute($stmt);
        if (!$ok) {
            error_log('[audit] execute failed: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
        return (bool)$ok;
    }

    /**
     * Fetch a single row by primary key for a given allowed table
     */
    function audit_fetch_row(mysqli $conn, string $table, int $recordId): ?array {
        $allowed = audit_allowed_tables();
        if (!array_key_exists($table, $allowed)) return null;
        $pk = $allowed[$table];
        $sql = "SELECT * FROM `" . $conn->real_escape_string($table) . "` WHERE `" . $conn->real_escape_string($pk) . "` = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'i', $recordId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res) ?: null;
        mysqli_stmt_close($stmt);
        return $row;
    }

    /**
     * Convenience: log insert
     */
    function audit_insert(mysqli $conn, string $table, int $recordId, array $newData, ?int $changedBy = null): bool {
        return audit_log($conn, $table, $recordId, 'INSERT', null, $newData, $changedBy);
    }

    /**
     * Convenience: log update (supply both old and new)
     */
    function audit_update(mysqli $conn, string $table, int $recordId, array $oldData, array $newData, ?int $changedBy = null): bool {
        return audit_log($conn, $table, $recordId, 'UPDATE', $oldData, $newData, $changedBy);
    }

    /**
     * Convenience: log delete
     */
    function audit_delete(mysqli $conn, string $table, int $recordId, array $oldData, ?int $changedBy = null): bool {
        return audit_log($conn, $table, $recordId, 'DELETE', $oldData, null, $changedBy);
    }
}

// Example usage in comments:
/*
include 'tools/audit.php';
// on insert
$newRow = ['patient_id'=>1,'patient_name'=>'Asha', 'total_amount'=>600.0];
audit_insert($conn, 'billings', $newBillId, $newRow, $USER['id'] ?? null);

// on update
$old = audit_fetch_row($conn, 'billings', $billId);
// ... perform update ...
$new = audit_fetch_row($conn, 'billings', $billId);
audit_update($conn, 'billings', $billId, $old, $new, $USER['id'] ?? null);
*/
