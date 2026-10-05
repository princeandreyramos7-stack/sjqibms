<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = $page_title ?? APP_NAME;
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Barangay San Jose Management System">
    <title><?= e($page_title) ?> | <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php // The file's modification time is added so browsers load updated styles instead of a cached copy. ?>
    <link href="assets/css/app.css?v=<?= e((string) @filemtime(__DIR__ . '/../assets/css/app.css')) ?>" rel="stylesheet">
<?php foreach ($page_styles ?? [] as $page_style): ?>
    <link href="<?= e($page_style) ?>?v=<?= e((string) @filemtime(__DIR__ . '/../' . $page_style)) ?>" rel="stylesheet">
<?php endforeach; ?>
</head>
<body>
