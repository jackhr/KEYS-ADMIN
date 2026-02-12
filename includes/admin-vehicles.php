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

function bind_params($stmt, $types, &$params)
{
    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = &$params[$key];
    }
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function normalize_slug($value)
{
    $slug = strtolower(trim((string) $value));
    $slug = preg_replace('/[^a-z0-9_-]+/', '_', $slug);
    $slug = preg_replace('/_+/', '_', $slug);
    $slug = trim($slug, '_');
    return $slug;
}

function make_unique_slug($con, $slug, $exclude_id = 0)
{
    $base = normalize_slug($slug);
    if ($base === '') $base = 'vehicle';

    $candidate = $base;
    $suffix = 2;

    while (true) {
        $query = "SELECT id FROM vehicles WHERE slug = ?";
        if ($exclude_id > 0) {
            $query .= " AND id != ?";
        }
        $query .= " LIMIT 1";

        $stmt = $con->prepare($query);
        if (!$stmt) return $candidate;

        if ($exclude_id > 0) {
            $stmt->bind_param('si', $candidate, $exclude_id);
        } else {
            $stmt->bind_param('s', $candidate);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result && $result->fetch_assoc();
        $stmt->close();

        if (!$exists) return $candidate;

        $candidate = $base . '_' . $suffix;
        $suffix++;
    }
}

function fetch_vehicle($con, $id)
{
    $stmt = $con->prepare("SELECT * FROM vehicles WHERE id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $vehicle = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $vehicle ?: null;
}

function parse_vehicle_payload($raw_vehicle, $for_create = false)
{
    $vehicle = is_array($raw_vehicle) ? $raw_vehicle : [];
    $errors = [];

    $name = trim((string) ($vehicle['name'] ?? ''));
    $type = trim((string) ($vehicle['type'] ?? ''));
    $slug = trim((string) ($vehicle['slug'] ?? ''));

    if ($for_create && $name === '') $name = 'New Vehicle';
    if ($for_create && $type === '') $type = 'car';
    if ($for_create && $slug === '') $slug = $name;

    $base_price_USD = $vehicle['base_price_USD'] ?? ($for_create ? 0 : null);
    $base_price_XCD = $vehicle['base_price_XCD'] ?? null;
    $insurance = $vehicle['insurance'] ?? ($for_create ? 0 : null);
    $people = $vehicle['people'] ?? ($for_create ? 4 : null);
    $bags = $vehicle['bags'] ?? ($for_create ? 0 : null);
    $doors = $vehicle['doors'] ?? ($for_create ? 4 : null);
    $manual = ((int) ($vehicle['manual'] ?? 0)) === 1 ? 1 : 0;
    $ac = ((int) ($vehicle['ac'] ?? 0)) === 1 ? 1 : 0;
    $fourwd = ((int) ($vehicle['4wd'] ?? 0)) === 1 ? 1 : 0;
    $showing = ((int) ($vehicle['showing'] ?? 1)) === 1 ? 1 : 0;
    $landing_order = $vehicle['landing_order'] ?? null;

    if ($name === '') $errors[] = 'Name is required.';
    if ($type === '') $errors[] = 'Type is required.';

    $slug = normalize_slug($slug);
    if ($slug === '') $errors[] = 'Slug is required.';

    if (!is_numeric($base_price_USD)) $errors[] = 'Base price (USD) must be numeric.';
    if ($base_price_XCD !== null && $base_price_XCD !== '' && !is_numeric($base_price_XCD)) $errors[] = 'Base price (XCD) must be numeric.';
    if (!is_numeric($insurance)) $errors[] = 'Insurance must be numeric.';
    if (!is_numeric($people)) $errors[] = 'Seats must be numeric.';
    if (!is_numeric($bags)) $errors[] = 'Bags must be numeric.';
    if (!is_numeric($doors)) $errors[] = 'Doors must be numeric.';

    if ($landing_order !== null && $landing_order !== '' && !is_numeric($landing_order)) {
        $errors[] = 'Landing order must be numeric.';
    }

    if (count($errors) > 0) {
        return [null, $errors];
    }

    $parsed = [
        'name' => $name,
        'type' => $type,
        'slug' => $slug,
        'base_price_USD' => (float) $base_price_USD,
        'base_price_XCD' => ($base_price_XCD === null || $base_price_XCD === '') ? null : (float) $base_price_XCD,
        'insurance' => (float) $insurance,
        'people' => (int) $people,
        'bags' => (int) $bags,
        'doors' => (int) $doors,
        'manual' => $manual,
        'ac' => $ac,
        '4wd' => $fourwd,
        'showing' => $showing,
        'landing_order' => ($landing_order === null || $landing_order === '') ? null : (int) $landing_order
    ];

    return [$parsed, []];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['success' => false, 'message' => 'Invalid request method.'], 405);
}

if (!table_exists($con, 'vehicles')) {
    send_json(['success' => false, 'message' => 'Vehicles table not found.'], 500);
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data)) {
    send_json(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
}

$action = $data['action'] ?? '';

if ($action !== 'update_vehicle' && $action !== 'create_vehicle') {
    send_json(['success' => false, 'message' => 'Unsupported action.'], 400);
}

$vehicle_payload = $data['vehicle'] ?? [];
list($vehicle, $errors) = parse_vehicle_payload($vehicle_payload, $action === 'create_vehicle');
if (count($errors) > 0 || !$vehicle) {
    send_json(['success' => false, 'message' => implode(' ', $errors)], 422);
}

$columns = get_table_columns($con, 'vehicles');
if (count($columns) === 0) {
    send_json(['success' => false, 'message' => 'Unable to inspect vehicles table.'], 500);
}

if ($action === 'update_vehicle') {
    $id = isset($vehicle_payload['id']) ? (int) $vehicle_payload['id'] : 0;
    if ($id <= 0) {
        send_json(['success' => false, 'message' => 'Invalid vehicle id.'], 400);
    }

    $existing = fetch_vehicle($con, $id);
    if (!$existing) {
        send_json(['success' => false, 'message' => 'Vehicle not found.'], 404);
    }

    $vehicle['slug'] = make_unique_slug($con, $vehicle['slug'], $id);

    $field_values = [
        'name' => $vehicle['name'],
        'type' => $vehicle['type'],
        'base_price_USD' => $vehicle['base_price_USD'],
        'insurance' => $vehicle['insurance'],
        'people' => $vehicle['people'],
        'bags' => $vehicle['bags'],
        'doors' => $vehicle['doors'],
        'manual' => $vehicle['manual'],
        'ac' => $vehicle['ac'],
        '4wd' => $vehicle['4wd'],
        'slug' => $vehicle['slug'],
        'showing' => $vehicle['showing'],
        'landing_order' => $vehicle['landing_order']
    ];
    if (in_array('base_price_XCD', $columns, true)) {
        $field_values['base_price_XCD'] = $vehicle['base_price_XCD'] !== null ? $vehicle['base_price_XCD'] : $vehicle['base_price_USD'];
    }

    $set = [];
    $types = '';
    $params = [];
    foreach ($field_values as $column => $value) {
        if (!in_array($column, $columns, true)) continue;
        $set[] = "`{$column}` = ?";
        $types .= 's';
        $params[] = $value;
    }

    if (count($set) === 0) {
        send_json(['success' => false, 'message' => 'No editable columns available.'], 500);
    }

    $types .= 'i';
    $params[] = $id;
    $sql = "UPDATE vehicles SET " . implode(', ', $set) . " WHERE id = ?";
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        send_json(['success' => false, 'message' => 'Failed to prepare update.'], 500);
    }
    bind_params($stmt, $types, $params);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        send_json(['success' => false, 'message' => "Update failed: {$error}"], 500);
    }
    $stmt->close();

    $updated_vehicle = fetch_vehicle($con, $id);
    if (!$updated_vehicle) {
        send_json(['success' => false, 'message' => 'Updated vehicle not found.'], 500);
    }

    send_json(['success' => true, 'vehicle' => $updated_vehicle]);
}

