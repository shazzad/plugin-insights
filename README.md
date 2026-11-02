# Plugin Insights

Consent-based usage insights for WordPress plugins: `shazzad/plugin-insights`.

It holds **no update, license or admin-page code**, so it can ship inside a wordpress.org
plugin. Nothing is sent until a site admin clicks **Allow** in the consent notice (or the plugin
calls `opt_in()` from its own UI), as wordpress.org guideline 7 requires.

It talks to the `wp-repo/v4` Insights routes of `shazzad/plugin-repo` (2.9.0+). The namespace
number matches that API: `Shazzad\PluginInsights\V4` ↔ `wp-repo/v4`.

Requires PHP 7.4+.

## Install

With Composer:

```bash
composer require shazzad/plugin-insights
```

Without Composer (for plugins that ship no `vendor/`), copy the package folder into the plugin
and load it:

```php
require_once __DIR__ . '/lib/plugin-insights/load.php';
```

Every class has its own `class_exists()` guard, so two plugins bundling their own copies on one
site do not collide.

## Quick start

```php
<?php
// Nothing is sent until an admin clicks "Allow" in the notice.
$insights = new \Shazzad\PluginInsights\V4\Insights( [
    'api_url'       => 'https://w4dev.com/wp-json/wp-repo/v4', // required
    'file'          => __FILE__,                               // required
    'product_uid'   => 'prod_xxxxxxxxxxxxxxxxxxxx',            // required
    'name'          => 'My Plugin',          // shown in the notice; default = plugin header Name
    'privacy_url'   => 'https://example.com/privacy', // "Learn more" link; omitted when empty
    'notice'        => [
        'screens' => [ 'settings_page_my-plugin' ], // screen ids; default: every admin screen
        'items'   => [ 'Number of lists' ],          // describes what your meta holds
    ],                                              // or false = draw your own UI, call opt_in()
    'meta_callback' => function () {
        return [ 'lists' => (int) wp_count_posts( 'my_list' )->publish ];
    },
] );
```

The notice ("Want to help make **My Plugin** even better?… Allow / No thanks") shows to users
with `manage_options` while consent is unanswered. **Allow** stores consent, schedules the daily
`wprepo_insights_track_{slug}` cron and sends an `optin` track. **No thanks** stores the refusal
and sends nothing.

On the instance: `has_consent()`, `get_consent()` (`'yes'|'no'|''`), `opt_in()`, `opt_out()`
(asks the server to delete this site's data; a failed request is retried on `admin_init` and
daily for up to 7 days), and `->collector->collect()` to show exactly what would be sent.

## Config keys

| Key             | Type          | Default            | Description |
| --------------- | ------------- | ------------------ | ----------- |
| `api_url`       | string        | -                  | **Required.** Repo API base, e.g. `https://w4dev.com/wp-json/wp-repo/v4` |
| `file`          | string        | -                  | **Required.** `__FILE__` or its `plugin_basename()` form |
| `product_uid`   | string        | -                  | **Required.** `prod_…` uid. Without a valid one nothing is sent and no notice is shown |
| `product_id`    | string        | `''`               | Numeric product id. Optional, never sent |
| `name`          | string        | plugin header Name | Name shown in the notice |
| `privacy_url`   | string        | `''`               | "Learn more" link in the notice; omitted when empty |
| `notice`        | array\|false  | `[]`               | `screens` (screen ids), `text` (override, `%s` = name), `show_callback` (extra gate), `items` (extra "What we collect" lines); `false` = no notice |
| `meta`          | array         | `[]`               | Static metadata; Closures resolve at send time |
| `meta_callback` | callable      | `null`             | Returns a metadata array at send time |

Unknown or missing keys fire `_doing_it_wrong()` in debug mode. Construction always proceeds:
a misconfigured Insights never takes the plugin down.

## What is sent

JSON, `POST {api_url}/plugins/{uid}/track`, daily plus on `activate`, `deactivate`, `upgrade`
and `optin`:

- `event`, `mode`, `token`, `plugin_version`, `plugin_status`
- `site`: url, name, locale, multisite, is_local (a non-production `wp_get_environment_type()`
  or a local-looking host; the `wprepo_insights_is_local` filter has the last word)
- `wp`: version, memory limit, active theme (slug, name, version, parent)
- `server`: PHP and database versions, server software, PHP memory limit
- `plugins.active[]`: active plugins only (max 200), each slug, name, version, `Plugin URI`
- `meta`: your `meta` / `meta_callback`, when set

No admin email or name, no user counts. The notice's "What we collect" list matches this line
for line. When you send `meta`, pass `notice.items` to describe it; otherwise a generic "Usage
statistics specific to {name}" line is shown.

`opt_out()` sends `POST {api_url}/plugins/{uid}/optout` with `{ site_url, token }`, and the
server deletes the install's data.

## Storage and uninstall

Options per plugin (`{slug}` = plugin directory): `{slug}_insights_consent`,
`{slug}_insights_token`, `{slug}_insights_last_send`, `{slug}_insights_optout_pending`,
`{slug}_insights_last_attempt`, `{slug}_insights_disabled_version`; cron hook
`wprepo_insights_track_{slug}`. Deactivation clears the cron and keeps the options, so
re-activating does not ask again.

When the server refuses a track with a 4xx, the next one waits the normal 20 hours. After
`403 wprepo_insights_tracking_disabled` nothing is tracked until the plugin version changes or
7 days pass; an opt-out still goes out. Network errors and 5xx retry on the next run.

Remove everything on uninstall (every site of a multisite network; sends nothing):

```php
// uninstall.php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
require_once __DIR__ . '/lib/plugin-insights/load.php'; // or vendor/autoload.php
\Shazzad\PluginInsights\V4\Insights::uninstall( WP_UNINSTALL_PLUGIN );
```

## Development

```bash
composer test   # PHPUnit 9 + Brain Monkey
composer lint   # WordPress coding standards + PHP 7.4 compatibility
```

`tests/V4/WporgPolicyTest.php` fails the build if any shipped file contains an update hook,
updater code or a reference to `shazzad/plugin-updater` — the same checks a wordpress.org
reviewer runs.
