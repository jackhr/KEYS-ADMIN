<?php

include 'connection.php';
include 'admin-auth.php';

header('Content-Type: application/json');

require_admin_json();

$admin_user = get_admin_user();

function send_json($payload, $status = 200)
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function table_exists($con, $table)
{
    $safe_table = mysqli_real_escape_string($con, (string) $table);
    $result = mysqli_query($con, "SHOW TABLES LIKE '{$safe_table}'");
    if (!$result) return false;
    return $result->num_rows > 0;
}

function get_table_columns($con, $table)
{
    $columns = [];
    $result = mysqli_query($con, "SHOW COLUMNS FROM `{$table}`");
    if (!$result) return $columns;
    while ($row = mysqli_fetch_assoc($result)) {
        if (!empty($row['Field'])) $columns[] = $row['Field'];
    }
    return $columns;
}

function bind_params($stmt, $types, $params)
{
    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = &$params[$key];
    }
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function format_change_value($value)
{
    if ($value === null) return '—';
    if (is_string($value) && trim($value) === '') return '—';
    if (is_bool($value)) return $value ? 'true' : 'false';
    return (string) $value;
}

function resolve_admin_display_name($con, $admin_user)
{
    $fallback = 'admin';
    if (is_array($admin_user)) {
        $fallback = $admin_user['username'] ?? $fallback;
        $session_name = trim((string) ($admin_user['name'] ?? $admin_user['full_name'] ?? $admin_user['display_name'] ?? ''));
        if ($session_name !== '') return $session_name;
    }

    $admin_id = is_array($admin_user) ? (int) ($admin_user['id'] ?? 0) : 0;
    if ($admin_id <= 0 || !table_exists($con, 'admin_users')) {
        return $fallback;
    }

    $columns = get_table_columns($con, 'admin_users');
    $name_columns = [];
    foreach (['name', 'full_name', 'display_name', 'first_name', 'last_name'] as $column) {
        if (in_array($column, $columns, true)) $name_columns[] = $column;
    }
    if (count($name_columns) === 0) return $fallback;

    $select_cols = implode(', ', array_map(function ($column) {
        return "`{$column}`";
    }, $name_columns));

    $stmt = $con->prepare("SELECT {$select_cols} FROM admin_users WHERE id = ? LIMIT 1");
    if (!$stmt) return $fallback;
    $stmt->bind_param('i', $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) return $fallback;

    $name = '';
    foreach (['name', 'full_name', 'display_name'] as $column) {
        if (!empty($row[$column])) {
            $name = trim((string) $row[$column]);
            if ($name !== '') return $name;
        }
    }

    $first = trim((string) ($row['first_name'] ?? ''));
    $last = trim((string) ($row['last_name'] ?? ''));
    $combined = trim($first . ' ' . $last);

    return $combined !== '' ? $combined : $fallback;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data)) {
    send_json(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
}

$action = $data['action'] ?? '';
if ($action === 'fetch_history') {
    $order_id = isset($data['order_id']) ? (int) $data['order_id'] : 0;
    if ($order_id <= 0) {
        send_json(['success' => false, 'message' => 'Invalid order id.'], 400);
    }
    if (!table_exists($con, 'order_request_history')) {
        send_json(['success' => false, 'message' => 'Order history unavailable.'], 404);
    }
    $history = [];
    $stmt = $con->prepare("SELECT * FROM order_request_history WHERE order_request_id = ? ORDER BY created_at DESC LIMIT 100");
    if ($stmt) {
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        $stmt->close();
    }
    send_json(['success' => true, 'history' => $history]);
}

if ($action !== 'update_order') {
    send_json(['success' => false, 'message' => 'Unsupported action.'], 400);
}

$order = $data['order'] ?? null;
$contact = $data['contact'] ?? null;

if (!is_array($order)) {
    send_json(['success' => false, 'message' => 'Missing order data.'], 400);
}

$order_id = isset($order['id']) ? (int) $order['id'] : 0;
if ($order_id <= 0) {
    send_json(['success' => false, 'message' => 'Invalid order id.'], 400);
}

if (!table_exists($con, 'order_requests')) {
    send_json(['success' => false, 'message' => 'Order requests table not found.'], 500);
}

$order_columns = get_table_columns($con, 'order_requests');
$has_status = in_array('status', $order_columns, true);

$order_stmt = $con->prepare("SELECT * FROM order_requests WHERE id = ? LIMIT 1");
if (!$order_stmt) {
    send_json(['success' => false, 'message' => 'Unable to load order.'], 500);
}
$order_stmt->bind_param('i', $order_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();
$order_current = $order_result->fetch_assoc();
$order_stmt->close();

if (!$order_current) {
    send_json(['success' => false, 'message' => 'Order not found.'], 404);
}

$contact_id = (int) ($order_current['contact_info_id'] ?? 0);
$contact_current = [];
if ($contact_id > 0 && table_exists($con, 'contact_info')) {
    $contact_stmt = $con->prepare("SELECT * FROM contact_info WHERE id = ? LIMIT 1");
    if ($contact_stmt) {
        $contact_stmt->bind_param('i', $contact_id);
        $contact_stmt->execute();
        $contact_result = $contact_stmt->get_result();
        $contact_current = $contact_result->fetch_assoc() ?: [];
        $contact_stmt->close();
    }
}

$order_before = $order_current;
$contact_before = $contact_current;

$updates = [];
$params = [];
$types = '';
$changes = [];

if (in_array('status', $order_columns, true)) {
    $status = trim((string) ($order['status'] ?? 'pending'));
    if ($status === '') $status = 'pending';
    $updates[] = "`status` = ?";
    $params[] = $status;
    $types .= 's';
    if (($order_current['status'] ?? '') !== $status) {
        $changes['status'] = [$order_current['status'] ?? null, $status];
    }
}

if (in_array('confirmed', $order_columns, true)) {
    $status_value = strtolower(trim((string) ($order['status'] ?? 'pending')));
    $confirmed = in_array($status_value, ['confirmed', 'completed'], true) ? 1 : 0;
    $updates[] = "`confirmed` = ?";
    $params[] = $confirmed;
    $types .= 'i';
}

if (count($updates) > 0) {
    $sql = "UPDATE order_requests SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        send_json(['success' => false, 'message' => 'Failed to prepare update.'], 500);
    }
    $types .= 'i';
    $params[] = $order_id;
    bind_params($stmt, $types, $params);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        send_json(['success' => false, 'message' => "Update failed: {$error}"], 500);
    }
    $stmt->close();
}

