<?php
// FIX #6: Google OAuth callback — rewrite lengkap
// Root cause:
// 1. session_start() dipanggil ulang padahal sudah dipanggil oleh config/session.php
//    → conflict session → error
// 2. Menggunakan google-config.php (Google_Client library) tapi google-login.php
//    menggunakan raw OAuth URL tanpa library — inkonsistensi total
// 3. SQL injection pada INSERT/SELECT (raw $email interpolasi langsung)
// 4. Kolom 'photo' dan 'provider' tidak ada di tabel users → fatal SQL error
// 5. Header redirect tanpa exit → eksekusi berlanjut
// 6. Redirect ke "../admin/" bukan "../admin/index.php" → 404 di shared hosting
//
// Solusi: gunakan raw OAuth (curl) konsisten dengan google-login.php,
// tanpa library Google_Client, dan tanpa kolom yang tidak ada di schema.

require_once '../config/config.php';
// config/config.php sudah load session.php yang memanggil session_start()
// JANGAN panggil session_start() lagi di sini

$clientId     = GOOGLE_CLIENT_ID;
$clientSecret = GOOGLE_CLIENT_SECRET;
$redirectUri  = GOOGLE_REDIRECT_URI;

// Validasi CSRF state OAuth
$state      = $_GET['state'] ?? '';
$savedState = $_SESSION['oauth_state'] ?? '';
unset($_SESSION['oauth_state']);

if (empty($state) || empty($savedState) || !hash_equals($savedState, $state)) {
    header('Location: ' . BASE_URL . 'login.php?error=Validasi+keamanan+state+OAuth+gagal.');
    exit;
}

// Tidak ada 'code' → kembalikan ke login
if (!isset($_GET['code'])) {
    header('Location: ' . BASE_URL . 'login.php?error=Login+Google+gagal%2C+coba+lagi.');
    exit;
}

// Fungsi helper HTTP request (mendukung cURL dan fallback file_get_contents)
function makeHttpRequest(string $url, string $method = 'GET', ?string $data = null, array $headers = []): ?string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 15,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST]       = true;
            $opts[CURLOPT_POSTFIELDS] = $data;
        }
        if (!empty($headers)) {
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }
        curl_setopt_array($ch, $opts);
        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        return ($err || $res === false) ? null : $res;
    } else {
        // Fallback jika ekstensi cURL belum aktif di php.ini
        $streamOpts = [
            'http' => [
                'method'        => $method,
                'timeout'       => 15,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ]
        ];
        if ($method === 'POST' && $data !== null) {
            $streamOpts['http']['content'] = $data;
        }
        if (!empty($headers)) {
            $streamOpts['http']['header'] = implode("\r\n", $headers);
        }
        $context = stream_context_create($streamOpts);
        $res = @file_get_contents($url, false, $context);
        return $res !== false ? $res : null;
    }
}

// Tukar code dengan access token
$tokenUrl  = 'https://oauth2.googleapis.com/token';
$tokenData = http_build_query([
    'code'          => $_GET['code'],
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri'  => $redirectUri,
    'grant_type'    => 'authorization_code',
]);

$tokenResponse = makeHttpRequest(
    $tokenUrl,
    'POST',
    $tokenData,
    ['Content-Type: application/x-www-form-urlencoded']
);

if (!$tokenResponse) {
    header('Location: ' . BASE_URL . 'login.php?error=Koneksi+ke+Google+gagal.+Pastikan+koneksi+internet+stabil.');
    exit;
}

$tokenJson   = json_decode($tokenResponse, true);
$accessToken = $tokenJson['access_token'] ?? '';

if (!$accessToken) {
    header('Location: ' . BASE_URL . 'login.php?error=Token+Google+tidak+valid.');
    exit;
}

// Ambil data user dari Google
$userResponse = makeHttpRequest(
    'https://www.googleapis.com/oauth2/v2/userinfo',
    'GET',
    null,
    ['Authorization: Bearer ' . $accessToken]
);

if (!$userResponse) {
    header('Location: ' . BASE_URL . 'login.php?error=Gagal+ambil+data+Google.');
    exit;
}

$googleUser = json_decode($userResponse, true);
$email      = $googleUser['email'] ?? '';
$name       = $googleUser['name']  ?? 'Google User';

if (!$email) {
    header('Location: ' . BASE_URL . 'login.php?error=Email+Google+tidak+ditemukan.');
    exit;
}

// FIX: Prepared statement — tidak ada interpolasi raw variable
$stmtChk = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmtChk->bind_param('s', $email);
$stmtChk->execute();
$user = $stmtChk->get_result()->fetch_assoc();
$stmtChk->close();

// Jika belum ada, daftarkan — hanya kolom yang ADA di schema (tanpa photo/provider)
if (!$user) {
    $defaultPass = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $stmtIns     = $conn->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, 'user', 'active')");
    $stmtIns->bind_param('sss', $name, $email, $defaultPass);
    $stmtIns->execute();
    $newId = $conn->insert_id;
    $stmtIns->close();

    // Ambil user yang baru dibuat
    $stmtNew = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmtNew->bind_param('i', $newId);
    $stmtNew->execute();
    $user = $stmtNew->get_result()->fetch_assoc();
    $stmtNew->close();
}

// Cek status aktif
if (($user['status'] ?? '') !== 'active') {
    header('Location: ' . BASE_URL . 'login.php?error=Akun+dinonaktifkan.+Hubungi+admin.');
    exit;
}

// Set session (konsisten dengan login.php)
$_SESSION['user_id'] = $user['id'];
$_SESSION['name']    = $user['name'];
$_SESSION['email']   = $user['email'];
$_SESSION['role']    = $user['role'];
$_SESSION['avatar']  = $user['avatar'] ?? '';

// Log activity
$uid = (int)$user['id'];
$act = 'Login via Google';
$mod = 'auth';
$stmtLog = $conn->prepare("INSERT INTO activity_log (user_id, action, module) VALUES (?, ?, ?)");
$stmtLog->bind_param('iss', $uid, $act, $mod);
$stmtLog->execute();
$stmtLog->close();

// Redirect sesuai role — FIX: gunakan index.php bukan directory agar aman di shared hosting
switch ($user['role']) {
    case 'admin': header('Location: ' . BASE_URL . 'admin/index.php'); break;
    case 'staff': header('Location: ' . BASE_URL . 'staff/index.php'); break;
    default:      header('Location: ' . BASE_URL . 'index.php');       break;
}
exit;
