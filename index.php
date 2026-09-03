<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$page = $_GET['page'] ?? 'dashboard';
$action = $_POST['action'] ?? $_GET['action'] ?? null;
$isAjax = isset($_POST['action']);

// ---- AJAX / POST action handling -----------------------------------------
if ($action !== null) {

    if ($action === 'logout') {
        do_logout();
        header('Location: index.php?page=login');
        exit;
    }

    if ($action === 'setup') {
        if (is_configured()) {
            json_response(['ok' => false, 'message' => 'Already configured.'], 400);
        }
        $pw = (string)($_POST['password'] ?? '');
        $pw2 = (string)($_POST['password_confirm'] ?? '');
        if ($pw !== $pw2) {
            json_response(['ok' => false, 'message' => 'Passwords do not match.']);
        }
        [$ok, $msg] = setup_password($pw);
        json_response(['ok' => $ok, 'message' => $msg]);
    }

    if ($action === 'login') {
        [$ok, $msg] = attempt_login((string)($_POST['password'] ?? ''));
        json_response(['ok' => $ok, 'message' => $msg]);
    }

    // Everything below requires an authenticated session + CSRF token.
    if (!is_logged_in()) {
        json_response(['ok' => false, 'message' => 'Session expired. Please log in again.'], 401);
    }
    if (!csrf_verify($_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null)) {
        json_response(['ok' => false, 'message' => 'Invalid or expired CSRF token. Refresh the page.'], 403);
    }

    if ($action === 'save_project') {
        [$ok, $msg, $project] = upsert_project($_POST);
        if ($ok && $project) {
            set_active_project_id($project['id']);
        }
        json_response(['ok' => $ok, 'message' => $msg, 'project' => $project]);
    }

    if ($action === 'delete_project') {
        $id = (string)($_POST['id'] ?? '');
        $ok = delete_project($id);
        if (get_active_project_id() === $id) {
            unset($_SESSION['active_project_id']);
        }
        json_response(['ok' => $ok]);
    }

    if ($action === 'set_active_project') {
        $id = (string)($_POST['id'] ?? '');
        if (!get_project($id)) {
            json_response(['ok' => false, 'message' => 'Project not found.'], 404);
        }
        set_active_project_id($id);
        json_response(['ok' => true]);
    }

    if ($action === 'run_command') {
        $project = get_active_project();
        if (!$project) {
            json_response(['ok' => false, 'message' => 'No active project selected.']);
        }

        $commandId = (string)($_POST['command_id'] ?? '');
        if ($commandId === 'custom') {
            $cmd = trim((string)($_POST['custom_command'] ?? ''));
            if ($cmd === '') {
                json_response(['ok' => false, 'message' => 'Custom command cannot be empty.']);
            }
        } else {
            [$built, $cmd] = build_command($commandId, $project);
            if (!$built) {
                json_response(['ok' => false, 'message' => $cmd]);
            }
        }

        activity_log("RUN [{$project['name']}] $cmd");
        $result = run_command($cmd, $project['path'], 280);
        activity_log("RESULT [{$project['name']}] exit=" . var_export($result['exit_code'], true) . " ok=" . ($result['ok'] ? '1' : '0'));

        json_response([
            'ok' => $result['ok'],
            'exit_code' => $result['exit_code'],
            'stdout' => $result['stdout'],
            'stderr' => $result['stderr'],
            'duration' => round($result['duration'], 2),
            'method' => $result['method'],
            'message' => $result['message'],
            'command' => $cmd,
        ]);
    }

    if ($action === 'download_composer') {
        [$ok, $msg] = download_composer_phar();
        json_response(['ok' => $ok, 'message' => $msg]);
    }

    if ($action === 'save_env') {
        $project = get_active_project();
        if (!$project) {
            json_response(['ok' => false, 'message' => 'No active project selected.']);
        }
        $envPath = rtrim($project['path'], '/') . '/.env';
        $content = (string)($_POST['content'] ?? '');
        if (file_exists($envPath) && !@copy($envPath, $envPath . '.bak.' . date('YmdHis'))) {
            json_response(['ok' => false, 'message' => "Could not create a backup before saving — check that the web server user can write to $envPath."]);
        }
        if (@file_put_contents($envPath, $content) === false) {
            json_response(['ok' => false, 'message' => "Failed to write $envPath — check file/folder permissions for the web server user."]);
        }
        activity_log("Saved .env for {$project['name']}");
        json_response(['ok' => true]);
    }

    if ($action === 'clear_log') {
        file_put_contents(data_path('logs/activity.log'), '');
        json_response(['ok' => true]);
    }

    json_response(['ok' => false, 'message' => 'Unknown action.'], 400);
}

