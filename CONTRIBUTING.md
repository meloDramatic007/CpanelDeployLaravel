# Contributing

Thanks for considering a contribution. This is a small, single-purpose tool,
so the bar for new features is "does this help someone deploying Laravel (or
any Composer-based PHP app) on shared hosting without a terminal" — general
DevOps-panel scope creep will likely get pushback in review.

## Reporting bugs / requesting features

Open a GitHub issue. For bugs, include:

- Your host and PHP version (shared cPanel hosts vary a lot in what's
  allowed — `disable_functions`, `open_basedir`, execution time limits).
- What the **System Check** page reported.
- The exact command/action you ran and what happened, including any error
  text shown in the console output.

For anything that could be a **security** issue (auth bypass, CSRF, path
traversal, information disclosure) see [SECURITY.md](SECURITY.md) instead of
opening a public issue.

## Development setup

No terminal on shared cPanel hosting doesn't mean no terminal for local
development. Use the included Docker harness:

```bash
docker compose up -d
# app is now at http://localhost:8080/
```

This runs `php:8.2-apache` (matching a common cPanel EA4 version) and mounts
the whole repo into the container, so edits on your host are picked up live.
A fake Laravel app at [docker/sample-project/](docker/sample-project/) (a
stub `artisan`, no real database needed) lets you exercise the quick-command
buttons end to end without a real Laravel install.

**Gotcha:** if you change `docker-compose.yml`, keep directory-level bind
mounts (`.:/var/www/html`), not single-file mounts — a bind mount on an
individual file pins to that file's original inode, so most editors
(write-via-temp-file-then-rename) will silently keep serving the pre-edit
version until the container is recreated.

Before testing, make sure `data/` is in its pristine state (no
`config.json`/`projects.json` — the app creates them on first visit). After a
test session, clear `data/config.json`, `data/projects.json`,
`data/logs/*.log`, `data/home/*`, and `data/composer-home/*` before
committing — none of that is meant to be checked in (see `.gitignore`).

## Making changes

1. Fork the repo and create a branch off `main`.
2. Keep changes focused — one fix or feature per PR.
3. If you touch `includes/executor.php` or anything that writes files
   (`.env` editor, project config save), **test against the running Docker
   container**, not just a read-through. This tool's whole value is running
   real commands with real exit codes; a past bug here (`proc_close()`
   returning a false `-1` exit code under a SIGCHLD race) was only caught by
   live testing, not code review.
4. Match the existing code style: no framework, plain PHP with small
   `includes/*.php` modules, no build step.
5. Open a PR describing what changed and why, and how you tested it
   (ideally: ran it against the Docker harness, or against a real cPanel
   account and which host).

## Project structure

```
index.php              Entry point / router for all pages and AJAX actions
includes/
  bootstrap.php         Session setup, idle timeout, requires
  auth.php               Login, password hashing, rate limiting/lockout
  csrf.php                Token generation/verification
  projects.php           Multi-project config (data/projects.json)
  executor.php            Runs shell commands (proc_open/exec/etc.), HOME/COMPOSER_HOME env fix
  commands.php             Quick-command definitions (composer install, artisan migrate, ...)
  system_check.php          Detects exec function, PHP CLI binaries + their loaded extensions
  render.php                  HTML view helpers
assets/                 CSS/JS for the dashboard UI
data/                   Runtime state — config, per-install projects, logs (gitignored, created on first run)
docker/                 Local dev/test harness (not part of the deployed tool)
```

## Code of conduct

Be respectful, assume good faith, keep discussion focused on the project.
