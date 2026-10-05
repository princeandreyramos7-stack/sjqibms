<?php
declare(strict_types=1);

require_once __DIR__ . '/health.php';

// Health programs (migration 20261018_health_programs): Immunization (simple record of vaccines given), Nutrition
// (Operation Timbang), Chronic Care and Referrals, the "Needs attention" bar and the Monthly Health Report. (The Maternal
// tab was removed by the owner on 2026-10-04: prenatal check-ups are recorded as consultations; health_pregnancies stays empty.) Health Workers only, within their assigned
// Puroks (residents_purok_scope()). Every record points to an existing resident; names, birthdates, sex and addresses
// are read from the resident profile, never typed again. Records are archived, never deleted.

function health_programs_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) {
        $tables = (int) $connection->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('health_pregnancies', 'health_vaccines', 'health_immunizations', 'health_growth_reference', 'health_nutrition', 'health_chronic_cases')")->fetchColumn();
        $ready = $tables === 6 && health_programs_columns_ready($connection);
    }
    return $ready;
}

function health_programs_require(PDO $connection): void
{
    health_require_manage();
    if (!health_programs_ready($connection)) { flash('health_error', 'This part of the Health module needs its database update first.'); redirect('health.php'); }
}

// Tabs (same style as the Disaster Management tabs).
function health_tabs(string $active): string
{
    $tabs = ['consultations' => ['health.php', 'Consultations'], 'immunization' => ['health_immunization.php', 'Immunization'], 'nutrition' => ['health_nutrition.php', 'Nutrition'], 'chronic' => ['health_chronic.php', 'Chronic Care'], 'referrals' => ['health_referrals.php', 'Referrals']];
    if (!health_programs_ready(db())) $tabs = array_slice($tabs, 0, 1, true);
    $html = '<nav class="announcement-tabs page-tabs health-tabs" aria-label="Health sections">';
    foreach ($tabs as $key => [$href, $label]) $html .= '<a class="announcement-tab' . ($key === $active ? ' active" aria-current="page' : '') . '" href="' . e($href) . '">' . e($label) . '</a>';
    return $html . '</nav>';
}

// Purok scope for residents aliased r: [sql, params] (1 = 1 when the Health Worker has no assigned Puroks).
function health_scope(string $prefix = 'scope'): array
{
    return residents_purok_scope_sql(db(), 'r.purok', $prefix);
}

// Completed months / days of age on a date.
function health_age_months(string $birth_date, string $on): int
{
    $diff = (new DateTimeImmutable($birth_date))->diff(new DateTimeImmutable($on));
    return $diff->invert ? -1 : $diff->y * 12 + $diff->m;
}

function health_age_label(?string $birth_date, ?string $on = null): string
{
    if (!$birth_date) return 'Age unknown';
    $months = health_age_months($birth_date, $on ?? date('Y-m-d'));
    if ($months < 0) return 'Not yet born';
    if ($months < 24) return $months . ' mo';
    return intdiv($months, 12) . ' yrs' . ($months < 60 && $months % 12 ? ' ' . ($months % 12) . ' mo' : '');
}

// A resident within the Health Worker's Puroks (null otherwise). $kind: '' any active, 'female' (10+ years),
// 'under5' (0–59 months today).
function health_program_resident(PDO $connection, int $id, string $kind = '', bool $allow_inactive = false): ?array
{
    $resident = health_resident($connection, $id);
    if ($resident === null || (!$allow_inactive && $resident['status'] !== 'active')) return null;
    if ($kind === 'female' && ($resident['sex'] !== 'female' || $resident['birth_date'] === null || health_age_months($resident['birth_date'], date('Y-m-d')) < 120)) return null;
    if ($kind === 'under5' && ($resident['birth_date'] === null || ($m = health_age_months($resident['birth_date'], date('Y-m-d'))) < 0 || $m > 59)) return null;
    return $resident;
}

function health_program_audit(PDO $connection, int $id, string $action, array $details = []): void
{
    health_audit($connection, $id, $action, $details);
}

// Archive / restore of a program record (immunization, nutrition, chronic case).
function health_program_tables(): array
{
    return [
        'immunization' => ['health_immunizations', 'health_immunization', 'Vaccine record'],
        'nutrition' => ['health_nutrition', 'health_nutrition', 'Weighing'],
        'chronic' => ['health_chronic_cases', 'health_chronic', 'Chronic care record'],
    ];
}

