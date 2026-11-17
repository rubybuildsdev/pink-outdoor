<?php
// ============================================
// config/session.php — Session & Auth Guard & CSRF
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------- Helper: cek login ----------
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getRole(): string {
    return $_SESSION['role'] ?? '';
}

function getUserId(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function getUserName(): string {
    return $_SESSION['name'] ?? 'Guest';
}

function getUserEmail(): string {
    return $_SESSION['email'] ?? '';
}

function isAdmin(): bool {
    return isLoggedIn() && getRole() === 'admin';
}

function isStaff(): bool {
    return isLoggedIn() && in_array(getRole(), ['admin', 'staff']);
}

// ---------- Guard per role ----------

/**
 * Halaman hanya untuk admin.
 * Jika bukan admin → redirect + pesan.
 */
function requireAdmin(): void {
    if (!isLoggedIn() || getRole() !== 'admin') {
        header('Location: ' . BASE_URL . 'login.php?error=Akses+ditolak');
        exit;
    }
}

/**
 * Halaman untuk admin ATAU staff.
 */
function requireStaff(): void {
    if (!isLoggedIn() || !in_array(getRole(), ['admin', 'staff'])) {
        header('Location: ' . BASE_URL . 'login.php?error=Akses+ditolak');
        exit;
    }
}

/**
 * Halaman untuk semua user yang sudah login.
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php?error=Silakan+login+terlebih+dahulu');
        exit;
    }
}

/**
 * Jika sudah login → redirect ke dashboard sesuai role.
 * Dipakai di login.php & register.php agar tidak bisa balik.
 */
function redirectIfLoggedIn(): void {
    if (!isLoggedIn()) return;
    switch (getRole()) {
        case 'admin': header('Location: ' . BASE_URL . 'admin/index.php'); break;
        case 'staff': header('Location: ' . BASE_URL . 'staff/index.php'); break;
        default:      header('Location: ' . BASE_URL . 'index.php');       break;
    }
    exit;
}

// ---------- Flash message ----------
function setFlash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ---------- CSRF Protection ----------
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function verifyCsrfToken(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrf(): void {
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Token keamanan (CSRF) tidak valid atau sesi telah berakhir.');
        $referer = $_SERVER['HTTP_REFERER'] ?? BASE_URL;
        header('Location: ' . $referer);
        exit;
    }
}
