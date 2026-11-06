<?php
/**
 * Solvex Report — Shared Header Partial
 * 
 * Provides responsive app shell, navigation, meta tags,
 * and user session state.
 */

if (!defined('SOLVEX_ROOT')) {
    define('SOLVEX_ROOT', dirname(__DIR__));
}

require_once SOLVEX_ROOT . '/includes/config.php';
require_once SOLVEX_ROOT . '/includes/db.php';
require_once SOLVEX_ROOT . '/includes/helpers.php';
require_once SOLVEX_ROOT . '/includes/ui.php';
require_once SOLVEX_ROOT . '/includes/auth.php';
require_once SOLVEX_ROOT . '/includes/csrf.php';

init_session();
$currentUser = current_user();
$pageTitle   = $pageTitle ?? APP_NAME;
$flashes     = get_flashes();

// Header initialization
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Solvex Report — Hostel Complaint and Maintenance Management System.">
    <meta name="theme-color" content="#0E9F8E">
    <title><?= e($pageTitle) ?> — Solvex Report</title>

    <!-- Favicon & Icons -->
    <link rel="icon" type="image/svg+xml" href="<?= asset_url('img/favicon.svg') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset_url('img/favicon-32.png') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= asset_url('img/apple-touch-icon.png') ?>">

    <!-- Open Graph / Meta -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($pageTitle) ?> — Solvex Report">
    <meta property="og:description" content="Hostel problem reporting, warden triage, and maintenance tracking.">
    <meta property="og:image" content="<?= asset_url('img/og-image.png') ?>">
    <meta property="og:url" content="<?= e(base_url()) ?>">

    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Solvex Custom Design System -->
    <link rel="stylesheet" href="<?= asset_url('css/solvex.css') ?>">
    <script>
        // Apply theme immediately to prevent flashing
        (function() {
            var savedTheme = localStorage.getItem('solvex_theme');
            if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
        window.SOLVEX_BASE_URL = '<?= e(base_url()) ?>';
        window.SOLVEX_CSRF = '<?= e(csrf_token()) ?>';
        function toggleSolvexTheme() {
            var current = document.documentElement.getAttribute('data-theme');
            var next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('solvex_theme', next);
        }
    </script>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
    <!-- Accessibility Skip Link -->
    <a href="#main-content" class="visually-hidden-focusable p-2 bg-dark text-white position-absolute top-0 start-0 z-3">
        Skip to main content
    </a>

    <!-- Top App Navigation -->
    <header class="navbar-solvex">
        <div class="container d-flex align-items-center justify-content-between">
            <a class="navbar-brand d-flex align-items-center" href="<?= base_url() ?>" aria-label="Solvex Report Home">
                <img src="<?= asset_url('img/logo-with-text.png') ?>" alt="Solvex Report" height="38" class="d-none d-sm-inline">
                <img src="<?= asset_url('img/logo-icon.png') ?>" alt="Solvex Report" height="36" class="d-inline d-sm-none">
            </a>

            <!-- Nav Items -->
            <nav class="d-none d-md-flex align-items-center gap-3" aria-label="Primary Navigation">
                <?php if (!$currentUser): ?>
                    <a href="<?= base_url() ?>" class="nav-link <?= ($activeNav ?? '') === 'home' ? 'active' : '' ?>">Home</a>
                    <a href="<?= base_url('login.php') ?>" class="nav-link">Sign In</a>
                    <a href="<?= base_url('register.php') ?>" class="nav-link">Register</a>
                <?php else: ?>
                    <?php if ($currentUser['role'] === 'student'): ?>
                        <a href="<?= base_url('student/dashboard.php') ?>" class="nav-link <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>">
                            <i class="bi bi-grid-fill me-1"></i> Dashboard
                        </a>
                        <a href="<?= base_url('student/my_complaints.php') ?>" class="nav-link <?= ($activeNav ?? '') === 'complaints' ? 'active' : '' ?>">
                            <i class="bi bi-list-task me-1"></i> My Complaints
                        </a>
                        <a href="<?= base_url('student/report.php') ?>" class="btn-solvex btn-primary btn-sm py-1 px-3">
                            <i class="bi bi-plus-lg me-1"></i> Report Issue
                        </a>
                    <?php elseif ($currentUser['role'] === 'staff'): ?>
                        <a href="<?= base_url('staff/queue.php') ?>" class="nav-link <?= ($activeNav ?? '') === 'queue' ? 'active' : '' ?>">
                            <i class="bi bi-inbox-fill me-1"></i> Assigned Work Queue
                        </a>
                        <a href="<?= base_url('student/notifications.php') ?>" class="nav-link">
                            <i class="bi bi-bell me-1"></i> Notifications
                        </a>
                    <?php elseif ($currentUser['role'] === 'admin'): ?>
                        <a href="<?= base_url('admin/dashboard.php') ?>" class="nav-link <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>">
                            <i class="bi bi-speedometer2 me-1"></i> Warden Overview
                        </a>
                        <a href="<?= base_url('admin/complaints.php') ?>" class="nav-link <?= ($activeNav ?? '') === 'complaints' ? 'active' : '' ?>">
                            <i class="bi bi-list-task me-1"></i> All Complaints
                        </a>
                        <a href="<?= base_url('admin/users.php') ?>" class="nav-link <?= ($activeNav ?? '') === 'users' ? 'active' : '' ?>">
                            <i class="bi bi-people me-1"></i> Users & Staff
                        </a>
                        <a href="<?= base_url('admin/master.php') ?>" class="nav-link <?= ($activeNav ?? '') === 'master' ? 'active' : '' ?>">
                            <i class="bi bi-building-gear me-1"></i> Hostels & Categories
                        </a>
                        <a href="<?= base_url('admin/reports.php') ?>" class="nav-link <?= ($activeNav ?? '') === 'reports' ? 'active' : '' ?>">
                            <i class="bi bi-bar-chart me-1"></i> Reports
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </nav>

            <!-- Actions & User Menu -->
            <div class="d-flex align-items-center gap-2">
                <!-- Dark Mode Toggle Button -->
                <button type="button" class="theme-toggle-btn" onclick="toggleSolvexTheme()" title="Toggle Light/Dark Theme" aria-label="Toggle Theme">
                    <span class="d-inline dark-hide"><?= icon('moon', 16) ?></span>
                    <span class="d-none dark-show"><?= icon('sun', 16) ?></span>
                </button>

                <?php if (!$currentUser): ?>
                    <a href="<?= base_url('login.php') ?>" class="btn-solvex btn-ghost btn-sm">Log In</a>
                    <a href="<?= base_url('register.php') ?>" class="btn-solvex btn-primary btn-sm">
                        <i class="bi bi-person-plus-fill"></i> Register
                    </a>
                <?php else: ?>
                    <!-- Notification Bell with Dropdown -->
                    <div class="dropdown" id="notificationDropdownContainer">
                        <button class="notification-bell dropdown-toggle no-caret" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="notifBellButton" aria-label="Notifications" title="Notifications">
                            <i class="bi bi-bell"></i>
                            <span class="badge-count d-none" id="navNotificationBadge">0</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end shadow-lg p-0" style="width: 320px; max-width: 90vw;" aria-labelledby="notifBellButton">
                            <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-light">
                                <span class="fw-bold small text-navy">Notifications</span>
                                <a href="<?= base_url('student/notifications.php') ?>" class="small text-primary text-decoration-none">View All</a>
                            </div>
                            <div id="notifDropdownList" class="p-2" style="max-height: 280px; overflow-y: auto;">
                                <div class="text-center py-3 text-muted small">Loading...</div>
                            </div>
                            <div class="p-2 border-top bg-light text-center">
                                <a href="<?= base_url('student/notifications.php') ?>" class="btn-solvex btn-ghost btn-sm w-100 py-1" style="font-size: 0.75rem;">
                                    See Notification Center <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- User Menu Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-ghost d-flex align-items-center gap-2 py-1 px-2 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="userMenuButton">
                            <span class="avatar-initials sm" style="background-color: <?= avatar_color($currentUser['full_name']) ?>;">
                                <?= e(initials($currentUser['full_name'])) ?>
                            </span>
                            <span class="d-none d-sm-inline fw-semibold text-truncate" style="max-width: 130px;">
                                <?= e($currentUser['full_name']) ?>
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="userMenuButton">
                            <li class="px-3 py-2 border-bottom">
                                <div class="fw-bold text-dark text-truncate"><?= e($currentUser['full_name']) ?></div>
                                <div class="small text-muted text-truncate"><?= e($currentUser['email']) ?></div>
                                <span class="badge bg-light text-dark border text-uppercase mt-1"><?= e($currentUser['role']) ?></span>
                            </li>
                            <li>
                                <a class="dropdown-item py-2" href="<?= role_home($currentUser['role']) ?>">
                                    <i class="bi bi-speedometer2 me-2 text-primary"></i> Home Dashboard
                                </a>
                            </li>
                            <?php if ($currentUser['role'] === 'student'): ?>
                                <li>
                                    <a class="dropdown-item py-2" href="<?= base_url('student/my_complaints.php') ?>">
                                        <i class="bi bi-list-task me-2 text-primary"></i> My Complaints
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2" href="<?= base_url('student/profile.php') ?>">
                                        <i class="bi bi-person-gear me-2 text-primary"></i> Profile &amp; Settings
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item py-2 text-danger" href="<?= base_url('logout.php') ?>">
                                    <i class="bi bi-box-arrow-right me-2"></i> Log Out
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Flash Messages -->
    <?php if (!empty($flashes)): ?>
        <div class="container mt-3">
            <?php foreach ($flashes as $flashItem): ?>
                <div class="alert-solvex <?= e($flashItem['type']) ?> alert-dismissible fade show" role="alert">
                    <i class="bi <?= match($flashItem['type']) {
                        'success' => 'bi-check-circle-fill',
                        'warning' => 'bi-exclamation-triangle-fill',
                        'danger', 'error' => 'bi-x-circle-fill',
                        default   => 'bi-info-circle-fill',
                    } ?>"></i>
                    <div class="flex-grow-1"><?= e($flashItem['message']) ?></div>
                    <button type="button" class="btn-close ms-2" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Main Content Target -->
    <main id="main-content">
