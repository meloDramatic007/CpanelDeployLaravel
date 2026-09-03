<?php
declare(strict_types=1);
defined('ROOT_DIR') || exit('Direct access not permitted.');


function disabled_functions(): array
{
    $raw = (string)ini_get('disable_functions');
    if ($raw === '') {
        return [];
    }
    return array_map('trim', explode(',', $raw));
}

function is_func_available(string $fn): bool
{
    return function_exists($fn) && !in_array($fn, disabled_functions(), true);
}

function best_exec_method(): ?string
{
    foreach (['proc_open', 'exec', 'shell_exec', 'system'] as $fn) {
        if (is_func_available($fn)) {
            return $fn;
        }
    }
    return null;
}

/**
 * Many PHP-FPM setups run with no HOME set, which breaks Composer
 * ("The HOME or COMPOSER_HOME environment variable must be set").
 * Give every command a writable HOME and a dedicated COMPOSER_HOME
 * so Composer's cache/config has somewhere to live.
 */
function build_env(): array
{
    $env = [];
    foreach ($_SERVER as $k => $v) {
        if (is_string($v)) {
            $env[$k] = $v;
        }
    }
    foreach ($_ENV as $k => $v) {
        if (is_string($v)) {
            $env[$k] = $v;
        }
    }
    if (empty($env['PATH'])) {
        $env['PATH'] = getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin';
    }

    $home = data_path('home');
    if (!is_dir($home)) {
        @mkdir($home, 0755, true);
    }
    $env['HOME'] = $home;

    $composerHome = data_path('composer-home');
    if (!is_dir($composerHome)) {
        @mkdir($composerHome, 0755, true);
    }
    $env['COMPOSER_HOME'] = $composerHome;

    return $env;
}

function env_export_prefix(): string
{
    $env = build_env();
    $parts = [];
    foreach (['HOME', 'COMPOSER_HOME'] as $key) {
        $parts[] = $key . '=' . escapeshellarg($env[$key]);
    }
    return 'export ' . implode(' ', $parts) . '; ';
}

/**
 * @return array{ok:bool,exit_code:?int,stdout:string,stderr:string,duration:float,method:?string,message:?string}
 */
function run_command(string $cmd, string $cwd, int $timeout = 300): array
{
    $start = microtime(true);
    $method = best_exec_method();

    if ($method === null) {
        return [
            'ok' => false, 'exit_code' => null, 'stdout' => '', 'stderr' => '',
            'duration' => 0.0, 'method' => null,
            'message' => 'No shell execution function is available (proc_open, exec, shell_exec, system are all disabled by the host). Ask your hosting provider to enable one, or run these commands another way.',
        ];
    }

    if (!is_dir($cwd)) {
        return [
            'ok' => false, 'exit_code' => null, 'stdout' => '', 'stderr' => '',
            'duration' => 0.0, 'method' => $method,
            'message' => "Working directory does not exist: $cwd",
        ];
    }

    @set_time_limit(0);

    if ($method === 'proc_open') {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        // Apache/mod_php (and some PHP-FPM setups) ignore SIGCHLD, which lets
        // the kernel reap the child before proc_close()/proc_get_status() can
        // waitpid() it — that call then fails with ECHILD and PHP reports
        // exit code -1 even though the command actually succeeded. Sidestep
        // it entirely by having the shell report $? itself via a sentinel.
        $sentinel = '___CPDEPLOY_EXIT_' . bin2hex(random_bytes(6)) . '___';
        $wrappedCmd = '(' . $cmd . '); __ec=$?; echo "' . $sentinel . ':$__ec"';

        $process = proc_open($wrappedCmd, $descriptors, $pipes, $cwd, build_env());
        if (!is_resource($process)) {
            return [
                'ok' => false, 'exit_code' => null, 'stdout' => '', 'stderr' => '',
                'duration' => microtime(true) - $start, 'method' => $method,
                'message' => 'Failed to start process.',
            ];
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $timedOut = false;
        while (true) {
            $status = proc_get_status($process);
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);

            if (!$status['running']) {
                break;
            }
            if ((microtime(true) - $start) > $timeout) {
                proc_terminate($process, 9);
                $stderr .= "\n[Terminated: exceeded {$timeout}s timeout]";
                $timedOut = true;
                break;
            }
            usleep(100000);
        }
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $exitCode = null;
        if (preg_match('/' . preg_quote($sentinel, '/') . ':(-?\d+)\s*\z/', $stdout, $m)) {
            $exitCode = (int)$m[1];
            $stdout = substr($stdout, 0, -strlen($m[0]));
        }

        return [
            'ok' => !$timedOut && $exitCode === 0, 'exit_code' => $timedOut ? null : $exitCode,
            'stdout' => strip_ansi(rtrim($stdout, "\n")), 'stderr' => strip_ansi($stderr),
            'duration' => microtime(true) - $start, 'method' => $method, 'message' => null,
        ];
    }

    // Fallback methods run inside a subshell that first cd's into the target directory,
    // since exec()/shell_exec()/system() don't accept a cwd argument or an env array.
    $wrapped = env_export_prefix() . 'cd ' . escapeshellarg($cwd) . ' && ' . $cmd . ' 2>&1';

    if ($method === 'exec') {
        $output = [];
        $returnVar = 0;
        exec($wrapped, $output, $returnVar);
        $text = strip_ansi(implode("\n", $output));
        return [
            'ok' => $returnVar === 0, 'exit_code' => $returnVar,
            'stdout' => $text, 'stderr' => '',
            'duration' => microtime(true) - $start, 'method' => $method, 'message' => null,
        ];
    }

    if ($method === 'shell_exec') {
        $out = shell_exec($wrapped);
        return [
            'ok' => $out !== null, 'exit_code' => null,
            'stdout' => strip_ansi((string)$out), 'stderr' => '',
            'duration' => microtime(true) - $start, 'method' => $method,
            'message' => $out === null ? 'Command produced no output or failed to run.' : null,
        ];
    }

    // system()
    ob_start();
    $returnVar = 0;
    system($wrapped, $returnVar);
    $out = ob_get_clean();
    return [
        'ok' => $returnVar === 0, 'exit_code' => $returnVar,
        'stdout' => strip_ansi((string)$out), 'stderr' => '',
        'duration' => microtime(true) - $start, 'method' => $method, 'message' => null,
    ];
}
