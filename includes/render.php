<?php
declare(strict_types=1);
defined('ROOT_DIR') || exit('Direct access not permitted.');


function render_header(string $title, bool $showNav = true): void
{
    $active = $_GET['page'] ?? 'dashboard';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> · cPanel Deploy Console</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php if ($showNav): ?>
<header class="topbar">
    <div class="brand">cPanel Deploy Console</div>
    <nav>
        <a href="index.php?page=dashboard" class="<?= $active === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
        <a href="index.php?page=projects" class="<?= $active === 'projects' ? 'active' : '' ?>">Projects</a>
        <a href="index.php?page=env" class="<?= $active === 'env' ? 'active' : '' ?>">.env Editor</a>
        <a href="index.php?page=system" class="<?= $active === 'system' ? 'active' : '' ?>">System Check</a>
        <a href="index.php?page=logs" class="<?= $active === 'logs' ? 'active' : '' ?>">Logs</a>
        <a href="index.php?action=logout" class="logout">Logout</a>
    </nav>
</header>
<?php endif; ?>
<main class="container">
<?php
}

function render_footer(): void
{
    ?>
</main>
<?php if (is_logged_in()): ?>
<script>
window.APP = {
    csrf: <?= json_encode(csrf_token()) ?>,
    activeProjectId: <?= json_encode(get_active_project_id()) ?>
};
</script>
<?php endif; ?>
<script src="assets/app.js"></script>
</body>
</html>
<?php
}

function render_flash(?string $error = null, ?string $success = null): void
{
    if ($error) {
        echo '<div class="alert alert-error">' . h($error) . '</div>';
    }
    if ($success) {
        echo '<div class="alert alert-success">' . h($success) . '</div>';
    }
}
