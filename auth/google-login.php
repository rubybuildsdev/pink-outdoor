<?php
require_once '../config/config.php';

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

$scope = 'email profile';
$url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => $scope,
    'state'         => $state,
    'prompt'        => 'select_account'
]);

header('Location: ' . $url);
exit;
