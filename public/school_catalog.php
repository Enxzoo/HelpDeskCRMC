<?php
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/models/StudentProfile.php';
require_once __DIR__ . '/../app/helpers/csrf.php';
require_once __DIR__ . '/../app/helpers/stylesheets.php';
session_start();
requireRole('admin');
header('Cache-Control: no-store');
$model = new StudentProfile();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_verify())
            throw new StudentAccountException('Invalid or expired form submission.', 403);
        $model->saveCatalog((int) $_SESSION['user_id'], $_POST);
        $_SESSION['catalog_notice'] = 'School catalog updated.';
        header('Location: school_catalog.php');
        exit;
    } catch (StudentAccountException $exception) {
        $error = $exception->getMessage();
        http_response_code($exception->status);
    } catch (Throwable $exception) {
        error_log('Catalog save failed: ' . $exception->getMessage());
        $error = $exception instanceof mysqli_sql_exception && $exception->getCode() === 1062 ? 'This catalog code or academic term already exists.' : 'Unable to save this catalog entry.';
    }
}
$catalog = $model->catalog(true);
$escape = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$notice = $_SESSION['catalog_notice'] ?? '';
unset($_SESSION['catalog_notice']);
$editing = null;
try {
    if (isset($_GET['kind'], $_GET['catalog_id'])) {
        $kind = StudentProfile::text($_GET, 'kind', 20, true);
        $id = StudentProfile::id($_GET, 'catalog_id');
        $map = ['college' => ['colleges', 'college_id'], 'program' => ['programs', 'program_id'], 'term' => ['terms', 'term_id']];
        if (!isset($map[$kind]))
            throw new StudentAccountException('Unknown catalog type.');
        foreach ($catalog[$map[$kind][0]] as $item)
            if ((int) $item[$map[$kind][1]] === $id)
                $editing = $item + ['kind' => $kind, 'catalog_id' => $id];
        if (!$editing)
            throw new StudentAccountException('Catalog entry not found.', 404);
    }
} catch (StudentAccountException $exception) {
    $error = $exception->getMessage();
    http_response_code($exception->status);
}
$workspaceRole = 'admin';
$workspacePage = 'school_catalog';
$workspaceTitle = 'School catalog';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>School Catalog - HelpdeskCRMC</title>
    <?php require __DIR__ . '/assets/components/ui-fonts.php'; ?>
    <?= stylesheet_bundle('workspace_ui') ?>
    <?= stylesheet_bundle('workspace_tables') ?>
    <?= stylesheet_bundle('student_accounts') ?>
    <script src="assets/js/workspace.js?v=<?= md5_file(__DIR__ . '/assets/js/workspace.js') ?>" defer></script>
</head>

