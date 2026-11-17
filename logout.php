<?php
require_once 'config/config.php';

if (isLoggedIn()) {
    $uid  = getUserId();
    $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, module) VALUES (?, 'Logout', 'auth')");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $stmt->close();
}

// BUG FIX #2: Kosongkan cart saat logout agar tidak persistent antar user
$_SESSION['cart'] = [];

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);
}
session_destroy();
header('Location: ' . BASE_URL . 'login.php');
exit;
