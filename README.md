# cPanel Deploy Console

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)
![No framework](https://img.shields.io/badge/dependencies-none-informational.svg)

A small, password-protected web UI for running Composer and `artisan` commands
on shared cPanel hosting where the terminal / SSH is disabled. Drop it into a
folder under `public_html`, visit it in a browser, and run deploy commands
through buttons or a free-form console — one tool can manage several Laravel
(or any Composer-based PHP) projects hosted on the same shared account.

No dependencies, no build step, no framework — just PHP files you upload.

Requires PHP 7.4+ and one of `proc_open`, `exec`, `shell_exec`, or `system`
enabled (the System Check page tells you which one your host allows).

> For a longer, illustrated walkthrough of installing and using the tool, see
> [docs/field-guide.html](docs/field-guide.html) (download it and open it in
> a browser — GitHub shows HTML files as source, not rendered).

## Contents

- [Security warning](#️-security-warning)
- [Installation](#installation)
- [What's included](#whats-included)
- [Two gotchas this tool works around automatically](#two-gotchas-this-tool-works-around-automatically)
- [Known limitations of shared hosting](#known-limitations-of-shared-hosting)
- [Local development / testing](#local-development--testing)
- [Contributing](#contributing)
- [License](#license)

## ⚠️ Security warning

This tool is effectively a web shell for your hosting account: whoever knows
the password can execute arbitrary commands as your cPanel user. Treat it
accordingly:

- **Use a strong, unique password** (12+ random characters). It's hashed with
  `password_hash()` and never stored in plain text.
- **Rename the folder** you deploy it into to something unguessable
  (e.g. `public_html/deploy-x7f2q9`), instead of an obvious name like
  `deploy` or `admin`.
- **Don't link to it** from anywhere public, and don't commit the URL/folder
  name to a public repo.
- **Delete the tool (or at least the `data/` folder) once you're done**
  deploying, or re-deploy it fresh each time you need it.
- Login is rate-limited (5 attempts, then a 15-minute lockout per IP) and
  sessions expire after 30 minutes idle, but this is not a substitute for a
  strong password.
- The `data/` and `includes/` folders each ship with a `.htaccess` denying
  all direct access — verify after upload that requests like
  `yourdomain.com/deploy-x7f2q9/data/config.json` return 403, not the file.
  (Apache/LiteSpeed with `AllowOverride All` honor this; if your host doesn't
  process `.htaccess` in subfolders, move `data/` outside `public_html`
  instead — see below.)

## Installation

1. Upload the whole folder (via cPanel File Manager or a zip you extract in
   place) into `public_html/`, e.g. `public_html/deploy-x7f2q9/`.
2. Visit `https://yourdomain.com/deploy-x7f2q9/` — you'll be prompted to set
   a password on first visit.
3. Log in, then go to **Projects → Add / Edit Project** and add each Laravel
   app you want to manage:
   - **Absolute Path**: e.g. `/home/youruser/myapp` or
     `/home/youruser/public_html/myapp` (find this in cPanel File Manager —
     click a folder and check the path shown, or use the **System Check**
     page's PHP binary detector as a sanity check that paths resolve).
   - **PHP CLI Binary**: usually `php`. If that doesn't work, check
     **System Check** for detected binaries such as
     `/opt/cpanel/ea-php82/root/usr/bin/php` (cPanel installs one per PHP
     version you've selected via MultiPHP Manager) and use the one matching
     your project's PHP version.
   - **Composer.phar Path**: leave blank to use a single shared
     `composer.phar` (downloadable from the System Check page) reused across
     all your projects — saves you from installing Composer per project.
4. Switch to the project on the **Dashboard** and use the quick-command
   buttons, or type a custom command.

### If `public_html/<folder>/.htaccess` isn't honored

Some hosts disable `.htaccess` overrides even though terminal access is also
disabled. If `data/config.json` or `includes/auth.php` are downloadable
directly in a browser, move `data/` above `public_html` (e.g. to
`/home/youruser/deploy-data`) and point `DATA_DIR` in
[includes/bootstrap.php](includes/bootstrap.php) at that absolute path — cPanel
accounts can generally read/write outside `public_html` within the same home
directory even though the web server won't serve files from there.

## What's included

- **Dashboard** — pick the active project, run one-click Composer/artisan
  commands (install, migrate, cache clear/warm, storage:link, key:generate,
  queue:restart, git pull, permission fixes), or run any custom shell
  command in the project directory.
- **Projects** — save multiple projects (name, path, PHP binary, optional
  per-project composer.phar), since one shared-hosting account often hosts
  several apps.
- **.env Editor** — view/edit the active project's `.env` directly, with an
  automatic timestamped backup on every save.
- **System Check** — shows which exec function your host allows
  (`proc_open`/`exec`/`shell_exec`/`system`), detected PHP CLI binaries
  *and which extensions each one actually has loaded* (checked against the
  CLI binary itself, not just the web process — these commonly differ),
  whether Composer can be downloaded (cURL/`allow_url_fopen`), and key
  `php.ini` limits (`max_execution_time`, `memory_limit`, `open_basedir`).
- **Logs** — an audit trail of every command run, with timestamps and IPs,
  stored in `data/logs/activity.log`.

## Two gotchas this tool works around automatically

- **`Factory.php: The HOME or COMPOSER_HOME environment variable must be
  set`** — PHP-FPM processes on shared hosting often have no `HOME` set.
  Every command this tool runs (not just Composer ones) is given `HOME` and
  `COMPOSER_HOME` pointed at `data/home` and `data/composer-home`
  automatically, so you shouldn't hit this.
- **`composer install` fails with "requires ext-intl" / "requires PHP
  >=8.4"`** even though your project targets a lower PHP version — this
  usually means `composer.lock` was generated locally with different
  extensions/PHP available than what your host's *CLI* binary has, not that
  your app actually needs them at runtime. Two things to check on System
  Check: (1) does the CLI binary you configured for the project actually
  have `intl`/`fileinfo`/etc. loaded — cPanel's **Select PHP Version →
  Extensions** page is scoped to whichever PHP version is selected at the
  top, so switching versions means re-checking those boxes; (2) if the
  extensions genuinely aren't available and don't matter to your app's actual
  code paths, use the **Composer Install (ignore platform reqs)** quick
  command instead of plain Composer Install.

## Known limitations of shared hosting

- **Execution time limits**: shared hosts often cap `max_execution_time`
  regardless of what this tool requests. A `composer install` on a large
  project may time out — check System Check first, and prefer
  `--no-dev --optimize-autoloader` (already the default on the quick-command
  button) to speed it up. If it keeps timing out, ask your host to raise the
  limit for your account or via the MultiPHP INI Editor.
- **No true streaming output**: output is returned only once the command
  finishes, not line-by-line, because of how PHP-FPM/CGI buffers responses
  on typical shared hosting.
- **If no exec function is enabled at all**, this tool cannot run shell
  commands — the System Check page will tell you plainly, and you'll need to
  ask your host to enable `proc_open` (preferred) or `exec` in
  `disable_functions`.

## Local development / testing

A Docker harness is included so you can develop and test without a live
cPanel account:

```bash
docker compose up -d
# app is now at http://localhost:8080/
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for the full development workflow,
project structure, and what to test before opening a PR.

## Contributing

Bug reports, feature requests, and PRs are welcome — see
[CONTRIBUTING.md](CONTRIBUTING.md). For anything that could be a security
issue (auth bypass, CSRF, path traversal, information disclosure), please
follow [SECURITY.md](SECURITY.md) instead of opening a public issue.

## License

[MIT](LICENSE) — do what you like with it, no warranty provided. See the
[Security warning](#️-security-warning) above: you are responsible for how
you deploy and secure your own instance.
