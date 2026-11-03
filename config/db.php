<?php
/**
 * Shared bootstrap for every page:
 * session, SQLite connection, auth guards, helpers and page layout.
 * Usage: require_once __DIR__ . '/../config/db.php';
 * Requires PHP 7.4+ with the pdo_sqlite extension.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

const APP_NAME = 'Senior High School Portal';
const DB_FILE = __DIR__ . '/senior_high_db.db';
const PASSING_GRADE = 75;
const SEMESTERS = ['1st Semester', '2nd Semester'];
const QUARTERS = ['1st Quarter', '2nd Quarter', '3rd Quarter', '4th Quarter'];
const STATUSES = ['Pending', 'Enrolled', 'Graduated', 'Dropped'];
const GENDERS = ['Male', 'Female', 'Other'];

/* Base URL of the project folder, so links work whether the site lives at
   http://localhost/senior-high-system/ or at the web root. */
(function () {
    $doc = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $root = realpath(__DIR__ . '/..');
    $base = '';
    if ($doc && $root) {
        $doc = rtrim(str_replace('\\', '/', $doc), '/');
        $root = str_replace('\\', '/', $root);
        if ($doc !== '' && strpos($root, $doc) === 0) {
            $base = substr($root, strlen($doc));
        }
    }
    define('BASE_URL', $base);
})();

/* ---------- Database ---------- */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (!is_file(DB_FILE)) {
        http_response_code(500);
        exit('Database file not found. Expected: config/senior_high_db.db');
    }
    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    migrate($pdo);
    return $pdo;
}

/* The students table shipped without a password column; students need one to sign in. */
function migrate(PDO $pdo): void
{
    foreach ($pdo->query('PRAGMA table_info(students)')->fetchAll() as $col) {
        if ($col['name'] === 'password') {
            return;
        }
    }
    $pdo->exec('ALTER TABLE students ADD COLUMN password TEXT');
}

/* ---------- Small helpers ---------- */

