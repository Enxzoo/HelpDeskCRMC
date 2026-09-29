<?php
/**
 * upload_file.php
 * Handle file uploads for inquiries and chat attachments
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../app/config/env.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../app/helpers/csrf.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Require authenticated session
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Verify CSRF token
if (!csrf_verify_header()) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

try {
    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error');
    }

    $file = $_FILES['file'];
    $userId = (int)$_SESSION['user_id'];

    // Validate file size (5MB max)
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        throw new Exception('File too large. Maximum size is 5MB');
    }

    // Validate file type
    $allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain', 'text/csv'
    ];

    $fileType = mime_content_type($file['tmp_name']);
    if (!in_array($fileType, $allowedTypes)) {
        throw new Exception('File type not allowed. Allowed: images, PDF, Word, Excel, text files');
    }

    // Create uploads directory if it doesn't exist
    $uploadDir = __DIR__ . '/../../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to save file');
    }

    // Store file info in database using existing inquiry_attachments table
    $dbConn = getDbConnection();
    $stmt = $dbConn->prepare("
        INSERT INTO inquiry_attachments (inquiry_id, response_id, uploaded_by, file_name, file_path, file_type, file_size)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    // For chat attachments, inquiry_id and response_id are NULL until linked
    $inquiryId = null;
    $responseId = null;

    $stmt->bind_param('iisissi',
        $inquiryId,
        $responseId,
        $userId,
        $file['name'],
        'uploads/' . $filename,
        $fileType,
        $file['size']
    );

    if (!$stmt->execute()) {
        // Clean up file if database insert fails
        unlink($filepath);
        throw new Exception('Failed to save file info');
    }

    $attachmentId = $dbConn->insert_id;

    echo json_encode([
        'success' => true,
        'attachment_id' => $attachmentId,
        'filename' => $filename,
        'original_name' => $file['name'],
        'file_size' => $file['size'],
        'file_type' => $fileType
    ]);

} catch (Exception $e) {
    error_log('File upload error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}