$vehicle['slug'] = make_unique_slug($con, $vehicle['slug'], 0);

$insert_values = [
    'name' => $vehicle['name'],
    'type' => $vehicle['type'],
    'slug' => $vehicle['slug'],
    'base_price_USD' => $vehicle['base_price_USD'],
    'insurance' => $vehicle['insurance'],
    'people' => $vehicle['people'],
    'bags' => $vehicle['bags'],
    'doors' => $vehicle['doors'],
    'manual' => $vehicle['manual'],
    'ac' => $vehicle['ac'],
    '4wd' => $vehicle['4wd'],
    'showing' => $vehicle['showing'],
    'landing_order' => $vehicle['landing_order']
];
if (in_array('base_price_XCD', $columns, true)) {
    $insert_values['base_price_XCD'] = $vehicle['base_price_XCD'] !== null ? $vehicle['base_price_XCD'] : $vehicle['base_price_USD'];
}
if (in_array('times_requested', $columns, true)) {
    $insert_values['times_requested'] = 0;
}

$insert_columns = [];
$placeholders = [];
$types = '';
$params = [];
foreach ($insert_values as $column => $value) {
    if (!in_array($column, $columns, true)) continue;
    $insert_columns[] = "`{$column}`";
    $placeholders[] = '?';
    $types .= 's';
    $params[] = $value;
}

if (count($insert_columns) === 0) {
    send_json(['success' => false, 'message' => 'No writable columns available for create.'], 500);
}

$sql = "INSERT INTO vehicles (" . implode(', ', $insert_columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
$stmt = $con->prepare($sql);
if (!$stmt) {
    send_json(['success' => false, 'message' => 'Failed to prepare create statement.'], 500);
}
bind_params($stmt, $types, $params);
if (!$stmt->execute()) {
    $error = $stmt->error;
    $stmt->close();
    send_json(['success' => false, 'message' => "Create failed: {$error}"], 500);
}
$new_id = (int) $stmt->insert_id;
$stmt->close();

$created_vehicle = fetch_vehicle($con, $new_id);
if (!$created_vehicle) {
    send_json(['success' => false, 'message' => 'Created vehicle not found.'], 500);
}

send_json(['success' => true, 'vehicle' => $created_vehicle]);