// Archive / Restore button for a program record; returns to the page it was pressed on.
function health_program_archive_button(string $type, array $row): string
{
    $archived = ($row['archived_at'] ?? null) !== null;
    $label = health_program_tables()[$type][2] ?? 'Record';
    $back = basename((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH)) . (($query = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY)) !== '' ? '?' . $query : '');
    return '<form method="post" action="health_program_action.php" class="doc-action-form">' . csrf_field()
        . '<input type="hidden" name="type" value="' . e($type) . '"><input type="hidden" name="id" value="' . e((string) $row['id']) . '"><input type="hidden" name="action" value="' . ($archived ? 'restore' : 'archive') . '"><input type="hidden" name="back" value="' . e($back) . '">'
        . '<button class="btn btn-sm ' . ($archived ? 'btn-outline-primary' : 'btn-outline-danger') . '" type="submit" data-form-confirm="custom" data-dialog-heading="' . e($archived ? "Restore this $label?" : "Archive this $label?") . '" data-dialog-message="' . e($archived ? 'It will be counted again.' : 'It will be hidden and no longer counted. It is kept and can be restored.') . '" data-dialog-confirm="' . ($archived ? 'Restore' : 'Archive') . '" data-dialog-dismiss="Cancel"' . ($archived ? '' : ' data-dialog-danger="true"') . '>' . ($archived ? 'Restore' : 'Archive') . '</button></form>';
}

// Safe return page after an action (Health pages only).
function health_program_back(string $back, string $default): string
{
    $page = (string) parse_url($back, PHP_URL_PATH);
    $ok = preg_match('/^health[a-z_]*\.php$/', $page) === 1 && is_file(__DIR__ . '/../' . $page) && preg_match('/^[A-Za-z0-9_=&%+.\-]*$/', (string) parse_url($back, PHP_URL_QUERY)) === 1;
    return $ok ? $back : $default;
}

// ── Immunization (simple record of vaccines given) ────────────────────────────────
// The Health Worker records each vaccine given: the resident, the vaccine name (typed, or picked from names used
// before), the dose number if any, the date, who gave it and the lot number. There is no schedule and no due or
// missed computation. Vaccine names are kept in health_vaccines only as the list of names already used.

// Vaccine names already used (for the suggestion list on the form).
function health_vaccine_names(PDO $connection): array
{
    return $connection->query('SELECT DISTINCT name FROM health_vaccines ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
}

// Id of the vaccine name + dose in the name list, added on first use. Called inside the caller's transaction.
function health_vaccine_id(PDO $connection, string $name, int $dose): int
{
    // A name already used (any letter case) is kept as first written, so "measles" becomes "Measles".
    $known = $connection->prepare('SELECT name FROM health_vaccines WHERE LOWER(name) = LOWER(:n) ORDER BY id LIMIT 1');
    $known->execute(['n' => $name]);
    $name = (string) ($known->fetchColumn() ?: $name);
    $find = $connection->prepare('SELECT id FROM health_vaccines WHERE LOWER(name) = LOWER(:n) AND dose_no = :d LIMIT 1');
    $find->execute(['n' => $name, 'd' => $dose]);
    $id = $find->fetchColumn();
    if ($id !== false) return (int) $id;
    // code: a short unique key from the name (the table needs one); due ages are not used.
    $base = substr(strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $name) ?: 'VACCINE'), 0, 24);
    $code = $base;
    for ($i = 2; ; $i++) {
        $taken = $connection->prepare('SELECT COUNT(*) FROM health_vaccines WHERE code = :c AND dose_no = :d');
        $taken->execute(['c' => $code, 'd' => $dose]);
        if ((int) $taken->fetchColumn() === 0) break;
        $code = $base . '-' . $i;
    }
    $connection->prepare('INSERT INTO health_vaccines (code, name, dose_no, due_age_days, late_after_days, source_note) VALUES (:c, :n, :d, 0, 0, :s)')->execute(['c' => $code, 'n' => $name, 'd' => $dose, 's' => 'Entered by a Health Worker']);
    return (int) $connection->lastInsertId();
}

function health_vaccine_label(array $row): string
{
    return $row['name'] . ((int) ($row['dose_no'] ?? 1) > 1 ? ' — dose ' . (int) $row['dose_no'] : '');
}

function health_immunization_validate(PDO $connection, array $input): array
{
    $v = [
        'resident_id' => (int) ($input['resident_id'] ?? 0),
        'vaccine_name' => residents_collapse($input['vaccine_name'] ?? ''),
        'dose_no' => trim((string) ($input['dose_no'] ?? '')),
        'date_given' => trim((string) ($input['date_given'] ?? '')),
        'given_by' => residents_collapse($input['given_by'] ?? ''),
        'lot_no' => residents_collapse($input['lot_no'] ?? ''),
        'remarks' => residents_collapse($input['remarks'] ?? ''),
    ];
    $errors = [];
    $resident = health_program_resident($connection, $v['resident_id']);
    if ($resident === null) $errors['resident_id'] = 'Select the resident from the list.';
    if (mb_strlen($v['vaccine_name']) < 2 || mb_strlen($v['vaccine_name']) > 120) $errors['vaccine_name'] = 'Enter the vaccine name (2 to 120 characters).';
    if ($v['dose_no'] === '') $v['dose_no'] = 1;
    elseif (($dose = filter_var($v['dose_no'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]])) === false) $errors['dose_no'] = 'Dose number 1 to 10, or leave blank.';
    else $v['dose_no'] = $dose;
    $date = health_valid_date($v['date_given']);
    if ($date === null) $errors['date_given'] = 'Enter a valid date.';
    elseif ($date > new DateTimeImmutable('today')) $errors['date_given'] = 'The date cannot be in the future.';
    elseif ($resident !== null && $resident['birth_date'] !== null && $date->format('Y-m-d') < $resident['birth_date']) $errors['date_given'] = 'The date cannot be before the birthdate.';
    if (mb_strlen($v['given_by']) < 2 || mb_strlen($v['given_by']) > 150 || !preg_match("/^\p{L}[\p{L}\p{M}\s.,'\-]*$/u", $v['given_by'])) $errors['given_by'] = 'Enter the name of who gave the vaccine.';
    if (mb_strlen($v['lot_no']) > 50) $errors['lot_no'] = 'Keep the lot number to 50 characters.';
    if (mb_strlen($v['remarks']) > 255) $errors['remarks'] = 'Keep the remarks to 255 characters.';
    foreach (['lot_no', 'remarks'] as $key) if ($v[$key] === '') $v[$key] = null;
    return [$v, $errors];
}