// ---- Page rendering ---------------------------------------------------------

if (!is_configured()) {
    render_header('Setup', false);
    ?>
    <div class="card narrow">
        <h1>First-time Setup</h1>
        <p class="muted">Set a password to protect this tool. Anyone with this password can run shell commands on your hosting account, so make it strong and don't share this URL.</p>
        <form id="setup-form">
            <label>Password<input type="password" name="password" minlength="10" required autocomplete="new-password"></label>
            <label>Confirm Password<input type="password" name="password_confirm" minlength="10" required autocomplete="new-password"></label>
            <button type="submit">Set Password &amp; Continue</button>
            <div class="form-message"></div>
        </form>
    </div>
    <script>
    document.getElementById('setup-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        fd.set('action', 'setup');
        const res = await fetch('index.php', { method: 'POST', body: fd });
        const data = await res.json();
        const msgEl = e.target.querySelector('.form-message');
        if (data.ok) { window.location = 'index.php?page=login'; }
        else { msgEl.textContent = data.message; msgEl.className = 'form-message error'; }
    });
    </script>
    <?php
    render_footer();
    exit;
}

if (!is_logged_in()) {
    render_header('Login', false);
    ?>
    <div class="card narrow">
        <h1>Log In</h1>
        <form id="login-form">
            <label>Password<input type="password" name="password" required autocomplete="current-password" autofocus></label>
            <button type="submit">Log In</button>
            <div class="form-message"></div>
        </form>
    </div>
    <script>
    document.getElementById('login-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        fd.set('action', 'login');
        const res = await fetch('index.php', { method: 'POST', body: fd });
        const data = await res.json();
        const msgEl = e.target.querySelector('.form-message');
        if (data.ok) { window.location = 'index.php?page=dashboard'; }
        else { msgEl.textContent = data.message; msgEl.className = 'form-message error'; }
    });
    </script>
    <?php
    render_footer();
    exit;
}

// Authenticated pages below.
$projects = get_projects();
$activeProject = get_active_project();
$csrf = csrf_token();

if ($page === 'projects') {
    render_header('Projects');
    ?>
    <h1>Projects</h1>
    <p class="muted">Each hosting account can run several apps side by side. Add one entry per project pointing at its folder (e.g. <code>/home/youruser/myapp</code> or <code>/home/youruser/public_html/myapp</code>).</p>

    <div class="card">
        <h2>Add / Edit Project</h2>
        <form id="project-form">
            <input type="hidden" name="id" value="">
            <label>Name<input type="text" name="name" required placeholder="My Laravel App"></label>
            <label>Absolute Path<input type="text" name="path" required placeholder="/home/username/myapp"></label>
            <label>PHP CLI Binary<input type="text" name="php_bin" value="php" placeholder="php"></label>
            <label>Composer Path (optional, blank = shared)<input type="text" name="composer_path" placeholder="leave blank to use shared data/composer-lib"></label>
            <button type="submit">Save Project</button>
            <button type="button" id="project-cancel" class="secondary" style="display:none">Cancel Edit</button>
            <div class="form-message"></div>
        </form>
    </div>

    <div class="card">
        <h2>Saved Projects</h2>
        <table class="list">
            <thead><tr><th>Name</th><th>Path</th><th>PHP Bin</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($projects as $p): ?>
                <tr>
                    <td><?= h($p['name']) ?></td>
                    <td><code><?= h($p['path']) ?></code></td>
                    <td><code><?= h($p['php_bin']) ?></code></td>
                    <td class="actions">
                        <button class="edit-project" data-project='<?= h(json_encode($p)) ?>'>Edit</button>
                        <button class="use-project" data-id="<?= h($p['id']) ?>">Use</button>
                        <button class="delete-project danger" data-id="<?= h($p['id']) ?>">Delete</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($projects)): ?>
                <tr><td colspan="4" class="muted">No projects yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    render_footer();
    exit;
}

