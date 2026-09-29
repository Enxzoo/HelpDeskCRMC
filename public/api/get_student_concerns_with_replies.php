<?php
/**
 * get_student_concerns_with_replies.php
 * API endpoint to get all concerns with their staff replies for a student
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../app/config/database.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$studentId = (int)$_SESSION['user_id'];

try {
    $db = getDbConnection();

    // Get all inquiries for this student with their replies
    $query = "
        SELECT
            i.inquiry_id,
            i.subject,
            i.description,
            i.status,
            i.created_at,
            o.office_name,
            (SELECT COUNT(*) FROM inquiry_responses WHERE inquiry_id = i.inquiry_id) as reply_count
        FROM inquiries i
        LEFT JOIN offices o ON i.office_id = o.office_id
        WHERE i.student_id = ?
        ORDER BY i.created_at DESC
    ";

    $stmt = $db->prepare($query);
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $inquiries = $result->fetch_all(MYSQLI_ASSOC);

    // For each inquiry, get its replies
    $concerns = [];
    foreach ($inquiries as $inq) {
        $repliesQuery = "
            SELECT
                ir.response_id,
                ir.message,
                ir.created_at,
                CONCAT(u.first_name, ' ', u.last_name) as staff_name
            FROM inquiry_responses ir
            JOIN users u ON ir.staff_id = u.user_id
            WHERE ir.inquiry_id = ?
            ORDER BY ir.created_at ASC
        ";

        $repliesStmt = $db->prepare($repliesQuery);
        $repliesStmt->bind_param('i', $inq['inquiry_id']);
        $repliesStmt->execute();
        $repliesResult = $repliesStmt->get_result();
        $replies = $repliesResult->fetch_all(MYSQLI_ASSOC);

        $concerns[] = [
            'inquiry_id' => $inq['inquiry_id'],
            'subject' => $inq['subject'],
            'message' => $inq['description'],
            'status' => $inq['status'],
            'office' => $inq['office_name'],
            'created_at' => $inq['created_at'],
            'reply_count' => $inq['reply_count'],
            'replies' => $replies
        ];
    }

    echo json_encode([
        'success' => true,
        'concerns' => $concerns
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