// Children followed for immunization and nutrition: active residents 0–59 months old today.
function health_under5_sql(): string
{
    return "r.status = 'active' AND r.birth_date IS NOT NULL AND r.birth_date <= CURDATE() AND r.birth_date > DATE_SUB(CURDATE(), INTERVAL 60 MONTH)";
}

// ── Nutrition (WHO Child Growth Standards) ───────────────────────────────────────

function health_wfa_statuses(): array { return ['severely_underweight' => 'Severely underweight', 'underweight' => 'Underweight', 'normal' => 'Normal', 'overweight' => 'Overweight']; }
function health_hfa_statuses(): array { return ['severely_stunted' => 'Severely stunted', 'stunted' => 'Stunted', 'normal' => 'Normal', 'tall' => 'Tall']; }
function health_wfh_statuses(): array { return ['severely_wasted' => 'Severely wasted', 'wasted' => 'Wasted', 'normal' => 'Normal', 'overweight' => 'Overweight', 'obese' => 'Obese']; }

function health_nutrition_badge(?string $status, array $labels): string
{
    if ($status === null || $status === '') return '<span class="activity-detail-muted">—</span>';
    $tone = in_array($status, ['normal'], true) ? 'active' : (in_array($status, ['severely_underweight', 'severely_stunted', 'severely_wasted', 'obese'], true) ? 'deceased' : 'pending');
    return '<span class="resident-status resident-status-' . e($tone) . '">' . e($labels[$status] ?? $status) . '</span>';
}

function health_growth_reference_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) $ready = (int) $connection->query('SELECT COUNT(DISTINCT indicator) FROM health_growth_reference')->fetchColumn() === 4;
    return $ready;
}

// L, M, S for an indicator at x (months for wfa/lhfa; cm for wfl/wfh, linearly interpolated between the 0.5 cm rows).
function health_lms(PDO $connection, string $indicator, string $sex, float $x): ?array
{
    $statement = $connection->prepare('(SELECT x_value, l_value, m_value, s_value FROM health_growth_reference WHERE indicator = :i AND sex = :s AND x_value <= :x ORDER BY x_value DESC LIMIT 1) UNION ALL (SELECT x_value, l_value, m_value, s_value FROM health_growth_reference WHERE indicator = :i2 AND sex = :s2 AND x_value >= :x2 ORDER BY x_value ASC LIMIT 1)');
    $statement->execute(['i' => $indicator, 's' => $sex, 'x' => $x, 'i2' => $indicator, 's2' => $sex, 'x2' => $x]);
    $rows = $statement->fetchAll();
    if (count($rows) < 2) return null;   // outside the WHO table
    [$a, $b] = $rows;
    $span = (float) $b['x_value'] - (float) $a['x_value'];
    $t = $span > 0 ? ($x - (float) $a['x_value']) / $span : 0.0;
    $mix = static fn (string $k): float => (float) $a[$k] + ((float) $b[$k] - (float) $a[$k]) * $t;
    return ['L' => $mix('l_value'), 'M' => $mix('m_value'), 'S' => $mix('s_value')];
}

// WHO z-score from LMS; for weight indicators beyond ±3 SD the WHO restricted computation is used.
function health_zscore(float $value, array $lms, bool $restricted): float
{
    ['L' => $l, 'M' => $m, 'S' => $s] = $lms;
    $z = abs($l) < 1e-9 ? log($value / $m) / $s : (($value / $m) ** $l - 1) / ($l * $s);
    if (!$restricted || abs($z) <= 3) return $z;
    $sd = static fn (float $k): float => abs($l) < 1e-9 ? $m * exp($s * $k) : $m * (1 + $l * $s * $k) ** (1 / $l);
    if ($z > 3) return 3 + ($value - $sd(3)) / ($sd(3) - $sd(2));
    return -3 + ($value - $sd(-3)) / ($sd(-2) - $sd(-3));
}