if ($page === 'env') {
    render_header('.env Editor');
    ?>
    <h1>.env Editor</h1>
    <?php if (!$activeProject): ?>
        <p class="muted">Select a project on the <a href="index.php?page=projects">Projects</a> page first.</p>
    <?php else:
        $envPath = rtrim($activeProject['path'], '/') . '/.env';
        $content = file_exists($envPath) ? file_get_contents($envPath) : '';
    ?>
        <p class="muted">Editing <code><?= h($envPath) ?></code>. A timestamped backup is kept alongside it on every save.</p>
        <form id="env-form">
            <textarea name="content" rows="24" spellcheck="false"><?= h($content) ?></textarea>
            <button type="submit">Save .env</button>
            <div class="form-message"></div>
        </form>
    <?php endif; ?>
    <?php
    render_footer();
    exit;
}

if ($page === 'system') {
    $info = gather_system_info();
    render_header('System Check');
    ?>
    <h1>System Check</h1>
    <div class="card">
        <h2>Command Execution</h2>
        <table class="kv">
            <tr><td>Best available method</td><td><code><?= h($info['best_exec_method'] ?? 'none available') ?></code></td></tr>
            <?php foreach ($info['exec_functions'] as $fn => $available): ?>
                <tr><td><?= h($fn) ?>()</td><td class="<?= $available ? 'ok' : 'bad' ?>"><?= $available ? 'available' : 'disabled' ?></td></tr>
            <?php endforeach; ?>
        </table>
        <?php if ($info['best_exec_method'] === null): ?>
            <div class="alert alert-error">No shell execution functions are enabled. Ask your host to enable one of proc_open / exec / shell_exec in php.ini disable_functions.</div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>PHP Binaries Found</h2>
        <p class="muted">Extensions are checked against the CLI binary itself — this is what actually matters for <code>composer install</code> / <code>artisan</code>, and can differ from the web PHP version below (e.g. host forces PHP-FPM at one version while a PHP Selector CLI binary exists at another).</p>
        <?php foreach ($info['php_binaries'] as $bin): ?>
            <table class="kv" style="margin-bottom:10px">
                <tr><td><code><?= h($bin['path']) ?></code></td><td><?= h($bin['version'] ?? 'not tested / not runnable') ?></td></tr>
                <?php if ($bin['extensions']): ?>
                    <tr><td colspan="2">
                        <?php foreach ($bin['extensions'] as $ext => $loaded): ?>
                            <span class="<?= $loaded ? 'ok' : 'bad' ?>" style="margin-right:10px"><?= h($ext) ?></span>
                        <?php endforeach; ?>
                    </td></tr>
                <?php endif; ?>
            </table>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <h2>PHP Extensions (web process)</h2>
        <p class="muted">Common requirements for Laravel/Filament-style apps, checked against the PHP process serving this page (not necessarily the CLI binary above). Missing <code>intl</code> or <code>fileinfo</code> commonly breaks <code>composer install</code> on filament/flysystem packages — enable them under cPanel's <strong>Select PHP Version → Extensions</strong> for the exact PHP version your domain uses; extensions are configured per-version, so switching PHP versions requires re-enabling them.</p>
        <table class="kv">
            <?php foreach ($info['extensions'] as $ext => $loaded): ?>
                <tr><td><?= h($ext) ?></td><td class="<?= $loaded ? 'ok' : 'bad' ?>"><?= $loaded ? 'loaded' : 'missing' ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="card">
        <h2>Composer</h2>
        <table class="kv">
            <tr><td>cURL extension</td><td class="<?= $info['curl_available'] ? 'ok' : 'bad' ?>"><?= $info['curl_available'] ? 'yes' : 'no' ?></td></tr>
            <tr><td>allow_url_fopen</td><td class="<?= $info['allow_url_fopen'] ? 'ok' : 'bad' ?>"><?= $info['allow_url_fopen'] ? 'yes' : 'no' ?></td></tr>
            <tr><td>Shared Composer file present</td><td class="<?= $info['shared_composer_phar'] ? 'ok' : 'bad' ?>">
                <?= $info['shared_composer_phar'] ? 'yes — ' . number_format($info['shared_composer_phar_size']) . ' bytes' : 'no' ?>
            </td></tr>
            <tr><td>Expected at</td><td><code><?= h($info['shared_composer_phar_path']) ?></code></td></tr>
            <tr><td>HOME (set for every command)</td><td><code><?= h($info['home']) ?></code></td></tr>
            <tr><td>COMPOSER_HOME (set for every command)</td><td><code><?= h($info['composer_home']) ?></code></td></tr>
        </table>
        <button id="download-composer">Download / Update Shared Composer</button>
        <div class="form-message"></div>
    </div>

    <div class="card">
        <h2>Other</h2>
        <table class="kv">
            <tr><td>PHP version (web)</td><td><?= h($info['php_version_web']) ?> (<?= h($info['php_sapi']) ?>)</td></tr>
            <tr><td>open_basedir</td><td><code><?= h($info['open_basedir']) ?></code></td></tr>
            <tr><td>max_execution_time</td><td><?= h((string)$info['max_execution_time']) ?>s</td></tr>
            <tr><td>memory_limit</td><td><?= h($info['memory_limit']) ?></td></tr>
        </table>
    </div>
    <?php
    render_footer();
    exit;
}

