<?php
header('Content-Type: application/json');
header('Cache-Control: no-store');
require_once __DIR__ . '/../../app/config/env.php';
require_once __DIR__ . '/../../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../app/helpers/csrf.php';
require_once __DIR__ . '/../../app/models/AdminWorkspace.php';
require_once __DIR__ . '/../../app/models/Office.php';

session_start();
$actor = requireApiUser(['admin']);
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'], true)) {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}
if ($method === 'POST' && !csrf_verify_header()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRF verification failed.']);
    exit;
}
session_write_close();
try {
    $model = new AdminWorkspace();
    if ($method === 'GET') {
        $view = AdminWorkspace::text($_GET, 'view', 30);
        $data = match ($view) {
            'overview' => $model->overview(),
            'staff' => ['items' => $model->staff()],
            'knowledge' => ['items' => $model->knowledge()],
            'concerns' => $model->concerns($_GET),
            'concern' => ['item' => $model->concern(AdminWorkspace::id($_GET, 'inquiry_id'))],
            'offices' => ['items' => (new Office())->findAll()],
            default => throw new AdminRequestException('Unknown admin view.'),
        };
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input))
            throw new AdminRequestException('Invalid request data.');
        $action = AdminWorkspace::text($input, 'action', 40);
        $data = [];
        switch ($action) {
            case 'create_staff':
            case 'update_staff':
                $data['user_id'] = $model->saveStaff($input, $action === 'create_staff');
                break;
            case 'delete_staff':
                $model->deleteStaff(AdminWorkspace::id($input, 'user_id'));
                break;
            case 'create_knowledge':
            case 'update_knowledge':
                $data['entry_id'] = $model->saveKnowledge($input, (int) $actor['user_id'], $action === 'create_knowledge');
                break;
            case 'delete_knowledge':
                $model->deleteKnowledge(AdminWorkspace::id($input, 'entry_id'));
                break;
            case 'assign_concern':
                $staffId = ($input['staff_id'] ?? '') === '' || ($input['staff_id'] ?? null) === null ? null : AdminWorkspace::id($input, 'staff_id');
                $model->assign(AdminWorkspace::id($input, 'inquiry_id'), $staffId);
                break;
            default:
                throw new AdminRequestException('Unknown admin action.');
        }
    }
    echo json_encode(['success' => true] + $data, JSON_INVALID_UTF8_SUBSTITUTE);
} catch (AdminRequestException $error) {
    http_response_code($error->status);
    echo json_encode(['success' => false, 'error' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('Admin workspace failed: ' . $error->getMessage());
    $duplicate = $error instanceof mysqli_sql_exception && $error->getCode() === 1062;
    http_response_code($duplicate ? 409 : 500);
    echo json_encode(['success' => false, 'error' => $duplicate ? 'An account with this email already exists.' : 'Unable to complete this request. Please try again.']);
}