// Nutritional status of a child from the WHO standards (null where the WHO table does not cover the measurement).
// Length is measured lying under 2 years and height standing from 2 years; a measurement taken the other way is
// adjusted by 0.7 cm as the WHO instructs.
function health_who_status(PDO $connection, string $sex, int $age_months, float $weight, float $height, bool $lying): array
{
    $out = ['wfa_status' => null, 'hfa_status' => null, 'wfh_status' => null, 'z' => []];
    if (!in_array($sex, ['male', 'female'], true) || $age_months < 0 || $age_months > 60) return $out;
    $length = $height;
    if ($age_months < 24 && !$lying) $length = $height + 0.7;
    if ($age_months >= 24 && $lying) $length = $height - 0.7;
    if ($lms = health_lms($connection, 'wfa', $sex, $age_months)) {
        $z = health_zscore($weight, $lms, true);
        $out['z']['wfa'] = round($z, 2);
        $out['wfa_status'] = $z < -3 ? 'severely_underweight' : ($z < -2 ? 'underweight' : ($z > 2 ? 'overweight' : 'normal'));
    }
    if ($lms = health_lms($connection, 'lhfa', $sex, $age_months)) {
        $z = health_zscore($length, $lms, false);
        $out['z']['hfa'] = round($z, 2);
        $out['hfa_status'] = $z < -3 ? 'severely_stunted' : ($z < -2 ? 'stunted' : ($z > 2 ? 'tall' : 'normal'));
    }
    if ($lms = health_lms($connection, $age_months < 24 ? 'wfl' : 'wfh', $sex, round($length, 1))) {
        $z = health_zscore($weight, $lms, true);
        $out['z']['wfh'] = round($z, 2);
        $out['wfh_status'] = $z < -3 ? 'severely_wasted' : ($z < -2 ? 'wasted' : ($z > 3 ? 'obese' : ($z > 2 ? 'overweight' : 'normal')));
    }
    return $out;
}

function health_nutrition_validate(PDO $connection, array $input): array
{
    $v = [
        'resident_id' => (int) ($input['resident_id'] ?? 0),
        'weigh_date' => trim((string) ($input['weigh_date'] ?? '')),
        'weight_kg' => trim(str_replace(',', '.', (string) ($input['weight_kg'] ?? ''))),
        'height_cm' => trim(str_replace(',', '.', (string) ($input['height_cm'] ?? ''))),
        'measured_lying' => (string) ($input['measured_lying'] ?? '') === '1' ? 1 : 0,
        'wfa_status' => (string) ($input['wfa_status'] ?? ''),
        'hfa_status' => (string) ($input['hfa_status'] ?? ''),
        'wfh_status' => (string) ($input['wfh_status'] ?? ''),
        'remarks' => residents_collapse($input['remarks'] ?? ''),
    ];
    $errors = [];
    $child = health_program_resident($connection, $v['resident_id'], '', false);
    $date = health_valid_date($v['weigh_date']);
    if ($date === null) $errors['weigh_date'] = 'Enter a valid date.';
    elseif ($date > new DateTimeImmutable('today')) $errors['weigh_date'] = 'The date cannot be in the future.';
    elseif ($date < new DateTimeImmutable('today -2 years')) $errors['weigh_date'] = 'Enter a weighing from the past two years.';
    if ($child === null || $child['birth_date'] === null) $errors['resident_id'] = 'Select a child from the list.';
    elseif ($date !== null) {
        $v['age_months'] = health_age_months($child['birth_date'], $date->format('Y-m-d'));
        if ($v['age_months'] < 0 || $v['age_months'] > 59) $errors['resident_id'] = 'Operation Timbang covers children 0–59 months old on the weighing date.';
    }
    $weight = filter_var($v['weight_kg'], FILTER_VALIDATE_FLOAT);
    if ($weight === false || $weight < 1 || $weight > 40 || !preg_match('/^\d+(\.\d{1,2})?$/', $v['weight_kg'])) $errors['weight_kg'] = 'Weight must be 1–40 kg (up to 2 decimals).'; else $v['weight_kg'] = round($weight, 2);
    $height = filter_var($v['height_cm'], FILTER_VALIDATE_FLOAT);
    if ($height === false || $height < 40 || $height > 130 || !preg_match('/^\d+(\.\d)?$/', $v['height_cm'])) $errors['height_cm'] = 'Length / height must be 40–130 cm (1 decimal).'; else $v['height_cm'] = round($height, 1);
    if (mb_strlen($v['remarks']) > 255) $errors['remarks'] = 'Keep the remarks to 255 characters.';
    if ($errors === []) {
        if (health_growth_reference_ready($connection)) {
            $who = health_who_status($connection, (string) $child['sex'], (int) $v['age_months'], (float) $v['weight_kg'], (float) $v['height_cm'], $v['measured_lying'] === 1);
            $v['wfa_status'] = $who['wfa_status']; $v['hfa_status'] = $who['hfa_status']; $v['wfh_status'] = $who['wfh_status'];
            $v['status_source'] = 'who_auto';
            $v['z'] = $who['z'];
        } else {
            // No WHO table loaded: the Health Worker reads the status from the official chart.
            if (!array_key_exists($v['wfa_status'], health_wfa_statuses())) $errors['wfa_status'] = 'Select the weight-for-age status from the chart.';
            if ($v['hfa_status'] !== '' && !array_key_exists($v['hfa_status'], health_hfa_statuses())) $errors['hfa_status'] = 'Select from the list.';
            if ($v['wfh_status'] !== '' && !array_key_exists($v['wfh_status'], health_wfh_statuses())) $errors['wfh_status'] = 'Select from the list.';
            $v['status_source'] = 'manual';
        }
    }
    foreach (['wfa_status', 'hfa_status', 'wfh_status', 'remarks'] as $key) if ($v[$key] === '') $v[$key] = null;
    return [$v, $errors];
}

