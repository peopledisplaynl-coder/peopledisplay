<?php
/**
 * birthday.php
 * Upload naar: /includes/birthday.php
 *
 * Birthdays of employees are stored as "MM-DD" only (no year, privacy) in
 * employees.verjaardag. Filling it in is the opt-in for the "Vandaag jarig" slide.
 */

/** Normalise a birthday input ("dd-mm", "d-m", "dd-mm-jjjj", "jjjj-mm-dd") to "MM-DD"; '' when empty, null when invalid. */
function pd_birthday_normalize(?string $input): ?string
{
    $v = trim((string)$input);
    if ($v === '') return '';
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $v, $m)) {
        [$d, $mo] = [(int)$m[3], (int)$m[2]];
    } elseif (preg_match('/^(\d{1,2})[-\/. ](\d{1,2})(?:[-\/. ]\d{2,4})?$/', $v, $m)) {
        [$d, $mo] = [(int)$m[1], (int)$m[2]];
    } else {
        return null;
    }
    if (!checkdate($mo, $d, 2024)) return null; // leap year: 29-02 allowed
    return sprintf('%02d-%02d', $mo, $d);
}

/** "MM-DD" → "DD-MM" for forms and exports. */
function pd_birthday_display(?string $mmdd): string
{
    return preg_match('/^(\d{2})-(\d{2})$/', (string)$mmdd, $m) ? $m[2] . '-' . $m[1] : '';
}

/**
 * Store the birthday of one employee from form input. Returns false (value unchanged)
 * when the input is not a valid day and month; an empty input clears it.
 */
function pd_birthday_save(PDO $db, int $employeeId, ?string $input): bool
{
    $value = pd_birthday_normalize($input);
    if ($value === null) return false;
    try {
        $db->prepare("UPDATE employees SET verjaardag = ? WHERE id = ?")
           ->execute([$value === '' ? null : $value, $employeeId]);
    } catch (\Throwable $e) {
        // column not migrated yet: ignore
    }
    return true;
}
