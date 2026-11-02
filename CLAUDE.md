# CLAUDE.md

Composer library `shazzad/plugin-insights`: consent-based usage insights for WordPress plugins,
safe for wordpress.org because it holds no update, license or admin-page code. Built 2026-11-02
by moving `V4\Insights` + `V4\Insights\*` out of `shazzad/plugin-updater` (its `main` at
`cfda4c9`). Usage and payload: `README.md`.

## Commands

```bash
composer test    # locally: docker run --rm -v "$PWD":/app -w /app php:7.4-cli vendor/bin/phpunit
composer lint
```

CI (`.github/workflows/tests.yml`) runs `composer test` on PHP 7.4. The code style carries the
same phpcs findings it had in plugin-updater; CI does not lint.

## Rules

- **No updater code, ever.** `tests/V4/WporgPolicyTest.php` greps every shipped file (`src/`,
  `load.php`) for update hooks (`site_transient_update_plugins`, `plugins_api`, …),
  `PluginUpdater`, `plugin-updater`, `vendor/autoload` and `wp-repo/v3`. Adminkeep's `bin/build`
  rejects a ZIP containing those strings. `upgrader_process_complete` is fine: the Scheduler
  uses it to send the `upgrade` track.
- **Namespace number = API version** (`V4` ↔ `wp-repo/v4`), the same rule as plugin-updater.
  Changes within V4 are additive; a breaking change opens `src/V5/`.
- **Storage is a contract.** Option keys (`{slug}_insights_*`), the cron hook
  (`wprepo_insights_track_{slug}`), the `wprepo_insights_*` error codes and the
  `wprepo_insights_is_local` filter match plugin-updater V4 byte for byte, so plugin-updater
  can later depend on this package without moving any stored data. Don't rename them.
- **Commercial mode stays.** `Consent::MODE_COMMERCIAL`, the collector's `license_callback` /
  `license_sent_callback` and the full payload exist for plugin-updater's `V4\Integration`,
  which will consume this package later. The free entry point `V4\Insights` always uses
  consent mode.
- **Free payload is trimmed** (Shazzad's call 2026-09-27): no `admin`/`users` blocks, debug
  mode, PHP execution/upload limits or plugin counts. `Notice::get_collected_items()` must stay
  in step with `Collector::collect()` in consent mode.
- `load.php` must `require_once` every file under `src/` (a test checks it).
- Every file keeps a plain `defined( 'ABSPATH' )` guard (Plugin Check misses `\defined()`) and
  every class a `class_exists()` guard.

## Relationship to plugin-updater

plugin-updater still carries its own copy of this code under `src/V4/Insights*`. The plan is
to test this package in a wp.org plugin first, then make plugin-updater depend on it. Until
Shazzad says so, don't touch plugin-updater for this.
