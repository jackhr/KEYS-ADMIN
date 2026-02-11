<?php

include 'connection.php';
include 'admin-auth.php';

header('Content-Type: application/json');

require_admin_json();

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

function pick_first_column($columns, $candidates)
{
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) return $candidate;
    }
    return null;
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

function normalize_discount_row($row, $field_map)
{
    if (!$row || !$field_map) return null;
    $normalized = [
        'price_USD' => $field_map['price_USD'] ? ($row[$field_map['price_USD']] ?? null) : null,
        'price_XCD' => $field_map['price_XCD'] ? ($row[$field_map['price_XCD']] ?? null) : null
    ];
    if (!empty($field_map['days'])) {
        $normalized['days'] = $row[$field_map['days']] ?? null;
    }
    return $normalized;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data)) {
    send_json(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
}

if (($data['action'] ?? '') !== 'update_discount') {
    send_json(['success' => false, 'message' => 'Unsupported action.'], 400);
}

$vehicle_id = isset($data['vehicle_id']) ? (int) $data['vehicle_id'] : 0;
if ($vehicle_id <= 0) {
    send_json(['success' => false, 'message' => 'Invalid vehicle id.'], 400);
}

$discount_data = $data['discount'] ?? null;
if (!is_array($discount_data)) {
    send_json(['success' => false, 'message' => 'Missing discount data.'], 400);
}

$discount_mode = 'none';
$discount_field_map = [
    'id' => null,
    'vehicle_id' => null,
    'price_USD' => null,
    'price_XCD' => null,
    'days' => null
];

if (table_exists($con, 'vehicle_discounts')) {
    $discount_columns = get_table_columns($con, 'vehicle_discounts');
    $discount_field_map['id'] = pick_first_column($discount_columns, ['id']);
    $discount_field_map['vehicle_id'] = pick_first_column($discount_columns, ['vehicle_id', 'vehicle']);
    $discount_field_map['price_USD'] = pick_first_column($discount_columns, ['price_USD']);
    $discount_field_map['price_XCD'] = pick_first_column($discount_columns, ['price_XCD']);
    $discount_field_map['days'] = pick_first_column($discount_columns, ['days']);

    if ($discount_field_map['vehicle_id'] && ($discount_field_map['price_USD'] || $discount_field_map['price_XCD'])) {
        $discount_mode = 'table';
    }
}

if ($discount_mode === 'none') {
    send_json(['success' => false, 'message' => 'Discount fields not found.'], 500);
}

$price_usd = $discount_data['price_USD'] ?? null;
if ($price_usd === '') $price_usd = null;
if ($price_usd !== null && !is_numeric($price_usd)) {
    send_json(['success' => false, 'message' => 'USD price must be numeric.'], 422);
}
$price_xcd = $discount_data['price_XCD'] ?? null;
if ($price_xcd === '') $price_xcd = null;
if ($price_xcd !== null && !is_numeric($price_xcd)) {
    send_json(['success' => false, 'message' => 'XCD price must be numeric.'], 422);
}

$days = $discount_data['days'] ?? null;
if ($days === '') $days = null;
if ($days !== null && !is_numeric($days)) {
    send_json(['success' => false, 'message' => 'Days must be numeric.'], 422);
}
$days = $days !== null ? (int) $days : null;

if ($discount_mode === 'table') {
    $vehicle_column = $discount_field_map['vehicle_id'];
    $id_column = $discount_field_map['id'];

    if ($price_usd === null && $price_xcd === null) {
        $stmt = $con->prepare("DELETE FROM vehicle_discounts WHERE `{$vehicle_column}` = ?");
        if (!$stmt) {
            send_json(['success' => false, 'message' => 'Failed to remove discount.'], 500);
        }
        $stmt->bind_param('i', $vehicle_id);
        $stmt->execute();
        $stmt->close();
        send_json(['success' => true, 'discount' => null]);
    }

    $existing = null;
    $select = $con->prepare("SELECT * FROM vehicle_discounts WHERE `{$vehicle_column}` = ? ORDER BY `{$id_column}` DESC LIMIT 1");

    if ($select) {
        $select->bind_param('i', $vehicle_id);
        $select->execute();
        $result = $select->get_result();
        $existing = $result->fetch_assoc();
        $select->close();
    }

    $updates = [];
    $params = [];
    $types = '';

    if ($discount_field_map['price_USD']) {
        $updates[] = "`{$discount_field_map['price_USD']}` = ?";
        $params[] = $price_usd !== null ? (float) $price_usd : null;
        $types .= 's';
    }
    if ($discount_field_map['price_XCD']) {
        $updates[] = "`{$discount_field_map['price_XCD']}` = ?";
        $params[] = $price_xcd !== null ? (float) $price_xcd : null;
        $types .= 's';
    }
    if ($discount_field_map['days']) {
        $updates[] = "`{$discount_field_map['days']}` = ?";
        $params[] = $days !== null ? $days : 1;
        $types .= 'i';
    }

    if ($existing) {
        $where_column = $id_column ? $id_column : $vehicle_column;
        $sql = "UPDATE vehicle_discounts SET " . implode(', ', $updates) . " WHERE `{$where_column}` = ?";
        $stmt = $con->prepare($sql);
        if (!$stmt) {
            send_json(['success' => false, 'message' => 'Failed to prepare update.'], 500);
        }
        $types .= 'i';
        $params[] = $id_column ? (int) ($existing[$id_column] ?? 0) : $vehicle_id;
        bind_params($stmt, $types, $params);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            send_json(['success' => false, 'message' => "Update failed: {$error}"], 500);
        }
        $stmt->close();
    } else {
        $columns = [$vehicle_column];
        $insert_params = [$vehicle_id];
        $insert_types = 'i';

        if ($discount_field_map['price_USD']) {
            $columns[] = $discount_field_map['price_USD'];
            $insert_params[] = $price_usd !== null ? (float) $price_usd : null;
            $insert_types .= 's';
        }
        if ($discount_field_map['price_XCD']) {
            $columns[] = $discount_field_map['price_XCD'];
            $insert_params[] = $price_xcd !== null ? (float) $price_xcd : null;
            $insert_types .= 's';
        }

        if ($discount_field_map['days']) {
            $columns[] = $discount_field_map['days'];
            $insert_params[] = $days !== null ? $days : 1;
            $insert_types .= 'i';
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columns_sql = '`' . implode('`, `', $columns) . '`';
        $insert_sql = "INSERT INTO vehicle_discounts ({$columns_sql}) VALUES ({$placeholders})";
        $stmt = $con->prepare($insert_sql);
        if (!$stmt) {
            send_json(['success' => false, 'message' => 'Failed to prepare insert.'], 500);
        }
        bind_params($stmt, $insert_types, $insert_params);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            send_json(['success' => false, 'message' => "Insert failed: {$error}"], 500);
        }
        $stmt->close();
    }

    $select = $con->prepare("SELECT * FROM vehicle_discounts WHERE `{$vehicle_column}` = ? ORDER BY " . ($id_column ? "`{$id_column}` DESC" : "`{$vehicle_column}` DESC") . " LIMIT 1");
    if (!$select) {
        send_json(['success' => false, 'message' => 'Failed to load updated discount.'], 500);
    }
    $select->bind_param('i', $vehicle_id);
    $select->execute();
    $result = $select->get_result();
    $updated = $result->fetch_assoc();
    $select->close();

    $normalized = normalize_discount_row($updated, $discount_field_map);
    send_json(['success' => true, 'discount' => $normalized]);
}

send_json(['success' => false, 'message' => 'Unable to process discount update.'], 500);
