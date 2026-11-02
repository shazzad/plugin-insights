# Changelog

## 4.0.0 - Unreleased

First release. The free-plugin Insights client from `shazzad/plugin-updater` (`V4\Insights`
and `V4\Insights\*`, as of plugin-updater `main` at `cfda4c9`, the unreleased 4.1.0), moved
into its own package so wordpress.org plugins can ship it without any updater code.

- Namespace `Shazzad\PluginInsights\V4`, matching the `wp-repo/v4` API it calls
- Option keys, the cron hook, the `wprepo_insights_is_local` filter and the wire format are
  unchanged from plugin-updater V4
- Removed: the `wp-repo/v3` `api_url` check and its `wprepo_insights_v3_api_url` error. It only
  guarded against configs copied from plugin-updater V2
- New: `load.php`, to load the package without Composer
- New: a test that fails when any shipped file contains update hooks or updater code