<body class="student-account-page">
    <div class="workspace-app">
        <?php require __DIR__ . '/assets/components/workspace-sidebar.php'; ?>
        <main class="workspace-main">
            <?php require __DIR__ . '/assets/components/workspace-header.php'; ?>
            <div class="account-shell wide">
                <h1 class="account-title">School catalog</h1>
                <?php if ($notice): ?>
                    <div class="account-success" role="status"><?= $escape($notice) ?></div>
                <?php endif; ?><?php if ($error): ?>
                    <div class="account-error" role="alert"><?= $escape($error) ?></div><?php endif; ?>
                <?php foreach (['college' => ['colleges', 'college_id', 'Colleges'], 'program' => ['programs', 'program_id', 'Programs / Courses'], 'term' => ['terms', 'term_id', 'Academic terms']] as $kind => [$list, $key, $title]): ?>
                    <?php $values = $editing && $editing['kind'] === $kind ? $editing : [];
                    if ($error && ($_POST['kind'] ?? '') === $kind)
                        foreach ($_POST as $field => $value)
                            if (is_string($value))
                                $values[$field] = $value; ?>
                    <section class="catalog-section">
                        <h2><?= $title ?></h2>
                        <form method="POST" class="catalog-form"><?= csrf_field() ?><input type="hidden" name="kind"
                                value="<?= $kind ?>"><input type="hidden" name="catalog_id"
                                value="<?= $escape($values['catalog_id'] ?? '') ?>">
                            <?php if ($kind !== 'term'): ?>
                                <label>Code<input name="code" required maxlength="<?= $kind === 'college' ? '20' : '30' ?>"
                                        value="<?= $escape($values['code'] ?? '') ?>"></label><label>Name<input name="name"
                                        required maxlength="<?= $kind === 'college' ? '150' : '200' ?>"
                                        value="<?= $escape($values['name'] ?? '') ?>"></label>
                                <?php if ($kind === 'program'): ?><label>College<select name="college_id" required>
                                            <option value="">Select college</option>
                                            <?php foreach ($catalog['colleges'] as $college): ?>
                                                <option value="<?= (int) $college['college_id'] ?>" <?= (string) ($values['college_id'] ?? '') === (string) $college['college_id'] ? 'selected' : '' ?>>
                                                    <?= $escape($college['name']) ?></option><?php endforeach; ?>
                                        </select></label><label>Maximum year level<input type="number" name="max_year_level" min="1"
                                            max="8" required
                                            value="<?= $escape($values['max_year_level'] ?? '4') ?>"></label><?php endif; ?>
                                <label class="checkbox-label"><input type="checkbox" name="is_active" value="1" <?= !$values || (int) ($values['is_active'] ?? 0) ? 'checked' : '' ?>>Available for registration</label>
                            <?php else: ?><label>Academic year<input name="academic_year" placeholder="2026-2027"
                                        pattern="20[0-9]{2}-20[0-9]{2}" maxlength="9" required
                                        value="<?= $escape($values['academic_year'] ?? '') ?>"></label><label>Term<select
                                        name="semester"
                                        required><?php foreach (['1st Semester', '2nd Semester', 'Summer'] as $semester): ?>
                                            <option <?= ($values['semester'] ?? '') === $semester ? 'selected' : '' ?>><?= $semester ?>
                                            </option><?php endforeach; ?>
                                    </select></label><label class="checkbox-label"><input type="checkbox"
                                        name="registration_open" value="1" <?= (int) ($values['registration_open'] ?? 0) ? 'checked' : '' ?>>Registration open</label><?php endif; ?>
                            <div class="account-actions full"><?php if (!empty($values['catalog_id'])): ?><a class="button"
                                        href="school_catalog.php">Cancel edit</a><?php endif; ?><button type="submit"
                                    class="button primary"><img class="icon"
                                        src="assets/icons/<?= empty($values['catalog_id']) ? 'plus' : 'circle-check' ?>.svg"
                                        alt=""><?= empty($values['catalog_id']) ? 'Add' : 'Save' ?>     <?= $kind ?></button>
                            </div>
                        </form>
                        <div class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th><?= $kind === 'term' ? 'Academic year' : 'Code' ?></th>
                                        <th><?= $kind === 'term' ? 'Term' : 'Name' ?></th><?php if ($kind === 'program'): ?>
                                            <th>College</th>
                                            <th>Year levels</th><?php endif; ?>
                                        <th>Registration</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($catalog[$list] as $item): ?>
                                        <tr>
                                            <td><?= $escape($item[$kind === 'term' ? 'academic_year' : 'code']) ?></td>
                                            <td><?= $escape($item[$kind === 'term' ? 'semester' : 'name']) ?></td>
                                            <?php if ($kind === 'program'): ?>
                                                <td><?= $escape($item['college_name']) ?></td>
                                                <td><?= (int) $item['max_year_level'] ?></td><?php endif; ?>
                                            <td><?= (int) $item[$kind === 'term' ? 'registration_open' : 'is_active'] ? 'Open' : 'Closed' ?>
                                            </td>
                                            <td><a class="icon-button"
                                                    href="?kind=<?= $kind ?>&amp;catalog_id=<?= (int) $item[$key] ?>"
                                                    title="Edit <?= $kind ?>"
                                                    aria-label="Edit <?= $escape($item[$kind === 'term' ? 'academic_year' : 'code']) ?>"><img
                                                        class="icon" src="assets/icons/pencil.svg" alt=""></a></td>
                                        </tr><?php endforeach; ?>
                                    <?php if (!$catalog[$list]): ?>
                                        <tr>
                                            <td colspan="6" class="empty-cell">No <?= strtolower($title) ?> yet.</td>
                                        </tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
</body>

</html>