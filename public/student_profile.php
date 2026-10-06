<?php
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/models/StudentProfile.php';
require_once __DIR__ . '/../app/helpers/csrf.php';
require_once __DIR__ . '/../app/helpers/stylesheets.php';
session_start();
requireRole('student');
header('Cache-Control: no-store');
$model = new StudentProfile();
$userId = (int) $_SESSION['user_id'];
$profile = $model->find($userId);
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify())
        $error = 'Invalid or expired form submission. Please try again.';
    else
        try {
            $action = StudentProfile::text($_POST, 'action', 20, true);
            if ($action === 'update')
                $model->updateDetails($userId, $_POST);
            elseif ($action === 'contact')
                $model->updateContact($userId, $_POST);
            else
                throw new StudentAccountException('Unknown profile action.');
            $_SESSION['profile_notice'] = $action === 'update' ? 'Your profile was updated.' : 'Your mobile number was updated.';
            header('Location: student_profile.php');
            exit;
        } catch (StudentAccountException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('Profile update failed: ' . $exception->getMessage());
            $error = $exception instanceof mysqli_sql_exception && $exception->getCode() === 1062
                ? 'An account with these details already exists. Contact the registrar for help.' : 'Unable to update your profile. Please try again.';
        }
}
require_once __DIR__ . '/../app/helpers/student_profile_view.php';
$studentProfileView = student_profile_view($model, $userId, $profile, $error);
$studentInitialView = 'profile';
require __DIR__ . '/dashboard_student.php';
