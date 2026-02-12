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

function normalize_slug($value)
{
    $slug = strtolower(trim((string) $value));
    $slug = preg_replace('/[^a-z0-9_-]+/', '_', $slug);
    $slug = preg_replace('/_+/', '_', $slug);
    return trim($slug, '_');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$vehicle_id = isset($_POST['vehicle_id']) ? (int) $_POST['vehicle_id'] : 0;
if ($vehicle_id <= 0) {
    send_json(['success' => false, 'message' => 'Invalid vehicle id.'], 400);
}

if (!isset($_FILES['image'])) {
    send_json(['success' => false, 'message' => 'Image file is required.'], 400);
}

$file = $_FILES['image'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    send_json(['success' => false, 'message' => 'Image upload failed.'], 400);
}

$max_bytes = 8 * 1024 * 1024;
if (($file['size'] ?? 0) > $max_bytes) {
    send_json(['success' => false, 'message' => 'Image must be 8MB or smaller.'], 422);
}

$stmt = $con->prepare("SELECT id, slug FROM vehicles WHERE id = ? LIMIT 1");
if (!$stmt) {
    send_json(['success' => false, 'message' => 'Unable to load vehicle.'], 500);
}
$stmt->bind_param('i', $vehicle_id);
$stmt->execute();
$result = $stmt->get_result();
$vehicle = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$vehicle) {
    send_json(['success' => false, 'message' => 'Vehicle not found.'], 404);
}

$slug = normalize_slug($vehicle['slug'] ?? '');
if ($slug === '') {
    send_json(['success' => false, 'message' => 'Vehicle slug is missing.'], 422);
}

$allowed_extensions = ['avif', 'webp', 'jpg', 'jpeg', 'png'];
$original_name = (string) ($file['name'] ?? '');
$extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
if (!in_array($extension, $allowed_extensions, true)) {
    send_json(['success' => false, 'message' => 'Allowed image types: avif, webp, jpg, jpeg, png.'], 422);
}

$target_dir = __DIR__ . '/../assets/images/vehicles';
if (!is_dir($target_dir)) {
    if (!mkdir($target_dir, 0755, true) && !is_dir($target_dir)) {
        send_json(['success' => false, 'message' => 'Unable to create vehicle image directory.'], 500);
    }
}

foreach ($allowed_extensions as $ext) {
    $existing = $target_dir . '/' . $slug . '.' . $ext;
    if (file_exists($existing)) {
        @unlink($existing);
    }
}

$target_file = $target_dir . '/' . $slug . '.' . $extension;
if (!move_uploaded_file($file['tmp_name'], $target_file)) {
    send_json(['success' => false, 'message' => 'Failed to store uploaded image.'], 500);
}

$image_url = "/assets/images/vehicles/{$slug}.{$extension}?v=" . time();
send_json([
    'success' => true,
    'image_url' => $image_url,
    'message' => 'Vehicle image updated.'
]);
