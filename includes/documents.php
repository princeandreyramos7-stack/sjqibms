<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/navigation.php';
require_once __DIR__ . '/residents.php';

// Document requests (document_requests table). Every request — online or staff-assisted — is created through
// documents_create_request() and processed through document_action.php.
// Current schema supports: pending → approved, pending → rejected (reason kept in the audit trail), and existing released rows.
// Processing, Ready for Release, Cancelled, fees, claimant release records, templates and document notifications need the
// review-only migration in database/migrations/review/ (not imported).

// INTERIM type list until the approved document_types table exists. Names/descriptions are not barangay-approved wording.
function documents_types(): array
{
    return [
        'Barangay Clearance' => 'General clearance for employment, travel, banking, and other requirements.',
        'Certificate of Residency' => 'Certifies that you are a resident of Barangay San Jose.',
        'Certificate of Indigency' => 'For medical, burial, educational, or financial assistance applications.',
        'Certificate of Good Moral Character' => 'For school, scholarship, or employment requirements.',
        'Business Clearance' => 'Required for new or renewing small businesses operating in the barangay.',
        // 'Building / Construction Clearance' was removed by the owner (2026-10-04).
    ];
}

function documents_status_labels(): array
{
    return ['pending' => 'Pending Review', 'approved' => 'Approved', 'released' => 'Released', 'rejected' => 'Rejected'];
}

function documents_status_badge(string $status): string
{
    $tone = ['pending' => 'pending', 'approved' => 'moved', 'released' => 'active', 'rejected' => 'deceased'][$status] ?? 'inactive';
    $label = ['pending' => 'Pending', 'approved' => 'Approved', 'released' => 'Released', 'rejected' => 'Rejected'][$status] ?? ucfirst($status);
    return '<span class="resident-status resident-status-' . e($tone) . '">' . e($label) . '</span>';
}

function documents_can_process(): bool
{
    return role_can('documents.process');
}

// The signed-in resident's linked profile (users.resident_id), or null when the account is not linked yet.
function documents_resident_profile(PDO $connection): ?array
{
    $statement = $connection->prepare('SELECT r.id, r.first_name, r.middle_name, r.last_name, r.suffix, r.birth_date, r.sex, r.civil_status, r.contact_number, r.address, r.purok, r.status FROM users u INNER JOIN residents r ON r.id = u.resident_id WHERE u.id = :user_id LIMIT 1');
    $statement->execute(['user_id' => current_user()['id']]);
    $row = $statement->fetch();
    return $row ?: null;
}

function documents_validate_request(array $input): array
{
    $type = (string) ($input['document_type'] ?? '');
    $purpose = trim(preg_replace('/\s+/u', ' ', (string) ($input['purpose'] ?? '')) ?? '');
    $errors = [];
    if (!array_key_exists($type, documents_types())) $errors['document_type'] = 'Select a document type.';
    if (mb_strlen($purpose) < 5 || mb_strlen($purpose) > 500) $errors['purpose'] = 'Describe the purpose in 5 to 500 characters.';
    return ['values' => ['document_type' => $type, 'purpose' => $purpose], 'errors' => $errors];
}

function documents_generate_reference(PDO $connection): string
{
    $check = $connection->prepare('SELECT 1 FROM document_requests WHERE reference_code = :code');
    do {
        $code = 'DOC-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $check->execute(['code' => $code]);
    } while ($check->fetchColumn());
    return $code;
}

function documents_audit(PDO $connection, int $request_id, string $action, array $details = []): void
{
    residents_audit($connection, 'document', $request_id, $action, $details);
}