if ($page === 'logs') {
    $logFile = data_path('logs/activity.log');
    $lines = file_exists($logFile) ? array_slice(file($logFile), -300) : [];
    render_header('Logs');
    ?>
    <h1>Activity Log</h1>
    <button id="clear-log" class="danger">Clear Log</button>
    <pre class="console"><?= h(implode('', $lines)) ?></pre>
    <?php
    render_footer();
    exit;
}

// Default: dashboard
$catalog = quick_commands();
$groups = [];
foreach ($catalog as $id => $c) {
    $groups[$c['group']][$id] = $c;
}
render_header('Dashboard');
?>
<h1>Dashboard</h1>

<div class="card">
    <h2>Active Project</h2>
    <?php if (empty($projects)): ?>
        <p class="muted">No projects yet. <a href="index.php?page=projects">Add one</a>.</p>
    <?php else: ?>
        <select id="active-project-select">
            <option value="">-- select a project --</option>
            <?php foreach ($projects as $p): ?>
                <option value="<?= h($p['id']) ?>" <?= ($activeProject && $activeProject['id'] === $p['id']) ? 'selected' : '' ?>>
                    <?= h($p['name']) ?> — <?= h($p['path']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
</div>

<?php if ($activeProject): ?>
<div class="card">
    <h2>Quick Commands — <?= h($activeProject['name']) ?></h2>
    <?php foreach ($groups as $groupName => $items): ?>
        <div class="cmd-group">
            <h3><?= h($groupName) ?></h3>
            <div class="cmd-buttons">
                <?php foreach ($items as $id => $c): ?>
                    <button class="run-btn <?= $c['danger'] ? 'danger' : '' ?>" data-command-id="<?= h($id) ?>" data-danger="<?= $c['danger'] ? '1' : '0' ?>">
                        <?= h($c['label']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <h2>Custom Command</h2>
    <p class="muted">Runs inside <code><?= h($activeProject['path']) ?></code>. This executes arbitrary shell commands — use with care.</p>
    <div class="custom-cmd-row">
        <input type="text" id="custom-command" placeholder="e.g. php artisan tinker --execute=&quot;...&quot;">
        <button id="run-custom" class="danger">Run</button>
    </div>
</div>

<div class="card">
    <h2>Output</h2>
    <div id="run-status" class="muted">No command run yet.</div>
    <pre id="run-output" class="console"></pre>
</div>
<?php endif; ?>

<?php
render_footer();