// Latest weighing per child (alias n), not archived.
function health_latest_weighing_sql(): string
{
    return 'n.archived_at IS NULL AND n.id = (SELECT n2.id FROM health_nutrition n2 WHERE n2.resident_id = n.resident_id AND n2.archived_at IS NULL ORDER BY n2.weigh_date DESC, n2.id DESC LIMIT 1)';
}

// ── Chronic care ────────────────────────────────────────────────────────────────

function health_chronic_conditions(): array
{
    return ['hypertension' => 'Hypertension', 'diabetes' => 'Diabetes', 'tb' => 'Tuberculosis (TB)'];
}

function health_chronic_statuses(): array
{
    return ['active' => 'Active', 'completed' => 'Treatment completed', 'inactive' => 'Inactive'];
}

// The monitoring service used to record readings for each condition (health_records.service).
function health_chronic_service(string $condition): string
{
    return ['hypertension' => 'bp_monitoring', 'diabetes' => 'blood_sugar_monitoring', 'tb' => 'tb_dots_monitoring'][$condition] ?? 'check_up';
}

function health_chronic_validate(PDO $connection, array $input, ?array $existing = null): array
{
    $v = [
        'resident_id' => (int) ($existing['resident_id'] ?? ($input['resident_id'] ?? 0)),
        'condition_type' => (string) ($existing['condition_type'] ?? ($input['condition_type'] ?? '')),
        'diagnosed_on' => trim((string) ($input['diagnosed_on'] ?? '')),
        'status' => (string) ($input['status'] ?? 'active'),
        'maintenance_medicines' => trim((string) ($input['maintenance_medicines'] ?? '')),
        'is_serious' => (string) ($input['is_serious'] ?? '') === '1' ? 1 : 0,
        'remarks' => trim((string) ($input['remarks'] ?? '')),
    ];
    $errors = [];
    if ($existing === null && health_program_resident($connection, $v['resident_id']) === null) $errors['resident_id'] = 'Select a resident from the list.';
    if (!array_key_exists($v['condition_type'], health_chronic_conditions())) $errors['condition_type'] = 'Select the condition.';
    if (!array_key_exists($v['status'], health_chronic_statuses())) $errors['status'] = 'Select a status.';
    if ($v['diagnosed_on'] !== '') {
        $d = health_valid_date($v['diagnosed_on']);
        if ($d === null) $errors['diagnosed_on'] = 'Enter a valid date.';
        elseif ($d > new DateTimeImmutable('today') || $d < new DateTimeImmutable('1940-01-01')) $errors['diagnosed_on'] = 'Enter a realistic date (not in the future).';
    }
    if (mb_strlen($v['maintenance_medicines']) > 500) $errors['maintenance_medicines'] = 'Keep the medicines to 500 characters.';
    if (mb_strlen($v['remarks']) > 500) $errors['remarks'] = 'Keep the remarks to 500 characters.';
    if ($v['status'] === 'active' && !isset($errors['resident_id']) && !isset($errors['condition_type'])) {
        $dup = $connection->prepare("SELECT COUNT(*) FROM health_chronic_cases WHERE resident_id = :r AND condition_type = :c AND status = 'active' AND archived_at IS NULL AND id <> :id");
        $dup->execute(['r' => $v['resident_id'], 'c' => $v['condition_type'], 'id' => (int) ($existing['id'] ?? 0)]);
        if ((int) $dup->fetchColumn() > 0) $errors['condition_type'] = 'This resident already has an active record for this condition.';
    }
    foreach (['diagnosed_on', 'maintenance_medicines', 'remarks'] as $key) if ($v[$key] === '') $v[$key] = null;
    return [$v, $errors];
}

