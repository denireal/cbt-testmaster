<?php
/**
 * app/views/partials/header.php
 * Shared layout head. Bootstrap 5 + jQuery are linked LOCALLY when the files
 * exist in public/assets/, otherwise it falls back to CDN so XAMPP works
 * out-of-the-box. Drop bootstrap.min.css / bootstrap.bundle.min.js /
 * jquery.min.js into public/assets/ to go fully offline.
 */
$localBootstrap = file_exists(ASSETS_PATH . 'css/bootstrap.min.css');
$localJquery    = file_exists(ASSETS_PATH . 'js/jquery.min.js');
$localBundle    = file_exists(ASSETS_PATH . 'js/bootstrap.bundle.min.js');
$user           = current_user();
$flash          = function_exists('pull_flash') ? pull_flash() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'CBT Examination System') ?></title>

    <?php if ($localBootstrap): ?>
        <link rel="stylesheet" href="<?= url('assets/css/bootstrap.min.css') ?>">
    <?php else: ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <?php endif; ?>

    <link rel="stylesheet" href="<?= url('assets/css/cbt.css') ?>">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="<?= url('index.php') ?>">
            🎓 CBT System
            <span class="badge bg-primary ms-1 align-middle">TESTMASTER</span>
        </a>

        <?php if ($user): ?>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="text-white-50 small d-none d-md-inline">
                    <?= e($user['full_name']) ?>
                    <span class="badge bg-<?= $user['role'] === 'admin' ? 'primary' : 'success' ?> ms-1">
                        <?= e(ucfirst($user['role'])) ?>
                    </span>
                </span>

                <?php if ($user['role'] === 'admin'): ?>
                    <a class="btn btn-sm btn-outline-light" href="<?= url('index.php?page=admin_dashboard') ?>">Dashboard</a>
                    <a class="btn btn-sm btn-outline-light" href="<?= url('index.php?page=admin_groups') ?>">Groups</a>
                    <a class="btn btn-sm btn-outline-light" href="<?= url('index.php?page=admin_examinees') ?>">Examinees</a>
                    <a class="btn btn-sm btn-outline-light" href="<?= url('index.php?page=admin_exams') ?>">Exams</a>
                    <a class="btn btn-sm btn-outline-light" href="<?= url('index.php?page=admin_results') ?>">Results</a>
                    <!-- ✅ NEW: Item Analysis Link -->
                    <a class="btn btn-sm btn-outline-warning" href="<?= url('index.php?page=admin_analytics') ?>">
                        <i class="bi bi-graph-up me-1"></i>Analytics
                    </a>
                <?php else: ?>
                    <a class="btn btn-sm btn-outline-light" href="<?= url('index.php?page=student_dashboard') ?>">My Exams</a>
                <?php endif; ?>

                <a class="btn btn-sm btn-outline-danger" href="<?= url('index.php?page=logout') ?>">Logout</a>
            </div>
        <?php endif; ?>
    </div>
</nav>

<div class="container-fluid px-4 py-4">
    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type'] === 'warning' ? 'warning' : ($flash['type'] === 'danger' ? 'danger' : 'success')) ?> alert-dismissible fade show shadow-sm">
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>