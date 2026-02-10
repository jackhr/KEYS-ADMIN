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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data)) {
    send_json(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
}

$action = $data['action'] ?? '';
if ($action !== 'update_vehicle') {
    send_json(['success' => false, 'message' => 'Unsupported action.'], 400);
}

$vehicle = $data['vehicle'] ?? null;
if (!is_array($vehicle)) {
    send_json(['success' => false, 'message' => 'Missing vehicle data.'], 400);
}

$id = isset($vehicle['id']) ? (int) $vehicle['id'] : 0;
if ($id <= 0) {
    send_json(['success' => false, 'message' => 'Invalid vehicle id.'], 400);
}

$name = trim((string) ($vehicle['name'] ?? ''));
$type = trim((string) ($vehicle['type'] ?? ''));
$slug = trim((string) ($vehicle['slug'] ?? ''));

$base_price_USD = $vehicle['base_price_USD'] ?? null;
$insurance = $vehicle['insurance'] ?? null;
$people = $vehicle['people'] ?? 0;
$bags = $vehicle['bags'] ?? 0;
$doors = $vehicle['doors'] ?? 0;

$manual = ((int) ($vehicle['manual'] ?? 0)) === 1 ? 1 : 0;
$ac = ((int) ($vehicle['ac'] ?? 0)) === 1 ? 1 : 0;
$fourwd = ((int) ($vehicle['4wd'] ?? 0)) === 1 ? 1 : 0;
$showing = ((int) ($vehicle['showing'] ?? 0)) === 1 ? 1 : 0;

$landing_order = $vehicle['landing_order'] ?? null;

$errors = [];
if ($name === '') $errors[] = 'Name is required.';
if ($type === '') $errors[] = 'Type is required.';
if ($slug === '') $errors[] = 'Slug is required.';

if (!is_numeric($base_price_USD)) $errors[] = 'Base price must be numeric.';
if (!is_numeric($insurance)) $errors[] = 'Insurance must be numeric.';
if (!is_numeric($people)) $errors[] = 'Seats must be numeric.';
if (!is_numeric($bags)) $errors[] = 'Bags must be numeric.';
if (!is_numeric($doors)) $errors[] = 'Doors must be numeric.';

if ($landing_order !== null && $landing_order !== '') {
    if (!is_numeric($landing_order)) {
        $errors[] = 'Landing order must be numeric.';
    }
}

if (count($errors) > 0) {
    send_json(['success' => false, 'message' => implode(' ', $errors)], 422);
}

$base_price_USD = (float) $base_price_USD;
$insurance = (float) $insurance;
$people = (int) $people;
$bags = (int) $bags;
$doors = (int) $doors;
$landing_order = ($landing_order === null || $landing_order === '') ? null : (int) $landing_order;

$stmt = $con->prepare("UPDATE vehicles SET name = ?, type = ?, base_price_USD = ?, people = ?, bags = ?, doors = ?, manual = ?, ac = ?, `4wd` = ?, insurance = ?, slug = ?, showing = ?, landing_order = ? WHERE id = ?");

if (!$stmt) {
    send_json(['success' => false, 'message' => 'Failed to prepare update.'], 500);
}

$stmt->bind_param(
    "ssdiiiiiidsiii", // type string that maps 1‑to‑1 to each variable you pass after it
    $name,
    $type,
    $base_price_USD,
    $people,
    $bags,
    $doors,
    $manual,
    $ac,
    $fourwd,
    $insurance,
    $slug,
    $showing,
    $landing_order,
    $id
);

if (!$stmt->execute()) {
    $error = $stmt->error;
    $stmt->close();
    send_json(['success' => false, 'message' => "Update failed: {$error}"], 500);
}

$stmt->close();

$select = $con->prepare("SELECT * FROM vehicles WHERE id = ?");
if (!$select) {
    send_json(['success' => false, 'message' => 'Failed to load updated vehicle.'], 500);
}

$select->bind_param('i', $id);
$select->execute();
$result = $select->get_result();
$updated_vehicle = $result->fetch_assoc();
$select->close();

if (!$updated_vehicle) {
    send_json(['success' => false, 'message' => 'Updated vehicle not found.'], 500);
}

send_json(['success' => true, 'vehicle' => $updated_vehicle]);