// Latest reading for a chronic case (alias c): BP for hypertension, blood sugar for diabetes, last TB-DOTS visit for TB.
function health_chronic_reading_sql(): string
{
    $base = 'FROM health_records hr WHERE hr.resident_id = c.resident_id AND hr.archived_at IS NULL AND hr.status IN (\'completed\', \'follow_up\')';
    return "(SELECT CONCAT(hr.service_date, '|', hr.bp_systolic, '/', hr.bp_diastolic) $base AND hr.bp_systolic IS NOT NULL ORDER BY hr.service_date DESC, hr.id DESC LIMIT 1) AS last_bp, "
        . "(SELECT CONCAT(hr.service_date, '|', hr.blood_sugar_mgdl) $base AND hr.blood_sugar_mgdl IS NOT NULL ORDER BY hr.service_date DESC, hr.id DESC LIMIT 1) AS last_sugar, "
        . "(SELECT MAX(hr.service_date) $base AND hr.service = 'tb_dots_monitoring') AS last_tb_visit";
}

function health_chronic_reading_label(array $row): string
{
    $split = static fn (?string $v): ?array => $v ? explode('|', $v, 2) : null;
    return match ($row['condition_type']) {
        'hypertension' => ($bp = $split($row['last_bp'] ?? null)) ? $bp[1] . ' mmHg · ' . health_format_date($bp[0]) : 'No BP reading yet',
        'diabetes' => ($s = $split($row['last_sugar'] ?? null)) ? rtrim(rtrim($s[1], '0'), '.') . ' mg/dL · ' . health_format_date($s[0]) : 'No blood sugar reading yet',
        default => !empty($row['last_tb_visit']) ? 'Last TB-DOTS visit ' . health_format_date($row['last_tb_visit']) : 'No TB-DOTS visit yet',
    };
}

// ── Referrals ───────────────────────────────────────────────────────────────────

function health_referral_status(array $row): string
{
    return ($row['referral_status'] ?? null) === 'completed' ? 'completed' : 'pending';
}

function health_referral_badge(string $status): string
{
    return $status === 'completed' ? '<span class="resident-status resident-status-active">Completed</span>' : '<span class="resident-status resident-status-pending">Pending</span>';
}

// ── "Needs attention" bar (top of every Health tab) ────────────────────────────────
// Only what needs action and has a count above zero is shown; each item opens that filtered list.
function health_dashboard_counts(PDO $connection): array
{
    [$scope_sql, $scope_params] = health_scope('dash');
    $count = static function (string $sql) use ($connection, $scope_params): int {
        $statement = $connection->prepare($sql);
        $statement->execute($scope_params);
        return (int) $statement->fetchColumn();
    };
    $from_records = 'SELECT COUNT(*) FROM health_records h INNER JOIN residents r ON r.id = h.resident_id WHERE ' . $scope_sql . ' AND ' . health_due_sql('h');
    $counts = [
        'overdue' => $count($from_records . ' AND ' . health_due_date_sql('h') . ' < CURDATE()'),
        'due_today' => $count($from_records . ' AND ' . health_due_date_sql('h') . ' = CURDATE()'),
    ];
    if (!health_programs_ready($connection)) return $counts;
    $counts['underweight'] = $count("SELECT COUNT(*) FROM health_nutrition n INNER JOIN residents r ON r.id = n.resident_id WHERE $scope_sql AND " . health_under5_sql() . ' AND ' . health_latest_weighing_sql() . " AND n.wfa_status IN ('underweight', 'severely_underweight')");
    $counts['pending_referrals'] = $count("SELECT COUNT(*) FROM health_records h INNER JOIN residents r ON r.id = h.resident_id WHERE $scope_sql AND h.archived_at IS NULL AND h.referred_rhu = 1 AND COALESCE(h.referral_status, 'pending') = 'pending'");
    return $counts;
}

function health_attention_bar(PDO $connection): string
{
    $counts = health_dashboard_counts($connection);
    $items = [
        'overdue' => ['overdue follow-up', 'overdue follow-ups', 'health.php?show=all&due=1&when=overdue'],
        'due_today' => ['visit due today', 'visits due today', 'health.php?show=all&due=1&when=today'],
        'underweight' => ['underweight child', 'underweight children', 'health_nutrition.php?status=underweight'],
        'pending_referrals' => ['pending referral', 'pending referrals', 'health_referrals.php?status=pending'],
    ];
    $links = [];
    foreach ($items as $key => [$one, $many, $href]) {
        $n = (int) ($counts[$key] ?? 0);
        if ($n > 0) $links[] = '<a class="health-attention-item" href="' . e($href) . '"><strong>' . e(number_format($n)) . '</strong> ' . e($n === 1 ? $one : $many) . ' <span aria-hidden="true">›</span></a>';
    }
    if ($links === []) return '<div class="health-attention is-clear" role="status"><span aria-hidden="true">✓</span> Nothing needs attention right now.</div>';
    return '<div class="health-attention" role="status"><span class="health-attention-label">Needs attention:</span>' . implode('', $links) . '</div>';
}

