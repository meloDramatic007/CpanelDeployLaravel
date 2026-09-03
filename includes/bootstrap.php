<?php
declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));
define('DATA_DIR', ROOT_DIR . '/data');

require_once ROOT_DIR . '/includes/helpers.php';

ensure_data_dir();

$cookieParams = [
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
];
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $cookieParams['secure'] = true;
}
session_set_cookie_params($cookieParams);
session_name('cpdeploy_sess');
session_start();

require_once ROOT_DIR . '/includes/csrf.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/projects.php';
require_once ROOT_DIR . '/includes/executor.php';
require_once ROOT_DIR . '/includes/commands.php';
require_once ROOT_DIR . '/includes/system_check.php';
require_once ROOT_DIR . '/includes/render.php';

const SESSION_IDLE_LIMIT = 1800; // 30 minutes

if (!empty($_SESSION['auth_ok'])) {
    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_IDLE_LIMIT) {
        $_SESSION = [];
        session_destroy();
        session_start();
    } else {
        $_SESSION['last_activity'] = time();
    }
}