$refresh_query = "
    SELECT
        o.id,
        o.created_at,
        o.pick_up,
        o.drop_off,
        o.pick_up_location,
        o.drop_off_location,
        o.confirmed,
        o.sub_total,
        o.days,
        o.car_id,
        o.contact_info_id" . ($has_status ? ", o.status" : "") . ",
        v.name AS vehicle_name,
        v.type AS vehicle_type,
        v.slug AS vehicle_slug,
        v.base_price_USD AS vehicle_base_price,
        v.people AS vehicle_people,
        v.bags AS vehicle_bags,
        v.doors AS vehicle_doors,
        v.manual AS vehicle_manual,
        v.ac AS vehicle_ac,
        v.`4wd` AS vehicle_4wd,
        c.first_name,
        c.last_name,
        c.driver_license,
        c.hotel,
        c.country_or_region,
        c.street,
        c.town_or_city,
        c.state_or_county,
        c.email,
        c.phone
    FROM order_requests o
    LEFT JOIN vehicles v ON v.id = o.car_id
    LEFT JOIN contact_info c ON c.id = o.contact_info_id
    WHERE o.id = ?
    LIMIT 1
";
$refresh_stmt = $con->prepare($refresh_query);
if (!$refresh_stmt) {
    send_json(['success' => false, 'message' => 'Unable to load updated order.'], 500);
}
$refresh_stmt->bind_param('i', $order_id);
$refresh_stmt->execute();
$refresh_result = $refresh_stmt->get_result();
$updated = $refresh_result->fetch_assoc();
$refresh_stmt->close();

if (!$updated) {
    send_json(['success' => false, 'message' => 'Updated order not found.'], 500);
}

$updated['customer_name'] = trim(($updated['first_name'] ?? '') . ' ' . ($updated['last_name'] ?? ''));
if (!$has_status) {
    $updated['status'] = ((int) ($updated['confirmed'] ?? 0) === 1) ? 'confirmed' : 'pending';
}

if (table_exists($con, 'order_request_history') && count($changes) > 0) {
    $history_columns = get_table_columns($con, 'order_request_history');

    $contact_after = [];
    if ($contact_id > 0 && table_exists($con, 'contact_info')) {
        $contact_stmt = $con->prepare("SELECT * FROM contact_info WHERE id = ? LIMIT 1");
        if ($contact_stmt) {
            $contact_stmt->bind_param('i', $contact_id);
            $contact_stmt->execute();
            $contact_result = $contact_stmt->get_result();
            $contact_after = $contact_result->fetch_assoc() ?: [];
            $contact_stmt->close();
        }
    }

    $change_lines = [];
    foreach ($changes as $field => $pair) {
        $old_value = format_change_value($pair[0] ?? null);
        $new_value = format_change_value($pair[1] ?? null);
        $change_lines[] = "{$field}: {$old_value} → {$new_value}";
    }

    $history_data = [
        'order_request_id' => $order_id,
        'admin_user' => resolve_admin_display_name($con, $admin_user),
        'action' => 'update',
        'change_summary' => implode("\n", $change_lines),
        'previous_data' => json_encode(['order' => $order_before, 'contact' => $contact_before]),
        'new_data' => json_encode(['order' => $updated, 'contact' => $contact_after]),
        'created_at' => null
    ];

    $columns = [];
    $placeholders = [];
    $values = [];
    $types = '';
    foreach ($history_data as $column => $value) {
        if (!in_array($column, $history_columns, true)) continue;
        if ($column === 'created_at') continue;
        $columns[] = "`{$column}`";
        $placeholders[] = '?';
        $values[] = $value;
        $types .= 's';
    }

    if (count($columns) > 0) {
        $sql = "INSERT INTO order_request_history (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $con->prepare($sql);
        if ($stmt) {
            bind_params($stmt, $types, $values);
            $stmt->execute();
            $stmt->close();
        }
    }
}

send_json(['success' => true, 'order' => $updated]);
