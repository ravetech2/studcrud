<?php
/** Shared helpers for sessions, access control, page layout, and safe output. */
if (session_status() === PHP_SESSION_NONE)
    session_start();
require_once __DIR__ . '/../config/db.php';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
function logged_in(): bool
{
    return !empty($_SESSION['role']);
}
function require_role(string $role): void
{
    if (($_SESSION['role'] ?? '') !== $role) {
        header('Location: ../auth/login.php');
        exit;
    }
}
function redirect_home(): void
{
    if (($_SESSION['role'] ?? '') === 'admin') {
        header('Location: ../admin/dashboard.php');
        exit;
    }
    if (($_SESSION['role'] ?? '') === 'student') {
        header('Location: ../student/dashboard.php');
        exit;
    }
}
function page_header(string $title): void
{
    $role = $_SESSION['role'] ?? '';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . e($title) . ' | Senior High Portal</title><link rel="stylesheet" href="../assets/css/style.css"></head><body>';
    echo '<header class="topbar"><a class="brand" href="../index.php">SHS <span>Portal</span></a><nav>';
    if ($role === 'admin')
        echo '<a href="../admin/dashboard.php">Dashboard</a><a href="../admin/students.php">Students</a><a href="../admin/subjects.php">Subjects</a><a href="../admin/grades.php">Grades</a><a href="../admin/year-levels.php">Year Levels</a><a href="../admin/strands.php">Strands</a><a href="../admin/manage-admin.php">Admins</a>';
    elseif ($role === 'student')
        echo '<a href="../student/dashboard.php">Dashboard</a><a href="../student/profile.php">Profile</a><a href="../student/subjects.php">Subjects</a><a href="../student/grades.php">Grades</a>';
    if ($role)
        echo '<a class="nav-logout" href="../auth/logout.php">Logout</a>';
    else
        echo '<a href="../auth/login.php">Login</a><a href="../auth/register.php">Student Registration</a>';
    echo '</nav></header><main class="container">';
}
function page_footer(): void
{
    echo '</main><footer>Senior High School Management System</footer><script src="../assets/js/app.js"></script></body></html>';
}
function notice(): void
{
    if (!empty($_SESSION['flash'])) {
        echo '<div class="notice">' . e($_SESSION['flash']) . '</div>';
        unset($_SESSION['flash']);
    }
}
function field(string $label, string $name, string $value = '', string $type = 'text', bool $required = false): void
{
    echo '<label>' . e($label) . '<input type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '" ' . ($required ? 'required' : '') . '></label>';
}
