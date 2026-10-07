<?php
$input = static function (
    string $key,
    string $label,
    int $max,
    bool $required = false,
    string $type = 'text'
) use ($values, $escape): void {
    echo '<label' . dev_locator_attributes(__FILE__, __LINE__) . ' for="' . $key . '">'
        . $escape($label)
        . '<input' . dev_locator_attributes(__FILE__, __LINE__) . ' id="' . $key . '" name="' . $key . '" type="' . $type
        . '" maxlength="' . $max . '"'
        . ($required ? ' required' : '')
        . ' value="' . $escape($values[$key] ?? '') . '"></label>';
};
?>
<?php if (empty($academicOnly)): ?>
    <fieldset <?= dev_locator_attributes(__FILE__, __LINE__) ?> class="profile-section"<?= !empty($registrationSteps) ? ' data-registration-step="0"' : '' ?>>
        <legend <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Personal details</legend>
        <div class="profile-grid" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
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
<fieldset <?= dev_locator_attributes(__FILE__, __LINE__) ?> class="profile-section"<?= !empty($registrationSteps) ? ' data-registration-step="1"' : '' ?>>
    <legend <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Academic details</legend>
    <div class="profile-grid" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
        <?php if (empty($academicOnly)): ?>
            <?php $input('student_number', 'Student ID number', 30, true); ?>
        <?php endif; ?>
        <label <?= dev_locator_attributes(__FILE__, __LINE__) ?> for="college_id">
            College
            <select id="college_id" <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="college_id" required>
                <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="">Select college</option>
                <?php foreach ($catalog['colleges'] as $college): ?>
                    <option <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                        value="<?= (int)$college['college_id'] ?>"
                        <?= (string)($values['college_id'] ?? '') === (string)$college['college_id']
                            ? 'selected' : '' ?>
                    ><?= $escape($college['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label for="program_id" class="full" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            Program / Course
            <select id="program_id" <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="program_id" required>
                <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="">Select program</option>
                <?php foreach ($catalog['programs'] as $program): ?>
                    <option <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                        value="<?= (int)$program['program_id'] ?>"
                        data-college="<?= (int)$program['college_id'] ?>"
                        data-years="<?= (int)$program['max_year_level'] ?>"
                        <?= (string)($values['program_id'] ?? '') === (string)$program['program_id']
                            ? 'selected' : '' ?>
                    ><?= $escape($program['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label <?= dev_locator_attributes(__FILE__, __LINE__) ?> for="year_level">
            Year level
            <select id="year_level" <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="year_level" required>
                <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="">Select year level</option>
                <?php for ($year = 1; $year <= 8; $year++): ?>
                    <option <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                        value="<?= $year ?>"
                        <?= (string)($values['year_level'] ?? '') === (string)$year ? 'selected' : '' ?>
                    >Year <?= $year ?></option>
                <?php endfor; ?>
            </select>
        </label>
        <?php $input('section', 'Section / Block (optional)', 50); ?>
        <label <?= dev_locator_attributes(__FILE__, __LINE__) ?> for="term_id">
            Academic year / Term
            <select id="term_id" <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="term_id" required>
                <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="">Select academic term</option>
                <?php foreach ($catalog['terms'] as $term): ?>
                    <option <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                        value="<?= (int)$term['term_id'] ?>"
                        <?= (string)($values['term_id'] ?? '') === (string)$term['term_id']
                            ? 'selected' : '' ?>
                    ><?= $escape($term['academic_year'] . ' / ' . $term['semester']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label <?= dev_locator_attributes(__FILE__, __LINE__) ?> for="enrollment_type">
            Enrollment classification
            <select id="enrollment_type" <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="enrollment_type" required>
                <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="">Select classification</option>
                <?php foreach (['Regular', 'Irregular', 'Transferee', 'Returning'] as $classification): ?>
                    <option <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                        <?= ($values['enrollment_type'] ?? '') === $classification ? 'selected' : '' ?>
                    ><?= $classification ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
</fieldset>
