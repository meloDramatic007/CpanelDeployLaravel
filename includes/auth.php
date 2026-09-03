<?php
declare(strict_types=1);
defined('ROOT_DIR') || exit('Direct access not permitted.');


const MAX_LOGIN_ATTEMPTS = 5;
const LOCKOUT_SECONDS = 900; // 15 minutes

function config_path(): string
{
    return data_path('config.json');
}

function is_configured(): bool
{
    return file_exists(config_path());
}

function load_config(): array
{
    return json_read(config_path(), []);
}

function save_config(array $cfg): void
{
    json_write(config_path(), $cfg);
}

function setup_password(string $password): array
{
    if (strlen($password) < 10) {
        return [false, 'Password must be at least 10 characters.'];
    }
    save_config([
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'created_at' => date('c'),
        'failed_attempts' => [],
        'allowed_ips' => [],
    ]);
    activity_log('Tool configured (password set).');
    return [true, ''];
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function is_locked_out(array $cfg): int
{
    $ip = client_ip();
    $entry = $cfg['failed_attempts'][$ip] ?? null;
    if (!$entry) {
        return 0;
    }
    if (($entry['count'] ?? 0) >= MAX_LOGIN_ATTEMPTS) {
        $remaining = ($entry['locked_at'] ?? 0) + LOCKOUT_SECONDS - time();
        return $remaining > 0 ? $remaining : 0;
    }
    return 0;
}

function register_failed_attempt(array &$cfg): void
{
    $ip = client_ip();
    $entry = $cfg['failed_attempts'][$ip] ?? ['count' => 0, 'locked_at' => 0];
    $entry['count'] = ($entry['count'] ?? 0) + 1;
    if ($entry['count'] >= MAX_LOGIN_ATTEMPTS) {
        $entry['locked_at'] = time();
    }
    $cfg['failed_attempts'][$ip] = $entry;
    save_config($cfg);
}

function clear_failed_attempts(array &$cfg): void
{
    $ip = client_ip();
    unset($cfg['failed_attempts'][$ip]);
    save_config($cfg);
}

function is_ip_allowed(array $cfg): bool
{
    $allowed = $cfg['allowed_ips'] ?? [];
    if (empty($allowed)) {
        return true;
    }
    return in_array(client_ip(), $allowed, true);
}

function attempt_login(string $password): array
{
    $cfg = load_config();

    if (!is_ip_allowed($cfg)) {
        activity_log('Login blocked: IP not allowed.');
        return [false, 'Your IP address is not permitted to access this tool.'];
    }

    $lockedFor = is_locked_out($cfg);
    if ($lockedFor > 0) {
        return [false, 'Too many failed attempts. Try again in ' . ceil($lockedFor / 60) . ' minute(s).'];
    }

    if (!password_verify($password, $cfg['password_hash'] ?? '')) {
        register_failed_attempt($cfg);
        activity_log('Failed login attempt.');
        return [false, 'Incorrect password.'];
    }

    clear_failed_attempts($cfg);
    $_SESSION['auth_ok'] = true;
    $_SESSION['last_activity'] = time();
    session_regenerate_id(true);
    activity_log('Login successful.');
    return [true, ''];
}

function is_logged_in(): bool
{
    return !empty($_SESSION['auth_ok']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: index.php?page=login');
        exit;
    }
}

function do_logout(): void
{
    activity_log('Logout.');
    $_SESSION = [];
    session_destroy();
}