function documents_for_resident(PDO $connection, int $resident_id, int $limit = 50): array
{
    $statement = $connection->prepare('SELECT id, document_type, purpose, status, reference_code, requested_at, approved_at, released_at FROM document_requests WHERE resident_id = :resident_id ORDER BY requested_at DESC, id DESC LIMIT :limit');
    $statement->bindValue('resident_id', $resident_id, PDO::PARAM_INT);
    $statement->bindValue('limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

function documents_format_datetime(?string $value): string
{
    if (!$value) return '';
    $date = date_create($value);
    return $date ? $date->format('M j, Y g:i A') : $value;
}

// ── Request lookup and access ────────────────────────────────────────────────

// Loads one request by its numeric ID (never by the displayed reference) with the resident and approver names.
function documents_find(PDO $connection, int $id): ?array
{
    $residency = residents_residency_ready($connection) ? ' r.residency_start_year,' : '';
    $statement = $connection->prepare('SELECT d.id, d.resident_id, d.document_type, d.purpose, d.status, d.reference_code, d.requested_at, d.approved_at, d.released_at, d.approved_by, r.first_name, r.middle_name, r.last_name, r.suffix, r.civil_status, r.address, r.purok,' . $residency . ' r.status AS resident_status, a.name AS approver_name FROM document_requests d INNER JOIN residents r ON r.id = d.resident_id LEFT JOIN users a ON a.id = d.approved_by WHERE d.id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row ?: null;
}

// Staff with document processing rights see every request; a resident sees only requests for their own linked profile.
// Anyone else — including another resident — gets nothing (callers answer 404 so request IDs are not confirmed).
function documents_can_view_request(PDO $connection, array $request): bool
{
    if (documents_can_process()) return true;
    if (!has_role('resident', 'health_worker')) return false;   // Health Workers request documents for themselves too
    $profile = documents_resident_profile($connection);
    return $profile !== null && (int) $profile['id'] === (int) $request['resident_id'];
}

// Processing history from the audit trail (who did what, when, and the recorded reason). Oldest first.
function documents_history(PDO $connection, int $request_id): array
{
    $statement = $connection->prepare("SELECT l.action, l.details, l.created_at, u.name AS actor_name, u.role AS actor_role FROM audit_logs l LEFT JOIN users u ON u.id = l.user_id WHERE l.entity_type = 'document' AND l.entity_id = :id ORDER BY l.created_at ASC, l.id ASC");
    $statement->execute(['id' => $request_id]);
    $labels = ['document_requested' => 'Request submitted', 'document_approved' => 'Request approved', 'document_rejected' => 'Request rejected', 'document_released' => 'Document released'];
    $history = [];
    foreach ($statement->fetchAll() as $row) {
        $details = json_decode((string) $row['details'], true);
        $history[] = [
            'action' => $row['action'],
            'label' => $labels[$row['action']] ?? 'Recorded update',
            'actor' => $row['actor_name'] ?: 'Unavailable account',
            'actor_role' => $row['actor_role'],
            'at' => (string) $row['created_at'],
            'reason' => is_array($details) && isset($details['reason']) ? (string) $details['reason'] : null,
            'source' => is_array($details) && isset($details['source']) ? (string) $details['source'] : null,
            'received_by' => is_array($details) && isset($details['received_by']) ? (string) $details['received_by'] : null,
        ];
    }
    return $history;
}

// Request source, derived from the recorded submission (explicit 'source' when present, otherwise the submitting account's role).
function documents_request_source(array $history): ?string
{
    foreach ($history as $entry) {
        if ($entry['action'] !== 'document_requested') continue;
        $source = $entry['source'] ?? ($entry['actor_role'] === 'resident' ? 'online' : ($entry['actor_role'] !== null ? 'staff' : null));
        return match ($source) { 'online' => 'Online (resident account)', 'staff' => 'Staff-assisted', default => null };
    }
    return null;
}

function documents_rejection_reason(array $history): ?string
{
    foreach (array_reverse($history) as $entry) {
        if ($entry['action'] === 'document_rejected') return $entry['reason'];
    }
    return null;
}

// ── Centralized request creation (online and staff-assisted) ──────────────────

// Raised when the resident already has an open request for the same document; carries that request so staff pages can
// link straight to it (to release or reject it).
final class DocumentOpenRequestException extends RuntimeException
{
    public function __construct(string $message, public readonly int $request_id, public readonly string $reference, public readonly string $status)
    {
        parent::__construct($message);
    }
}

// Creates a request inside one transaction. $source is 'online' or 'staff'; the caller has already authorized the actor and
// resolved $resident_id (online: from the signed-in account's linked profile; staff: a selected existing resident).
function documents_create_request(PDO $connection, int $resident_id, string $document_type, string $purpose, string $source, int $max_open = 5): array
{
    $connection->beginTransaction();
    try {
        $resident = $connection->prepare('SELECT id, status FROM residents WHERE id = :id FOR UPDATE');
        $resident->execute(['id' => $resident_id]);
        $row = $resident->fetch();
        if (!$row) throw new RuntimeException('The selected resident profile no longer exists.');
        if ($row['status'] !== 'active') throw new RuntimeException('Only active resident profiles can request documents.');
        // Locks the resident's open requests so two quick submissions cannot both pass these checks.
        $open = $connection->prepare("SELECT id, document_type, reference_code, status FROM document_requests WHERE resident_id = :resident_id AND status IN ('pending', 'approved') FOR UPDATE");
        $open->execute(['resident_id' => $resident_id]);
        $open_rows = $open->fetchAll();
        $open_types = array_column($open_rows, 'document_type');
        foreach ($open_rows as $open_row) {
            if ($open_row['document_type'] === $document_type) throw new DocumentOpenRequestException('There is already an open request for a ' . $document_type . ' for this resident (' . $open_row['reference_code'] . ', ' . (documents_status_labels()[$open_row['status']] ?? $open_row['status']) . '). ' . ($open_row['status'] === 'approved' ? 'Release' : 'Approve and release, or reject,') . ' that request first.', (int) $open_row['id'], (string) $open_row['reference_code'], (string) $open_row['status']);
        }
        if (count($open_types) >= $max_open) throw new RuntimeException('A resident can have at most ' . $max_open . ' open requests at a time.');
        $reference = documents_generate_reference($connection);
        $insert = $connection->prepare('INSERT INTO document_requests (resident_id, document_type, purpose, reference_code) VALUES (:resident_id, :document_type, :purpose, :reference)');
        $insert->execute(['resident_id' => $resident_id, 'document_type' => $document_type, 'purpose' => $purpose, 'reference' => $reference]);
        $id = (int) $connection->lastInsertId();
        documents_audit($connection, $id, 'document_requested', ['reference_code' => $reference, 'document_type' => $document_type, 'resident_id' => $resident_id, 'source' => $source]);
        $connection->commit();
        return ['id' => $id, 'reference' => $reference];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

// ── Protected document renderer (shared by preview, print and — after approval — PDF) ─────────────

// Placeholders the renderer understands. Anything else in a template is shown as a visible [unknown] marker.
function documents_placeholders(): array
{
    return [
        'resident_full_name' => 'Resident full name',
        'resident_address' => 'Resident address',
        'resident_purok' => 'Resident Purok',
        'document_type' => 'Document type',
        'document_title' => 'Document title',
        'document_purpose' => 'Stated purpose',
        'document_date' => 'Document date',
        'request_reference' => 'Request reference number',
        'authorized_signatory_name' => 'Authorized signatory name',
        'authorized_signatory_position' => 'Authorized signatory position',
        'resident_name_caps' => 'Resident name (capitals, middle initial)',
        'resident_civil_status_text' => 'Resident civil status',
        'resident_civil_status_choice' => 'Resident civil status (or single/married/widow)',
        'resident_purok_label' => 'Resident Purok (e.g. Purok 1)',
        'resident_years_of_residency' => 'Years of residency',
        'issued_day' => 'Day issued',
        'issued_day_suffix' => 'Day suffix (st, nd, rd, th)',
        'issued_month' => 'Month issued',
        'issued_year' => 'Year issued',
    ];
}

// Values come only from verified database records. Signatories are not stored yet, so their placeholders stay visibly
// unfilled instead of inventing names or positions.
function documents_placeholder_values(?array $request): array
{
    if ($request === null) return [];
    return [
        'resident_full_name' => residents_full_name($request),
        'resident_address' => residents_collapse((string) $request['address']),
        'resident_purok' => (string) $request['purok'],
        'document_type' => (string) $request['document_type'],
        'document_title' => mb_strtoupper((string) $request['document_type']),
        'document_purpose' => (string) $request['purpose'],
        'document_date' => date('F j, Y'),
        'request_reference' => (string) $request['reference_code'],
    ] + documents_official_format_values($request);
}

// Values used by the barangay's official document formats (Barangay Clearance, Certificate of Indigency), all taken from the
// resident's record and the issue date. Unknown civil status leaves a blank line to be filled in by hand (Clearance) or the
// printed "single/married/widow" choice (Indigency), as on the paper forms.
function documents_official_format_values(array $request): array
{
    $middle = residents_collapse((string) ($request['middle_name'] ?? ''));
    $name = residents_collapse(implode(' ', [(string) $request['first_name'], $middle !== '' ? mb_substr($middle, 0, 1) . '.' : '', (string) $request['last_name'], (string) ($request['suffix'] ?? '')]));
    $civil = ['single' => 'single', 'married' => 'married', 'widowed' => 'widow', 'separated' => 'separated'][(string) ($request['civil_status'] ?? '')] ?? '';
    $day = (int) date('j');
    $suffix = in_array($day % 100, [11, 12, 13], true) ? 'th' : (['st', 'nd', 'rd'][$day % 10 - 1] ?? 'th');
    return [
        'resident_name_caps' => mb_strtoupper($name),
        'resident_civil_status_text' => $civil !== '' ? $civil : str_repeat("\u{00A0}", 16),
        'resident_civil_status_choice' => $civil !== '' ? $civil : 'single/married/widow',
        'resident_purok_label' => residents_purok_label((string) $request['purok']),
        // Blank line (to fill in by hand) when the years of residency are not recorded.
        'resident_years_of_residency' => ($years = residents_years_of_residency($request['residency_start_year'] ?? null)) !== null ? (string) $years : str_repeat("\u{00A0}", 6),
        'issued_day' => (string) $day,
        'issued_day_suffix' => $suffix,
        'issued_month' => date('F'),
        'issued_year' => date('Y'),
    ];
}

// Keeps only a small allowlist of formatting tags with a class attribute; removes scripts, styles, event handlers,
// links, forms and every other attribute, so template content can never execute code.
function documents_sanitize_template(string $html): string
{
    $allowed = ['div', 'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'sup', 'span', 'h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tr', 'td', 'th', 'header', 'footer', 'section', 'hr'];
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8"><div id="sjq-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $root = $document->getElementById('sjq-root');
    if ($root === null) return '';
    $walk = static function (DOMNode $node) use (&$walk, $allowed): void {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (!in_array($tag, $allowed, true)) {
                    // Dangerous containers are dropped with their content; other unknown tags keep only their text.
                    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'link', 'meta', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math', 'template'], true)) {
                        $node->removeChild($child);
                        continue;
                    }
                    $walk($child);
                    while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                    $node->removeChild($child);
                    continue;
                }
                foreach (iterator_to_array($child->attributes) as $attribute) {
                    $keep = $attribute->name === 'class' && preg_match('/^[a-z0-9 _-]{1,120}$/i', $attribute->value);
                    if (!$keep) $child->removeAttribute($attribute->name);
                }
                $walk($child);
            } elseif ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);
            }
        }
    };
    $walk($root);
    $out = '';
    foreach ($root->childNodes as $child) $out .= $document->saveHTML($child);
    return $out;
}

// Sanitizes the template, then replaces {{placeholder}} tokens with HTML-escaped values. Missing values render as a
// visible bracketed label (for example "[Authorized signatory name]") so nothing is silently fabricated.
function documents_render_template(string $template_html, array $values): string
{
    $safe = documents_sanitize_template($template_html);
    $labels = documents_placeholders();
    return (string) preg_replace_callback('/\{\{\s*([a-z_]{1,60})\s*\}\}/', static function (array $match) use ($values, $labels): string {
        $key = $match[1];
        if (isset($values[$key]) && $values[$key] !== '') return e($values[$key]);
        return '<span class="doc-missing">[' . e($labels[$key] ?? 'Unknown placeholder: ' . $key) . ']</span>';
    }, $safe);
}

// Development-only sample layout shipped as a file (never stored in the database, never approved). It is neutral for every
// document type: the title comes from {{document_title}} and the body contains no certification wording. Any approved
// template for the type (documents_type_template) always takes priority over it.
function documents_sample_template(): string
{
    $path = __DIR__ . '/../templates/documents/sample_barangay_clearance.html';
    return is_file($path) ? (string) file_get_contents($path) : '';
}

// Featured (Frequently Used) templates: approved, non-sample templates of active types, max four, in slot order.
// Returns [] until the template tables exist; nothing is created or approved automatically.
function documents_featured_templates(PDO $connection): array
{
    if (!documents_templates_installed($connection)) return [];
    return $connection->query("SELECT t.id, t.title, ty.name AS document_type, ty.description FROM document_featured_templates f INNER JOIN document_templates t ON t.id = f.document_template_id INNER JOIN document_types ty ON ty.id = t.document_type_id WHERE t.status = 'approved' AND t.is_development_sample = 0 AND ty.is_active = 1 ORDER BY f.position LIMIT 4")->fetchAll();
}

// One approved, active, non-sample template by ID (used to validate template shortcuts server-side), or null.
function documents_approved_template(PDO $connection, int $template_id): ?array
{
    if (!documents_templates_installed($connection)) return null;
    $statement = $connection->prepare("SELECT t.id, t.title, ty.name AS document_type FROM document_templates t INNER JOIN document_types ty ON ty.id = t.document_type_id WHERE t.id = :id AND t.status = 'approved' AND t.is_development_sample = 0 AND ty.is_active = 1 LIMIT 1");
    $statement->execute(['id' => $template_id]);
    return $statement->fetch() ?: null;
}

// The latest approved, non-sample template for an active document type (by name), or null. Never falls back to the sample.
function documents_type_template(PDO $connection, string $document_type): ?array
{
    if (!documents_templates_installed($connection)) return null;
    $statement = $connection->prepare("SELECT t.id, t.title, t.version_no, t.body FROM document_templates t INNER JOIN document_types ty ON ty.id = t.document_type_id WHERE ty.name = :type AND ty.is_active = 1 AND t.status = 'approved' AND t.is_development_sample = 0 ORDER BY t.version_no DESC LIMIT 1");
    $statement->execute(['type' => $document_type]);
    return $statement->fetch() ?: null;
}

// The barangay's official paper formats, shipped as template files (provided by the barangay): used for preview and printing
// when no database-approved template exists for the type. 'layout' => 'official-format' tells the page to render the sheet
// full-bleed (the format draws its own page border, seals and watermark).
function documents_official_format_files(): array
{
    return [
        'Barangay Clearance' => ['file' => 'barangay_clearance.html', 'title' => 'Barangay Clearance — Official Format'],
        'Certificate of Indigency' => ['file' => 'certificate_of_indigency.html', 'title' => 'Certificate of Indigency — Official Format'],
        'Business Clearance' => ['file' => 'business_clearance.html', 'title' => 'Business Clearance — Official Format'],
        'Certificate of Good Moral Character' => ['file' => 'good_moral_certification.html', 'title' => 'Good Moral Certification — Official Format'],
        'Certificate of Residency' => ['file' => 'certificate_of_residency.html', 'title' => 'Certificate of Residency — Official Format'],
    ];
}

function documents_official_format_template(string $document_type): ?array
{
    $format = documents_official_format_files()[$document_type] ?? null;
    $path = $format ? __DIR__ . '/../templates/documents/' . $format['file'] : null;
    if ($path === null || !is_file($path)) return null;
    return ['id' => null, 'title' => $format['title'], 'version_no' => 1, 'body' => (string) file_get_contents($path), 'layout' => 'official-format'];
}

// Template used for preview and printing: a database-approved template first, then the barangay's official format file.
function documents_print_template(PDO $connection, string $document_type): ?array
{
    return documents_type_template($connection, $document_type) ?? documents_official_format_template($document_type);
}

// ── Status-based actions (shared by the list and the details page; document_action.php enforces the same rules) ──

// Actions staff may start for a status. Processing / Ready for Release only exist after the database update.
function documents_status_actions(string $status): array
{
    return match ($status) {
        'pending', 'processing' => ['approve', 'reject'],
        'approved' => ['release'], // an approved request only proceeds to release (no Cancel Request action)
        'ready_for_release' => ['release'],
        default => [],
    };
}

// Release checklist for an approved request. Items the current database cannot record are reported as unmet, so the
// Release workflow shows exactly what is missing instead of releasing without verification.
function documents_release_requirements(PDO $connection, array $request): array
{
    $installed = documents_templates_installed($connection);
    $needs_update = 'Needs the Documents database update';
    return [
        ['Request approved', in_array($request['status'], ['approved', 'ready_for_release'], true), null],
        ['Approved document template available', false, $installed ? 'No approved template for this type' : 'Template library not installed'],
        ['Document prepared', false, $needs_update],
        ['Physical signing confirmed', false, $needs_update],
        ['Payment verified or exemption recorded', false, 'No fee or payment records yet — needs the Documents database update and the official fee schedule'],
        ['Claimant identity verified', false, 'No claimant verification records yet — needs the Documents database update'],
        ['Representative authorization (when not the resident)', false, $needs_update],
    ];
}

// Blocked-action explanations, also used by document_action.php so the page and the endpoint say the same thing.
function documents_blocked_message(string $action): string
{
    return match ($action) {
        'release' => 'Release is not available yet: claimant verification, physical-signing confirmation and payment verification cannot be recorded until the Documents database update is approved and imported. The request stays Approved.',
        'cancel' => 'Approved requests cannot be cancelled; they proceed to release. The request stays Approved.',
        default => 'This action is not available.',
    };
}

// ── Development test release (retired) ─────────────────────────────────────────
// The session-only test release is switched off: the official Release (document_action.php) now saves the release, so
// a request is never shown as "Released (Test)" while its saved status is still Approved.
function documents_test_release_enabled(): bool
{
    return false;
}

// The current user's test release for a request, or null. Test releases live only in this user's server-side session:
// document_requests cannot tell a test release from an official one, so nothing is written to the database.
function documents_test_release(array $request): ?array
{
    if (!documents_test_release_enabled() || $request['status'] !== 'approved') return null;
    return $_SESSION['documents_test_releases'][(int) $request['id']] ?? null;
}

function documents_test_release_badge(): string
{
    return '<span class="resident-status document-test-badge">Released (Test)</span>';
}

// Status-dependent action buttons. Every button opens the shared confirmation dialog first; nothing is submitted on click.
// $return is a validated list query string ('' for the details page) so the list can be restored after the action.
// Buttons use the shared .doc-action-btn style; the caller's .doc-action-group container sets their size.
function documents_action_buttons(PDO $connection, array $request, string $return = ''): string
{
    if (!documents_can_process()) return '';
    // A test-released request shows the Released state: no processing actions (the details page offers Reset Test Release).
    if (documents_test_release($request) !== null) return '';
    $name = residents_full_name($request);
    $details = json_encode([['Reference', $request['reference_code']], ['Resident', $name], ['Document type', $request['document_type']], ['Current status', documents_status_labels()[$request['status']] ?? ucfirst($request['status'])]], JSON_UNESCAPED_UNICODE);
    $hidden = csrf_field() . '<input type="hidden" name="id" value="' . e((string) $request['id']) . '">' . ($return !== '' ? '<input type="hidden" name="return" value="' . e($return) . '">' : '');
    $html = '';
    foreach (documents_status_actions($request['status']) as $action) {
        $html .= match ($action) {
            'approve' => '<form method="post" action="document_action.php" class="doc-action-form">' . $hidden . '<input type="hidden" name="action" value="approve"><button class="btn doc-action-btn is-primary" type="submit" data-form-confirm="custom" data-dialog-heading="Confirm Document Approval" data-dialog-message="Are you sure you want to approve this document request?" data-dialog-details="' . e($details) . '" data-dialog-confirm="Confirm Approval" data-dialog-dismiss="Cancel">Approve</button></form>',
            'reject' => '<form method="post" action="document_action.php" class="doc-action-form">' . $hidden . '<input type="hidden" name="action" value="reject"><input type="hidden" name="reason" value=""><button class="btn doc-action-btn is-danger" type="submit" data-form-confirm="custom" data-dialog-heading="Reject Document Request" data-dialog-message="The reason is recorded and shown to the resident." data-dialog-details="' . e($details) . '" data-dialog-reason="Reason for Rejection" data-dialog-confirm="Confirm Rejection" data-dialog-dismiss="Cancel" data-dialog-danger="true">Reject</button></form>',
            // Official release: records the release date and time, the releasing staff member and the person who received it.
            'release' => '<form method="post" action="document_action.php" class="doc-action-form">' . $hidden . '<input type="hidden" name="action" value="release"><input type="hidden" name="reason" value=""><button class="btn doc-action-btn is-primary" type="submit" data-form-confirm="custom" data-dialog-heading="Release Document" data-dialog-message="Hand the signed document to the resident or their representative, then record who received it. The request becomes Released and the resident may request this document again." data-dialog-details="' . e($details) . '" data-dialog-reason="Received by (full name of the person who claimed the document)" data-dialog-confirm="Confirm Release" data-dialog-dismiss="Cancel">Release</button></form>',
            default => '',
        };
    }
    return $html;
}

// True once the approved template tables exist (the review-only migration has been imported).
function documents_templates_installed(PDO $connection): bool
{
    static $installed = null;
    if ($installed === null) {
        $check = $connection->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('document_types', 'document_templates', 'document_featured_templates')");
        $installed = (int) $check->fetchColumn() === 3;
    }
    return $installed;
}
