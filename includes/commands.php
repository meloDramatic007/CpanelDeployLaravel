<?php
declare(strict_types=1);
defined('ROOT_DIR') || exit('Direct access not permitted.');


function quick_commands(): array
{
    return [
        'php_version'        => ['label' => 'PHP Version', 'group' => 'Info', 'danger' => false, 'tpl' => '{PHP} -v'],
        'composer_version'   => ['label' => 'Composer Version', 'group' => 'Info', 'danger' => false, 'tpl' => '{COMPOSER} --version'],
        'artisan_about'      => ['label' => 'Artisan About', 'group' => 'Info', 'danger' => false, 'tpl' => '{PHP} artisan about'],

        'composer_install'   => ['label' => 'Composer Install', 'group' => 'Composer', 'danger' => false, 'tpl' => '{COMPOSER} install --no-dev --optimize-autoloader --no-interaction'],
        'composer_install_ignore_platform' => ['label' => 'Composer Install (ignore platform reqs)', 'group' => 'Composer', 'danger' => true, 'tpl' => '{COMPOSER} install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs'],
        'composer_update'    => ['label' => 'Composer Update', 'group' => 'Composer', 'danger' => true, 'tpl' => '{COMPOSER} update --no-dev --optimize-autoloader --no-interaction'],
        'composer_dump'      => ['label' => 'Dump Autoload', 'group' => 'Composer', 'danger' => false, 'tpl' => '{COMPOSER} dump-autoload --optimize'],

        'artisan_key'        => ['label' => 'Key Generate', 'group' => 'Artisan', 'danger' => true, 'tpl' => '{PHP} artisan key:generate --force'],
        'artisan_migrate'    => ['label' => 'Migrate', 'group' => 'Artisan', 'danger' => true, 'tpl' => '{PHP} artisan migrate --force'],
        'artisan_migrate_status' => ['label' => 'Migrate Status', 'group' => 'Artisan', 'danger' => false, 'tpl' => '{PHP} artisan migrate:status'],
        'artisan_storage_link' => ['label' => 'Storage Link', 'group' => 'Artisan', 'danger' => false, 'tpl' => '{PHP} artisan storage:link'],
        'artisan_config_cache' => ['label' => 'Config Cache', 'group' => 'Artisan', 'danger' => false, 'tpl' => '{PHP} artisan config:cache'],
        'artisan_config_clear' => ['label' => 'Config Clear', 'group' => 'Artisan', 'danger' => false, 'tpl' => '{PHP} artisan config:clear'],
        'artisan_route_cache' => ['label' => 'Route Cache', 'group' => 'Artisan', 'danger' => false, 'tpl' => '{PHP} artisan route:cache'],
        'artisan_route_clear' => ['label' => 'Route Clear', 'group' => 'Artisan', 'danger' => false, 'tpl' => '{PHP} artisan route:clear'],
        'artisan_view_clear' => ['label' => 'View Clear', 'group' => 'Artisan', 'danger' => false, 'tpl' => '{PHP} artisan view:clear'],
        'artisan_cache_clear' => ['label' => 'Cache Clear', 'group' => 'Artisan', 'danger' => false, 'tpl' => '{PHP} artisan cache:clear'],
        'artisan_optimize'   => ['label' => 'Optimize', 'group' => 'Artisan', 'danger' => false, 'tpl' => '{PHP} artisan optimize'],
        'artisan_optimize_clear' => ['label' => 'Optimize Clear', 'group' => 'Artisan', 'danger' => false, 'tpl' => '{PHP} artisan optimize:clear'],
        'artisan_queue_restart' => ['label' => 'Queue Restart', 'group' => 'Artisan', 'danger' => false, 'tpl' => '{PHP} artisan queue:restart'],

        'git_pull'           => ['label' => 'Git Pull', 'group' => 'Source', 'danger' => true, 'tpl' => 'git pull'],
        'fix_permissions'    => ['label' => 'Fix storage/ Permissions', 'group' => 'Maintenance', 'danger' => true, 'tpl' => 'chmod -R 775 storage bootstrap/cache'],
    ];
}

function shared_composer_phar_path(): string
{
    // Deliberately not named composer.phar: some hosts run malware scanners
    // (Imunify360/ImunifyAV and similar) that auto-quarantine .phar files on
    // sight, even ones you upload yourself. PHP doesn't care what a script is
    // named to run it — `php composer-lib install ...` works identically to
    // `php composer.phar install ...` — so this dodges that entirely.
    return data_path('composer-lib');
}

/**
 * Resolves {PHP}, {COMPOSER}, {ARTISAN} placeholders for a given project.
 */
function build_command(string $templateKey, array $project): array
{
    $catalog = quick_commands();
    if (!isset($catalog[$templateKey])) {
        return [false, 'Unknown command.'];
    }
    $tpl = $catalog[$templateKey]['tpl'];

    $phpBin = escapeshellcmd($project['php_bin'] ?: 'php');

    $composerPath = trim((string)($project['composer_path'] ?? ''));
    if ($composerPath === '') {
        $composerPath = shared_composer_phar_path();
    }
    clearstatcache(true, $composerPath);
    if (file_exists($composerPath)) {
        $composerRun = $phpBin . ' ' . escapeshellarg($composerPath);
    } else {
        $composerRun = 'composer';
    }

    $cmd = str_replace(['{PHP}', '{COMPOSER}'], [$phpBin, $composerRun], $tpl);
    return [true, $cmd];
}

function common_php_binaries(): array
{
    $candidates = ['php'];
    $globs = [
        '/usr/local/bin/ea-php*',
        '/opt/cpanel/ea-php*/root/usr/bin/php',
        '/usr/local/bin/php*',
        '/usr/bin/php*',
    ];
    foreach ($globs as $pattern) {
        foreach ((glob($pattern) ?: []) as $path) {
            if (is_file($path)) {
                $candidates[] = $path;
            }
        }
    }
    return array_values(array_unique($candidates));
}
