# AGENTS.md

Guidance for AI agents working on this repository.

## Project

**Fanaloka Maintenance Manager** — a WordPress plugin (v1.0.x) that turns incoming email into maintenance/helpdesk tickets for website clients. Admin replies via the WP admin UI; replies are sent over SMTP and threaded back by the client's reply emails (IMAP).

- Plugin name: `Fanaloka Maintenance Manager`
- Namespace root: `Fanaloka\Maintenance`
- Custom PSR-4-ish autoloader: `includes/class-autoloader.php` (has an explicit `CLASS_MAP` + PSR-4 fallback). **No Composer autoload** — `vendor/` only contains a `.htaccess`.

## Layout

```
fanaloka-maintenance.php       entry point; constants (FM_VERSION, FM_PLUGIN_DIR, ...), hooks, CPT, cron
admin/                         WP admin pages (one class per screen)
  class-admin.php              Admin facade: menus, AJAX handlers, ticket HTML renderers
  class-*-page.php             dashboard, requests, clients, developers, reports,
                               settings, guide, activity-log, email-log, ticket-detail
includes/                      core logic
  class-cron-manager.php       IMAP sync loop (INBOX + [Gmail]/Sent Mail), ajax_sync_now
  class-imap-reader.php        IMAP connection/fetch (imap.gmail.com, ssl/novalidate-cert)
  class-email-parser.php       parse + should_ignore + subject normalization
  class-ticket-manager.php     ticket create/update/find (P1..P4 matching)
  class-conversation-manager.php  conversation entries + message_id dedup
  class-notification-manager.php  email notifications (new/reply/assign/status)
  class-database.php           custom tables (fm_conversations, fm_email_log, fm_activity_log)
  class-logger.php             file logger (debug.log)
public/                        frontend (placeholder)
assets/js/admin.js             shared admin JS (FMAdmin object: reply, ajax fields, sync)
assets/css/admin.css           global admin styles + modern tables/badges
```

Email pipeline: `IMAPReader` → `EmailParser::parse_email` → `TicketManager::find_ticket_for_email` (P1 In-Reply-To, P2 References, P3 Subject+email, P4 Subject) → reply entry or new ticket. Sent-folder sync adds admin-sent emails back to tickets with dedup.

## Commands

```bash
# PHP syntax check (always before deploy)
php -l path/to/file.php

# No test suite, no linter, no composer scripts exist.
# git for versioning (origin: github.com/zeinaziz/fanaloka-maintenance-backup)
```

## Deploy (production via SSH + scp)

The plugin is deployed directly by scp (no CI). Server details used in this project:

```bash
SSH_HOST="u62-pm7yombcffoj@ssh.christhopers55.sg-host.com"
SSH_PORT=18765
REMOTE_PLUGIN="/home/u62-pm7yombcffoj/www/christhopers55.sg-host.com/public_html/wp-content/plugins/fanaloka-maintenance-backup"
```

- Deploy a file to its matching subdir:
  ```bash
  scp -P 18765 admin/class-requests-page.php "$SSH_HOST:$REMOTE_PLUGIN/admin/"
  scp -P 18765 assets/css/admin.css      "$SSH_HOST:$REMOTE_PLUGIN/assets/css/"
  scp -P 18765 includes/class-ticket-manager.php "$SSH_HOST:$REMOTE_PLUGIN/includes/"
  ```
- **Careful**: don't `scp` multiple files of different subdirs to one destination path (e.g. dropping `admin.css` + `admin/*.php` into the plugin root). Always match the remote subdirectory.
- `wp-cli` is available on the server at `/usr/local/bin/wp` (run from the site's `public_html`):
  ```bash
  ssh -p 18765 "$SSH_HOST" "cd /home/.../public_html && wp eval '<php>'"
  ```

## Verification / testing workflow (no unit tests)

Useful manual techniques used in this repo:

- **Browser automation** (chrome-devtools MCP): navigate admin pages, fill forms, trigger AJAX, read console.
- **DB / state via wp-cli**:
  ```bash
  wp post list --post_type=maintenance_request
  wp eval 'global $wpdb; ...'   # query fm_conversations, fm_email_log, fm_activity_log
  ```
- **Manual sync trigger**: `wp eval 'delete_transient("fm_sync_lock"); \Fanaloka\Maintenance\Cron\CronManager::instance()->run_sync();'`
- **Simulating client emails**: append raw RFC822 to the watched Gmail INBOX via `imap_append` (use `ssl/novalidate-cert`), then run sync. This exercises the real parse/match/ticket pipeline without external mail delivery.

## Conventions

- PHP: WordPress coding style, `<?php` open tag, `namespace Fanaloka\Maintenance\...;`, `defined('ABSPATH') || exit;`.
- All admin pages print inline `<style>`/`<script>` in their `render()` method; shared styles go to `assets/css/admin.css`, shared JS to `assets/js/admin.js`.
- Text is localized with `esc_html_e`/`__('...','fanaloka-maintenance')` (domain `fanaloka-maintenance`; `languages/` is empty — no .po files yet).
- Add a class to `Autoloader::CLASS_MAP` when creating a new class (map is explicit).
- When adding an admin AJAX handler, register it in `admin/class-admin.php` constructor (`wp_ajax_fm_*`) and call it from `assets/js/admin.js` (or page inline script) with `fmAdmin.nonce` + `fmAdmin.ajaxUrl`.
- No code comments unless required; match surrounding style.

## Gotchas

- IMAP uses `ssl/novalidate-cert` (self-signed cert on server).
- Admin replies sent to the admin's own address/alias can be re-delivered into the watched INBOX; the sync must skip emails whose sender equals `fm_imap_username` (this was a past bug — see `class-cron-manager.php`).
- Gmail rewrites outgoing `Message-ID`, so dedup relies on both `message_id_exists` and `has_recent_developer_reply` (sender + time window).
- Deploying only some files can leave the live site out of sync with git — verify with `git status` before committing, and only commit/push when asked.
