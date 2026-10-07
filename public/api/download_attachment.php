<?php
require_once __DIR__ . '/../../app/middleware/AuthMiddleware.php';

header('Content-Type: application/json');
header('Cache-Control: private, no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
session_start();
$user = requireApiUser(['student', 'staff', 'admin']);
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || $id === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid attachment']);
    exit;
}
try {
    $stmt = getDbConnection()->prepare('SELECT a.file_name, a.file_path, i.student_id, i.office_id
        FROM inquiry_attachments a JOIN inquiries i ON i.inquiry_id = a.inquiry_id WHERE a.attachment_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $attachment = $stmt->get_result()->fetch_assoc();
    if (!$attachment) {
        http_response_code(404);
        echo json_encode(['error' => 'Attachment not found']);
        exit;
    }
    if (($user['role'] === 'student' && (int)$attachment['student_id'] !== (int)$user['user_id'])
        || ($user['role'] === 'staff' && (!$user['office_id'] || (int)$attachment['office_id'] !== (int)$user['office_id']))) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    $relative = $attachment['file_path'];
    if (!preg_match('~^(storage/attachments|uploads)/[a-f0-9]{32}\.[a-z0-9]+$~D', $relative)) {
        throw new RuntimeException('Invalid stored attachment path.');
    }
    $directory = realpath(__DIR__ . '/../../' . dirname($relative));
    $file = realpath(__DIR__ . '/../../' . $relative);
    if (!$directory || !$file || !str_starts_with($file, $directory . DIRECTORY_SEPARATOR) || !is_readable($file)) {
        http_response_code(404);
        echo json_encode(['error' => 'Attachment file is unavailable']);
        exit;
    }
    session_write_close();
    header('Content-Type: application/octet-stream');
    header('X-Content-Type-Options: nosniff');
    header("Content-Disposition: attachment; filename=\"attachment\"; filename*=UTF-8''" . rawurlencode($attachment['file_name']));
    header('Content-Length: ' . filesize($file));
    readfile($file);
} catch (Throwable $error) {
    error_log('Attachment download failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to download attachment.']);
}