function h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function nullable($s): ?string
{
    $s = trim((string)$s);
    return $s === '' ? null : $s;
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function render_flashes(): void
{
    foreach (flashes() as $f) {
        echo '<div class="alert alert-' . h($f['type']) . '" role="status"><span>' . h($f['msg'])
            . '</span><button type="button" class="alert-close" aria-label="Dismiss">&times;</button></div>';
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $sent = (string)($_POST['csrf'] ?? '');
        if (!hash_equals(csrf_token(), $sent)) {
            http_response_code(419);
            exit('Your session expired. Go back, refresh the page and try again.');
        }
    }
}

function options_html(array $pairs, $selected = '', ?string $placeholder = null): string
{
    $out = '';
    if ($placeholder !== null) {
        $out .= '<option value="">' . h($placeholder) . '</option>';
    }
    foreach ($pairs as $value => $label) {
        $sel = ((string)$value === (string)$selected) ? ' selected' : '';
        $out .= '<option value="' . h($value) . '"' . $sel . '>' . h($label) . '</option>';
    }
    return $out;
}

function same_map(array $list): array
{
    return array_combine($list, $list);
}

function qs(array $override = []): string
{
    $q = array_merge($_GET, $override);
    foreach ($q as $k => $v) {
        if ($v === '' || $v === null) {
            unset($q[$k]);
        }
    }
    return '?' . http_build_query($q);
}

function render_pager(int $page, int $pages): void
{
    if ($pages <= 1) {
        return;
    }
    echo '<nav class="pager" aria-label="Pages">';
    if ($page > 1) {
        echo '<a href="' . h(qs(['page' => $page - 1])) . '">Previous</a>';
    }
    echo '<span>Page ' . $page . ' of ' . $pages . '</span>';
    if ($page < $pages) {
        echo '<a href="' . h(qs(['page' => $page + 1])) . '">Next</a>';
    }
    echo '</nav>';
}

function status_badge(string $s): string
{
    return '<span class="badge badge-' . h(strtolower($s)) . '">' . h($s) . '</span>';
}

/* ---------- Lookups ---------- */

function lookup_year_levels(): array
{
    $out = [];
    foreach (db()->query('SELECT year_level_id, year_level_name FROM year_levels ORDER BY year_level_id') as $r) {
        $out[$r['year_level_id']] = $r['year_level_name'];
    }
    return $out;
}

function lookup_strands(): array
{
    $out = [];
    foreach (db()->query('SELECT strand_id, strand_code, strand_name FROM strands ORDER BY strand_code') as $r) {
        $out[$r['strand_id']] = $r['strand_code'] . ' – ' . $r['strand_name'];
    }
    return $out;
}

function load_student(int $id): ?array
{
    $st = db()->prepare(
        'SELECT s.*, y.year_level_name, g.strand_code, g.strand_name
         FROM students s
         JOIN year_levels y ON y.year_level_id = s.year_level_id
         JOIN strands g ON g.strand_id = s.strand_id
         WHERE s.student_id = ?'
    );
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

function full_name(array $s): string
{
    $mid = trim((string)($s['middlename'] ?? ''));
    $mi = $mid !== '' ? ' ' . mb_substr($mid, 0, 1) . '.' : '';
    return trim($s['firstname'] . $mi . ' ' . $s['lastname']);
}

function next_student_number(PDO $pdo): string
{
    $prefix = date('Y') . '-';
    $st = $pdo->prepare('SELECT student_number FROM students WHERE student_number LIKE ? ORDER BY student_number DESC LIMIT 1');
    $st->execute([$prefix . '%']);
    $last = $st->fetchColumn();
    $n = $last ? ((int)substr((string)$last, strlen($prefix))) + 1 : 1;
    return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

// School years start around June: from June on we are in 'this year-next year', before that in 'last year-this year'.
function current_school_year(): string
{
    $y = (int)date('Y');
    $m = (int)date('n');
    return $m >= 6 ? $y . '-' . ($y + 1) : ($y - 1) . '-' . $y;
}

/* ---------- Grades ---------- */

function fmt_grade($g): string
{
    if ($g === null || $g === '') {
        return '—';
    }
    return rtrim(rtrim(number_format((float)$g, 2, '.', ''), '0'), '.');
}

function grade_class($g): string
{
    if ($g === null || $g === '') {
        return '';
    }
    return (float)$g >= PASSING_GRADE ? 'pass' : 'fail';
}

function remark_text($g): string
{
    if ($g === null || $g === '') {
        return '—';
    }
    return (float)$g >= PASSING_GRADE ? 'Passed' : 'Failed';
}

// Mean of the non-null numbers, or null when there is nothing to average.
function average(array $nums): ?float
{
    $nums = array_values(array_filter($nums, function ($n) {
        return $n !== null;
    }));
    return $nums ? array_sum($nums) / count($nums) : null;
}

/** Subjects a student is enrolled in for one term, with quarter grades and a final grade. */
function fetch_report(int $studentId, string $sy, string $sem): array
{
    $st = db()->prepare(
        'SELECT ss.enrollment_id, sub.subject_code, sub.subject_name, sub.units, g.grading_period, g.grade
         FROM student_subjects ss
         JOIN subjects sub ON sub.subject_id = ss.subject_id
         LEFT JOIN grades g ON g.enrollment_id = ss.enrollment_id
         WHERE ss.student_id = ? AND ss.school_year = ? AND ss.semester = ?
         ORDER BY sub.subject_code'
    );
    $st->execute([$studentId, $sy, $sem]);
    $rows = [];
    foreach ($st->fetchAll() as $r) {
        $id = $r['enrollment_id'];
        if (!isset($rows[$id])) {
            $rows[$id] = [
                'enrollment_id' => $id,
                'subject_code' => $r['subject_code'],
                'subject_name' => $r['subject_name'],
                'units' => $r['units'],
                'q' => array_fill_keys(QUARTERS, null),
                'final' => null,
            ];
        }
        if ($r['grading_period'] !== null && $r['grade'] !== null) {
            $rows[$id]['q'][$r['grading_period']] = (float)$r['grade'];
        }
    }
    foreach ($rows as &$row) {
        $row['final'] = average($row['q']);
    }
    unset($row);
    return array_values($rows);
}

function general_average(array $rows): ?float
{
    return average(array_map(function ($r) {
        return $r['final'];
    }, $rows));
}

/** School year / semester pairs a student has enrollments in, newest first. */
function student_terms(int $studentId): array
{
    $st = db()->prepare('SELECT DISTINCT school_year, semester FROM student_subjects WHERE student_id = ? ORDER BY school_year DESC, semester DESC');
    $st->execute([$studentId]);
    return $st->fetchAll();
}

/* ---------- Auth ---------- */

function admin_user(): ?array
{
    return $_SESSION['admin'] ?? null;
}

function student_user(): ?array
{
    return $_SESSION['student'] ?? null;
}

function require_admin(): array
{
    $a = admin_user();
    if (!$a) {
        redirect('auth/login.php?role=admin');
    }
    return $a;
}

/** Returns the signed-in student's full record (with year level and strand). */
function require_student(): array
{
    $u = student_user();
    if (!$u) {
        redirect('auth/login.php');
    }
    $row = load_student((int)$u['student_id']);
    if (!$row) {
        unset($_SESSION['student']);
        redirect('auth/login.php');
    }
    return $row;
}

/* ---------- Layout ---------- */

function icon(string $name): string
{
    $p = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'students' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8"/><path d="M18 14.3c2 .8 3.5 2.6 3.5 5.7"/>',
        'subjects' => '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 21V5"/><path d="M9 8h6"/>',
        'grades' => '<path d="M5 20V11"/><path d="M12 20V4"/><path d="M19 20v-6"/>',
        'levels' => '<path d="M12 3 2 8l10 5 10-5z"/><path d="M6 10.5V16c0 1.4 2.7 3 6 3s6-1.6 6-3v-5.5"/>',
        'strands' => '<path d="M4 7h16M4 12h16M4 17h10"/>',
        'admins' => '<path d="M12 3 4 6v6c0 4.4 3.2 7.6 8 9 4.8-1.4 8-4.6 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'profile' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.2 3.6-7 8-7s8 2.8 8 7"/>',
        'logout' => '<path d="M9 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h4"/><path d="m16 8 4 4-4 4"/><path d="M20 12H9"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
    ];
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$name] ?? '') . '</svg>';
}

