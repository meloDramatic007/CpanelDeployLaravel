<?php
declare(strict_types=1);
defined('ROOT_DIR') || exit('Direct access not permitted.');


function projects_path(): string
{
    return data_path('projects.json');
}

function get_projects(): array
{
    return json_read(projects_path(), []);
}

function get_project(string $id): ?array
{
    foreach (get_projects() as $p) {
        if ($p['id'] === $id) {
            return $p;
        }
    }
    return null;
}

/** @return array{0: bool, 1: string, 2: ?array} */
function upsert_project(array $input): array
{
    $name = trim((string)($input['name'] ?? ''));
    $path = trim((string)($input['path'] ?? ''));
    $phpBin = trim((string)($input['php_bin'] ?? '')) ?: 'php';
    $composerPath = trim((string)($input['composer_path'] ?? ''));
    $id = trim((string)($input['id'] ?? ''));

    if ($name === '') {
        return [false, 'Project name is required.', null];
    }
    if ($path === '') {
        return [false, 'Project path is required.', null];
    }
    $real = realpath($path);
    if ($real === false || !is_dir($real)) {
        return [false, "Path does not exist or is not a directory: $path", null];
    }

    $projects = get_projects();

    if ($id === '') {
        $id = bin2hex(random_bytes(8));
        $projects[] = [
            'id' => $id,
            'name' => $name,
            'path' => $real,
            'php_bin' => $phpBin,
            'composer_path' => $composerPath,
            'created_at' => date('c'),
        ];
    } else {
        $found = false;
        foreach ($projects as &$p) {
            if ($p['id'] === $id) {
                $p['name'] = $name;
                $p['path'] = $real;
                $p['php_bin'] = $phpBin;
                $p['composer_path'] = $composerPath;
                $found = true;
                break;
            }
        }
        unset($p);
        if (!$found) {
            return [false, 'Project not found.', null];
        }
    }

    json_write(projects_path(), $projects);
    activity_log("Project saved: $name ($real)");
    return [true, '', get_project($id)];
}

function delete_project(string $id): bool
{
    $projects = get_projects();
    $filtered = array_values(array_filter($projects, fn($p) => $p['id'] !== $id));
    if (count($filtered) === count($projects)) {
        return false;
    }
    json_write(projects_path(), $filtered);
    activity_log("Project deleted: $id");
    return true;
}

function get_active_project_id(): ?string
{
    return $_SESSION['active_project_id'] ?? null;
}

function set_active_project_id(string $id): void
{
    $_SESSION['active_project_id'] = $id;
}

function get_active_project(): ?array
{
    $id = get_active_project_id();
    if (!$id) {
        return null;
    }
    return get_project($id);
}
