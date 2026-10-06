<?php
$input = static function (
    string $key,
    string $label,
    int $max,
    bool $required = false,
    string $type = 'text'
) use ($values, $escape): void {
    echo '<label for="' . $key . '">'
        . $escape($label)
        . '<input id="' . $key . '" name="' . $key . '" type="' . $type
        . '" maxlength="' . $max . '"'
        . ($required ? ' required' : '')
        . ' value="' . $escape($values[$key] ?? '') . '"></label>';
};
?>
<?php if (empty($academicOnly)): ?>
    <fieldset class="profile-section"<?= !empty($registrationSteps) ? ' data-registration-step="0"' : '' ?>>
        <legend>Personal details</legend>
        <div class="profile-grid">
            <?php
            $input('first_name', 'First name', 100, true);
            $input('last_name', 'Last name', 100, true);
            $input('middle_name', 'Middle name (optional)', 100);
            $input('suffix', 'Suffix (optional)', 20);
            if (empty($registrationSteps)) {
                $input('email', 'Email address', 150, true, 'email');
            }
            $input('mobile_number', 'Mobile number (optional)', 25, false, 'tel');
            ?>
        </div>
    </fieldset>
<?php endif; ?>
<fieldset class="profile-section"<?= !empty($registrationSteps) ? ' data-registration-step="1"' : '' ?>>
    <legend>Academic details</legend>
    <div class="profile-grid">
        <?php if (empty($academicOnly)): ?>
            <?php $input('student_number', 'Student ID number', 30, true); ?>
        <?php endif; ?>
        <label for="college_id">
            College
            <select id="college_id" name="college_id" required>
                <option value="">Select college</option>
                <?php foreach ($catalog['colleges'] as $college): ?>
                    <option
                        value="<?= (int)$college['college_id'] ?>"
                        <?= (string)($values['college_id'] ?? '') === (string)$college['college_id']
                            ? 'selected' : '' ?>
                    ><?= $escape($college['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label for="program_id" class="full">
            Program / Course
            <select id="program_id" name="program_id" required>
                <option value="">Select program</option>
                <?php foreach ($catalog['programs'] as $program): ?>
                    <option
                        value="<?= (int)$program['program_id'] ?>"
                        data-college="<?= (int)$program['college_id'] ?>"
                        data-years="<?= (int)$program['max_year_level'] ?>"
                        <?= (string)($values['program_id'] ?? '') === (string)$program['program_id']
                            ? 'selected' : '' ?>
                    ><?= $escape($program['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label for="year_level">
            Year level
            <select id="year_level" name="year_level" required>
                <option value="">Select year level</option>
                <?php for ($year = 1; $year <= 8; $year++): ?>
                    <option
                        value="<?= $year ?>"
                        <?= (string)($values['year_level'] ?? '') === (string)$year ? 'selected' : '' ?>
                    >Year <?= $year ?></option>
                <?php endfor; ?>
            </select>
        </label>
        <?php $input('section', 'Section / Block (optional)', 50); ?>
        <label for="term_id">
            Academic year / Term
            <select id="term_id" name="term_id" required>
                <option value="">Select academic term</option>
                <?php foreach ($catalog['terms'] as $term): ?>
                    <option
                        value="<?= (int)$term['term_id'] ?>"
                        <?= (string)($values['term_id'] ?? '') === (string)$term['term_id']
                            ? 'selected' : '' ?>
                    ><?= $escape($term['academic_year'] . ' / ' . $term['semester']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label for="enrollment_type">
            Enrollment classification
            <select id="enrollment_type" name="enrollment_type" required>
                <option value="">Select classification</option>
                <?php foreach (['Regular', 'Irregular', 'Transferee', 'Returning'] as $classification): ?>
                    <option
                        <?= ($values['enrollment_type'] ?? '') === $classification ? 'selected' : '' ?>
                    ><?= $classification ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
</fieldset>
