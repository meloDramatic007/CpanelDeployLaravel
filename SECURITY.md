# Security Policy

## Please read this before you file an issue

**cPanel Deploy Console is, by design, a password-protected web shell.**
Anyone who knows the login password can execute arbitrary shell commands as
your cPanel user. That is the entire point of the tool (running `composer`
and `artisan` where no terminal exists) — it is not an unintended
vulnerability, and reports along the lines of "this tool lets an
authenticated user run any command" will be closed as expected behavior.

What *is* in scope for a security report:

- **Authentication bypass** — reaching the dashboard or running a command
  without a valid session/password.
- **CSRF** — a state-changing action (login, running a command, saving
  `.env`, editing project config) that succeeds without a valid CSRF token.
- **Path traversal / arbitrary file read-write** outside the configured
  project directories.
- **Information disclosure** — e.g. `data/config.json`, `.env` backups, or
  logs being servable directly over HTTP despite the shipped `.htaccess`
  rules, or the password hash/config leaking through some other endpoint.
- **Session handling issues** — session fixation, missing `HttpOnly`, weak
  session ID generation, the idle-timeout/lockout logic being bypassable.
- **Rate-limiting / brute-force bypass** on the login form.

## Reporting a vulnerability

Please **do not open a public GitHub issue** for anything in the list above.
Instead, use GitHub's private [Security Advisories](../../security/advisories/new)
feature for this repository, or reach out to the maintainer directly. Include:

- The affected file/endpoint and a minimal reproduction.
- The impact (what an attacker could do, and whether it requires prior
  authentication).
- A suggested fix, if you have one.

You should get an initial response within a few days. This is a small,
unfunded open-source project — there is no bug bounty, but you will be
credited in the fix's release notes if you'd like.

## Deployment hardening checklist

Most real-world risk with this tool comes from *deployment*, not code. Before
you put it on a live server:

- Use a long, random login password (12+ characters) — it's the only thing
  standing between the internet and a shell on your account.
- Rename the folder you deploy it into to something unguessable instead of
  `deploy` or `admin`.
- Confirm `data/` and `includes/` return `403` when requested directly
  (`yourdomain.com/<folder>/data/config.json`) — the shipped `.htaccess`
  files should handle this, but some hosts ignore `.htaccess` in
  subdirectories. If yours does, move `data/` outside `public_html`
  (see the README).
- Delete the tool, or at least the `data/` folder, once you're done
  deploying — don't leave a standing web shell live indefinitely.
- Consider restricting access further at the host level (IP allowlist in
  cPanel, or HTTP Basic Auth in front of it) if your host supports it.

See the README's [Security warning](README.md#️-security-warning) section
for the full picture.
