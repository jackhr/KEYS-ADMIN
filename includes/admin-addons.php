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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data)) {
    send_json(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
}

if (($data['action'] ?? '') !== 'update_addon') {
    send_json(['success' => false, 'message' => 'Unsupported action.'], 400);
}

$addon = $data['addon'] ?? null;
if (!is_array($addon)) {
    send_json(['success' => false, 'message' => 'Missing add-on data.'], 400);
}

$id = isset($addon['id']) ? (int) $addon['id'] : 0;
if ($id <= 0) {
    send_json(['success' => false, 'message' => 'Invalid add-on id.'], 400);
}

if (!table_exists($con, 'add_ons')) {
    send_json(['success' => false, 'message' => 'Add-ons table not found.'], 500);
}

$columns = get_table_columns($con, 'add_ons');
$id_field = pick_first_column($columns, ['id', 'addon_id']);

if (!$id_field) {
    send_json(['success' => false, 'message' => 'Add-ons table is missing an id field.'], 500);
}

$field_map = [
    'name' => pick_first_column($columns, ['name', 'title']),
    'price' => pick_first_column($columns, ['cost', 'price_USD', 'price', 'amount', 'daily_price']),
    'description' => pick_first_column($columns, ['description', 'details', 'notes']),
    'active' => null,
    'per_day' => pick_first_column($columns, ['fixed_price']),
    'sort_order' => null
];

$updates = [];
$params = [];
$types = '';
$errors = [];

$name = trim((string) ($addon['name'] ?? ''));
if ($field_map['name']) {
    if ($name === '') {
        $errors[] = 'Name is required.';
    } else {
        $updates[] = "`{$field_map['name']}` = ?";
        $params[] = $name;
        $types .= 's';
    }
}

if ($field_map['price']) {
    $price = $addon['price'] ?? null;
    if ($price === '') $price = null;
    if ($price !== null && !is_numeric($price)) {
        $errors[] = 'Price must be numeric.';
    } else {
        $updates[] = "`{$field_map['price']}` = ?";
        $params[] = $price !== null ? (float) $price : null;
        $types .= 's';
    }
}

if ($field_map['description']) {
    $description = trim((string) ($addon['description'] ?? ''));
    $updates[] = "`{$field_map['description']}` = ?";
    $params[] = $description !== '' ? $description : null;
    $types .= 's';
}

if ($field_map['per_day']) {
    $per_day = ((int) ($addon['per_day'] ?? 0) === 1) ? 1 : 0;
    $updates[] = "`{$field_map['per_day']}` = ?";
    $params[] = $per_day;
    $types .= 's';
}

if ($field_map['sort_order']) {
    $sort_order = $addon['sort_order'] ?? null;
    if ($sort_order === '') $sort_order = null;
    if ($sort_order !== null && !is_numeric($sort_order)) {
        $errors[] = 'Sort order must be numeric.';
    } else {
        $updates[] = "`{$field_map['sort_order']}` = ?";
        $params[] = $sort_order !== null ? (int) $sort_order : null;
        $types .= 's';
    }
}

if (count($errors) > 0) {
    send_json(['success' => false, 'message' => implode(' ', $errors)], 422);
}

if (count($updates) === 0) {
    send_json(['success' => false, 'message' => 'No fields to update.'], 400);
}

$sql = "UPDATE add_ons SET " . implode(', ', $updates) . " WHERE `{$id_field}` = ?";
$stmt = $con->prepare($sql);
if (!$stmt) {
    send_json(['success' => false, 'message' => 'Failed to prepare update.'], 500);
}

$types .= 'i';
$params[] = $id;
bind_params($stmt, $types, $params);

if (!$stmt->execute()) {
    $error = $stmt->error;
    $stmt->close();
    send_json(['success' => false, 'message' => "Update failed: {$error}"], 500);
}

$stmt->close();

$select = $con->prepare("SELECT * FROM add_ons WHERE `{$id_field}` = ? LIMIT 1");
if (!$select) {
    send_json(['success' => false, 'message' => 'Failed to load updated add-on.'], 500);
}

$select->bind_param('i', $id);
$select->execute();
$result = $select->get_result();
$updated = $result->fetch_assoc();
$select->close();

if (!$updated) {
    send_json(['success' => false, 'message' => 'Updated add-on not found.'], 500);
}

send_json(['success' => true, 'addon' => $updated]);