// ── Monthly Health Report ──────────────────────────────────────────────────────
// Month (YYYY-MM) and Purok from the query string; the Purok must be one the Health Worker may see.
function health_monthly_state(PDO $connection, array $input): array
{
    $month = (string) ($input['month'] ?? date('Y-m'));
    if (!preg_match('/^\d{4}-\d{2}$/', $month) || health_valid_date($month . '-01') === null || $month > date('Y-m') || $month < '2000-01') $month = date('Y-m');
    $purok = (string) ($input['purok'] ?? '');
    if ($purok !== '' && !in_array($purok, health_resident_puroks($connection), true)) $purok = '';
    $start = $month . '-01';
    return ['month' => $month, 'purok' => $purok, 'start' => $start, 'end' => (new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d'), 'label' => (new DateTimeImmutable($start))->format('F Y')];
}

function health_monthly_query(array $state): string
{
    return http_build_query(array_filter(['month' => $state['month'], 'purok' => $state['purok']]));
}

// Sections of the report: [title, [[label, count], …], note]. Counts only, no names.
function health_monthly_data(PDO $connection, array $state): array
{
    [$scope_sql, $scope_params] = health_scope('ms');
    $resident_where = $scope_sql . ($state['purok'] !== '' ? ' AND r.purok = :mpurok' : '');
    $base = $scope_params + ($state['purok'] !== '' ? ['mpurok' => $state['purok']] : []) + ['mstart' => $state['start'], 'mend' => $state['end']];
    // Native prepared statements cannot repeat a placeholder, so every occurrence gets its own name (:mend → :mend_1, …).
    $rows = static function (string $sql, array $extra = []) use ($connection, $base): array {
        $values = $extra + $base;
        $params = [];
        $n = 0;
        $sql = preg_replace_callback('/:([a-z][a-z0-9_]*)/', static function (array $m) use (&$params, &$n, $values): string {
            if (!array_key_exists($m[1], $values)) return $m[0];
            $name = $m[1] . '_' . ++$n;
            $params[$name] = $values[$m[1]];
            return ':' . $name;
        }, $sql);
        $statement = $connection->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_NUM);
    };
    $one = static fn (string $sql): int => (int) ($rows($sql)[0][0] ?? 0);
    $sections = [];

    // 1. Consultations (Completed and Follow-up visits in the month), including prenatal check-ups.
    $visit = "FROM health_records h INNER JOIN residents r ON r.id = h.resident_id WHERE $resident_where AND h.archived_at IS NULL AND h.status IN ('completed', 'follow_up') AND h.service_date BETWEEN :mstart AND :mend";
    $by_service = [];
    foreach ($rows("SELECT h.service, COUNT(*) $visit GROUP BY h.service") as [$service, $n]) $by_service[] = [health_services()[$service] ?? $service, (int) $n];
    $sex = $rows("SELECT SUM(r.sex = 'male'), SUM(r.sex = 'female') $visit")[0] ?? [0, 0];
    $sections[] = ['Consultations', array_merge([['Total consultations', $one("SELECT COUNT(*) $visit")], ['Residents seen', $one("SELECT COUNT(DISTINCT h.resident_id) $visit")], ['Male', (int) $sex[0]], ['Female', (int) $sex[1]]], $by_service), 'Completed and Follow-up visits dated in the month.'];

    // 2. Vaccines given (as recorded by the Health Workers).
    $vax = "FROM health_immunizations i INNER JOIN residents r ON r.id = i.resident_id INNER JOIN health_vaccines v ON v.id = i.vaccine_id WHERE $resident_where AND i.archived_at IS NULL AND i.date_given BETWEEN :mstart AND :mend";
    $by_vaccine = [];
    foreach ($rows("SELECT v.name, v.dose_no, COUNT(*) $vax GROUP BY v.id, v.name, v.dose_no ORDER BY v.name, v.dose_no") as [$name, $dose, $n]) $by_vaccine[] = [health_vaccine_label(['name' => $name, 'dose_no' => $dose]), (int) $n];
    $sections[] = ['Vaccines Given', array_merge([['Total vaccines given', $one("SELECT COUNT(*) $vax")], ['Residents vaccinated', $one("SELECT COUNT(DISTINCT i.resident_id) $vax")]], $by_vaccine), 'Vaccines recorded with a date given in the month.'];

    // 3. Children weighed and their nutritional status (each child's latest weighing in the month).
    $weighed = "FROM health_nutrition n INNER JOIN residents r ON r.id = n.resident_id WHERE $resident_where AND n.archived_at IS NULL AND n.weigh_date BETWEEN :mstart AND :mend AND n.id = (SELECT n2.id FROM health_nutrition n2 WHERE n2.resident_id = n.resident_id AND n2.archived_at IS NULL AND n2.weigh_date BETWEEN :mstart2 AND :mend2 ORDER BY n2.weigh_date DESC, n2.id DESC LIMIT 1)";
    $extra = ['mstart2' => $state['start'], 'mend2' => $state['end']];
    $nutrition = [['Children weighed', (int) ($rows("SELECT COUNT(*) $weighed", $extra)[0][0] ?? 0)]];
    foreach (['wfa_status' => ['Weight-for-age', health_wfa_statuses()], 'hfa_status' => ['Height-for-age', health_hfa_statuses()], 'wfh_status' => ['Weight-for-height', health_wfh_statuses()]] as $column => [$label, $labels]) {
        $counts = [];
        foreach ($rows("SELECT n.$column, COUNT(*) $weighed GROUP BY n.$column", $extra) as [$k, $n]) $counts[(string) $k] = (int) $n;
        foreach ($labels as $key => $name) $nutrition[] = [$label . ': ' . $name, $counts[$key] ?? 0];
    }
    $sections[] = ['Nutrition (Operation Timbang)', $nutrition, 'Latest weighing of each child in the month, WHO Child Growth Standards.'];

    // 4. Referrals to the RHU.
    $ref = "FROM health_records h INNER JOIN residents r ON r.id = h.resident_id WHERE $resident_where AND h.archived_at IS NULL AND h.referred_rhu = 1";
    $sections[] = ['Referrals to the RHU', [
        ['Referred in the month', $one("SELECT COUNT(*) $ref AND h.service_date BETWEEN :mstart AND :mend")],
        ['Completed in the month', $one("SELECT COUNT(*) $ref AND h.referral_status = 'completed' AND h.referral_completed_on BETWEEN :mstart AND :mend")],
        ['Still pending at the end of the month', $one("SELECT COUNT(*) $ref AND h.service_date <= :mend AND (COALESCE(h.referral_status, 'pending') = 'pending' OR h.referral_completed_on > :mend)")],
    ], 'Pending at the end of the month: referred on or before the last day and not completed by then.'];
    return $sections;
}

// ── Page layout shared by the Health tabs ──────────────────────────────────────────
// Heading: "Health", one short line, the tab's main button and a Reports menu.
function health_page_heading(string $description, string $primary_href = '', string $primary_label = ''): string
{
    return '<div class="page-heading health-heading"><div><h1>Health</h1><p>' . e($description) . '</p></div><div class="resident-detail-actions">'
        . ($primary_href !== '' ? '<a class="btn btn-primary announcements-new-btn" href="' . e($primary_href) . '">' . e($primary_label) . '</a>' : '')
        . health_reports_menu() . '</div></div>';
}

function health_reports_menu(): string
{
    $links = [['health_morbidity.php', 'Morbidity Report'], ['module_report.php?report=health', 'Health Report']];
    if (health_programs_ready(db())) $links[] = ['health_monthly.php', 'Monthly Report'];
    return '<details class="health-menu"><summary class="btn btn-light resident-action-btn">Reports <span aria-hidden="true">▾</span></summary><div class="health-menu-list">'
        . implode('', array_map(static fn (array $l): string => '<a href="' . e($l[0]) . '">' . e($l[1]) . '</a>', $links)) . '</div></details>';
}

// Under the heading: the tabs first, then the "Needs attention" bar.
function health_module_top(PDO $connection, string $active): string
{
    return health_tabs($active) . health_attention_bar($connection);
}



// Resident picker on program forms (same component as the health record form).
function health_resident_picker(?array $resident, string $filter, string $error = '', string $hint = ''): string
{
    $connection = db();
    $options = '';
    foreach (health_resident_puroks($connection) as $purok) $options .= '<option value="' . e($purok) . '">' . e(residents_purok_label($purok)) . '</option>';
    $invalid = $error !== '' ? ' is-invalid' : '';
    return '<div class="health-resident-picker" data-health-resident data-health-filter="' . e($filter) . '">'
        . '<input type="hidden" name="resident_id" value="' . e($resident ? (string) $resident['id'] : '') . '" data-health-resident-id>'
        . '<div class="health-selected' . ($resident ? '' : ' is-empty') . '" data-health-selected><div><strong data-health-selected-name>' . ($resident ? e(residents_full_name($resident)) : 'No resident selected') . '</strong><span data-health-selected-meta>' . ($resident ? e(health_resident_meta($resident)) : 'Select the Purok, then choose the resident.') . '</span></div><button class="btn btn-sm btn-outline-secondary" type="button" data-health-change' . ($resident ? '' : ' hidden') . '>Change</button></div>'
        . '<div class="health-search" data-health-search' . ($resident ? ' hidden' : '') . '><div class="health-search-grid">'
        . '<div><label class="form-label" for="health-purok">Purok <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select' . $invalid . '" id="health-purok" data-health-purok data-summary-skip><option value="">Select Purok</option>' . $options . '</select></div>'
        . '<div class="health-search-box"><label class="form-label" for="health-resident-search">Resident <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control' . $invalid . '" type="search" id="health-resident-search" placeholder="Select a Purok first" autocomplete="off" maxlength="100" disabled data-health-query data-summary-skip aria-controls="health-resident-list" aria-expanded="false"><div class="case-lookup-results health-dropdown" id="health-resident-list" role="listbox" aria-label="Residents in the selected Purok" data-health-results hidden></div></div>'
        . '</div><p class="health-hint" data-health-hint>' . e($hint !== '' ? $hint : 'Select the resident\'s Purok, then pick the resident from the list.') . '</p></div>'
        . ($error !== '' ? '<div class="invalid-feedback d-block">' . e($error) . '</div>' : '') . '</div>';
}
