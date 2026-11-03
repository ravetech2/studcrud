<?php
/** Shared helpers for sessions, access control, page layout, and safe output. */
if (session_status() === PHP_SESSION_NONE)
    session_start();
require_once __DIR__ . '/../config/db.php';

/**
 * Build a site-relative URL that works when the app is served from a subdirectory.
 */
function base_url(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $dir = rtrim(dirname($script), '/');
    if ($dir !== '' && in_array(basename($dir), ['admin', 'auth', 'student'], true)) {
        $dir = rtrim(dirname($dir), '/');
    }
    return $dir === '' ? '' : $dir;
}

function url_for(string $path): string
{
    $base = base_url();
    return $base . '/' . ltrim($path, '/');
}

/**
 * Safely convert a value to a string for HTML output.
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Check whether a user is currently logged in.
 */
function logged_in(): bool
{
    return !empty($_SESSION['role']);
}

/**
 * Redirect the user to the login page if their role does not match the required role.
 */
function require_role(string $role): void
{
    if (($_SESSION['role'] ?? '') !== $role) {
        header('Location: ' . url_for('auth/login.php'));
        exit;
    }
}

/**
 * Send logged-in users to the correct dashboard based on their account role.
 */
function redirect_home(): void
{
    if (($_SESSION['role'] ?? '') === 'admin') {
        header('Location: ' . url_for('admin/dashboard.php'));
        exit;
    }
    if (($_SESSION['role'] ?? '') === 'student') {
        header('Location: ' . url_for('student/dashboard.php'));
        exit;
    }
}

/**
 * Output the shared page header with the proper navigation for the current user role.
 */
function page_header(string $title): void
{
    $role = $_SESSION['role'] ?? '';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . e($title) . ' | Senior High Portal</title><link rel="stylesheet" href="' . url_for('assets/css/style.css') . '"></head><body>';
    echo '<header class="topbar"><a class="brand" href="' . url_for('index.php') . '">SHS <span>Portal</span></a><nav>';
    if ($role === 'admin')
        echo '<a href="' . url_for('admin/dashboard.php') . '">Dashboard</a><a href="' . url_for('admin/students.php') . '">Students</a><a href="' . url_for('admin/subjects.php') . '">Subjects</a><a href="' . url_for('admin/grades.php') . '">Grades</a><a href="' . url_for('admin/year-levels.php') . '">Year Levels</a><a href="' . url_for('admin/strands.php') . '">Strands</a><a href="' . url_for('admin/manage-admin.php') . '">Admins</a>';
    elseif ($role === 'student')
        echo '<a href="' . url_for('student/dashboard.php') . '">Dashboard</a><a href="' . url_for('student/profile.php') . '">Profile</a><a href="' . url_for('student/subjects.php') . '">Subjects</a><a href="' . url_for('student/grades.php') . '">Grades</a>';
    if ($role)
        echo '<a class="nav-logout" href="' . url_for('auth/logout.php') . '">Logout</a>';
    echo '</nav></header><main class="container">';
}

/**
 * Output the closing page markup and shared footer scripts.
 */
function page_footer(): void
{
    echo '</main><footer>Senior High School Management System</footer><script src="' . url_for('assets/js/app.js') . '"></script></body></html>';
}

/**
 * Display a flash notice message if one has been set in the session.
 */
function notice(): void
{
    if (!empty($_SESSION['flash'])) {
        echo '<div class="notice">' . e($_SESSION['flash']) . '</div>';
        unset($_SESSION['flash']);
    }
}

/**
 * Render a form field with proper escaping for the label, name, value, and type.
 */
function field(string $label, string $name, string $value = '', string $type = 'text', bool $required = false): void
{
    echo '<label>' . e($label) . '<input type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '" ' . ($required ? 'required' : '') . '></label>';
}
