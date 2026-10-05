<?php
declare(strict_types=1);

require_once __DIR__ . '/complaints.php';

// Confidential evidence storage: storage/evidence/YYYY/MM/<random 32 hex> (no extension, never the uploaded name).
// Apache denies all direct HTTP access (storage/.htaccess and storage/evidence/.htaccess); files are only delivered by
// case_evidence.php after authentication, authorization and access logging.

function evidence_root(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'evidence';
}

// Allowed evidence types (default policy until the barangay approves one): extension => verified MIME type.
function evidence_allowed_types(): array
{
    return ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
}

// Uploads are enabled only while both deny rules are in place.
function evidence_storage_ready(): bool
{
    $root = evidence_root();
    foreach ([dirname($root) . DIRECTORY_SEPARATOR . '.htaccess', $root . DIRECTORY_SEPARATOR . '.htaccess'] as $rule) {
        if (!is_file($rule) || !preg_match('/^\s*Require all denied\s*$/mi', (string) file_get_contents($rule))) return false;
    }
    return is_dir($root) && is_writable($root);
}

// Content validation (shared by uploads and tests): size limit, allowed extension, server-detected MIME type that
// matches the extension, genuine PDF/image structure, and no embedded script markers. The uploaded name is reduced to
// a display-only basename and never used as a storage path.
function evidence_inspect_file(string $path, string $uploaded_name): array
{
    $size = is_file($path) ? (int) filesize($path) : 0;
    if ($size <= 0) throw new RuntimeException('The file is empty.');
    if ($size > COMPLAINTS_EVIDENCE_MAX_BYTES) throw new RuntimeException('The file is larger than ' . complaints_format_bytes(COMPLAINTS_EVIDENCE_MAX_BYTES) . '.');
    $original = basename(str_replace('\\', '/', $uploaded_name));
    $original = preg_replace('/[\x00-\x1F\x7F]+/u', '', $original) ?? '';
    $original = mb_substr(trim($original), 0, 200);
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed = evidence_allowed_types();
    if ($original === '' || !isset($allowed[$extension])) throw new RuntimeException('Only PDF, JPG and PNG files are accepted.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if ($mime !== $allowed[$extension]) throw new RuntimeException('The file content does not match its type. Only genuine PDF, JPG and PNG files are accepted.');
    $head = (string) file_get_contents($path, false, null, 0, 1024);
    if ($mime === 'application/pdf' && !str_starts_with($head, '%PDF-')) throw new RuntimeException('The PDF file is not valid.');
    if (str_starts_with($mime, 'image/') && @getimagesize($path) === false) throw new RuntimeException('The image file is not valid.');
    if (preg_match('/<\?php|<\?=|<script/i', (string) file_get_contents($path))) throw new RuntimeException('The file contains executable content and was rejected.');
    return ['original' => $original, 'mime' => $mime, 'size' => $size];
}

// Validates and stores an uploaded file for a complaint or blotter entry; returns the attachment id.
function evidence_store(PDO $connection, string $kind, int $case_id, array $file, string $description): int
{
    if (!evidence_storage_ready()) throw new RuntimeException('Evidence uploads are disabled because the protected storage failed its security check.');
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) throw new RuntimeException('Choose a file to upload.');
    if (($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($file['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('The file is larger than the allowed size.');
    if (($file['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) throw new RuntimeException('The file could not be uploaded. Please try again.');
    ['original' => $original, 'mime' => $mime, 'size' => $size] = evidence_inspect_file((string) $file['tmp_name'], (string) $file['name']);

    $table = $kind === 'blotter' ? 'blotter_entries' : 'complaint_cases';
    $ref_column = $kind === 'blotter' ? 'blotter_number' : 'case_number';
    $case = $connection->prepare("SELECT $ref_column AS reference, status FROM $table WHERE id = :id");
    $case->execute(['id' => $case_id]);
    $row = $case->fetch();
    if (!$row) throw new RuntimeException('This record no longer exists.');
    if ($row['status'] === 'closed') throw new RuntimeException('Closed records are read-only.');

    $relative = date('Y') . '/' . date('m');
    $directory = evidence_root() . DIRECTORY_SEPARATOR . date('Y') . DIRECTORY_SEPARATOR . date('m');
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) throw new RuntimeException('The evidence folder could not be prepared.');
    $stored = bin2hex(random_bytes(16));
    $target = $directory . DIRECTORY_SEPARATOR . $stored;
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) throw new RuntimeException('The file could not be stored. Please try again.');
    try {
        $connection->beginTransaction();
        $insert = $connection->prepare('INSERT INTO case_attachments (complaint_id, blotter_id, original_filename, stored_filename, storage_path, mime_type, file_size, sha256_checksum, description, uploaded_by) VALUES (:complaint, :blotter, :original, :stored, :path, :mime, :size, :sha, :description, :user)');
        $insert->execute(['complaint' => $kind === 'complaint' ? $case_id : null, 'blotter' => $kind === 'blotter' ? $case_id : null, 'original' => $original, 'stored' => $stored, 'path' => $relative, 'mime' => $mime, 'size' => $size, 'sha' => hash_file('sha256', $target), 'description' => $description === '' ? null : $description, 'user' => current_user()['id']]);
        $attachment_id = (int) $connection->lastInsertId();
        complaints_audit($connection, $attachment_id, 'evidence_uploaded', ['reference' => $row['reference']]);
        $connection->commit();
        return $attachment_id;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        @unlink($target); // never leave an unrecorded file behind
        throw $exception;
    }
}

function evidence_find(PDO $connection, int $id): ?array
{
    $statement = $connection->prepare('SELECT a.*, COALESCE(c.case_number, b.blotter_number) AS reference FROM case_attachments a LEFT JOIN complaint_cases c ON c.id = a.complaint_id LEFT JOIN blotter_entries b ON b.id = a.blotter_id WHERE a.id = :id');
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

// Resolves the stored file safely inside the evidence root (rejects any path that escapes it).
function evidence_file_path(array $attachment): ?string
{
    if (!preg_match('#^\d{4}/\d{2}$#', (string) $attachment['storage_path']) || !preg_match('/^[a-f0-9]{32}$/', (string) $attachment['stored_filename'])) return null;
    $root = realpath(evidence_root());
    $path = realpath(evidence_root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $attachment['storage_path']) . DIRECTORY_SEPARATOR . $attachment['stored_filename']);
    if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) return null;
    return $path;
}

// Removal keeps the record (and the stored file) as history; the file simply stops being downloadable.
function evidence_remove(PDO $connection, int $id, string $reason): string
{
    if (mb_strlen($reason) < 5 || mb_strlen($reason) > 255) throw new RuntimeException('Enter a removal reason of 5 to 255 characters.');
    $attachment = evidence_find($connection, $id);
    if (!$attachment) throw new RuntimeException('This file no longer exists.');
    $connection->beginTransaction();
    try {
        $update = $connection->prepare('UPDATE case_attachments SET removed_at = NOW(), removed_by = :user, removal_reason = :reason WHERE id = :id AND removed_at IS NULL');
        $update->execute(['user' => current_user()['id'], 'reason' => $reason, 'id' => $id]);
        if ($update->rowCount() !== 1) throw new RuntimeException('This file was already removed.');
        complaints_audit($connection, $id, 'evidence_removed', ['reference' => $attachment['reference']]);
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
    return (string) $attachment['reference'];
}
