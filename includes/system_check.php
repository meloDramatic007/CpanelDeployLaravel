<?php
declare(strict_types=1);
defined('ROOT_DIR') || exit('Direct access not permitted.');


function gather_system_info(): array
{
    // Avoid PHP serving a stale "file doesn't exist" answer from its
    // per-process realpath/stat cache right after a file was just written.
    clearstatcache();

    $execFns = ['proc_open', 'exec', 'shell_exec', 'system', 'passthru'];
    $execStatus = [];
    foreach ($execFns as $fn) {
        $execStatus[$fn] = is_func_available($fn);
    }

    $commonExtensions = [
        'intl', 'fileinfo', 'mbstring', 'zip', 'gd', 'curl', 'bcmath',
        'pdo_mysql', 'tokenizer', 'ctype', 'xml', 'dom', 'exif', 'openssl',
    ];
    $extensionStatus = [];
    foreach ($commonExtensions as $ext) {
        $extensionStatus[$ext] = extension_loaded($ext);
    }

    $phpBinaries = [];
    $method = best_exec_method();
    $tmp = sys_get_temp_dir() ?: '.';
    foreach (common_php_binaries() as $bin) {
        $version = null;
        $binExtensions = null;
        if ($method !== null) {
            $result = run_command(escapeshellcmd($bin) . ' -v', $tmp, 10);
            if ($result['ok'] && $result['stdout'] !== '') {
                $firstLine = strtok($result['stdout'], "\n");
                $version = $firstLine !== false ? $firstLine : null;
            }

            $modResult = run_command(escapeshellcmd($bin) . ' -m', $tmp, 10);
            if ($modResult['ok'] && $modResult['stdout'] !== '') {
                $loaded = array_map('strtolower', array_map('trim', explode("\n", $modResult['stdout'])));
                $binExtensions = [];
                foreach ($commonExtensions as $ext) {
                    $binExtensions[$ext] = in_array(strtolower($ext), $loaded, true);
                }
            }
        }
        $phpBinaries[] = ['path' => $bin, 'version' => $version, 'extensions' => $binExtensions];
    }

    return [
        'php_sapi' => PHP_SAPI,
        'php_version_web' => PHP_VERSION,
        'exec_functions' => $execStatus,
        'best_exec_method' => $method,
        'open_basedir' => ini_get('open_basedir') ?: '(none)',
        'curl_available' => extension_loaded('curl'),
        'allow_url_fopen' => (bool)ini_get('allow_url_fopen'),
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit'),
        'php_binaries' => $phpBinaries,
        'shared_composer_phar' => file_exists(shared_composer_phar_path()),
        'shared_composer_phar_path' => shared_composer_phar_path(),
        'shared_composer_phar_size' => file_exists(shared_composer_phar_path()) ? filesize(shared_composer_phar_path()) : null,
        'disable_functions' => disabled_functions(),
        'extensions' => $extensionStatus,
        'home' => data_path('home'),
        'composer_home' => data_path('composer-home'),
    ];
}

function download_composer_phar(): array
{
    $url = 'https://getcomposer.org/composer.phar';
    $target = shared_composer_phar_path();

    if (extension_loaded('curl')) {
        @set_time_limit(0);
        $fh = fopen($target . '.part', 'w');
        if ($fh === false) {
            return [false, "Could not open $target.part for writing — check that data/ is writable."];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fh,
            CURLOPT_FOLLOWLOCATION => true,
            // Some shared hosts throttle outbound bandwidth heavily (seen as low
            // as ~12KB/s), so a flat timeout on the ~3.6MB file is unreliable.
            // Cap connect time tightly but allow up to 10 minutes overall, and
            // only abort early if the transfer truly stalls rather than just runs slow.
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_LOW_SPEED_LIMIT => 512,
            CURLOPT_LOW_SPEED_TIME => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $ok = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fh);

        if (!$ok || $code !== 200) {
            @unlink($target . '.part');
            return [false, "cURL download failed: $err (HTTP $code)"];
        }
        $partSize = filesize($target . '.part');
        if ($partSize === false || $partSize < 1_000_000) {
            @unlink($target . '.part');
            return [false, "Downloaded file looks incomplete (" . ($partSize === false ? 'unreadable' : "$partSize bytes") . ") — try again."];
        }
        if (!rename($target . '.part', $target)) {
            return [false, "Download finished but could not move $target.part into place at $target — check permissions on data/."];
        }
        clearstatcache(true, $target);
        activity_log('Downloaded composer.phar via cURL.');
        return [true, ''];
    }

    if ((bool)ini_get('allow_url_fopen')) {
        @set_time_limit(0);
        $context = stream_context_create(['http' => ['timeout' => 600]]);
        $data = @file_get_contents($url, false, $context);
        if ($data === false) {
            return [false, 'file_get_contents download failed.'];
        }
        file_put_contents($target, $data);
        activity_log('Downloaded composer.phar via allow_url_fopen.');
        return [true, ''];
    }

    return [false, 'Neither cURL nor allow_url_fopen is available. Upload composer.phar manually into the data/ folder via cPanel File Manager.'];
}