/** [key, label, path, icon] */
function nav_items(string $role): array
{
    if ($role === 'admin') {
        return [
            ['dashboard', 'Dashboard', 'admin/dashboard.php', 'dashboard'],
            ['students', 'Students', 'admin/students.php', 'students'],
            ['subjects', 'Subjects', 'admin/subjects.php', 'subjects'],
            ['grades', 'Grades', 'admin/grades.php', 'grades'],
            ['year-levels', 'Year levels', 'admin/year-levels.php', 'levels'],
            ['strands', 'Strands', 'admin/strands.php', 'strands'],
            ['manage-admin', 'Administrators', 'admin/manage-admin.php', 'admins'],
        ];
    }
    if ($role === 'student') {
        return [
            ['dashboard', 'Dashboard', 'student/dashboard.php', 'dashboard'],
            ['profile', 'My profile', 'student/profile.php', 'profile'],
            ['subjects', 'My subjects', 'student/subjects.php', 'subjects'],
            ['grades', 'My grades', 'student/grades.php', 'grades'],
        ];
    }
    return [];
}

/**
 * Opens the page. $role: 'admin' | 'student' (sidebar layout) or 'public' (bare page).
 */
function page_start(string $title, string $role = 'public', string $active = ''): void
{
    $user = null;
    $label = '';
    if ($role === 'admin') {
        $a = admin_user();
        $user = $a ? $a['fullname'] : null;
        $label = 'Administrator';
    } elseif ($role === 'student') {
        $s = student_user();
        $user = $s ? $s['name'] : null;
        $label = $s ? 'Student ' . $s['number'] : '';
    }
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> – <?= h(APP_NAME) ?></title>
<link rel="icon" href="<?= h(asset('images/logo.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= h(asset('css/style.css')) ?>">
</head>
<body class="role-<?= h($role) ?>">
<?php if ($user !== null): ?>
<div class="shell">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= h(url($role . '/dashboard.php')) ?>">
      <img src="<?= h(asset('images/logo.svg')) ?>" alt="" width="34" height="34">
      <span>Senior High<small><?= $role === 'admin' ? 'Admin console' : 'Student portal' ?></small></span>
    </a>
    <nav aria-label="Main">
      <?php foreach (nav_items($role) as $item): ?>
        <a class="nav-link<?= $item[0] === $active ? ' active' : '' ?>" href="<?= h(url($item[2])) ?>"<?= $item[0] === $active ? ' aria-current="page"' : '' ?>>
          <?= icon($item[3]) ?><span><?= h($item[1]) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-user">
      <div class="who"><strong><?= h($user) ?></strong><span><?= h($label) ?></span></div>
      <form method="post" action="<?= h(url('auth/logout.php')) ?>">
        <?= csrf_field() ?>
        <button class="signout" type="submit"><?= icon('logout') ?><span>Sign out</span></button>
      </form>
    </div>
  </aside>
  <div class="scrim" id="scrim"></div>
  <div class="content">
    <div class="topbar">
      <button type="button" class="menu-btn" id="menu-btn" aria-label="Open menu" aria-controls="sidebar"><?= icon('menu') ?></button>
      <strong><?= h($title) ?></strong>
    </div>
    <main class="main">
      <?php render_flashes(); ?>
<?php endif;
}

function page_end(string $role = 'public'): void
{
    if ($role === 'admin' || $role === 'student') {
        echo "    </main>\n  </div>\n</div>\n";
    }
    echo '<script src="' . h(asset('js/app.js')) . '"></script>' . "\n</body>\n</html>\n";
}

// Runs on every request that includes this file: any POST without a valid CSRF token is rejected before the page does anything.
csrf_check();
