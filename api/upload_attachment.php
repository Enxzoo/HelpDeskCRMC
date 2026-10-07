<?php
require_once __DIR__ . '/../app/config/session.php';
require_once __DIR__ . '/../app/helpers/Database.php';
require_once __DIR__ . '/../app/config/auth.php';

header('Content-Type: application/json');

// Verify CSRF token
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
  http_response_code(403);
  echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
  exit;
}

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'Unauthorized']);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Method not allowed']);
  exit;
}

if (!isset($_FILES['files'])) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'No files provided']);
  exit;
}

$uploadDir = __DIR__ . '/../storage/attachments';
if (!is_dir($uploadDir)) {
  mkdir($uploadDir, 0755, true);
}

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$uploadedFiles = [];
$errors = [];

// Process each file
$files = $_FILES['files'];
$fileCount = is_array($files['name']) ? count($files['name']) : 1;

if (!is_array($files['name'])) {
  $files['name'] = [$files['name']];
  $files['tmp_name'] = [$files['tmp_name']];
  $files['type'] = [$files['type']];
  $files['size'] = [$files['size']];
  $files['error'] = [$files['error']];
}

for ($i = 0; $i < $fileCount; $i++) {
  if ($files['error'][$i] !== UPLOAD_ERR_OK) {
    $errors[] = "File {$files['name'][$i]}: Upload error code {$files['error'][$i]}";
    continue;
  }

  $fileName = $files['name'][$i];
  $tmpPath = $files['tmp_name'][$i];
  $fileType = $files['type'][$i];
  $fileSize = $files['size'][$i];

  // Validate file
  $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/plain', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
  if (!in_array($fileType, $allowedTypes)) {
    $errors[] = "File {$fileName}: Invalid file type";
    continue;
  }

  if ($fileSize > 10 * 1024 * 1024) { // 10MB max
    $errors[] = "File {$fileName}: File too large (max 10MB)";
    continue;
  }

  // Generate unique filename
  $ext = pathinfo($fileName, PATHINFO_EXTENSION);
  $uniqueName = uniqid('att_') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
  $filePath = $uploadDir . '/' . $uniqueName;

  // Move file
  if (!move_uploaded_file($tmpPath, $filePath)) {
    $errors[] = "File {$fileName}: Failed to save file";
    continue;
  }

  // Store in database
  $query = "INSERT INTO inquiry_attachments (inquiry_id, response_id, uploaded_by, file_name, file_path, file_type, file_size, uploaded_at)
            VALUES (NULL, NULL, ?, ?, ?, ?, ?, NOW())";

  try {
    $stmt = $db->prepare($query);
    $relPath = 'storage/attachments/' . $uniqueName;
    $stmt->execute([$userId, $fileName, $relPath, $fileType, $fileSize]);

    $uploadedFiles[] = [
      'attachment_id' => $db->lastInsertId(),
      'file_name' => $fileName,
      'file_size' => $fileSize,
      'file_type' => $fileType
    ];
  } catch (Exception $e) {
    unlink($filePath); // Clean up file if DB insert fails
    $errors[] = "File {$fileName}: Database error";
  }
}

http_response_code(200);
echo json_encode([
  'success' => count($uploadedFiles) > 0,
  'uploaded' => $uploadedFiles,
  'errors' => $errors
]);
