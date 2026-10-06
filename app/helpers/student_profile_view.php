<?php
require_once __DIR__ . '/../models/StudentProfile.php';

function student_profile_view(StudentProfile $model, int $userId, array $profile, ?string $error = null): array
{
    $values = $profile;
    if ($error) {
        foreach ($_POST as $key => $value) {
            if (is_string($value)) $values[$key] = $value;
        }
    }
    $showForm = isset($_GET['edit']) || ($error && ($_POST['action'] ?? '') === 'update');
    $notice = $_SESSION['profile_notice'] ?? '';
    unset($_SESSION['profile_notice']);
    return compact('profile', 'values', 'showForm', 'notice', 'error') + [
        'catalog' => $showForm ? $model->catalog() : ['colleges' => [], 'programs' => [], 'terms' => []],
        'history' => $model->history($userId),
    ];
}
