<?php
declare(strict_types=1);
defined('ROOT_DIR') || exit('Direct access not permitted.');


function h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function data_path(string $file = ''): string
{
    return DATA_DIR . ($file !== '' ? '/' . ltrim($file, '/') : '');
}

function ensure_data_dir(): void
{
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }
    if (!is_dir(DATA_DIR . '/logs')) {
        mkdir(DATA_DIR . '/logs', 0755, true);
    }
    $htaccess = DATA_DIR . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Require all denied\nDeny from all\n");
    }
}

function json_read(string $path, $default = [])
{
    if (!file_exists($path)) {
        return $default;
    }
    $fh = fopen($path, 'r');
    if (!$fh) {
        return $default;
    }
    flock($fh, LOCK_SH);
    $content = stream_get_contents($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    $data = json_decode($content, true);
    return $data === null ? $default : $data;
}

function json_write(string $path, $data): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $tmp = $path . '.tmp' . bin2hex(random_bytes(4));
    file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    rename($tmp, $path);
}

function activity_log(string $line): void
{
    ensure_data_dir();
    $logFile = data_path('logs/activity.log');
    if (file_exists($logFile) && filesize($logFile) > 2 * 1024 * 1024) {
        @rename($logFile, $logFile . '.1');
    }
    $ts = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '-';
    file_put_contents($logFile, "[$ts] [$ip] $line\n", FILE_APPEND | LOCK_EX);
}

function strip_ansi(string $s): string
{
    return preg_replace('/\x1b\[[0-9;]*m/', '', $s) ?? $s;
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
